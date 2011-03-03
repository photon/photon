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
 * Support module of the command line utility.
 */
namespace photon\manager;

use photon\config\Container as Conf;
use photon\log\Log as Log;

class Exception extends \Exception
{}

class Base
{
    public $params;
    public $project_dir;

    /**
     * Generate the structure of a new project.
     *
     * @param $params Parameters from the command line
     */
    public function __construct($params)
    {
        $this->params = $params;
        if (isset($this->params['project'])) {
            $this->project_dir = $this->params['cwd'] . '/' . $this->params['project'];
        }
    }

    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->params['verbose']) {
            echo $message.$eol;
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        echo $message.$eol;
    }

    /**
     * Returns an array with the configuration.
     *
     * Either the configuration is in the config.php file in the
     * current working directory or it is defined by the --conf
     * parameter.
     */
    public function getConfig()
    {
        $this->params['photon_path'] = realpath(__DIR__ . '/../');
        $config_file = $this->params['cwd'] . '/config.php';
        if (null !== $this->params['conf']) {
            $config_file = $this->params['conf'];
        }
        if (!file_exists($config_file)) {
            throw new Exception(sprintf('The configuration file is not available: %s.',
                                        $config_file));
        }
        $this->verbose(sprintf('Uses config file: %s.', 
                               realpath($config_file)));

        return include $config_file;
    }
}

/**
 * Initialisation of a new project.
 *
 * A new project includes the simple "Hello Wordl!" demo
 * application. You can use the m2config command to then get the
 * corresponding Mongrel2 configuration file to test your application.
 *
 */
class Init extends Base
{

    /**
     * Generate the default files for the project.
     * recursively copies the data/project_template directory
     * renames __APPNAME__ along the way
     * @param string $app_name the directory name for the app, like 'helloworld'
     * @return void
     */
    public function generateFiles($app_name)
    {
        // make the initial project directory
        if (!mkdir($this->project_dir)) {
            throw new Exception(sprintf("Failed to make directory {$this->project_dir}"));
        }

        // recursively copy the project_template directory
        $src_directory =  __DIR__ . '/data/project_template';
        $src_directory_length = strlen($src_directory) + 1;
        $dir_iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src_directory), \RecursiveIteratorIterator::SELF_FIRST);
        foreach($dir_iterator as $src_filepath) {
            if (substr(basename($src_filepath),0,1) == '.') continue; // ignore . .. .DS_Store

            // build the destination filepath
            $dest_directory_rel_path = substr($src_filepath, $src_directory_length);
            $dest_filepath = $this->project_dir . '/' . $dest_directory_rel_path;


            // make the directory or copy the file
            if (is_dir($src_filepath)) {
                // make sure the dest directory exists
                if (!file_exists($dest_filepath)) {
                    if (!mkdir($dest_filepath)) {
                        throw new Exception(sprintf("Failed to make directory {$dest_filepath}"));
                    }
                }
            } else {
                // copy the file
                if (!copy($src_filepath, $dest_filepath)) {
                    throw new Exception(sprintf('Failed to copy: %s to %s.', $src_filepath, $dest_filepath));
                }
            }
        }

        // TODO: Generate the unique private key

    }

    /**
     * Run the command.
     */
     public function run()
     {
         // make sure project directory doesn't already exist
         if (is_dir($this->project_dir)) {
             throw new Exception(sprintf('Project folder already exists: %s.',
                 $this->project_dir));
         }

         // copy the application template
         $this->generateFiles('helloworld');
     }
}

/**
 * Run the development server.
 */
class TestServer extends Base
{
    public function run()
    {
        Conf::load($this->getConfig());
        // If the ./apps subfolder is found, it is automatically added
        // to the include_path.
        if (file_exists($this->params['cwd'].'/apps')) {
            set_include_path(get_include_path() . PATH_SEPARATOR 
                             . $this->params['cwd'].'/apps');
            $this->verbose(sprintf('Include path is now: %s',
                                   get_include_path()));
        }
        $this->info('Starting the development server.');
        $this->info('Press ^C to exit.');
        $server = new \photon\server\TestServer(Conf::f('server_conf', array()));
        $server->start();
    }
}

