<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Performance PHP Framework.
# Copyright (C) 2010-2011 Loic d'Anterroches and contributors.
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
 * Event system.
 *
 * The event system is very simple, you simply register callables to
 * be run when an event is fired. Nothing more. If a callable returns
 * false, it stops the list and prevent the callables left in the
 * stack to be run.
 */
namespace photon\event;

/**
 * Event class.
 */
class Event
{
    public static $events = array();

    /**
     * Send an event.
     *
     * @param string Event to be sent
     * @param string Sender
     * @param array Parameters
     * @return void
     */
    public static function send($event, $sender, &$params=array())
    {
        if (!empty(self::$events[$event])) {
            foreach (self::$events[$event] as $key=>$val) {
                if ($val[1] === null || $sender == $val[1]) {
                    if (!call_user_func_array($val[0], array($event, &$params))) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Connect to an event.
     *
     * @param string Name of the event
     * @param callable Callable called when the event is sent
     * @param string Optional sender filtering
     */
    public static function connect($event, $who, $sender=null)
    {
        if (!isset(self::$events[$event])) {
            self::$events[$event] = array();
        }
        self::$events[$event][] = array($who, $sender);
    }
}

