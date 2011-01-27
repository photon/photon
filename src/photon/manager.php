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
     * Make the base project directory structure.
     */
    public function makeDirs()
    {
        $dirs = array($this->project_dir,
                      $this->project_dir . '/doc',
                      $this->project_dir . '/apps',
                      $this->project_dir . '/apps/helloworld',
                      $this->project_dir . '/www',
                      $this->project_dir . '/www/media',
                      $this->project_dir . '/www/media/helloworld',
                      $this->project_dir . '/templates',
                      );
        if (is_dir($this->project_dir)) {
            throw new Exception(sprintf('Project folder already exists: %s.',
                                        $this->project_dir));
        }
        foreach ($dirs as $new_dir) {
            if (!@mkdir($new_dir, 0755)) {
                throw new Exception(sprintf('Cannot create folder: %s.',
                                            $new_dir));
            }
        }
    }

    /**
     * Generate the default files for the project.
     *
     */
    public function generateFiles()
    {
        // Project files
        $project_template_dir = __DIR__ . '/data/project_template';
        $files = array('config.test.php', 'config.php', 'urls.php');
        foreach ($files as $file) {
            if (!copy($project_template_dir . '/' . $file,
                      $this->project_dir . '/' . $file)) {
                throw new Exception(sprintf('Failed to copy: %s to %s.',
                                            $project_template_dir . '/' . $file,
                                            $this->project_dir . '/' . $file));
            }
        }
        // TODO: Generate the unique private key

        // App files
        $app_template_dir = __DIR__ . '/data/app_template';
        $files = array('config.php', 'urls.php');
    }

    /**
     * Run the command.
     */
    public function run()
    {
        $this->makeDirs();
        $this->generateFiles();
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
 * Run the production server.
 *
 * Photon is a single threaded low size daemon. It will use about 2MB
 * of memory to run one server process. In practice you run a
 * collection of server process. Each start command will start a new
 * process, you can then stop all the processes or just one.
 *
 * The Photon servers runs in the background and listen to both the
 * Mongrel2 requests, the control requests and the SIGTERM
 * signal. This is done using a poller for the zeromq requests with a
 * 500ms timeout to catch the SIGTERM event.
 *
 */
class Server extends Base
{
    /** 
     * Requests poller. 
     *
     * 
     */
    public $poll = null; 
    public $ctl = null;

    /**
     * Track if in daemon or not. Needed for the info() and verbose()
     * calls.
     */
    public $daemon = false;

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
        // If the ./apps subfolder is found, it is automatically added
        // to the include_path.
        if (file_exists($this->params['cwd'].'/apps')) {
            set_include_path(get_include_path() . PATH_SEPARATOR 
                             . $this->params['cwd'].'/apps');
            $this->verbose(sprintf('Include path is now: %s',
                                   get_include_path()));
        }

        $server = new \photon\server\Server(Conf::f('server_conf', array()));
        // Go daemon
        $this->daemonize();

        return $server->start();
    }

    public function daemonize()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $this->verbose('Error: Could not fork.');
            return false;
        } elseif ($pid) {
            // In the parent, we can die.
            exit(0);
        } else {
            $this->daemon = true;
            return true;
        }
    }
}

/**
 * Command the production servers.
 *
 */
class CommandServer extends Base
{
    public $poll = null; /**< Answer poller. */
    public $ctl = null; /**< Control publisher socket. */

    /**
     * Setup the zmq communication to talk to servers.
     *
     */
    public function setupSenderCom()
    {
        $ctx = new \ZMQContext();

        $this->ctl = new \ZMQSocket($ctx, \ZMQ::SOCKET_PUB); 
        $this->ctl->bind(Conf::f('photon_ipc_control_orders', 
                                 'ipc://photon-control-orders'));
        $ctl_ans = new \ZMQSocket($ctx, \ZMQ::SOCKET_PULL);
        $ctl_ans->bind(Conf::f('photon_ipc_control_answers', 
                               'ipc://photon-control-answers'));
        $this->poll = new \ZMQPoll();
        $this->poll->add($ctl_ans, \ZMQ::POLL_IN);

        $this->verbose('Let the connections go up...', '');
        sleep(1); // Needed to let the subscribers connect
        $this->verbose(' done.');
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
        return $this->ctl->send(sprintf('%s %s', $target, $cmd));
    }

