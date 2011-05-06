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
 * Base MongoDB class.
 *
 */
namespace photon\db\mongo;

use photon\config\Container as Conf;

class DB extends \photon\db\DB
{
    public static function get_handle($handle='default')
    {
        if (!isset(self::$handles[$handle])) {
          $default = array('server' => 'mongodb://localhost:27017',
                           'options' => array('connect' => true));
          $cfg = array_merge($default, Conf::f('mongo', array()));

          self::$handles[$handle] = new \Mongo($cfg['server'], $cfg['options']);
        }

        return self::$handles[$handle];
    }
}