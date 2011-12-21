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

use photon\log\Timer as Timer;
use photon\log\Log as Log;

/**
 * Generate a uuid for each incoming request.
 *
 * You should use the Photon id for the unique string.
 *
 * @param $unique Required string to make more unique the uuid 
 * @return string Type 4 UUID
 */
function request_uuid($unique)
{
    $rnd = sha1(uniqid($unique));
    return sprintf('%s-%s-4%s-b%s-%s',
                   substr($rnd, 0, 8), substr($rnd, 8, 4),
                   substr($rnd, 12, 3), substr($rnd, 15, 3),
                   substr($rnd, 18, 12));
}

/**
 * Photon server.
 *
 * It can daemonize, it reacts on SIGTERM and can optionally send
 * smart statistics to a sink.
 */
class Server
{
   /**
     * Must be set if you want persistance of the published messages,
     * but, in this case, you need to have one per server. Also, if
     * you restart, you need to reuse the same and restart on the same
     * host.
     */
    public $sender_id = '';

   /**
     * The id of this server daemon. It is unique, randomly generated
     * or retrieved from the command line.
     */
    public $server_id = '';

    /**
     * Where the requests are provided.
     *
     * It is either a string, when the application server is
     * connecting to a single Mongrel2 server or an array when pulling
     * from many Mongrel2.
     */
    public $pull_addrs = 'tcp://127.0.0.1:9997';

    /**
     * Where the answers are pushed.
     */
    public $pub_addr = 'tcp://127.0.0.1:9996';

    /**
     * Where the control requests are given.
     */
    public $smart_port = '';

    /**
     * Where the control answer is pushed.
     */
    public $smart_interval = 0;

    /**
     * ZeroMQ sockets connected to the Mongrel2 servers.
     */
    public $pull_sockets = array();

    /**
     * ZeroMQ socket publishing the answers.
     */
    public $pub_socket = null;

    /**
     * ZeroMQ context.
     */
    public $ctx = null; 



    public $stats = array('start_time' => 0,
                          'requests' => 0,
                          'memory_current' => 0,
                          'poll_avg' => array(),
                          'memory_peak' => 0);

    /**
     * Store the necessary information to update the poll_avg stats.
     */
    public $poll_stats = array('avg' => 0.0, 'total' => 0, 'count' => 0);

    public function __construct($conf=array())
    {
        foreach ($conf as $key=>$value) {
            $this->$key = $value;
        }
        if (!is_array($this->pull_addrs)) {
            $this->pull_addrs = array($this->pull_addrs);
        }
        // Get a unique id for the process
        if ('' === $this->server_id) {
            $this->server_id = sprintf('%s-%s-%s', gethostname(), posix_getpid(), time());
        }
        // Check if smart is configured
        if (0 === strlen($this->smart_port)) {
            $this->smart_interval = 0;  
        } 
    }

    /**
     * Must be started when already running as daemon.
     */
    public function start()
    {
        $this->stats['start_time'] = time();

        $this->registerSignals(); // For SIGTERM handling

        // We create a zeromq context which will be used everywhere
        // within the process. The creation of a context is the
        // equivalent of one "zmq_init" and it should be run only
        // once.
        $this->ctx = new \ZMQContext(); 

        // If we have smart, we connect to the server to push the stats.
        if ($this->smart_interval) {
            $this->smart = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUSH); 
            $this->smart->connect($this->smart_port);
            Log::info(sprintf('Smart port: %s.', $this->smart_port));
            $smart_nextpush = time() + $this->smart_interval;
        }