/**
 * The server manager is forking children to handle the requests.
 *
 * The fork is done by running `photon server childstart` with the
 * same configuration file as the `photon server start` call.
 *
 * Photon is a single threaded low size daemon. It will use about 2MB
 * of memory to run one child process. In practice you run a
 * collection of child processes controlled by the ServerManager. You
 * communicate directly with the ServerManager process which itself
 * communicate with the children.
 *
 * The Photon servers runs in the background and listen to both the
 * Mongrel2 requests, the control requests from the ServerManager and
 * the SIGTERM signal. This is done using a poller for the zeromq
 * requests with a 500ms timeout to catch the SIGTERM event.
 *
 */
class ServerManager extends Base
{
    /** 
     * Answers and requests poller. 
     *
     * Contains $childs_pull and $ctl_rep.
     */
    public $poll = null; 
    public $childs_pub = null;
    public $childs_pull = null;
    public $ctl_rep = null;
    public $ctx = null;

    /**
     * List of children and tasks PIDs.
     *
     * Static to be able to access it in the signal handler for the
     * TERM call. That way the handler can TERM the childs too.
     */
    static $childs = array();
    static $tasks = array();

    /**
     * Track if in daemon or not. Needed for the info() and verbose()
     * calls.
     */
    public $daemon = false;

    /**
     * Command used to launch a new child and task.
     */
    public $childcmd = array();
    public $taskcmd = array();

    /**
     * Children/tasks answer stack.
     *
     * When an order is dispatched to the children, the answers can
     * take a bit of time to come. The answer stack is populated and
     * then after maximum 1 sec or after getting answers for all the
     * children, it is flushed back to the client.
     */
    public $childs_ans = array();

    /**
     * When the last order was requested.
     */
    public $order_time = null; 
    public $in_order = false;

    /**
     * Run the production Photon server.
     *
     * The system tries to compute the maximum number of information
     * before the fork, when everything ok, it forks and setup the zmq
     * and signal handlers and the parent dies.
     *
     * By default, it outputs nothing, if you want some details, run
     * in verbose mode.
     */
    public function run()
    {
        Conf::load($this->getConfig());
        $n_children = ($this->params['children']) ? 
            $this->params['children'] : 3;
        // We have a double fork. First to create the master daemon,
        // then to get new children.
        $this->daemonize(); 
        $this->setupChildrenCom();
        $this->registerSignals();
        // We are the master daemon.
        $this->childcmd = $this->makeChildCmd($this->params['argv']);
        $this->taskcmd = $this->makeTaskCmd($this->params['argv']);
        foreach (Conf::f('installed_tasks', array()) as $name => $class) {
            self::$tasks[] = $this->makeTask($name);
            usleep(20000); // sleep 20 ms between the forks to make
                           // the tasks
        }
        for ($i=$n_children; $i>0; $i--) {
            // Daemonize without killing the master
            self::$childs[] = $this->makeChild();
            usleep(20000); // sleep 20 ms between the forks to make
                           // the children
        }
        // Now, we poll for the control
        $this->setupControlCom();      
        return $this->poll();
    }

    /**
     * Send a command.
     *
     * Photon is using a very simple text based protocol to
     * communicate with the processes. The command format is:
     *
     * "TARGET COMMAND"
     *
     * TARGET is either ALL or a given Photon process id (not the pid
     * as we can have Photon processes with the same pid accross
     * different servers). The COMMAND is just a string. At the
     * moment, the availabel commands are LIST, INFO and STOP.
     *
     * @param $cmd string command
     * @param $target string (ALL)
     * @return bool success
     */
    public function sendCommand($cmd, $target='ALL')
    {
        $this->verbose(sprintf('Send command: %s %s', $target, $cmd));
        return $this->childs_pub->send(sprintf('%s %s', $target, $cmd));
    }

