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
     * @param $format String format passed to strptime()
     * @param $date Date string to be parsed
     * @return mixed Date object or false on errors
     */
    public static function fromFormat($format, $date)
    {
        $p = date_parse_from_format($format, $date);
        if (0 < $p['error_count'] || 0 < $p['warning_count']) {

            return false;
        }
        $defaults = array(
                          'year' => 0, 'month' => 1, 'day' => 1,
                          'hour' => 0, 'minute' => 0, 'second' => 0,
                          'fraction' => 0);
        $p = array_merge($defaults, $p);
        if (false !== strpos($format, 'y') && $p['year'] < 100) {
            // Match with a two digit year format, need to "interpret" it.
            $p['year'] = (self::$cutoff_year < $p['year'])
                ? $p['year'] + 1900
                : $p['year'] + 2000;
        }
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
}



/**
 * Timespan class.
 *
 * The time class is just a time duration. For example, how long does
 * it take to cook an egg. It is not the time of a day, for that, you
 * have the DateTime class.
 */
class Timespan
{

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
     * Cutoff year to interpret two digit dates.
     */
    public static $cutoff_year = 30;

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
        if (false !== strpos($format, 'y') && $p['year'] < 100) {
            // Match with a two digit year format, need to "interpret" it.
            $p['year'] = (self::$cutoff_year < $p['year'])
                ? $p['year'] + 1900
                : $p['year'] + 2000;
        }
        $date = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                        $p['year'], $p['month'], $p['day'],
                        $p['hour'], $p['minute'], $p['second']);
        return self::createFromFormat('Y-m-d H:i:s', $date);
    }

    /**
     * Format a date.
     *
     * @param $format 
     * @return string Formatted date
     */
    /*
    public function format($format)
    {
        $tz = new \DateTimeZone('UTC');
        $date = new \DateTime($this->y . '-' . $this->m . '-' . $this->d, $tz);
        return $date->format($format);
    }
    */
}

/**
 * Get a GM Date in the format YYYY-MM-DD HH:MM:SS and returns a
 * string with the given format in the current timezone.
 *
 * @param $gmdate GMT YYYY-MM-DD HH:MM:SS date (usually stored in the db)
 * @param string Format to be given to strftime ('%Y-%m-%d %H:%M:%S')
 * @return string Formated GMDate into the local time
 */
function gmstrfdate($gmdate, $format='%Y-%m-%d %H:%M:%S')
{
    return strftime($format, strtotime($gmdate . 'Z'));
}

/**
 * Get a GM Date in the format YYYY-MM-DD HH:MM:SS and returns a
 * string with the given format in GMT.
 *
 * @param string GMDate
 * @param string Format to be given to date ('c')
 * @return string Formated GMDate into GMT
 */
function gmtogmstrfdate($gmdate, $format='c')
{
    return date($format, strtotime($gmdate . 'Z'));
}

/**
 * Day compare.
 *
 * Compare if the first date is before or after the second date.
 * Returns:
 *    0 if the days are the same.
 *    1 if the first date is before the second.
 *   -1 if the first date is after the second.
 *
 * @param string YYYY-MM-DD date.
 * @param string YYYY-MM-DD date (today local time).
 * @return int
 */
function day_compare($date1, $date2=null)
{
    $date2 = (is_null($date2)) ? date('Y-m-d') : $date2;
    if ($date2 == $date1) return 0;
    if ($date1 > $date2) return -1;
    return 1;
}

/**
 * Compare two date and returns the number of seconds between the
 * first and the second. If only the date is given without time, the
 * end of the day is used (23:59:59).
 *
 * @param string Date to compare for ex: '2006-09-17 18:42:00'
 * @param string Second date to compare if null use now (null)
 * @return int Number of seconds between the two dates. Negative 
 *             value if the second date is before the first. 
 */
function daytime_compare($date1, $date2=null)
{
    if (strlen($date1) == 10) {
        $date1 .= ' 23:59:59';
    }
    if (is_null($date2)) {
        $date2 = time();
    } else {
        if (strlen($date2) == 10) {
            $date2 .= ' 23:59:59';
        }
        $date2 = strtotime(str_replace('-', '/', $date2));
    }
    $date1 = strtotime(str_replace('-', '/', $date1));
    return $date2 - $date1;
}

/**
 * Display a date in the format:
 * X days Y hours ago
 * X hours Y minutes ago
 * X hours Y minutes left
 *
 * "resolution" is year, month, day, hour, minute.
 *
 * If not time is given, only the day, the end of the day is
 * used: 23:59:59.
 *
 * @param string Date to compare with ex: '2006-09-17 18:42:00'
 * @param string Reference date to compare with by default now (null)
 * @param int Maximum number of elements to show (2)
 * @param string If no delay between the two dates display ('now')
 * @param bool Show ago/left suffix
 * @return string Formatted date
 */
function easy($date, $ref=null, $blocks=2, $notime='now', $show=true)
{
    if (strlen($date) == 10) {
        $date .= ' 23:59:59';
    }
    if (is_null($ref)) {
        $ref = date('Y-m-d H:i:s'); 
        $tref = time();
    } else {
        if (strlen($ref) == 10) {
            $ref .= ' 23:59:59';
        }
        $tref = strtotime(str_replace('-', '/', $ref));
    }
    $tdate = strtotime(str_replace('-', '/', $date));
    $past = true;
    if ($tref < $tdate) {
        // date in the past
        $past = false;
        $_tmp = $ref;
        $ref = $date;
        $date = $_tmp;
    }
    $ref = str_replace(array(' ', ':'), '-', $ref);
    $date = str_replace(array(' ', ':'), '-', $date);
    $refs = explode('-', $ref);
    $dates = explode('-', $date);
    // Modulo on the month is dynamically calculated after
    $modulos = array(365, 12, 31, 24, 60, 60);
    // day in month 
    $month = $refs[1] - 1;
    $modulos[2] = date('t', mktime(0, 0, 0, $month, 1, $refs[0]));
    $diffs = array();
    for ($i=0; $i<6; $i++) {
        $diffs[$i] = $refs[$i] - $dates[$i];
    }
    $retain = 0;
    for ($i=5; $i>-1; $i--) {
        $diffs[$i] = $diffs[$i] - $retain;
        $retain = 0;
        if ($diffs[$i] < 0) {
            $diffs[$i] = $modulos[$i] + $diffs[$i];
            $retain = 1;
        }
    }
    $res = '';
    $total = 0;
    for ($i=0; $i<5; $i++) {
        if ($diffs[$i] > 0) {
            $total++;
            $res .= $diffs[$i].' ';
            switch ($i) {
            case 0: 
                $res .= _n('year', 'years', $diffs[$i]);
            	break;
            case 1: 
                $res .= _n('month', 'months', $diffs[$i]);
            	break;
            case 2: 
                $res .= _n('day', 'days', $diffs[$i]);
            	break;
            case 3: 
                $res .= _n('hour', 'hours', $diffs[$i]);
            	break;
            case 4: 
                $res .= _n('minute', 'minutes', $diffs[$i]);
            	break;
            case 5: 
                $res .= _n('second', 'seconds', $diffs[$i]);
            	break;
            }
            $res .= ' ';
        }
        if ($total >= $blocks) break;
    }
    if (strlen($res) == 0) {
        return $notime;
    }
    if ($show) {
        if ($past) {
            $res = sprintf(__('%s ago'), $res);
        } else {
            $res = sprintf(__('%s left'), $res);
        }
    }
    return $res;
}
