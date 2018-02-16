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
use photon\template\compiler as compiler;
use photon\path\Dir;

class Exception extends \Exception {}

class Base
{
    /*
     * The following member variables are set directly from the
     * command line options.
     */

    public $verbose = false;
    public $conf = ''; /**< Path to the configuration file */
    public $cwd = ''; /**< Current working directory */
    public $cmd = ''; /**< Current command name, i.e 'serve' */
    public $photon_path = '';
    public $help;
    public $version;

    /*
     * These are default internal member variables.
     */
    public $defaults = array();
    public $params = array();

    /**
     * @param $params Parameters from the command line
     */
    public function __construct($params)
    {
        $this->photon_path = (\Phar::running()) 
            ? \Phar::running()
            : realpath(__DIR__ . '/../'); 
        $defaults = array();
        foreach ($params as $key => $value) {
            $defaults[$key] = $this->$key; 
            $this->$key = $value;
        }
        $this->defaults = $defaults;
        $this->params = $params;
    }

    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->verbose) {
            echo $message . $eol;
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        echo $message . $eol;
    }

    /**
     * Returns an array with the configuration.
     *
     * The configuration is either stored in a php file or as a php
     * file within the Phar archive. The search path is:
     *  
     * - path given with the --conf parameter
     * - config.php in the current folder
     * - config.php packaged in the current Phar
     *
     * The command line parameters are set in the 'cmd_params' key.
     */
    public function loadConfig($default='config.php')
    {
        $paths=array();
        if (strlen($this->conf)) {
            $paths[] = $this->conf;
        }
        $paths[] = $this->cwd . '/' . $default;
        if (\Phar::running()) {
            $paths[] = \Phar::running() . '/' . $default;
        }
        $conf = null;
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->verbose(sprintf('Uses config file: %s.', $path));
                $conf = array_merge(array('tmp_folder' => sys_get_temp_dir()),
                                    include $path);
                break;
            }
        }
        if (null === $conf) {
            throw new Exception('No configuration files available.');
        }
        Conf::load($conf);
        Conf::set('cmd_params', $this->params);
        if ('' === Conf::f('secret_key', '')) {
            throw new Exception('The "secret_key" configuration variable is required.');
        }

        $this->checkPHP();

        return $path;
    }

    /*
     *  Analyse the PHP configuration
     *  This function must log only warning and recommendation about php.ini
     */
    public function checkPHP()
    {
        // Short tag generate syntax error in PHP when template contains XML, because <?xml contains <?
        if (ini_get('short_open_tag') === 1) {
            $this->info('PHP Configuration warning : short_open_tag is On, recommended value is Off');
        }

        // xdebug impacts on runtime performance
        if (extension_loaded('xdebug') === true) {
            $this->info('You are running with xdebug enabled. This has a major impact on runtime performance.');
        }
    }

    public function daemonize()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $this->verbose('Error: Could not fork.');

            exit(1);
        } elseif ($pid) {
            // In the parent, go and die nicely
            exit(0);            
        } else {
            // First fork done, go for the 2nd
            $pid = pcntl_fork();
            if (-1 === $pid) {
                $this->verbose('Error: Could not fork.');

                exit(1);
            } elseif ($pid) {
                // In the parent, we can write the pid and die
                file_put_contents($this->pid_file, $pid, LOCK_EX);
                exit(0);
            } else {
                // In the grand child, we keep running as daemon
                $this->daemon = true;

                return true;
            }
        }
    }
    
    public function startup()
    {
        $startup = Conf::f('startup', array());
        foreach($startup as $i) {
            call_user_func($i);
        }
    }
}

class ShowConfig extends Base
{
    public function run()
    {
        $this->loadConfig();
        $conf = Conf::dump();
        unset($conf['cmd_params']);
        var_export($conf);
    }
}

/**
 * Base class for the Server and Task.
 *
 * It redefines some of the methods to take into account if the process
 * is running as daemon or not.
 *
 * Each class extending this class must implement a runService() method.
 */
class Service extends Base
{
    /**
     * Track if in daemon or not. Needed for the info() and verbose()
     * calls.
     */
    public $daemon = false;
    public $daemonize = false;
    public $pid_file = './photon.pid';
    public $server_id;

    public function run()
    {
        $this->loadConfig();
        if ($this->daemonize) {
            $this->daemonize(); 
            $this->daemon = true;
            Conf::set('daemon', true);
        } else {
            $this->info('Press ^C to exit.');
        }
        $this->startup();

        return $this->runService();
    }

    /**
     * Output a message if in verbose mode.
     */
    public function verbose($message, $eol=PHP_EOL)
    {
        if ($this->verbose && !$this->daemon) {
            echo $message . $eol;
        }
        if ($this->verbose && $this->daemon) {
            Log::info($message);
        }
    }

    /**
     * Output a message.
     */
    public function info($message, $eol=PHP_EOL)
    {
        if (!$this->daemon) {
            echo $message . $eol;
        } else {
            Log::info($message);
        }
    }
}