    /**
     * Read the children and command answers.
     *
     * Answers are sent by the processes, an answer is simply:
     *
     * "PHOTONID COMMAND LEN:JSONPAYLOAD"
     * 
     * The answer provides the name of the command back, the PHOTONID
     * of the process providing the answer and a net string with the
     * json encoded payload of the answer.
     *
     * Order are simply forwarded.
     *
     */
    public function poll()
    {
        $to_read = array();
        $to_write = array(); // Not used.
        $events = 0;
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
                // FIXME we need to kill the childs
                return 1;
            }
            if ($events > 0) {
                foreach ($to_read as $r) {
                    if ($r === $this->childs_pull) {
                        // We are receiving an answer from a child
                        $this->processAnswer($r);
                    }
                    if ($r === $this->ctl_rep) {
                        // We are receiving an order!
                        $this->processOrder($r);
                    }
                }
            }
            $this->flushOrder();
            $this->checkChilds();
            pcntl_signal_dispatch();
            clearstatcache();
        }
    }        

    /**
     * Check if a child has exited.
     */
    public function checkChilds()
    {
        $status = null;
        while (0 < ($pid = pcntl_wait($status, WNOHANG | WUNTRACED))) {
            $exit = pcntl_wexitstatus($status);
            $idx = array_search($pid, self::$childs);
            if (false !== $idx) {
                unset(self::$childs[$idx]);
            }
            $idx = array_search($pid, self::$tasks);
            if (false !== $idx) {
                unset(self::$tasks[$idx]);
            }
        }
    }

    /**
     * If needed, flush the order back to the client.
     */
    public function flushOrder()
    {
        if (!$this->in_order) {
            return;
        }
        if ((count(self::$childs) + count(self::$tasks)) === count($this->childs_ans)
            || (microtime(true) - $this->order_time) > 1.0) {
            $this->in_order = false;
            $this->ctl_rep->send(json_encode($this->childs_ans));
            $this->childs_ans = array();
        }
    }

    /**
     * Parse the command answers.
     *
     * @param $mess string Message
     * @return array($photonid, $answer)
     */
    public function processAnswer($socket)
    {
        $ans = $socket->recv();
        list($id, $cmd, $payload) = explode(' ', $ans, 3);
        if ('PONG' === $cmd) {
            return; // Discarded, just internal comm.
        }
        $this->childs_ans[] = $ans;
    }

    /**
     * Forward an order to the childs or process it directly.
     *
     * If a stop all is requested, the order is catched and the kill
     * is done through signals.
     */
    public function processOrder($socket)
    {
        $this->in_order = true;
        $this->order_time = microtime(true);
        $this->childs_ans = array();
        $order = $socket->recv();
        if ('ALL STOP' === $order) {
            $data = json_encode(array(microtime(true)));
            $ans = sprintf('%s %s %d:%s', 'ALL', 'STOP', strlen($data), $data);
            $this->ctl_rep->send(json_encode(array($ans)));
            self::signalHandler(SIGTERM);
        } elseif ('NEW START' === $order) {
            self::$childs[] = $this->makeChild();
            usleep(20000);
            $data = json_encode(array(microtime(true)));
            $ans = sprintf('%s %s %d:%s', 'NEW', 'OK', strlen($data), $data);
            $this->ctl_rep->send(json_encode(array($ans)));
            $this->in_order = false;
            return;
        } elseif ('OLD LESS' === $order) {
            $pid = array_shift(self::$childs);
            posix_kill($pid, SIGTERM);
            usleep(20000);
            $data = json_encode(array(microtime(true)));
            $ans = sprintf('%s %s %d:%s', 'LESS', 'OK', strlen($data), $data);
            $this->ctl_rep->send(json_encode(array($ans)));
            $this->in_order = false;
            return;
        }
        $this->childs_pub->send($order);
    }

    /**
     * Start a new child.
     */
    public function runStart()
    {

    }


    /**
     * Setup the zmq communication to talk to the children.
     *
     * Must be done before calling setupControlCom()
     */
    public function setupChildrenCom()
    {
        $this->ctx = new \ZMQContext();

        $this->childs_pub = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUB); 
        $this->childs_pub->bind(Conf::f('photon_ipc_internal_orders', 
                                        'ipc://photon-internal-orders'));
        $this->childs_pull = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PULL);
        $this->childs_pull->bind(Conf::f('photon_ipc_internal_answers', 
                               'ipc://photon-internal-answers'));
        $this->poll = new \ZMQPoll();
        $this->poll->add($this->childs_pull, \ZMQ::POLL_IN);
    }

    /**
     * Setup the zmq communication to listen to the orders.
     *
     */
    public function setupControlCom()
    {
        $this->ctl_rep = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_REP);
        $this->ctl_rep->bind(Conf::f('photon_control_server', 
                               'ipc://photon-control-server'));
        $this->poll->add($this->ctl_rep, \ZMQ::POLL_IN);
    }


    /**
     * Generate the cmd to run a child.
     */
    public function makeChildCmd($current_cmd)
    {
        $argv = array();
        foreach ($current_cmd as $key => $val) {
            if ($val == 'start') {
                $argv[] = 'childstart';
                continue;
            }
            if (0 === strpos($val, '--children')) {
                continue;
            }
            $argv[] = $val;
        }
        $cmd = PHP_BINDIR . '/php';
        return array($cmd, $argv);
    }


    /**
     * Generate the cmd to run a task.
     */
    public function makeTaskCmd($current_cmd)
    {
        $argv = array($current_cmd[0]);
        // Find conf
        foreach ($current_cmd as $key => $val) {
            if (0 === strpos($val, '--conf')) {
                $argv[] = $val;
                break;
            }
        }
        $argv[] = 'taskstart';
        $cmd = PHP_BINDIR . '/php';
        return array($cmd, $argv);
    }

    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->params['verbose'] && !$this->daemon) {
            echo $message.$eol;
        }
        if ($this->params['verbose'] && $this->daemon) {
            Log::info($message);
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        if (!$this->daemon) {
            echo $message.$eol;
        } else {
            Log::info($message);
        }
    }

    public function daemonize()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $this->verbose('Error: Could not fork.');
            return false;
        } elseif ($pid) {
            // In the parent, we can write the pid and die
            $pid_file = Conf::f('pid_file', './run.pid');
            file_put_contents($pid_file, $pid, LOCK_EX);
            exit(0);
        } else {
            $this->daemon = true;
            return true;
        }
    }

    public function makeChild()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $this->verbose('Error: Could not fork.');
            return false;
        } elseif ($pid) {
            // In the parent
            return $pid;
        } else {
            // We are in the child process
            pcntl_exec($this->childcmd[0], $this->childcmd[1], $_ENV);
            exit(0);
        }
    }

    public function makeTask($task)
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $this->verbose('Error: Could not fork.');
            return false;
        } elseif ($pid) {
            // In the parent
            return $pid;
        } else {
            // We are in the child process
            $arg = array_merge($this->taskcmd[1], array($task));
            pcntl_exec($this->taskcmd[0], $arg, $_ENV);
            exit(0);
        }
    }

    /**
     * Handles the signals.
     *
     * @param $signo The POSIX signal.
     */
     static public function signalHandler($signo)
     {
         if (SIGTERM === $signo) {
             foreach (self::$childs as $child) {
                 // TODO: Log the kill of the childs.
                 posix_kill($child, SIGTERM);
                 usleep(20000);
             }
             foreach (self::$tasks as $task) {
                 // TODO: Log the kill of the childs.
                 posix_kill($task, SIGTERM);
                 usleep(20000);
             }
             exit(0);
         }
     }

    public function registerSignals()
    {
        if (!pcntl_signal(SIGTERM, array('\photon\manager\ServerManager', 'signalHandler'))) {
            Log::fatal('Cannot install the SIGTERM signal handler.');
            die(1);
        }
    }

}

