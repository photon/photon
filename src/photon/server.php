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

use photon\log\Log as Log;

/**
 * Production server.
 *
 * Compared to the TestServer, the production server opens an IPC
 * control port to be commanded, it also reacts on SIGTERM to stop
 * itself nicely.
 */
class Server
{
   /**
     * The id of this server daemon. Many servers can have the same
     * id, you can group your servers by ids and filter at answer at
     * the Mongrel2 level.
     */
    public $sender_id = '26f97e5e-2ce7-4381-9649-f61673892d2e';

    /**
     * Where the request is provided.
     */
    public $sub_addr = 'tcp://127.0.0.1:9997';

    /**
     * Where the answer is pushed.
     */
    public $pub_addr = 'tcp://127.0.0.1:9996';

    /**
     * Where the control requests are given.
     */
    public $ipc_control_orders = 'ipc://photon-control-orders';

    /**
     * Where the control answer is pushed.
     */
    public $ipc_control_answers = 'ipc://photon-control-answers';

    /**
     * Wrapper for the zeromq connections.
     */
    public $conn = null;

    /**
     * zeromq context.
     */
    public $ctx = null; 

    public $phid = '';

    public $stats = array('start_time' => 0,
                          'requests' => 0,
                          'memory_current' => 0,
                          'memory_peak' => 0);

    public function __construct($conf=array())
    {
        foreach ($conf as $key=>$value) {
            $this->$key = $value;
        }
    }