        // Connect to the Mongrel2 servers and add them to the poll
        $poll = new \ZMQPoll();
        foreach ($this->pull_addrs as $addr) {
            $socket = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PULL);
            $socket->connect($addr);
            $poll_id = $poll->add($socket, \ZMQ::POLL_IN);
            $this->pull_sockets[$poll_id] = $socket;
        }

        // Connect to publish
        $this->pub_socket = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUB);
        if (0 < strlen($this->sender_id)) {
            $this->pub_socket->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $sender_id);
        }
        $this->pub_socket->connect($this->pub_addr);

        // We are using polling to not block indefinitely and be able
        // to process the SIGTERM signal and send the stats with
        // smart. The poll timeout is 1 second.
        $timeout = 1000000; 
        $to_read = $to_write = array();

        while (true) {
            $events = 0;
            try {
                Timer::start('photon.main_poll_time');
                $events = $poll->poll($to_read, $to_write, $timeout);
                $poll_time = Timer::stop('photon.main_poll_time');

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
                    $this->processRequest($r);
                    $this->stats['requests']++;
                }
            }
            if ($this->smart_interval) {
                $time = time();
                $this->updatePollStats($poll_time, $time);
                if ($smart_nextpush < $time) {
                    $this->sendSmartStats();
                    $smart_nextpush = $time + $this->smart_interval;
                }
            }
            pcntl_signal_dispatch();
        }
    }

    /**
     * Poll stats are good to know if your handlers are saturated.
     *
     * If the poll time starts to reach 0 for a long time, you know
     * that as soon as your handler finished answering a request,
     * another was still waiting in the pipe. This means that you are
     * near saturation. You can use this information to start more
     * children or kill the old ones.
     *
     * Poll time is sampled over one minute and the latest 15 minutes
     * are available. On an idle system it should be basically 200ms
     * (the timeout on the poll).
     *
     * @param $poll_time Latest poll time
     * @param $time Current time
     */
    public function updatePollStats($poll_time, $time)
    {
        $time = $time - ($time % 60);
        if (isset($this->stats['poll_avg'][$time])) {
            $this->poll_stats['count']++;
            $this->poll_stats['total'] += $poll_time;            
            $this->poll_stats['avg'] = $this->poll_stats['total'] / $this->poll_stats['count'];
            $this->stats['poll_avg'][$time] = $this->poll_stats['avg'];
        } else {
            // Entering a new sample minute
            $this->poll_stats['count'] = 1;
            $this->poll_stats['total'] = $poll_time;            
            $this->poll_stats['avg'] = $poll_time;
            $this->stats['poll_avg'][$time] = $poll_time;
            if (count($this->stats['poll_avg']) > 15) {
                array_shift($this->stats['poll_avg']);
            }
        }
    }

    /**
     * Process the request available on the socket.
     *
     * The socket is available for reading with recv().
     */
    public function processRequest($socket)
    {
        Timer::start('photon.process_request');
        $conn = new \photon\mongrel2\Connection($socket, $this->pub_socket);
        $mess = $conn->recv();
        // This could be converted to use server_id + listener
        // connection id, it will wrap but should provide enough
        // uniqueness to track the effect of a request in the app.
        $uuid = request_uuid($this->server_id); 
        $req = new \photon\http\Request($mess);
        $req->uuid = $uuid;
        $req->conn = $conn;
        list($req, $response) = \photon\core\Dispatcher::dispatch($req);
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
        unset($mess); // Cleans the memory with the __destruct call.
        Log::perf(array('photon.process_request', $uuid, 
                        Timer::stop('photon.process_request')));
    }

    /**
     * Send the smart stats to the smart server.
     *
     * A list request provides the id, memory stats, processed
     * requests and uptime of the current process.
     */
    public function sendSmartStats()
    {
        $this->stats['memory_current'] = memory_get_usage();
        $this->stats['memory_peak'] = memory_get_peak_usage();
        $data = json_encode($this->stats);
        $ans = sprintf('%s %s %d:%s', $this->server_id, 'STATS',
                       strlen($data), $data);
        return $this->smart->send($ans);
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
             die(0); // Happy death, normally we run the predeath hook.
         }
     }

    public function registerSignals()
    {
        if (!pcntl_signal(\SIGTERM, array('\photon\server\Server', 'signalHandler'))) {
            Log::fatal('Cannot install the SIGTERM signal handler.');
            die(1);
        }
    }
}
