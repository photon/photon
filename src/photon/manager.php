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
 * Initialisation of a new project.
 *
 * A new project includes the simple "Hello Wordl!" demo
 * application. You can use the m2config command to then get the
 * corresponding Mongrel2 configuration file to test your application.
 *
 */
class Init extends Base
{
    public $project_dir = ''; /**< Store the full path to the project files */

    /**
     * Generate the default files for the project.
     * recursively copies the data/project_template directory
     * renames __APPNAME__ along the way
     * @return void
     */
    public function generateFiles()
    {
        // recursively copy the project_template directory
        $src_directory =  __DIR__ . '/data/project_template';
        $src_directory_length = strlen($src_directory) + 1;
        $dir_iterator = new \RecursiveIteratorIterator(
                          new \RecursiveDirectoryIterator($src_directory), 
                          \RecursiveIteratorIterator::SELF_FIRST);
        foreach($dir_iterator as $src_filepath) {
            if (substr(basename($src_filepath), 0, 1) == '.') {
                continue; // ignore '.', '..', '.DS_Store', '.*'
            }
            // build the destination filepath
            $dest_directory_rel_path = substr($src_filepath, $src_directory_length);
            $dest_filepath = $this->project_dir . '/' . $dest_directory_rel_path;
            // make the directory or copy the file
            if (is_dir($src_filepath)) {
                // make sure the dest directory exists
                if (!file_exists($dest_filepath)) {
                    if (!mkdir($dest_filepath)) {
                        throw new Exception(sprintf('Failed to make directory %s', $dest_filepath));
                    }
                }
            } else {
                // copy the file
                if (!copy($src_filepath, $dest_filepath)) {
                    throw new Exception(sprintf('Failed to copy: %s to %s.', $src_filepath, $dest_filepath));
                }
            }
        }

        $this->info(sprintf('Default project created in: %s.', $this->project_dir));
        $this->info('A README file is in the project to explain how to start mongrel2 and your photon project.');
        $this->info('Have fun! The Photon Project Team.');
    }

