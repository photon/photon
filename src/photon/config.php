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
 * Configuration classes and tools.
 */
namespace photon\config;

/**
 * The standard configuration container.
 *
 * Configuration is stored in a singleton to not pollute the global
 * space. Usage is very simple:
 *
 * <pre>
 * use \photon\config\Container as Conf;
 * // Initialize the configuration
 * Conf::load(array('conf_key' => 'value'));
 * // Retrieve a key
 * $foo = Conf::f('conf_key', 'default value if not set');
 * </pre>
 */
class Container
{
    /**
     * Real storage of the configuration.
     */
    protected static $conf = array();

    /**
     * Load an array as configuration.
     */
    public static function load($conf)
    {
        self::$conf = $conf;
    }

    /**
     * Get a key value.
     */
    public static function f($key, $default=null)
    {
        return (isset(self::$conf[$key])) ? self::$conf[$key] : $default;
    }

    /**
     * Set a key value.
     */
    public static function set($key, $value)
    {
        self::$conf[$key] = $value;
    }
}
