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
 * Photon date and time functions.
 *
 * Whenever possible, always use the PHP builtin classes and
 * functions. 
 *
 * The functions available here are syntaxic sugar on top of the PHP
 * date routines.
 */
namespace photon\datetime;

/**
 * Date class.
 *
 * Used to manipulate dates and only dates, without associated time. A
 * date is just a year, a month and a day. You can calculate date
 * intervals but it stays with just dates.
 *
 * The default date is 1st January of year 0.
 */
class Date
{
    public $y = 0;
    public $m = 1;
    public $d = 1;

    public function __construct($y=0, $m=0, $d=0)
    {
        $this->y = $y;
        $this->m = $m;
        $this->d = $d;
    }

    /**
     * Returns a Date object or false on errors.
     *
     * @param $format String format passed to date_parse_from_format()
     * @param $date Date string to be parsed
     * @return mixed Date object or false on errors
     */
    public static function fromFormat($format, $date)
    {
        $p = date_parse_from_format($format, $date);
        if (0 < $p['error_count'] || 0 < $p['warning_count']) {

            return false;
        }
        $p = array_merge(array('year' => 0, 'month' => 1, 'day' => 1), $p);

        return new Date($p['year'], $p['month'], $p['day']);
    }

    /**
     * Format a date.
     *
     * @param $format 
     * @return string Formatted date
     */
    public function format($format)
    {
        $tz = new \DateTimeZone('UTC');
        $date = new \DateTime($this->y . '-' . $this->m . '-' . $this->d, $tz);
        return $date->format($format);
    }

    public function __toString()
    {
        return $this->format('Y-m-d');
    }
}

/**
 * DateTime.
 *
 * Syntaxic sugar on top of the PHP DateTime class. It allow automatic
 * formatting of the date when displayed as a string. It provides also
 * a fromFormat method acting a bit more sanely.
 */
class DateTime extends \DateTime
{
    /**
     * Returns a DateTime object or false on errors.
     *
     * Take into account only the years, months, days, hours, minutes
     * and seconds. The date is considered as beeing in the current
     * timezone.
     *
     * @param $format String format passed to strptime()
     * @param $date Date/time string to be parsed
     * @param $timezone Timezone to interpret the date/time (null)
     * @return mixed DateTime object or false on errors
     */
    public static function fromFormat($format, $datetime, $timezone=null)
    {
        $p = date_parse_from_format($format, $datetime);
        if (0 < $p['error_count'] || 0 < $p['warning_count']) {

            return false;
        }
        $defaults = array(
                          'year' => 0, 'month' => 1, 'day' => 1,
                          'hour' => 0, 'minute' => 0, 'second' => 0,
                          'fraction' => 0);
        $p = array_merge($defaults, $p);
        $date = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                        $p['year'], $p['month'], $p['day'],
                        $p['hour'], $p['minute'], $p['second']);

        // self::createFromFormat returns a \DateTime object, so we
        // need to use it to create a \photon\datetime\DateTime
        // object.
        return new DateTime(self::createFromFormat('Y-m-d H:i:s', $date)->format('c'));
    }

    public function __toString()
    {
        return $this->format('Y-m-d H:i:s');
    }
}