/**
 * Child of the production server.
 *
 * A child is just running the server. It answers queries on the
 * internal ipc ports and die on the SIG_TERM signal. It is supposed
 * to be forked by the ServerManager.
 */
class ChildServer extends Base
{
    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->params['verbose'] && $this->daemon) {
            Log::info($message);
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        Log::info($message);
    }

    /**
     * Run the production Photon server.
     */
    public function run()
    {
        Conf::load($this->getConfig());
        // If the ./apps subfolder is found, it is automatically added
        // to the include_path.
        if (file_exists($this->params['cwd'].'/apps')) {
            set_include_path(get_include_path() . PATH_SEPARATOR 
                             . $this->params['cwd'].'/apps');
            $this->verbose(sprintf('Include path is now: %s',
                                   get_include_path()));
        }

        $server = new \photon\server\Server(Conf::f('server_conf', array()));

        return $server->start();
    }
}

/**
 * Task.
 *
 * A task is a bit like a children, but it does not answer directly to
 * Mongrel2 requests. It has a ipc control ports and react on
 * SIG_TERM. The ServerManager is taking care of them.
 */
class Task extends Base
{
    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->params['verbose'] && $this->daemon) {
            Log::info($message);
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        Log::info($message);
    }

    /**
     * Run the production Photon server.
     */
    public function run()
    {
        Conf::load($this->getConfig());
        // If the ./apps subfolder is found, it is automatically added
        // to the include_path.
        if (file_exists($this->params['cwd'].'/apps')) {
            set_include_path(get_include_path() . PATH_SEPARATOR 
                             . $this->params['cwd'].'/apps');
            $this->verbose(sprintf('Include path is now: %s',
                                   get_include_path()));
        }
        $tasks = Conf::f('installed_tasks');
        $task = new $tasks[$this->params['task']];
        return $task->run();
    }
}

