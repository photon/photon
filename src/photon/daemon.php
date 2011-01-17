<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
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
 * Small Daemon Library.
 *
 * The purpose is not to provide a full featured daemon library, but
 * to serve the minimal needs of Photon. 
 *
 * If you need a library with the infrastructure to run a complex
 * daemon, take a look at the System_Daemon PEAR package.
 */
namespace photon\daemon;

/**
 * Base class to start the Photon daemon.
 *
 * This class is used in the photon\manager\RunServer class. 
 *
 * All the configuration settings are stored is static member
 * variables. This allows direct access from everywhere in the running
 * daemon and this does not pollute the global namespace.
 */
class Daemon
{
    /**
     * Process id, only set when in the forked child.
     */
    static public $pid = 0;

    /** 
     * Unique id to communicate with this given process over the IPC
     * zmq control.
     */
    static public $zmq_id = ''; 

    /**
     * Start the daemon.
     */
    public static function start()
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            // Impossible to fork, why?
            return false;
        } else if ($pid) {
            // Parent is dying.
            exit(0); 
        } else {
            // Now in the daemon, we get the pid and unique id.
            self::$pid = posix_getpid();
            self::$zmq_id = gethostname() . '-' . self::$pid . '-' . uniqid();
            return true;
        }
    }
}