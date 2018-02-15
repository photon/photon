#!/usr/bin/env php
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
 * Command line utility script.
 *
 * This script is used to create a new project or to start a photon
 * server.
 */

namespace photon
{
    const VERSION = '@version@';

    /**
     * Returns a parser of the command line arguments.
     */
    function getParser()
    {
        require_once 'Console/CommandLine.php';
        $parser = new \Console_CommandLine(array(
            'name' => 'hnu',
            'description' => 'Photon command line manager.',
            'version'     => VERSION));

        $options = array('verbose' =>
                         array('short_name'  => '-v',
                               'long_name'   => '--verbose',
                               'action'      => 'StoreTrue',
                               'description' => 'turn on verbose output'),
                         'conf' =>
                         array('long_name'   => '--conf',
                               'action'      => 'StoreString',
                               'help_name'   => 'path/conf.php',
                               'description' => 'where the configuration is to be found. By default, the configuration file is the config.php in the current working directory'));

        foreach ($options as $name => $option) {
            $parser->addOption($name, $option);
        }

        $cmds = array('pot' =>
           array('desc' => 'generate a standard gettext template file for the project (.pot)',
                 'opts' => array('potfile' =>
                                 array('long_name'   => '--pot-file',
                                       'action'      => 'StoreString',
                                       'help_name'   => 'myproject.pot',
                                       'description' => 'Output filename for the gettext template'))),
                      'show-config' =>
           array('desc' => 'Dump the config file on the standard output, usefull to show phar packaged configuration'),
                      'serve' =>
           array('desc' => 'start a Photon handler server',
                 'opts' => array('server_id' =>
                                 array('long_name'   => '--server-id',
                                       'action'      => 'StoreString',
                                       'help_name'   => 'id',
                                       'description' => 'set the Photon handler id'),
                                 'daemonize' =>
                                 array('long_name'   => '--daemonize',
                                       'action'      => 'StoreTrue',
                                       'description' => 'run as daemon'),
                                 )),
                      'worker' =>
           array('desc' => 'start a Photon worker',
                 'args' => array('task' =>
                                 array('description' => 'the name of the worker task')),
                 'opts' => array('server_id' =>
                                 array('long_name'   => '--server-id',
                                       'action'      => 'StoreString',
                                       'help_name'   => 'id',
                                       'description' => 'set the Photon task id'),
                                 'daemonize' =>
                                 array('long_name'   => '--daemonize',
                                       'action'      => 'StoreTrue',
                                       'description' => 'run as daemon'),
                                 )
                 ),


                      'package' =>
           array('desc' => 'package a project as a standalone .phar file',
                 'args' => array('project' =>
                                 array('description' => 'the name of the project')),
                 'opts' => array('conf_file' =>
                                 array('long_name'   => '--include-conf',
                                       'action'      => 'StoreString',
                                       'help_name'   => 'path/config.prod.php',
                                       'description' => 'path to the configuration file used in production'),
                                 'stub' =>
                                 array('long_name'   => '--stub',
                                       'action'      => 'StoreString',
                                       'help_name'   => 'path/pharstub.php',
                                       'description' => 'Use the provided custom pharstub instead of the photon pharstub.'),
                                 'exclude_files' =>
                                 array('long_name'   => '--exclude-files',
                                       'action'      => 'StoreString',
                                       'help_name'   => '\..*',
                                       'description' => 'comma separated list of patterns matching files to exclude'))),
                      'makekey' =>
           array('desc' => 'prints out a unique random secret key for your configuration',
                 'opts' => array('length' =>
                                 array('long_name'   => '--length',
                                       'action'      => 'StoreInt',
                                       'description' => 'length of the generate secret key (64)'))));

        $def_cmd = array('opts' => array(), 'args' => array());
        foreach ($cmds as $name => $cmd) {
            $pcmd = $parser->addCommand($name, 
                                        array('description' => $cmd['desc']));
            $cmd = array_merge($def_cmd, $cmd);
            foreach ($cmd['opts'] as $oname => $oinfo) {
                $pcmd->addOption($oname, $oinfo);
            }
            foreach ($cmd['args'] as $aname => $ainfo) {
                $pcmd->addArgument($aname, $ainfo);
            }
        }

        return $parser;
    }
}

namespace
{
    // This add the current directory in the include path and add the
    // Photon autoloader to the SPL autoload stack.
    include_once __DIR__ . '/photon/autoload.php';
    use photon\config\Container as Conf;

    // Let's its go
    try {
        $parser = \photon\getParser();
        $result = $parser->parse();
        $params = array('cwd' => getcwd(), 'cmd' => $result->command_name);
        $params = $params + $result->options;
        switch ($result->command_name) {
            case 'init':
                // options and arguments for this command are stored in the
                // $result->command instance:
                $m = new \photon\manager\Init($params);
                $m->run();
                break;

            case 'show-config':
                $m = new \photon\manager\ShowConfig($params);
                $m->run();
                break;

            case 'pot':
                $params['potfile'] = isset($result->command->options['potfile']) ? $result->command->options['potfile'] : 'myproject.pot';
                $m = new \photon\manager\PotGenerator($params);
                exit($m->run());
                break;

            case 'serve':
                $params += $result->command->options;
                $m = new \photon\manager\Server($params);
                exit($m->run()); 
                break;

            case 'worker':
                $params['task'] = $result->command->args['task'];
                $m = new \photon\manager\Task($params);
                exit($m->run());
                break;

            case 'makekey':
                $params['length'] = $result->command->options['length'];
                $m = new \photon\manager\SecretKeyGenerator($params);
                $m->run();
                break;

            case 'package':
                $params['project'] = $result->command->args['project'];
                $params['conf_file'] = $result->command->options['conf_file'];
                $params['exclude_files'] = $result->command->options['exclude_files'];
                $params['stub'] = $result->command->options['stub'];
                $m = new \photon\manager\Packager($params);
                $m->run();
                break;

            default:
                // no command entered
                print "No command entered, nothing to do.\n";
                $parser->displayUsage();
                exit(5);
        }
        exit(0);

    } catch (Exception $e) {
        $parser->displayError($e->getMessage());
        exit(1);
    }
}