    /**
     * Read the command answers.
     *
     * Answers are sent by the processes, an answer is simply:
     *
     * "PHOTONID COMMAND LEN:JSONPAYLOAD"
     * 
     * The answer provides the name of the command back, the PHOTONID
     * of the process providing the answer and a net string with the
     * json encoded payload of the answer.
     *
     * @param int $timeout Timeout in ms (1000)
     * @return array Photon id index array of payloads
     */
    public function read($timeout=1000)
    {
        $poller_timeout = (int) ($timeout * 1000 / 2);
        $timeout = $timeout / 1000.0;
        $start = microtime(true);
        $readable = array();
        $writable = array(); // Not used.
        $answers = array();

        while (true) {
            if ($timeout < (microtime(true) - $start)) {

                return $answers;
            }
            $events = 0;
            try {
                $events = $this->poll->poll($readable, $writable, $poller_timeout);
                $errors = $this->poll->getLastErrors();
                if (count($errors) > 0) {
                    foreach ($errors as $error) {
                        $this->info('Error polling object: ' . $error);
                    }
                }
            } catch (\ZMQPollException $e) {
                $this->info('Poll failed: ' . $e->getMessage());

                return $answers;
            }
            if ($events > 0) {
                foreach ($readable as $r) {
                    try {
                        list($id, $answer) = $this->parseAnswer($r->recv());
                        if ('PONG' === $answer) {
                            $this->verbose('PONG from: ' . $id);
                        } else {
                            $answers[$id] = $answer;
                        }
                    } catch (\ZMQException $e) {
                        $this->info('Erro recv failed: ' . $e->getMessage());

                        return $answers;
                    }
                }
            }
        }
        
        return $answers;
    }        

    /**
     * Parse the command answers.
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
        $timeout = ($this->params['wait'] > 0) ? $this->params['wait'] * 1000 : 1000;
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('PING');
        $this->sendCommand('LIST');
        $this->info('Waiting for the answers...');
        $answers = $this->read($timeout);
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
        $timeout = ($this->params['wait'] > 0) ? $this->params['wait'] * 1000 : 1000;
        // We send the order to all the servers. Servers must
        // subscribe to ALL and to their own id.
        $this->sendCommand('PING');
        $this->sendCommand('STOP');
        $this->info('Waiting for the answers...');
        $answers = $this->read($timeout);
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

    public function runStart()
    {

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
        $server = new \photon\server\Server(Conf::f('server_conf', array()));
        $server->start();
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
 * current working directory and use the photon/testbootstrap.php file
 * to bootstrap the run. These two variables can be overwritten.
 *
 */
class RunTests extends Base
{
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
            $this->verbose('Nothing to test.');

            return 2;
        }
        // Now we generate the XML config file for PHPUnit
        $tmpl = '<phpunit><testsuites><testsuite name="Photon Tests">'
            . "\n%s\n%s\n" . '</testsuite></testsuites></phpunit>';
        $test_files = array_map(function($file) { 
                                    return '<file>' . $file . '</file>';
                                }, $test_files);
        $test_dirs = array_map(function($dir) { 
                                   return '<directory>' . $dir . '</directory>';
                               }, $test_dirs);
        $xml = sprintf($tmpl, 
                       implode("\n", $test_files),
                       implode("\n", $test_dirs));
        $tmpfname = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit');
        file_put_contents($tmpfname, $xml, LOCK_EX);
        $this->verbose('PHPUnit configuration file:');
        $this->verbose($xml);

        if (isset($this->params['directory'])) {
            if (!file_exists($this->params['directory'])) {
                mkdir($this->params['directory']);
            }
            passthru('phpunit '.$inc_path.'--bootstrap '.realpath(__DIR__).'/autoload.php --coverage-html '.realpath($this->params['directory']).' --configuration '.$tmpfname, $rvar);
            $this->info(sprintf('Code coverage report: %s/index.html.',
                                realpath($this->params['directory'])));
        } else {
            $xmlout = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit').'.xml';

            passthru('phpunit '.$inc_path.'--bootstrap '.realpath(__DIR__).'/autoload.php --coverage-clover '.$xmlout.' --configuration '.$tmpfname, $rvar);
            if (!file_exists($xmlout)) {

                return $rvar;
            }
            $xml = simplexml_load_string(file_get_contents($xmlout));
            unlink($xmlout);
            if (!isset($xml->project->metrics['coveredstatements'])) {

                return $rvar;
            }
            $this->info(sprintf('Code coverage %s/%s (%s%%)',
                                $xml->project->metrics['coveredstatements'],
                                $xml->project->metrics['statements'],
                                round(($xml->project->metrics['coveredstatements']/(float)$xml->project->metrics['statements']) * 100.0, 2)));
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
        Conf::load($this->getConfig());
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