/**
 * Command the production servers.
 *
 */
class CommandServer extends Base
{
    /**
     * Client to access the ServerManager.
     */
    public $client = null; 
    public $ctx = null;

    /**
     * Setup the client.
     */
    public function setupSenderCom()
    {
        $this->ctx = new \ZMQContext();
        $this->client = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_REQ);
        $this->client->connect(Conf::f('photon_control_server', 
                                       'ipc://photon-control-server'));
    }

    /**
     * Send a command.
     *
     * Photon is using a very simple text based protocol to
     * communicate with the processes. The command format is:
     *
     * "TARGET COMMAND"
     *
     * TARGET is either ALL or a given Photon process id (not the pid
     * as we can have Photon processes with the same pid accross
     * different servers). The COMMAND is just a string. At the
     * moment, the availabel commands are LIST, INFO and STOP.
     *
     * @param $cmd string command
     * @param $target string (ALL)
     * @return bool success
     */
    public function sendCommand($cmd, $target='ALL')
    {
        $this->verbose(sprintf('Send command: %s %s', $target, $cmd));
        return $this->client->send(sprintf('%s %s', $target, $cmd));
    }

    public function readAns()
    {
        return $this->client->recv();
    }


    /**
     * The ServerManager is bulking together all the answers.
     */
    public function parseAnswers($raw)
    {
        $answers = array();
        foreach (json_decode($raw) as $ans) {
            list($id, $answer) = $this->parseAnswer($ans);
            $answers[$id] = $answer;
        }

        return $answers;
    }

    /**
     * Parse the command answer.
     *
     * @param $mess string Message
     * @return array($photonid, $answer)
     */
    public function parseAnswer($mess)
    {
        list($id, $cmd, $payload) = explode(' ', $mess, 3);
        if ('PONG' === $cmd) {

            return array($id, 'PONG', (float) $payload);
        }
        list($len, $payload) = explode(':', $payload, 2);

        return array($id, json_decode($payload));
    }

    /**
     * List the running Photon processes.
     *
     * If a given server id is provided, poll the process to retrieve
     * as many stats as possible.
     */
    public function runList()
    {
        Conf::load($this->getConfig());
        $this->setupSenderCom();
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('LIST');
        $this->info('Waiting for the answers...');
        $answers = $this->parseAnswers($this->readAns());
        $idlen = 0;
        foreach (array_keys($answers) as $id) {
            if (strlen($id) > $idlen) {
                $idlen = strlen($id);
            }
        }
        $now = new \DateTime();
        $total_mem = 0;
        $info = str_pad('Photon id', $idlen+2) .
            'Uptime        ' .
            'Served  ' .
            'Mem. (kB)  ' .
            'Peak mem. (kB)';
        $this->info($info);
        $this->info(str_pad('', strlen($info) + 2, '-'));
        foreach ($answers as $id => $stats) {
            $stats = (array) $stats;
            $date = new \DateTime();
            $date->setTimestamp($stats['start_time']);
            $uptime = date_diff($date, $now);

            $this->info(str_pad($id, $idlen+2) .
                        str_pad($uptime->format('%ad%H:%I:%S'), 12) . '  ' .
                        str_pad($stats['requests'], 6) . '  ' .
                        str_pad((int) ($stats['memory_current'] / 1000), 9) . '  ' .
                        (int) ($stats['memory_peak'] / 1000));
            $total_mem += $stats['memory_current'];
        }            
        $this->info(str_pad('', strlen($info) + 2, '-'));
        $this->info(sprintf('%d Photon servers running. Memory usage: %dkB.',
                            count($answers), $total_mem/1000));
        
        return 0;
    }

    /**
     * Stop the running Photon processes.
     *
     */
    public function runStop()
    {
        Conf::load($this->getConfig());
        $this->setupSenderCom();
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('STOP');
        $this->info('Waiting for the answers...');
        $answers = $this->parseAnswers($this->readAns());
        $idlen = 0;
        foreach (array_keys($answers) as $id) {
            if (strlen($id) > $idlen) {
                $idlen = strlen($id);
            }
        }
        $info = str_pad('Photon id', $idlen+2) . 'Answer ';
        $this->info($info);
        $this->info(str_pad('', strlen($info) + 2, '-'));
        foreach ($answers as $id => $stats) {
            $this->info(str_pad($id, $idlen+2) . 'KO');
        }            
        $this->info(str_pad('', strlen($info) + 2, '-'));
        $this->info(sprintf('%d Photon servers stopped.',
                            count($answers)));
        
        return 0;
    }

    /**
     * Start a new child.
     *
     */
    public function runStart()
    {
        Conf::load($this->getConfig());
        $this->setupSenderCom();
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('START', 'NEW');
        $this->info('Waiting for the answers...');
        $answers = $this->parseAnswers($this->readAns());
        $idlen = 0;
        foreach (array_keys($answers) as $id) {
            if (strlen($id) > $idlen) {
                $idlen = strlen($id);
            }
        }
        $info = str_pad('Photon id', $idlen+2) . 'Answer ';
        $this->info($info);
        $this->info(str_pad('', strlen($info) + 2, '-'));
        foreach ($answers as $id => $stats) {
            $this->info(str_pad($id, $idlen+2) . 'OK');
        }            
        $this->info(str_pad('', strlen($info) + 2, '-'));
        
        return 0;
    }

    /**
     * Stop an old child.
     *
     */
    public function runLess()
    {
        Conf::load($this->getConfig());
        $this->setupSenderCom();
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('LESS', 'OLD');
        $this->info('Waiting for the answers...');
        $answers = $this->parseAnswers($this->readAns());
        $idlen = 0;
        foreach (array_keys($answers) as $id) {
            if (strlen($id) > $idlen) {
                $idlen = strlen($id);
            }
        }
        $info = str_pad('Photon id', $idlen+2) . 'Answer ';
        $this->info($info);
        $this->info(str_pad('', strlen($info) + 2, '-'));
        foreach ($answers as $id => $stats) {
            $this->info(str_pad($id, $idlen+2) . 'OK');
        }            
        $this->info(str_pad('', strlen($info) + 2, '-'));
        
        return 0;
    }
}

