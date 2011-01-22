#!/usr/bin/php
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
    /**
     * Shortcut needed all over the place.
     *
     * Note that in some cases, we need to escape strings not in UTF-8, so
     * this is not possible to safely use a call to htmlspecialchars. This
     * is why str_replace is used.
     *
     * @param string Raw string
     * @return string HTML escaped string
     */
    function esc($string)
    {
        return str_replace(array('&',     '"',      '<',    '>'),
                           array('&amp;', '&quot;', '&lt;', '&gt;'),
                           (string) $string);
    }

    /**
     * Returns a parser of the command line arguments.
     */
    function getParser()
    {
        require_once 'Console/CommandLine.php';
        $parser = new \Console_CommandLine(array(
            'description' => 'Photon command line manager.',
            'version'     => '0.0.1'));
        $parser->addOption('verbose',
                           array('short_name'  => '-v',
                                 'long_name'   => '--verbose',
                                 'action'      => 'StoreTrue',
                                 'description' => 'turn on verbose output'
                                 ));
        $parser->addOption('conf',
                           array('long_name'   => '--conf',
                                 'action'      => 'StoreString',
                                 'help_name'   => 'path/conf.php',
                                 'description' => 'where the configuration is to be found. By default, the configuration file is the config.php in the current working directory'
                                 ));

        $init_cmd = $parser->addCommand('init',
                                        array('description' => 'generate the skeleton of a new Photon project in the current folder'));
        $init_cmd->addArgument('project',
                               array('description' => 'the name of the project'));
        $rs_cmd = $parser->addCommand('testserver',
                                      array('description' => 'run the development server to test your application'));
        $rt_cmd = $parser->addCommand('runtests',
                                      array('description' => 'run the tests of your project. Uses config.test.php as default config file'));
        $rt_cmd->addOption('directory',
                           array('long_name'   => '--coverage-html',
                                 'action'      => 'StoreString',
                                 'help_name'   => 'path/folder',
                                 'description' => 'directory to store the code coverage report'
                                 ));

        $rt_cmd->addOption('bootstrap',
                           array('long_name'   => '--bootstrap',
                                 'action'      => 'StoreString',
                                 'help_name'   => 'path/bootstrap.php',
                                 'description' => 'bootstrap PHP file given to PHPUnit. By default the photon/testbootstrap.php file'
                                 ));

        $rst_cmd = $parser->addCommand('selftest',
                                      array('description' => 'run the Photon self test procedure'));
        $rst_cmd->addOption('directory',
                           array('long_name'   => '--coverage-html',
                                 'action'      => 'StoreString',
                                 'help_name'   => 'path/folder',
                                 'description' => 'directory to store the code coverage report'
                                 ));

        $rserver_cmd = $parser->addCommand('server',
                                      array('description' => 'run or command the Photon servers'));
        $rserver_cmd->addOption('all',
                           array('long_name'   => '--all',
                                 'action'      => 'StoreTrue',
                                 'description' => 'run the subcommand for all the running Photon processes'
                                 ));

        $rserver_cmd->addOption('server_id',
                           array('long_name'   => '--server-id',
                                 'action'      => 'StoreString',
                                 'help_name'   => 'id',
                                 'description' => 'run the subcommand for the given server id. If you start a process, it will receive this server id. The default subcommand is "start".'
                                 ));


        $rserver_cmd->addCommand('start',
                                 array('description' => 'start a Photon server'));
        $rserver_cmd->addCommand('stop',
                                 array('description' => 'stop one or more Photon server'));
        $lcd = $rserver_cmd->addCommand('list',
                                        array('description' => 'list the running Photon servers'));
        $rserver_cmd->addOption('timeout',
                        array('long_name'   => '--wait',
                              'action'      => 'StoreString',
                              'description' => 'waiting time in seconds for the answers. Needed if your servers are under heavy load'
                                 ));
 

        return $parser;
    }

}

namespace
{
    // This add the current directory in the include path and add the
    // Photon autoloader to the SPL autoload stack.
    include_once __DIR__ . '/photon/autoload.php';

    try {
        $parser = \photon\getParser();
        $result = $parser->parse();
        $params = array('cwd' => getcwd());
        $params = $params + $result->options;
        // find which command was entered
        switch ($result->command_name) {
            case 'init':
                // options and arguments for this command are stored in the
                // $result->command instance:
                $params['project'] = $result->command->args['project'];
                $m = new \photon\manager\Init($params);
                $m->run();
                break;
            case 'testserver':
                $m = new \photon\manager\TestServer($params);
                $m->run();
                break;
            case 'runtests':
                $params['directory'] = $result->command->options['directory'];
                $params['bootstrap'] = $result->command->options['bootstrap'];
                $m = new \photon\manager\RunTests($params);
                exit($m->run());
                break;
            case 'selftest':
                $params['directory'] = $result->command->options['directory'];
                $m = new \photon\manager\SelfTest($params);
                exit($m->run());
                break;
            case 'server':
                // Server is a special command which has
                // subcommands. The sub commands are start/stop/list,
                // but they do not take any options, the options are
                // set at the server command level.
                // This is why you have runStart, runStop and runList
                // depending on the subcommand.
                $params['wait'] = $result->command->options['timeout'];
                switch ($result->command->command_name) {
                case 'stop':
                    $m = new \photon\manager\CommandServer($params);
                    exit($m->runStop());
                    break;
                case 'list':
                    $params += $result->command->command->options;
                    $m = new \photon\manager\CommandServer($params);
                    exit($m->runList());
                    break;
                case 'start':
                default:
                    // Will go daemon.
                    $m = new \photon\manager\Server($params);
                    exit($m->run()); 
                    break;
                }
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
