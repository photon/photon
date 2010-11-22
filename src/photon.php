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
        // add the init subcommand
        $init_cmd = $parser->addCommand('init',
                                        array('description' => 'generate the skeleton of a new Photon project in the current folder'
                                              ));
        $init_cmd->addArgument('project',
                               array('description' => 'the name of the project'
                                     ));

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
        $parts = explode('\\', $class);
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
        $config = array('cwd' => getcwd());
        // find which command was entered
        printf("In %s\n", $result->command_name);
        switch ($result->command_name) {
            case 'init':
                // the user typed the foo command
                // options and arguments for this command are stored in the
                // $result->command instance:
                print_r($result->command);
                $config['project'] = $result->command->args['project'];
                $m = new \photon\manager\Init($config);
                $m->run();
                exit(0);

            case 'bar':
                // the user typed the bar command
                // options and arguments for this command are stored in the
                // $result->command instance:
                print_r($result->command);
                exit(0);

            default:
                // no command entered
                exit(0);
        }
    } catch (Exception $exc) {
        $parser->displayError($exc->getMessage());
        exit(1);
    }
}