/**
 * Run the tests.
 *
 * It will run all the tests of all the apps in the current
 * project. It passes the control to PHPUnit after the creation of an
 * XML file with the configuration of the test suite.
 *
 * The test runner tries to load the ./config.test.php file in the
 * current working directory and use the photon/autoload.php file
 * to bootstrap the run. These two variables can be overwritten.
 *
 */
class RunTests extends Base
{
    /**
     * Returns an array with the configuration.
     *
     * Either the configuration is in the config.test.php file in the
     * current working directory or it is defined by the --conf
     * parameter.
     */
    public function getConfig()
    {
        $this->params['photon_path'] = realpath(__DIR__ . '/../');
        $config_file = $this->params['cwd'] . '/config.test.php';
        if (null !== $this->params['conf']) {
            $config_file = $this->params['conf'];
        }
        if (!file_exists($config_file)) {
            throw new Exception(sprintf('The configuration file is not available: %s.',
                                        $config_file));
        }
        $this->verbose(sprintf('Uses config file: %s.', 
                               realpath($config_file)));

        return include $config_file;
    }

    public function getConfigPath()
    {
        $config_file = $this->params['cwd'] . '/config.test.php';
        if (null !== $this->params['conf']) {
            $config_file = $this->params['conf'];
        }
        $config_file = realpath($config_file);
        if (!file_exists($config_file)) {
            throw new Exception(sprintf('The configuration file is not available: %s.',
                                        $config_file));
        }
        if ('config.php' === basename($config_file)) {
            throw new Exception(sprintf('The test configuration file cannot be named config.php: %s.',
                                        $config_file));

        }
        $this->verbose(sprintf('Uses config file: %s.', 
                               realpath($config_file)));
        return $config_file;
    }

