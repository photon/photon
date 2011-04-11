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
 * Processing of actions at different points in the REQ/RESP lifecycle.
 *
 */
namespace photon\processor;

class Exception extends \Exception {}

/**
 * Run actions just after the answer is sent to the client.
 *
 * For performance reason, you can push some work, just after the
 * answer to request was sent to your client. For example, you want to
 * send 10 emails. This is not a lot, but this can take 25 ms if you
 * need to contact a non local SMTP server. What you can do is to
 * schedule the work to be performed in the same process as the
 * current one, but after the answer has been sent to your client.
 *
 * Pros: 
 *
 * - fast answer for your client;
 * - same process, your PHP objects and resources are still available.
 * 
 * Cons: 
 *
 * - the queue of actions will be emptied before accepting new
 *   requests. This locks the application server process the time to
 *   empty the queue. So, you should not have too long processes in
 *   the queue or it will require many applications servers. If your
 *   job is several seconds long, maybe a standard queue is a better
 *   approach.
 *
 * Usage:
 * <pre>
 * \photon\processor\AfterAnswer::add('array_sum',
 *                                     array(array(1,2,3,4)));
 * </pre>
 */
class AfterAnswer
{
    protected static $queue = array();

    /**
     * Schedule a callable for execution after the answer is sent.
     *
     * @param string/mixed Callable/Callback
     * @param array Parameters for the callable
     */
    public static function add($callable, $parameters)
    {
        if (!is_callable($callable)) {
            throw new Exception('The first argument must be callable.');
        }
        self::$queue[] = array($callable, $parameters);
    }

    /**
     * Process the queue.
     */
    public static function process()
    {
        while (null !== ($item = array_shift(self::$queue))) {
            call_user_func_array($item[0], $item[1])
        }
    }
}
