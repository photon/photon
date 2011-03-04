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
 * Background task management.
 */
namespace photon\task;
use photon\config\Container as Conf;

/**
 * Default subscribe address as used many times in this module.
 */
const SUB_ADDR = 'tcp://127.0.0.1:5997';

class Exception extends \Exception {}

class Broker
{
}

/**
 * The runner connects to the tasks and send the work.
 */
class Runner
{
    static $ctx = null;
    static $sockets = array();
    static $types = array();
    public $id = '';
    /**
     * It connects to the tasks on creation by default.
     *
     * @param $connect Connect to the tasks directly (true)
     */
    public function __construct($connect=true)
    {
        $this->id = 'NOT-USED-YET'; 
        if ($connect) {
            $this->connect();
        }
    }

    public function connect()
    {
        if (null !== self::$ctx) {
            return;
        }
        self::$ctx = new \ZMQContext(); 
        foreach (Conf::f('installed_tasks', array()) as $name => $class) {
            // We need to know if this is an async or sync task
            // We need to get the bind socket.
            $type = $class::$type;
            $conf = Conf::f('photon_task_' . $name, array());
            $bind = (isset($conf['sub_addr'])) 
                ? $conf['sub_addr'] : SUB_ADDR;
            if ('async' === $type) {
                self::$sockets[$name] = new \ZMQSocket(self::$ctx, 
                                                       \ZMQ::SOCKET_DOWNSTREAM);
                self::$sockets[$name]->connect($bind);
            } else {
                self::$sockets[$name] = new \ZMQSocket(self::$ctx, 
                                                       \ZMQ::SOCKET_REQ);
                self::$sockets[$name]->connect($bind);
            }
            self::$types[$name] = $type;
        }
    }

    public function run($task, $payload, $encode=true)
    {
        if ($encode) {
            $payload = json_encode($payload);
        }
        $mess = sprintf('%s %s %s', $task, $this->id, $payload);
        self::$sockets[$task]->send($mess);
        if ('async' === self::$types[$task]) {

            return;
        }
        list( , , $res) = explode(' ', self::$sockets[$task]->recv(), 3);
        return ($encode) ? json_decode($res) : $res;
    }
}


/**
 * This class is never used directly.
 *
 * @see SyncTask 
 * @see AsyncTask
 */
class BaseTask
{
    /**
     * Name of your task.
     *
     * Each task has a unique name, the name is normally the first
     * root of its namespace, but not necessarily. Just have a unique
     * one. The name is used to retrieve the configuration of the
     * task.
     * 
     * The task configuration is stored in: Conf::f('photon_task_NAME')
     */
    public $name = '';

    /**
     * Poller to poll for incoming jobs.
     */
    public $poll = null;

    /**
     * The ZMQ context.
     */
    public $ctx = null;

    /**
     * Where the request is provided.
     */
    public $sub_addr = SUB_ADDR;

    /**
     * Where the control requests are given.
     */
    public $ipc_internal_orders = 'ipc://photon-internal-orders';

    /**
     * Where the control answer is pushed.
     */
    public $ipc_internal_answers = 'ipc://photon-internal-answers';

    public $ctl_ord;
    public $ctl_ans;

    public $phid = '';
    public $stats = array('start_time' => 0,
                          'requests' => 0,
                          'memory_current' => 0,
                          'memory_peak' => 0);

    public function __construct()
    {
        $this->loadConfig(Conf::f('photon_task_' . $this->name, array()));
        $this->setupControl();
        $this->setupCom();
    }

