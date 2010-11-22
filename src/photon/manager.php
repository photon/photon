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

class Exception extends \Exception
{}

/**
 * Initialisation of a new project.
 *
 * A new project includes the simple "Hello Wordl!" demo
 * application. You can use the m2config command to then get the
 * corresponding Mongrel2 configuration file to test your application.
 *
 */
class Init
{
    public $config;
    public $project_dir;

    /**
     * Generate the structure of a new project.
     *
     * @param $config Configuration from the command line
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->project_dir = $this->config['cwd'] . '/' . $this->config['project'];
    }

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
 * Initialisation of a new app.
 *
 */
class InitApp
{}
