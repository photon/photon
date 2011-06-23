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
    public $ipc_internal_orders = 'ipc://photon-internal-orders';

    /**
     * Where the control answer is pushed.
     */
    public $ipc_internal_answers = 'ipc://photon-internal-answers';

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
        $this->ctl_ans->connect($this->ipc_internal_answers);
        $this->ctl_ord = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_SUB); 
        $this->ctl_ord->connect($this->ipc_internal_orders);
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
                Timer::start('photon.main_poll_time');
                $events = $this->poll->poll($to_read, $to_write, 200000);
                $poll_time = Timer::stop('photon.main_poll_time');
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
            $this->updatePollStats($poll_time);
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
     */
    public function updatePollStats($poll_time)
    {
        $time = time();
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

    public function processRequest($conn)
    {
        $uuid = request_uuid($this->phid);
        Timer::start('photon.process_request');
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, $conn->reqs->recv());
        $stats = fstat($fp);
        rewind($fp);
        $mess = $conn->parse($fp);
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
                $response->sendIterable($mess, $conn);
            }
        }
        unset($mess); // Cleans the memory with the __destruct call.
        Log::perf(array('photon.process_request', $uuid, 
                        Timer::stop('photon.process_request'),
                        $stats['size']));
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
            $req = new \photon\http\Request($mess);
            list($req, $response) = \photon\core\Dispatcher::dispatch($req);
            // If the response is false, the view is simply not
            // sending an answer, most likely the work was pushed to
            // another backend. Yes, you do not need to reply after a
            // recv().
            if (false !== $response) {
                if (is_string($response->content)) {
                    $this->conn->reply($mess, $response->render());
                } else {
                    $response->sendIterable($mess, $this->conn);
                }
            }
            unset($mess); // Cleans the memory with the __destruct call.
        }
    }
}