    public function run()
    {
        Conf::load($this->getConfig());
        $this->verbose('Run the project tests...');
        $inc_path = ''; // for phpunit
        if (file_exists($this->params['cwd'].'/apps')) {
            set_include_path(get_include_path() . PATH_SEPARATOR 
                             . $this->params['cwd'].'/apps');
            $this->verbose(sprintf('Include path is now: %s',
                                   get_include_path()));
            $inc_path = '--include-path '.$this->params['cwd'].'/apps ';
        }
        $apps = Conf::f('installed_apps', array());
        // Now, we have a collection of apps, but each app is not
        // necessarily in the 'apps' subfolder of the project, some
        // can be available on the include_path. So, we try to find
        // for each app, the corresponding tests folder.
        $test_dirs = array();
        $test_files = array();
        $inc_dirs = explode(PATH_SEPARATOR,  get_include_path());
        foreach ($apps as $app) {
            foreach ($inc_dirs as $dir) {
                if (file_exists($dir.'/'.$app.'/tests')) {
                    $test_dirs[] = realpath($dir.'/'.$app.'/tests');
                }
                if (file_exists($dir.'/'.$app.'/tests.php')) {
                    $test_files[] = realpath($dir.'/'.$app.'/tests.php');
                }
            }
        }
        if (0 === count($test_dirs) and 0 === count($test_files)) {
            $this->info('Nothing to test.');

            return 2;
        }
        // Now we generate the XML config file for PHPUnit
        $tmpl = '<phpunit><testsuites><testsuite name="Photon Tests">'
            . "\n%s\n%s\n" . '</testsuite></testsuites>
<filter><blacklist><directory suffix=".php">%s</directory></blacklist></filter>
<php>
  <env name="photon_config" value="%s"/>
</php>
</phpunit>';
        $test_files = array_map(function($file) { 
                                    return '<file>' . $file . '</file>';
                                }, $test_files);
        $test_dirs = array_map(function($dir) { 
                                   return '<directory>' . $dir . '</directory>';
                               }, $test_dirs);
        $xml = sprintf($tmpl, 
                       implode("\n", $test_files),
                       implode("\n", $test_dirs),
                       $this->params['photon_path'],
                       $this->getConfigPath()
                       );
        $tmpfname = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit');
        file_put_contents($tmpfname, $xml, LOCK_EX);
        $this->verbose('PHPUnit configuration file:');
        $this->verbose($xml);
        if (isset($this->params['directory'])) {
            if (!file_exists($this->params['directory'])) {
                mkdir($this->params['directory']);
            }
            passthru('phpunit '.$inc_path.'--bootstrap '.realpath(__DIR__).'/autoload.php --coverage-html '.realpath($this->params['directory']).' --configuration '.$tmpfname, $rvar);
            unlink($tmpfname);
            $this->info(sprintf('Code coverage report: %s/index.html.',
                                realpath($this->params['directory'])));
        } else {
            $xmlout = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit').'.xml';
            $this->verbose('phpunit '.$inc_path.'--bootstrap '.realpath(__DIR__).'/autoload.php --coverage-clover '.$xmlout.' --configuration '.$tmpfname);
            passthru('phpunit '.$inc_path.'--bootstrap '.realpath(__DIR__).'/testbootstrap.php --coverage-clover '.$xmlout.' --configuration '.$tmpfname, $rvar);
            unlink($tmpfname);
            if (!file_exists($xmlout)) {

                return $rvar;
            }
            $xml = simplexml_load_string(file_get_contents($xmlout));
            unlink($xmlout);
            if (!isset($xml->project->metrics['coveredstatements'])) {

                return $rvar;
            }
            $perc = (0 == $xml->project->metrics['statements']) ? 1.0 
                : $xml->project->metrics['coveredstatements']/(float)$xml->project->metrics['statements'];
            $this->info(sprintf('Code coverage %s/%s (%s%%)',
                                $xml->project->metrics['coveredstatements'],
                                $xml->project->metrics['statements'],
                                round($perc * 100.0, 2)));
        }
        return $rvar;
    }
}