/**
 * The server is starting a single handler.
 *
 * Photon is a single threaded low size daemon. It will use about 2MB
 * of memory to run one handler/task process. In practice you run a
 * collection of them controlled by your process manager. 
 *
 */
class Server extends Service
{
    /**
     * Run the production Photon server.
     *
     * By default, it outputs nothing, if you want some details, run
     * in verbose mode.
     */
    public function runService()
    {
        $server = new \photon\server\Server;
        return $server->start();
    }

}

/**
 * Task.
 *
 */
class Task extends Service
{
    public $task = '';

    /**
     * Overloaded to set the pid file.
     */
    public function __construct($params)
    {
        $this->pid_file = sprintf('./photon-%s.pid', $params['task']);
        parent::__construct($params);
    }

    public function runService()
    {
        $tasks = Conf::f('installed_tasks');
        if (isset($tasks[$this->task]) === false) {
            $this->info(sprintf('Unknown task %s', $this->task));
            return false;
        }

        $conf = Conf::f('photon_task_' . $this->task, array());
        $task = new $tasks[$this->task]($conf);

        return $task->run();
    }
}

/**
 * Generate a unique <code>secret_key</code> for your project configuration.
 *
 * Your unique to the project secret key to hmac validation of the
 * cookies and more.  This is critical to have a unique key per
 * project installation.
 */
class SecretKeyGenerator extends Base
{
    /**
    * Excludes the following ascii characters: ', " and \
    * @var array
    */
    protected static $to_excludes = array(34, 39, 92);
 
    public $length = 64;

    public function run()
    {
        $length = $this->params['length'] ?: 64;
        $this->info(self::generateSecretKey($length));
    }

    public static function getAsciiCode()
    {
        $ascii = mt_rand(32, 126);
        if (in_array($ascii, self::$to_excludes)) {
          $ascii = self::getAsciiCode();
        }

        return $ascii;
    }

    public static function generateSecretKey($length)
    {
        $secret_key = '';
        for ($i = 0; $length > $i; ++$i) {
            $secret_key .= chr(self::getAsciiCode());
        }

        return $secret_key;
    }

    public static function makeUuid()
    {
        $rnd = sha1(self::generateSecretKey(100));
        return sprintf('%s-%s-4%s-b%s-%s',
                       substr($rnd, 0, 8),
                       substr($rnd, 8, 4),
                       substr($rnd, 12, 3),
                       substr($rnd, 15, 3),
                       substr($rnd, 18, 12));
    }
}

/**
 * Packager - Package a project as a .phar
 *
 */
class Packager extends Base
{
    public $project; /**< Name of the phar archive without the extension */
    public $conf_file; /**< Configuration file loaded in the phar */
    public $exclude_files = ''; /**< Exclude files from the packaging */
    public $composer = null; /**< Build a phar for the composer version of photon */
    public $stub = null; /**< Build a phar with a custom stub */

    public function run()
    {
        $this->loadConfig(); 
        $this->startup();
        // Package all the photon code without the tests folder
        $phar_name = sprintf('%s.phar', $this->project);
        @unlink($phar_name);
        $phar = new \Phar($phar_name, 0);
        $phar->startBuffering();
    
        // Add project content
        $this->addProjectFiles($phar);

        // Add compiled tempate
        $this->CompileAddTemplates($phar, Conf::f('template_folders', array()));

        // Add optional configuration file
        if (null !== $this->conf_file) {
            $phar->addFile($this->conf_file, 'config.php');
            $phar['config.php']->compress(\Phar::GZ);
        }
        
        // Add phar stub
        $stubContent = '';
        if ($this->stub !== null) {
            $stubContent = file_get_contents($this->stub);
        } else {
            $stubContent = file_get_contents($this->photon_path . '/photon/data/pharstub.php');
            $stubContent = sprintf($stubContent, $phar_name, $phar_name, $phar_name, $phar_name);
        }
        $phar->setStub($stubContent);
        
        $phar->stopBuffering();
    }

    /**
     * Add the project files.
     *
     * We compress only the .php files.
     */
    public function addProjectFiles(&$phar)
    {
        foreach ($this->getProjectFiles() as $file => $path) {
            $this->verbose("[PROJECT ADD] " . $file);

            // Remove shebang 
            if ($file === 'vendor/photon/photon/src/photon.php') {
                $photon = file($file);
                array_shift($photon); // Remove shebang
                $phar->addFromString($file, implode('', $photon));
            } else {
                $phar->addFile($path, $file);
            }

            $phar[$file]->compress(\Phar::GZ);
        }
    }

