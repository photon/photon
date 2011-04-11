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
 * Autoloader for Photon.
 */
function photonAutoLoad($class)
{
    $parts = array_filter(explode('\\', $class));
    if (1 < count($parts)) {
        // We have a namespace.
        $class_name = array_pop($parts);
        $file = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    } else {
        $file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    }
    // As we load only once to have everything is the process, the
    // require_once instead of require penalty is low. But the
    // require_once will prevent double loading a file and will result
    // in non confusing error messages.
    // printf("Class: %s, file: %s\n", $class, $file);    
    require_once $file;
}

/**
 * Load a namespaced function.
 *
 * Sometimes, you want to access a function in a namespace but the
 * namespace file has not been loaded yet. This function allows you to
 * do it simply. This function is not part of a namespace and always
 * loaded with Photon.
 *
 * @param $func Function with namespace, for example '\\fooo\\bar\\fonction'
 */
function photonLoadFunction($func)
{
    if (false !== strpos($func, '::')) {
        return false; // We be loaded by the autoload.
    }
    if (function_exists($func)) {
        return null;
    }
    $parts = array_filter(explode('\\', $func));
    $func_base_name = array_pop($parts);
    $file = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    require_once $file;
    return true;
} 

/**
 * Translate a string.
 *
 * @param $str String to be translated
 * @return string Translated string
 */
function __($str)
{
    return (!empty(\photon\translation\Translation::$loaded[\photon\translation\Translation::$current_lang][$str][0]))
        ? \photon\translation\Translation::$loaded[\photon\translation\Translation::$current_lang][$str][0]
        : $str;
}

/**
 * Translate the plural form of a string.
 *
 * @param $sing Singular form of the string
 * @param $plur Plural form of the string
 * @param $n Number of elements
 * @return string Translated string
 */
function _n($sing, $plur, $n)
{
    if (isset(\photon\translation\Translation::$plural_forms[\photon\translation\Translation::$current_lang])) {
        $cl = \photon\translation\Translation::$plural_forms[\photon\translation\Translation::$current_lang];
        $idx = $cl($n);
    } else {
        $idx = (int) ($n != 1);  // Default to English form
    }
    $str = $sing . '#' . $plur;
    if (!empty(\photon\translation\Translation::$loaded[\photon\translation\Translation::$current_lang][$str][$idx])) {

        return \photon\translation\Translation::$loaded[\photon\translation\Translation::$current_lang][$str][$idx];
    }

    return ($n == 1) ? $sing : $plur;
}

set_include_path(realpath(__DIR__ . '/../') . PATH_SEPARATOR . get_include_path());
spl_autoload_register('photonAutoLoad', true, true);

