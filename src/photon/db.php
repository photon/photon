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
 * Base database classes.
 *
 * Photon does not inforce the use of a given database. Each default
 * class provided by Photon uses a very thin swapable storage
 * interface to allow the developers to provide their own
 * implementation.
 *
 * This namespace provides a simple common interface used by the
 * built-in MongoDB and SQLite backend. You are invited to follow them
 * if you want to provide your own generic backend.
 */

namespace photon\db;

use photon\config\Container as Conf;

class UndefinedConnection extends \Exception {};

class Connection
{
    public static $conns = array();

    public static function get($db='default')
    {
        if (isset(self::$conns[$db])) {

            return self::$conns[$db];
        }
        $defs = Conf::f('databases', array());
        if (!isset($defs[$db])) {
            throw new UndefinedConnection(sprintf('The connection "%s" is not defined in the configuration.', $db));
        }
        $engine = $defs[$db]['engine'];
        self::$conns[$db] = $engine::get($defs[$db]);

        return self::$conns[$db];
    }
}

class MongoDB
{
    public static function get($def)
    {
        $cfg = array_merge(
                           array('server' => 'mongodb://localhost:27017',
                                 'options' => array('connect' => true),
                                 'database' => 'test'),
                           $def
                           );
        $conn = new \Mongo($cfg['server'], $cfg['options']);
        
        return $conn->selectDB($cfg['database']);
    }
}

class SQLite
{
    public static function get($def)
    {
        $cfg = array_merge(
                           array('database' => ':memory:',
                                 'options' => \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE),
                           $def
                           );
        return new \SQLite3($cfg['database'], $cfg['options']);
    }
}