    public function run()
    {
        $this->stats['start_time'] = time();
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
                    if ($r === $this->ctl_ord) {
                        // We are receiving an order!
                        $this->processOrder($this->ctl_ord);
                    } else {
                        $this->work($r);
                        $this->stats['requests']++;

                    }
                }
            }
            $this->loop();
            pcntl_signal_dispatch();
            clearstatcache();
        }
    }

    public function loadConfig($conf)
    {
        foreach ($conf as $key=>$value) {
            $this->$key = $value;
        }
    }

    /**
     * Setup the control ports.
     */
    public function setupControl()
    {
        // Get a unique id for the process
        $this->phid = sprintf('%s-task-%s-%s-%s', gethostname(), $this->name,
                              posix_getpid(), time());
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
        $this->ctl_ord->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, 'TASKS');
        $this->ctl_ord->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, $this->phid);

        $this->poll = new \ZMQPoll();
        $this->poll->add($this->ctl_ord, \ZMQ::POLL_IN);
        usleep(200000); 
    }

    /**
     * Setup the ZMQ communication with the clients.
     *
     * This really depends of the type of task, see AsyncTask and
     * SyncTask for examples. In fact, you should create your task by
     * extending SyncTask and AsyncTask most of the time.
     */
    public function setupCom()
    {
        throw new Exception('Not implemented.');
    }

    /**
     * This is the work method you need to implement.
     */
    public function work($socket)
    {
        throw new Exception('Not implemented.');
    }

    /**
     * If you want to perform operations on a regular basis.
     *
     * This function is called after each work() call or at least
     * every 200ms.
     */
    public function loop()
    {
    }

    /**
     * Handles the signals.
     *
     * @param $signo The POSIX signal.
     */
     static public function signalHandler($signo)
     {
         if (SIGTERM === $signo) {
             self::preTerm();
             exit(0);
         }
     }

     /**
      * Run just before exiting because of a TERM request.
      */
     static public function preTerm()
     {
     }

    public function registerSignals()
    {
        if (!pcntl_signal(SIGTERM, array('\photon\task\BaseTask', 'signalHandler'))) {
            Log::fatal('Cannot install the SIGTERM signal handler.');
            exit(1);
        }
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
        if (!in_array($target, array('ALL', 'TASKS', $this->phid))) {
            Log::warn(array('Bad order destination', 
                            array($target, $order), 
                            array('ALL', 'TASKS', $this->phid)));
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
        self::preTerm();
        usleep(200000);
        die(0);
    }

}

class AsyncTask extends BaseTask
{
    public $sub_addr = SUB_ADDR;
    public $ctl = null; /**< Socket getting the jobs. */
    static $type = 'async';

    /**
     * We create an upstream socket to receive work.
     */
    public function setupCom()
    {
        $this->ctl = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_UPSTREAM);
        $this->ctl->bind($this->sub_addr);
        $this->poll->add($this->ctl, \ZMQ::POLL_IN);
    }
}

/**
 * This task will directly perform the operation and send an answer.
 *
 */
class SyncTask extends BaseTask
{
    public $sub_addr = SUB_ADDR;
    public $ctl = null; /**< Socket getting the jobs. */
    static $type = 'sync';

    /**
     * We create a REP socket to receive work.
     */
    public function setupCom()
    {
        $this->ctl = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_REP);
        $this->ctl->bind($this->sub_addr);
        $this->poll->add($this->ctl, \ZMQ::POLL_IN);
    }
}

/**
 * Logger task.
 *
 * This is an example of asynchronous task, you can from your
 * application send a message to be logged by the task.
 *
 * To configure this task, you need to put:
 *
 * 'photon_task_logger' => array('log_file' => '/path/to/file.log'),
 *
 * in your configuration file. It will use the default socket to
 * receive the work load, this socket is SUB_ADDR.
 */
class Logger extends AsyncTask
{
    public $name = 'photon_logger';
    public $log_file = '';
    static $stack = array();
    static $n = 0;

    /**
     * We timestamp and put the message in the stack, we do not write
     * yet to return as fast as possible and wait for a new
     * request. 
     */
    public function work($socket)
    {
        list($taskname, $client, $payload) = explode(' ', $socket->recv(), 3);
        self::$stack[] = sprintf("%s %s %s\n", date(DATE_ISO8601), $client, $payload);
    }

    /**
     * We will write to disk only every 60s max or 300 messages.
     * 
     * This is rough calculations. We have a 200ms poll time for the
     * job, so with a low load, we have basically 5 calls to loop()
     * per second. After 300 calls, we have a minute.
     *
     */
    public function loop()
    {
        self::$n++;
        if (300 < self::$n and count(self::$stack)) {
            file_put_contents($this->log_file, 
                              join('', self::$stack),
                              FILE_APPEND | LOCK_EX);
            self::$stack = array();
            self::$n = 0; 
        }
    }

    /**
     * We may have some messages left to flush before dying.
     */
    public static function preTerm()
    {
        if (count(self::$stack)) {
            file_put_contents($this->log_file, 
                              join('', self::$stack),
                              FILE_APPEND | LOCK_EX);
            self::$stack = array();
        }
    }
}

/**
 * Timer task.
 *
 * This is an example of synchronous task, you can request the current
 * time with or without microseconds.
 */
class TimeServer extends SyncTask
{
   public $name = 'photon_timeserver';

    /**
     * We answer with the time.
     */
    public function work($socket)
    {
        list($taskname, $client, $payload) = explode(' ', $socket->recv(), 3);
        $payload = json_decode($payload);
        $time = ('ms' == $payload) ? microtime(true) : time();
        // We build the answer:
        $mess = sprintf('%s %s %s', $client, $taskname, json_encode($time));
        $socket->send($mess);
    }
}