    /**
     * Run the command.
     */
     public function run()
     {
         $this->project_dir = $this->cwd . '/';

         // copy the application template
         $this->generateFiles();
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
        $conf = Conf::f('photon_task_' . $this->task, array());
        $task = new $tasks[$this->task]($conf);

        return $task->run();
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
    public $directory; /** Where to store the results of the tests */
    public $bootstrap; /** Different bootstrap file to use */

    public function run()
    {
        $this->verbose('Run the project tests...');
        $config_path = $this->loadConfig('config.test.php');
        $apps = Conf::f('tested_components', array());
        // Now, we have a collection of apps, but each app is not
        // necessarily in the 'apps' subfolder of the project, some
        // can be available on the include_path. So, we try to find
        // for each app, the corresponding tests folder.
        $test_dirs = array();
        $test_files = array();
        $inc_dirs = Dir::getIncludePath();
        foreach ($apps as $app) {
            foreach ($inc_dirs as $dir) {
                if (file_exists($dir . '/' . $app . '/tests')) {
                    $test_dirs[] = realpath($dir . '/' . $app . '/tests');
                }
                if (file_exists($dir . '/' . $app . '/tests.php')) {
                    $test_files[] = realpath($dir . '/' . $app . '/tests.php');
                }
            }
        }
        if (empty($test_dirs) && empty($test_files)) {
            $this->info('Nothing to test.');

            return 2;
        }
        // Now we generate the XML config file for PHPUnit
        $tmpl = '<phpunit><testsuites><testsuite name="Photon Tests">'
            . "\n%s\n%s\n" . '</testsuite></testsuites>
<filter><blacklist><directory suffix=".php">%s</directory></blacklist></filter>
<php>
  <env name="photon.config" value="%s"/>
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
                       $this->photon_path,
                       $config_path
                       );
        $tmpfname = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit');
        file_put_contents($tmpfname, $xml, LOCK_EX);
        $this->verbose('PHPUnit configuration file:');
        $this->verbose($xml);

        if (isset($this->params['directory'])) {
            if (!file_exists($this->params['directory'])) {
                mkdir($this->params['directory']);
            }
            passthru('phpunit --bootstrap '.realpath(__DIR__).'/autoload.php --coverage-html '.realpath($this->params['directory']).' --configuration '.$tmpfname, $rvar);
            unlink($tmpfname);
            $this->info(sprintf('Code coverage report: %s/index.html.',
                                realpath($this->params['directory'])));
        } else {
            $xmlout = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit').'.xml';

            $cmd = 'phpunit --verbose --bootstrap ' . realpath(__DIR__) . 
                   '/testbootstrap.php --coverage-clover ' . $xmlout . ' --configuration ' . $tmpfname;
            $this->verbose($cmd);
            passthru($cmd, $rvar);
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
    public $directory; /**< Code coverage report files */

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
    public function loadConfig($default='config.php')
    {
        try {
            $path = parent::loadConfig($default);
        } catch (Exception $e) {
            $this->verbose('Uses automatically generated configuration:');
            $config = array('tmp_folder' => sys_get_temp_dir(),
                            'secret_key' => 'SECRET_KEY');
            $this->verbose(var_export($config, true));
            Conf::load($config);
            Conf::set('cmd_params', $this->params);
            $path = '';
        }

        return $path;
    }

    public function run()
    {
        $this->verbose('Run Photon selftesting routines...');
        $this->info(sprintf('Photon %s by Loïc d\'Anterroches and contributors.', \photon\VERSION));
        $this->loadConfig();
        $this->info('Using ', ''); // To avoid a confusion with PHPUnit
        if (null !== $this->directory) {
            if (!file_exists($this->directory)) {
                mkdir($this->directory);
            }
            $cmd = 'phpunit --verbose --bootstrap ' . $this->photon_path . '/photon/testbootstrap.php ' 
                . '--coverage-html ' . realpath($this->directory) . ' '
                . $this->photon_path . '/photon/tests/';
            $this->verbose($cmd);
            passthru($cmd, $rvar);
            $this->info(sprintf('Code coverage report: %s/index.html.',
                                realpath($this->directory)));
        } else {
            $xmlout = tempnam(Conf::f('tmp_folder', sys_get_temp_dir()), 'phpunit').'.xml';
            $cmd = 'phpunit --verbose --bootstrap ' . $this->photon_path . '/photon/testbootstrap.php '
                . '--coverage-clover ' . $xmlout . ' '
                . $this->photon_path . '/photon/tests/';

            $this->verbose($cmd);
            passthru($cmd, $rvar);
            
            if(file_exists($xmlout) === false) {
                $this->info('Code coverage output file not found. Ensure the Xdebug module is loaded with "php --re xdebug"');
            } else {
                $xml = simplexml_load_string(file_get_contents($xmlout));
                unlink($xmlout);
                $this->info(sprintf('Code coverage %s/%s (%s%%)',
                                    $xml->project->metrics['coveredstatements'],
                                    $xml->project->metrics['statements'],
                                    round(($xml->project->metrics['coveredstatements']/(float)$xml->project->metrics['statements']) * 100.0, 2)));
            }
        }

        return $rvar;
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
    
    public function run()
    {
        $this->loadConfig(); 
        $this->startup();
        // Package all the photon code without the tests folder
        $phar_name = sprintf('%s.phar', $this->project);
        @unlink($phar_name);
        $phar = new \Phar($phar_name, 0);
        $phar->startBuffering();
        
        $this->verbose("Use composer : " . (($this->composer) ? "Yes" : "No"));
        
        if ($this->composer !== true) {
            // Old style PEAR Mode
            $this->addPhotonFiles($phar);
        }
        $this->addProjectFiles($phar);
        
        $this->CompileAddTemplates($phar, 
                                   Conf::f('template_folders', array()));
        if (null !== $this->conf_file) {
            $phar->addFile($this->conf_file, 'config.php');
            $phar['config.php']->compress(\Phar::GZ);
        }
        
        $stubFilename = ($this->composer === true) ? 'pharstub-composer.php' : 'pharstub.php';
        $stub = file_get_contents($this->photon_path . '/photon/data/' . $stubFilename);
        $phar->setStub(sprintf($stub, 
                               $phar_name, $phar_name, $phar_name, $phar_name));
        
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

            if (substr($file, -4) === '.php') {
                $phar[$file]->compress(\Phar::GZ);
            }
        }
    }

    /**
     * Add the photon files to the phar.
     */
    public function addPhotonFiles(&$phar)
    {
        foreach ($this->getPhotonFiles() as $file => $path) {
            $phar->addFile($path, $file);
            $phar[$file]->compress(\Phar::GZ);
        }

        $photon = file($this->photon_path . '/photon.php');
        foreach ($photon as &$line) {
            if (trim($line) == 'include_once __DIR__ . \'/photon/autoload.php\';') {
                $line = '    include_once \'photon/autoload.php\';'."\n";
            } else
            if (false !== mb_strstr($line, '@version@')) {
                $this->info("Photon run from source, not a PEAR install");
                $output = '';
                $return_var = 0;
                $command = 'git --git-dir="'. $this->photon_path .'/../.git" log -1 --format="%h"';
                exec($command, $output, $return_var);
                if ($return_var !== 0) {
                    throw new Exception('Can\'t get the last commit id.');
                }
                $this->info('Photon version is ' . \end($output));
                $line = str_replace('@version@', 'commit ' . \end($output), $line);
            }
        }
        array_shift($photon); // Remove shebang
        $phar->addFromString('photon.php', implode('', $photon));
        $phar['photon.php']->compress(\Phar::GZ);
        $this->verbose('[PHOTON GENERATE] photon.php');

        $auto = file($this->photon_path . '/photon/autoload.php');
        foreach ($photon as &$line) {
            if (0 === strpos(trim($line), 'set_include_path')) {
                $line = '';
            }
        }
        $phar->addFromString('photon/autoload.php', implode('', $auto));
        $phar['photon/autoload.php']->compress(\Phar::GZ);
        $this->verbose('[PHOTON GENERATE] photon/autoload.php');
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
     * Returns the list of files for Photon.
     *
     * This is an array with the key being the path to use in the phar
     * and the value the path on disk. photon.php and
     * photon/autoload.php are not included.
     *
     * @return array files
     */
    public function getPhotonFiles()
    {
        $out = array();
        $files = new \RecursiveIteratorIterator(
                     new \RecursiveDirectoryIterator($this->photon_path),
                     \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $disk_path => $file) {
            if (!$files->isFile()) {
                continue;
            }
            $phar_path = substr($disk_path, strlen($this->photon_path) + 1);
            if (false !== strpos($phar_path, 'photon/tests/')) {
                $this->verbose("[PHOTON IGNORE] " . $phar_path);
                continue;
            }
            if (false !== strpos($phar_path, 'photon/data/project_template')) {
                $this->verbose("[PHOTON IGNORE] " . $phar_path);
                continue;
            }
            if ($phar_path == 'photon/autoload.php') {
                $this->verbose("[PHOTON IGNORE] " . $phar_path);
                continue;
            }
            if ($phar_path == 'photon.php') {
                $this->verbose("[PHOTON IGNORE] " . $phar_path);
                continue;
            }
            $out[$phar_path] = $disk_path;
            $this->verbose("[PHOTON ADD] " . $phar_path);
        }        

        return $out;
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