/**
 * Run the Photon tests.
 *
 * It will run all the Photon tests. You can run it from everywhere at
 * anytime, this is very good when setting up a new system. You can
 * easily assess if your system is compatible with Photon.
 */
class SelfTest extends Base
{
    /**
     * Custom configuration generation.
     *
     * The goal of the selftest sequence is to perform Photon tests
     * without the need of a configuration file or a project. This
     * makes the run a bit tricky as we need to figure out everything
     * without help from the user. But this is really important for
     * the ease of use of Photon.
     *
     * If the configuration file is forced with the --conf option, it
     * will be used. Else it will use an automatically generated
     * configuration and will use it.
     */
    public function getConfig()
    {
        if (null !== $this->params['conf']) {
            $config_file = $this->params['conf'];
            if (!file_exists($config_file)) {
                throw new Exception(sprintf('The configuration file is not available: %s.',
                                            $config_file));
            }
            $this->verbose(sprintf('Uses config file: %s.', 
                                   realpath($config_file)));

            return include $config_file;
        } else {
            $this->verbose('Uses automatically generated configuration:');
            $config = array('tmp_folder' => sys_get_temp_dir(),
                            'secret_key' => 'SECRET_KEY');
            $this->verbose(var_export($config, true));

            return $config;
        }
    }

    public function run()
    {
        $this->verbose('Run Photon selftesting routines...');
        $this->info(sprintf('Photon %s by Loïc d\'Anterroches and contributors.', \photon\VERSION));
        Conf::load($this->getConfig());
        $this->info('Using ', ''); // To avoid a confusion with PHPUnit
        if (isset($this->params['directory'])) {
            if (!file_exists($this->params['directory'])) {
                mkdir($this->params['directory']);
            }
            $this->verbose('phpunit --bootstrap '.realpath(__DIR__).'/testbootstrap.php --coverage-html '.realpath($this->params['directory']).' '.realpath(__DIR__).'/tests/');
            passthru('phpunit --bootstrap '.realpath(__DIR__).'/testbootstrap.php --coverage-html '.realpath($this->params['directory']).' '.realpath(__DIR__).'/tests/', $rvar);
            $this->info(sprintf('Code coverage report: %s/index.html.',
                                realpath($this->params['directory'])));
        } else {
            $xmlout = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit').'.xml';
            $this->verbose('phpunit --bootstrap '.realpath(__DIR__).'/testbootstrap.php --coverage-clover '.$xmlout.' '.realpath(__DIR__).'/tests/');
            passthru('phpunit --bootstrap '.realpath(__DIR__).'/testbootstrap.php --coverage-clover '.$xmlout.' '.realpath(__DIR__).'/tests/', $rvar);
            $xml = simplexml_load_string(file_get_contents($xmlout));
            
            unlink($xmlout);
            $this->info(sprintf('Code coverage %s/%s (%s%%)',
                                $xml->project->metrics['coveredstatements'],
                                $xml->project->metrics['statements'],
                                round(($xml->project->metrics['coveredstatements']/(float)$xml->project->metrics['statements']) * 100.0, 2)));
        }

        return $rvar;
    }
}

/**
 * Initialisation of a new app.
 *
 */
class InitApp
{}

/**
 * Generate a unique key to set the <code>secret_key</code> key of your project configuration.
 *
 * Your unique to the project secret key to hmac validation of the cookies and more.
 * This is critical to have a unique key per project installation.
 */
class SecretKeyGenenator extends Base
{
    /**
    * Excludes the following ascii characters: ', " and \
    * @var array
    */
    protected static $to_excludes = array(34, 39, 92);

    public function run()
    {
        $length = $this->params['length'] ?: 65;
        $this->info(self::generateSecretKey($length));
    }

    protected static function getAsciiCode()
    {
        $ascii = mt_rand(32, 126);
        if (in_array($ascii, self::$to_excludes)) {
          $ascii = self::getAsciiCode();
        }

        return $ascii;
    }

    protected static function generateSecretKey($lenght)
    {
        $secret_key = '';
        for ($i = 0; $lenght > $i; ++$i) {
            $secret_key .= chr(self::getAsciiCode());
        }

        return $secret_key;
    }
}
