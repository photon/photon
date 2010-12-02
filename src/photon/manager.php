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
     * Returns an array with the configuration.
     *
     * Either the configuration is in the config.php file in the
     * current working directory or it is defined by the --conf
     * parameter.
     */
    public function getConfig()
    {
        $config_file = $this->params['cwd'] . '/config.php';
        if (isset($this->params['conf'])) {
            $config_file = $this->params['conf'];
        }
        if (!file_exists($config_file)) {
            throw new Exception(sprintf('The configuration file is not available: %s.',
                                        $config_file));
        }
        $this->verbose(sprintf('Use config file: %s.', realpath($config_file)));
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
        $files = array('config.php', 'urls.php');
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
 *
 * Note that the only way to stop it is just to kill the server at the
 * moment, I will add handling of signals and an ipc "kill port"
 * later. Note that the ipc "kill port" will be more than that, it
 * will be an ipc "command port" to, for examples, announce that a
 * server upgrade needs a code freeze. That is, all the requests will
 * send a "temporarily not available" answer, then the app can arakiri
 * itself and we load the new daemons with the new version after the
 * migration... yeah... zeromq really means "flexibility".
 */
class RunServer extends Base
{
    public function run()
    {
        Conf::load($this->getConfig());
        $this->verbose('Starting the development server.');
        $this->verbose('Press ^C to exit.');
        $server = new \photon\server\Server(Conf::f('server_conf', array()));
        $server->start();
    }
}

/**
 * Initialisation of a new app.
 *
 */
class InitApp
{}
