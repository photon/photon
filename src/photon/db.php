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

/**
 * Access the handler of a given database.
 *
 * Provides a set of methods to access a database handler. This
 * support multiple DBs with the same backend. For example, using
 * several SQLite databases in the same application.
 */
class DB
{
    public static $handles = array();

    public static function get_handle($handle='default')
    {
        if (isset(self::$handles[$handle])) {
            return self::$handles[$handle];
        }
        // Here add your code to create the connection and put the
        // handler in self::$handles[$handle] 
        // See the photon\db\mongo\DB class for an example.
        throw new \Exception('Not implemented. You must use the database specific handler.');
    }
}