    /**
     * Must be started when running as daemon.
     */
    public function start()
    {
        $this->stats['start_time'] = time();

        // Get a unique id for the process
        $this->phid = sprintf('%s-%s-%s', gethostname(), posix_getpid(), time());
        $this->registerSignals(); // For SIGTERM handling

        // We create a zeromq context which will be used everywhere
        // within the process. The creation of a context is the
        // equivalent of one "zmq_init" and it should be run only
        // once.
        $this->ctx = new \ZMQContext(); 

        // We need to be able to listen to the control requests and
        // send answers.
        $this->ctl_ans = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUSH); 
        $this->ctl_ans->connect($this->ipc_control_answers);
        $this->ctl_ord = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_SUB); 
        $this->ctl_ord->connect($this->ipc_control_orders);
        $this->ctl_ord->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, 'ALL');
        $this->ctl_ord->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, $this->phid);
        usleep(200000); 

        // This makes the connection with the Mongrel2 server.        
        $this->conn = new \photon\mongrel2\Connection($this->sender_id,
                                                      $this->sub_addr,
                                                      $this->pub_addr,
                                                      $this->ctx);

        // Now, we push all the zmq sockets waiting for an answer in
        // the poll.
        $this->conn->reqs; // Mongrel2 requests.
        $this->conn->resp; // Mongrel2 answers.
        $this->conn->ctx; // zeromq context.

        $this->poll = new \ZMQPoll();
        $this->poll->add($this->conn->reqs, \ZMQ::POLL_IN);
        $this->poll->add($this->ctl_ord, \ZMQ::POLL_IN);

        $to_write = array(); 
        $to_read = array();

        while (true) {
            $events = 0;
            try {
                // We poll and wait a maximum of 200ms. 
                $events = $this->poll->poll($to_read, $to_write, 200000);
                $errors = $this->poll->getLastErrors();
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
                    if ($r === $this->conn->reqs) {
                        // We are receiving a request from Mongrel2
                        $this->processRequest($this->conn);
                        $this->stats['requests']++;
                    }
                    if ($r === $this->ctl_ord) {
                        // We are receiving an order!
                        $this->processOrder($this->ctl_ord);
                    }
                }
            }
            pcntl_signal_dispatch();
            clearstatcache();
        }
    }

    public function processRequest($conn)
    {
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, $conn->reqs->recv());
        rewind($fp);
        $mess = $conn->parse($fp);
        list($req, $response) = \photon\core\Dispatcher::dispatch($mess);
        // If the response is false, the view is simply not
        // sending an answer, most likely the work was pushed to
        // another backend. Yes, you do not need to reply after a
        // recv().
        if (false !== $response) {
            $conn->reply($mess, $response->render());
        }
        unset($mess); // Cleans the memory with the __destruct call.
    }

    /**
     * Process the orders.
     *
     * @param $socket zmq socket from which to read the orders.
     */
    public function processOrder($socket)
    {
        $order = $socket->recv();
        list($target, $order) = explode(' ', $order, 2);
        if (!in_array($target, array('ALL', $this->phid))) {
            Log::warn(array('Bad order destination', 
                            array($target, $order), 
                            array('ALL', $this->phid)));
            return false;
        }
        switch (trim($order)) {
        case 'PING':

            return $this->answerPong();
        case 'LIST':

            return $this->answerList();
        case 'STOP':

            return $this->answerStop();
        default:

            return false; // ignore
        }
    }

    /**
     * Answer to a LIST request.
     *
     * A list request provides the id, memory stats, processed
     * requests and uptime of the current process.
     */
    public function answerList()
    {
        $this->stats['memory_current'] = memory_get_usage();
        $this->stats['memory_peak'] = memory_get_peak_usage();
        $data = json_encode($this->stats);
        $ans = sprintf('%s %s %d:%s', $this->phid, 'LIST',
                       strlen($data), $data);
        return $this->ctl_ans->send($ans);
    }

    /**
     * Answer to a PING request.
     */
    public function answerPong()
    {
        $data = json_encode(array(microtime(true)));
        $ans = sprintf('%s %s %d:%s', $this->phid, 'PONG',
                       strlen($data), $data);
        return $this->ctl_ans->send($ans);
    }

    /**
     * Answer to a STOP request.
     */
    public function answerStop()
    {
        $data = json_encode(array(microtime(true)));
        $ans = sprintf('%s %s %d:%s', $this->phid, 'STOP',
                       strlen($data), $data);
        $this->ctl_ans->send($ans);
        usleep(200000);
        die(0);
    }

    
    /**
     * Handles the signals.
     *
     * @param $signo The POSIX signal.
     */
     static public function signalHandler($signo)
     {
         if (SIGTERM === $signo) {
             die(0); // Happy death, normally we run the predeath hook.
         }
     }

    public function registerSignals()
    {
        if (!pcntl_signal(SIGTERM, array('\photon\server\Server', 'signalHandler'))) {
            Log::fatal('Cannot install the SIGTERM signal handler.');
            die(1);
        }
    }
}


/**
 * Simplest server to handle requests without long polling.
 *
 */
class TestServer
{
    /**
     * The id of this server daemon. Many servers can have the same
     * id, you can group your servers by ids and filter at answer at
     * the Mongrel2 level.
     */
    public $sender_id = '26f97e5e-2ce7-4381-9649-f61673892d2e';

    /**
     * Where the request is provided.
     */
    public $sub_addr = 'tcp://127.0.0.1:9997';

    /**
     * Where the answer is pushed.
     */
    public $pub_addr = 'tcp://127.0.0.1:9996';

    /**
     * Wrapper for the zeromq connections.
     */
    public $conn = null;

    public function __construct($conf=array())
    {
        foreach ($conf as $key=>$value) {
            $this->$key = $value;
        }
    }

    public function start()
    {
        $this->conn = new \photon\mongrel2\Connection($this->sender_id,
                                                      $this->sub_addr,
                                                      $this->pub_addr);
        while ($mess = $this->conn->recv()) {
            list($req, $response) = \photon\core\Dispatcher::dispatch($mess);
            // If the response is false, the view is simply not
            // sending an answer, most likely the work was pushed to
            // another backend. Yes, you do not need to reply after a
            // recv().
            if (false !== $response) {
                $this->conn->reply($mess, $response->render());
            }
            clearstatcache();
            unset($mess); // Cleans the memory with the __destruct call.
        }
    }
}
