<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Photon Server.
 *
 * The server is the infinite loop to handle the requests from/to the
 * zeromq hub provided by Mongrel2.
 */
namespace photon\server;

use photon\log\Timer;
use photon\log\Log;
use photon\event\Event;
use photon\config\Container as Conf;
use photon\mongrel2\Connection;
use photon\shortcuts;

class Exception extends \Exception {}

/**
 * Photon server.
 *
 * It can daemonize, it reacts on SIGTERM.
 */
class Server
{
    /*
     *  Default mongrel2 configuration used when 'server_conf' configuration
     *  key is not defined
     */
    private static $defaultMongrel2Addr = array(
        /**
         * Where the requests are provided.
         */
        'pull_addr' => 'tcp://127.0.0.1:9997',

        /**
         * Where the answers are pushed.
         */
        'pub_addr' => 'tcp://127.0.0.1:9996',

        /**
         * Where the statistics about connections in mongrel2 are available
         * This feature must be explicitly activated in mongrel2
         */
        'crtl_addr' => null,
    );

    private $connections = array();
    private $dispatcher = null;

    public function __construct()
    {
        $servers = Conf::f('server_conf', array(self::$defaultMongrel2Addr));

        foreach ($servers as $key => $server) {
            if (in_array($key, array('pull_addrs', 'pub_addrs'), true) === true) {
                throw new Exception('Old style configuration detected, please update the "server_conf" key');
            }

            $pull_addr  = isset($server['pull_addr'])   ? $server['pull_addr']  : null;
            if ($pull_addr === null) {
                /*
                 *  This mongrel2 server is defined only to able to push data, not receive requests
                 */
                continue;
            }

            $pub_addr   = isset($server['pub_addr'])    ? $server['pub_addr']   : null;
            $ctrl_addr  = isset($server['ctrl_addr'])   ? $server['ctrl_addr']  : null;
            $connection = new Connection($pull_addr, $pub_addr, $ctrl_addr);

            $this->connections[] = $connection;
        }

        if (count($this->connections) === 0) {
            throw new Exception('No mongrel2 servers detected');
        }

        $this->dispatcher = new \photon\core\Dispatcher;
    }

    /**
     * Must be started when already running as daemon.
     */
    public function start()
    {
        $this->registerSignals(); // For SIGTERM handling

        $poll = new \ZMQPoll();
        foreach($this->connections as $connection) {
            $connection->connect();
            $poll->add($connection->pull_socket, \ZMQ::POLL_IN);
        }

        // We are using polling to not block indefinitely and be able
        // to process the SIGTERM signal. The poll timeout is .5 second.
        $timeout = 500; 
        $to_read = $to_write = array();
        $gc = gc_enabled();
        $i = 0; 
        while (true) {
            $events = 0;
            try {
                $events = $poll->poll($to_read, $to_write, $timeout);
                $errors = $poll->getLastErrors();
                if (count($errors) > 0) {
                    foreach ($errors as $error) {
                        Log::error('Error polling object: ' . $error);
                    }
                }
            } catch (\ZMQPollException $e) {
                Log::fatal('Poll failed: ' . $e->getMessage());

                return 1;
            }
            if ($events > 0) {
                foreach ($to_read as $r) {
                    foreach($this->connections as $connection) {
                        if ($connection->pull_socket === $r) {
                            $this->processRequest($connection);
                            break;
                        }
                    }
                    $i++;
                }
            }
            pcntl_signal_dispatch();
            if ($gc && 500 < $i) {
                $collected = gc_collect_cycles();
                Log::debug(array('photon.server.start', 
                                 'collected_cycles', $collected));
                $i = 0;
            }
        }
    }

    /**
     * Process the request available on the socket.
     *
     * The socket is available for reading with recv().
     */
    public function processRequest($conn)
    {
        Timer::start('photon.process_request');

        $rnd = sha1(uniqid('photon', true));
        $uuid = sprintf('%s-%s-4%s-b%s-%s',
                   substr($rnd, 0, 8), substr($rnd, 8, 4),
                   substr($rnd, 12, 3), substr($rnd, 15, 3),
                   substr($rnd, 18, 12));
        
        $mess = $conn->recv();

        if ($mess->is_disconnect()) {
            // A client disconnect from mongrel2 before a answer was send
            // Use this event to cleanup your context (long polling socket, task queue, ...)
            $event_params = array('conn_id' => $mess->conn_id);
            Event::send('\photon\server\Server\processRequest::disconnect', null, $event_params);
        } else {
            // This could be converted to use server_id + listener
            // connection id, it will wrap but should provide enough
            // uniqueness to track the effect of a request in the app.
            $req = new \photon\http\Request($mess);
            $req->uuid = $uuid;
            $req->conn = $conn;

            shortcuts\Server::setCurrentRequest($req);
            
            list($req, $response) = $this->dispatcher->dispatch($req);
            // If the response is false, the view is simply not
            // sending an answer, most likely the work was pushed to
            // another backend. Yes, you do not need to reply after a
            // recv().
            if (false !== $response) {
                if (is_string($response->content)) {
                    $conn->reply($mess, $response->render());
                } else {
                    Log::debug(array('photon.process_request', $uuid,
                                     'SendIterable'));
                    $response->sendIterable($mess, $conn);
                }
            }

            shortcuts\Server::setCurrentRequest(null);
        }

        unset($mess); // Cleans the memory with the __destruct call.
        Log::perf(array('photon.process_request', $uuid, Timer::stop('photon.process_request')));
    }

    /**
     * Handles the signals.
     *
     * @param $signo The POSIX signal.
     */
     static public function signalHandler($signo)
     {
        if (\SIGTERM === $signo) {
            Log::info('Received SIGTERM, now stopping.');
            foreach(Conf::f('shutdown', array()) as $i) {
                call_user_func($i);
            }
            die(0); // Happy death, normally we run the predeath hook.
        }
    }

    /**
     * Register POSIX signals to handle.
     */
    public function registerSignals()
    {
        if (!pcntl_signal(\SIGTERM, array('\photon\server\Server', 'signalHandler'))) {
            Log::fatal('Cannot install the SIGTERM signal handler.');
            die(1);
        }
    }
}
