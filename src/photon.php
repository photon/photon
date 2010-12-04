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
                                 'description' => 'where the configuration is to be found. By default, the configuration file is the config.php in the current working directory'
                                 ));
        // add the init subcommand
        $init_cmd = $parser->addCommand('init',
                                        array('description' => 'generate the skeleton of a new Photon project in the current folder'));
        $init_cmd->addArgument('project',
                               array('description' => 'the name of the project'));
        // add the runserver subcommand
        $rs_cmd = $parser->addCommand('runserver',
                                      array('description' => 'run the development server to test your application'));
        return $parser;
    }

}

namespace
{
    /**
     * Autoloader for Photon.
     */
    function photonAutoLoad($class)
    {
        $parts = array_filter(explode('\\', $class));
        if (1 < count($parts)) {
            // We have a namespace.
            $class_name = array_pop($parts);
            $file = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
        } else {
            $file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
        }
        require $file;
    }

    set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
    spl_autoload_register('photonAutoLoad', true, true);

    try {
        $parser = \photon\getParser();
        $result = $parser->parse();
        $params = array('cwd' => getcwd());
        $params = $params + $result->options;
        // find which command was entered
        switch ($result->command_name) {
            case 'init':
                // the user typed the foo command
                // options and arguments for this command are stored in the
                // $result->command instance:
                $params['project'] = $result->command->args['project'];
                $m = new \photon\manager\Init($params);
                $m->run();
                break;
            case 'runserver':
                // the user typed the runserver command
                $m = new \photon\manager\RunServer($params);
                $m->run();
                break;
            default:
                // no command entered
                exit(0);
        }
        exit(0);

    } catch (Exception $e) {
        $parser->displayError($e->getMessage());
        exit(1);
    }
}
