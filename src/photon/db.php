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

use \photon\config\Container as Conf;
use \photon\log\Log as Log;

class UndefinedConnection extends \Exception {}
class Exception extends \Exception {}

class Connection
{
    public static $conns = array();

    public static function get($db='default')
    {
        if (isset(self::$conns[$db]) === false) {
            $defs = Conf::f('databases', array());
            if (!isset($defs[$db])) {
                throw new UndefinedConnection(sprintf('The connection "%s" is not defined in the configuration.', $db));
            }

            $engine = $defs[$db]['engine'];
            $handler = $engine::get($defs[$db]);

            if (isset($defs[$db]['cache']) && $defs[$db]['cache'] === true) {
                self::$conns[$db] = $handler;
            }

            return $handler;
        }

        return self::$conns[$db];
    }
}

/*
 *  MongoDB driver (pecl mongodb)
 *  See https://docs.mongodb.com/ecosystem/drivers/php/
 *
 *  require mongodb/mongodb to use it
 */
class MongoDB
{
    public static function get($def)
    {
        $client = new \MongoDB\Client($def['server'], $def['options'], $def['options']);

        // Ensure the database is online
        // The time limit in milliseconds for detecting when a replica set’s primary is unreachable: 10s
        // https://docs.mongodb.com/v3.2/reference/replica-configuration/#rsconf.settings.electionTimeoutMillis
        $retry=10;
        while ($retry > 0) {
            $retry--;

            try {
                $manager = $client->getManager();
                $manager->executeCommand('admin', new \MongoDB\Driver\Command(array('isMaster' => 1)));
                break;
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                if ($retry === 0) {
                    throw new Exception('No suitable servers found');
                }

                sleep(2);
                continue;
            }
        }

        return $database = $client->{$def['database']};
    }
}

/*
 *  Legacy MongoDB driver (pecl mongo)
 *  See https://docs.mongodb.com/ecosystem/drivers/php/
 */
class Mongo
{
    public static function get($def)
    {
        $cfg = array_merge(
                           array('server' => 'mongodb://localhost:27017',
                                 'options' => array('connect' => true),
                                 'database' => 'test'),
                           $def
                           );

        // On mongo extension >= 1.3.0, Mongo class is rename MongoClient creating a compatibility break
        $class = class_exists('\MongoClient') ? '\MongoClient' : '\Mongo';

        $conn = new $class($cfg['server'], $cfg['options']);
        
        return $conn->selectDB($cfg['database']);
    }
}

class SQLite
{
    public static function get($def)
    {
        $cfg = array_merge(array('database' => ':memory:', $def));
        $dsn = 'sqlite:' . $cfg['database'];
        unset($cfg['database']); // All the other keys are options
        
        return new \PDO($dsn, null, null, $cfg);
    }
}

class PostgreSQL
{
    /**
     * The definition to get a connection is:
     *
     * Required:
     *
     * - server (mapped to host in the connection string);
     * - database (mapped to dbname in the connection string);
     *
     * Optional:
     *
     * - user;
     * - hostaddr;
     * - port;
     * - password;
     * - connect_timeout;
     * - options;
     * - sslmode;
     * - service.
     *
     * You can read more about it here: http://www.php.net/pg_connect
     */
    public static function get($def)
    {
        $user = null;
        $password = null;
        $allowed_cfg = array('host', 'dbname', 'user', 'hostaddr', 
                             'port', 'password', 'connect_timeout', 
                             'options', 'sslmode', 'service');

        $cfgs = array();
        $opts = array();
        foreach ($def as $key => $value) {
            if ('engine' === $key) {
                continue;
            }
            $key = str_replace(array('server', 'database'),
                               array('host',   'dbname'),
                               $key);
            if ('user' == $key) {
                $user = $value;
            } elseif ('password' == $key) {
                $password = $value;
            } elseif (in_array($key, $allowed_cfg)) {
                $cfgs[] = $key . '=' . $value;
            } else {
                $opts[$key] = $value;
            }
        }
        Log::debug(array('photon.db.PostgreSQL.get', $cfgs,
                         $user, $password, $opts));

        return new \PDO('pgsql:' . implode(';', $cfgs), 
                        $user, $password, $opts); 
    }
}

class Memcached
{
    public static function get($def)
    {
        $cfgDefault = array(
            'host' => array('localhost:11211'),
            'id' => null,
        );
        $cfg = array_merge($cfgDefault, $def);

        if (is_array($cfg['host']) === false) {
            $cfg['host'] = array($cfg['host']);
        }
        
        $srv = ($cfg['id'] === null) ? new \Memcached : new \Memcached($cfg['id']);
        foreach($cfg['host'] as $host) {
            list($ip, $port) = explode(':', $host, 2);
            $srv->addServer($ip, $port);
        }

        return $srv;
    }
}