    /**
     * Get the project files.
     *
     * This is an array with the key being the path to use in the phar
     * and the value the path on disk. Everything in the current
     * folder at the exception of the config.php, config.*.php is
     * included.
     *
     * @return array files
     */
    public function getProjectFiles()
    {
        $dirItr = new \RecursiveDirectoryIterator($this->cwd);

        if ($this->composer === true) {
            $files = glob('vendor/*/*/.pharignore');        // Pharignore of particules (including photon)
            $filter = array($this->cwd => '.pharignore');   // Pharignore of the project
            foreach($files as $f) {
                $filter[substr($f, 0, strlen($f) - strlen('/.pharignore'))] = '.pharignore';
            }
            $filterItr = new \photon\path\IgnoreFilterIterator($dirItr, 
                                                               $this->cwd, $filter);
        } else {
            $filterItr = new \photon\path\IgnoreFilterIterator($dirItr, 
                                                               $this->cwd, $this->cwd . '/.pharignore');
        }
    
        $itr = new \RecursiveIteratorIterator($filterItr, 
                                              \RecursiveIteratorIterator::SELF_FIRST);
        $files = array();
        foreach ($itr as $filePath => $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            $pharpath = substr($fileInfo->getRealPath(),
                               strlen($this->cwd) + 1,
                               strlen($fileInfo->getRealPath()));

            /*
             *  Fix for PHP Bug #64931 : phar_add_file is too restrive on filename
             *  Fixed in PHP 5.6.8 and 5.5.24
             */
            if (substr($pharpath, 0, 5) === '.phar') {
                $this->verbose("[PROJECT IGNORE] " . $filename);
                continue;
            }

            $files[$pharpath] = $fileInfo->getRealPath();
        }

        return $files;
    }

    /**
     * Compile and add the templates.
     *
     * @param $phar Phar archive
     * @param $folders Template folders
     */
    public function CompileAddTemplates($phar, $folders)
    {
        // The compiled template name not only depends on the file but
        // also on the possible folders in which it can be found.
        $base_name = var_export($folders, true);
        $compiled = '<?php
// Automatically generated by Photon at: ' . date('c') . '
// Photon - http://photon-project.com
namespace photon\template\compiled; 
';
        $base_template =  '// Extracted from: %s/%s
class %s
{
    public static function render($t) 
    {
        ?>%s<?php 
    } 
}
';
        $already_compiled = array();
        foreach ($folders as $folder) {
            $this->verbose(sprintf('Load templates in %s.', $folder));
            foreach (\photon\path\Dir::listFiles($folder) as $tpl) {
                if (!in_array($tpl, $already_compiled)) {
                    // Compile the template
                    $this->verbose("[PROJECT COMPILE] " . $tpl);
                    $compiler = new compiler\Compiler($tpl, $folders);
                    $content = $compiler->compile();
                    $class = 'Template_' . md5($tpl);
                    $content = sprintf($base_template, 
                                       $folder, $tpl,
                                       $class, $content);
                    $compiled .= $content;
                    $already_compiled[] = $tpl;
                }
            }
        }

        $phar->addFromString('photon/template/compiled.php', $compiled);
        //$phar['photon/template/compiled.php']->compress(\Phar::GZ);

        $this->verbose(sprintf('Added %d compiled templates.', 
                               count($already_compiled)));
    }
}


class PotGenerator extends Base
{
    public $potfile;

    public function run()
    {
        $this->loadConfig();
        $folders = Conf::f('template_folders', array());
        $tmp_folder = sys_get_temp_dir() . '/' . uniqid('photon', true);
        @mkdir($tmp_folder);
        @touch($this->potfile);

        // Compile all template to generate PHP Code
        $already_compiled = array();
        foreach ($folders as $folder) {
            foreach (\photon\path\Dir::listFiles($folder) as $tpl) {
                if (!in_array($tpl, $already_compiled)) {
                    // Compile the template
                    $compiler = new compiler\Compiler($tpl, $folders);
                    $content = $compiler->compile();

                    // save it
                    $output = $tmp_folder . '/' . $tpl;
                    $directory = dirname($output);
                    if (is_dir($directory) === false) {
                        mkdir($directory, 0777, true);
                    }
                    file_put_contents($output, $content);

                    $already_compiled[] = $tpl;
                }
            }
        }

        $return_var = 0;

        // Run xgettext on PHP project source
        $cmd = 'cd ' . $this->cwd . ' && find . -type f -iname "*.php" | sed -e \'s/^\\.\\///\' | xargs xgettext -o ' . $this->potfile . ' -p ' . $this->cwd . ' --from-code=UTF-8 -j --keyword --keyword=__ --keyword=_n:1,2 -L PHP';
        passthru($cmd, $return_var);

        // Run xgettext on PHP project compiled template source
        $cmd = 'cd ' . $tmp_folder . ' && find . -type f | sed -e \'s/^\\.\\///\' | xargs xgettext -o ' . $this->potfile . ' -p ' . $this->cwd . ' --from-code=UTF-8 -j --keyword --keyword=__ --keyword=_n:1,2 -L PHP';
        passthru($cmd, $return_var);

        \photon\path\Dir::remove($tmp_folder);
    }
}
