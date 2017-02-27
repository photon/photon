<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
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
 * Translation utilities. 
 *
 * This namespace provides utilities to load and cache translation
 * strings. The functions using the values are directly available when
 * loading Photon. They are __ and _n for simple translations and for
 * plural dependent translations respectively.
 *
 * Why reimplementing a gettext system when one is already available?
 * It is because the PHP gettext extension requires the corresponding
 * locale to be installed system wide to load the corresponding
 * translations. If your host has no locales outside English
 * installed, you can only provide English to your users. Which is not
 * really nice.
 *
 * A list of plural forms:
 * @see http://translate.sourceforge.net/wiki/l10n/pluralforms
 */
namespace photon\translation;

use photon\config\Container as Conf;
use photon\path\Dir;

class Translation
{
    /**
     * By default, we use English.
     */
    public static $current_lang = 'en'; 

    /**
     * Anonymous functions doing the plural form conversion.
     *
     * Associative array indexed by language.
     */
    public static $plural_forms = array();

    /**
     * Loaded locales.
     *
     * Associative array indexed by language.
     */
    public static $loaded = array();

    public static function setLocale($lang)
    {
        self::$current_lang = $lang;
        $lang5 = $lang . '_' . strtoupper($lang);

        $new_locale = setlocale(LC_TIME, array($lang . '.UTF-8', $lang5 . '.UTF-8', $lang, $lang5));
        setlocale(LC_CTYPE, $new_locale);
        setlocale(LC_COLLATE, $new_locale);
        setlocale(LC_MESSAGES, $new_locale);
        setlocale(LC_MONETARY, $new_locale);

        if (isset(self::$loaded[$lang])) {
            return; // We consider that it was already loaded.
        }

        self::loadLocale($lang);
    }
    
    /**
     * Context preprocessor for template engine
     *
     * Setup the currentLang variable to the current language used by the Translation engine
     *
     * @param Request Request object
     */
    public static function context($request)
    {
        return array('currentLang' => self::$current_lang);
    }

    /**
     * Get the singular version of a string in the current language
     *
     * @param string $singular
     */
    public static function singular($singular)
    {
        if (isset(self::$loaded[self::$current_lang][$singular]['msgstr']) === false) {
            return $singular;
        }

        return self::$loaded[self::$current_lang][$singular]['msgstr'][0];
    }

    /**
     * Get the singular or plural version of a string in the current language
     *
     * @param string $singular
     * @param string $plural
     * @param int $count
     */
    public static function plural($singular, $plural, $count)
    {
        if (isset(Translation::$plural_forms[Translation::$current_lang])) {
            $cl = Translation::$plural_forms[Translation::$current_lang];
            $idx = $cl($count);
        } else {
            $idx = (int) ($count != 1);  // Default to English form
        }

        if (isset(self::$loaded[self::$current_lang][$singular]['msgstr'][$idx])) {
            return self::$loaded[self::$current_lang][$singular]['msgstr'][$idx];
        }

        return ($count === 1) ? $singular : $plural;
    }

    /**
     * Load the locales of a lang.
     *
     * It does not activate the locale.
     *
     * @param $lang Language to load
     * @param $photon Load the Photon translations (true)
     * @return array Path to loaded file
     */
    public static function loadLocale($lang, $photon=true)
    {
        $locale_folders = Conf::f('locale_folders', array());
        $path_folders = Dir::getIncludePath();

        self::$loaded[$lang] = array();

        if ($photon) {
            $pofile = sprintf('%s/locale/%s/photon.po', __DIR__, $lang);
            if (file_exists($pofile)) {
                self::readPoFile($lang, $pofile);
            }
        }

        foreach ($locale_folders as $locale_folder) {
            foreach ($path_folders as $path_folder) {
                $pofile = sprintf('%s/%s/%s.po', $path_folder, $locale_folder, $lang);
                if (file_exists($pofile)) {
                    self::readPoFile($lang, $pofile);
                    break;
                }
            }
        }
    }
    
    /**
     * Return the "best" accepted language from the list of available
     * languages.
     *
     * Use $_SERVER['HTTP_ACCEPT_LANGUAGE'] if the accepted language
     * list is empty. The list must be something like:
     *      'da, en-gb;q=0.8, en;q=0.7'
     *
     * @param $available array Available languages in the system
     * @param $accepted string String of comma separated accepted languages ('')
     * @return string Language 2 or 5 letter iso code, first of
     *                available if not match
     */
    public static function getAcceptedLanguage($available, $accepted='')
    {
        // No accepted header or empty, fallback on first available in the application
        if (0 === strlen($accepted)) {
            return $available[0];
        }

        // Parse accepted languages
        $accepted = explode(',', $accepted);
        $accepted = array_map(function($item) {
            return array_map("trim", explode(';', $item));
        }, $accepted);

        // Sort accepted languages
        usort($accepted, function($a, $b) {
            // Get score for both language
            $sa = isset($a[1]) ? (float) substr($a[1], 2) : 1.0;
            $sb = isset($b[1]) ? (float) substr($b[1], 2) : 1.0;

            // Draw case, prefers the more localized (fr_FR > fr)
            if ($sa === $sb) {
                $la = strlen($a[0]);
                $lb = strlen($b[0]);

                if ($la === $lb) {
                    // PHP 7.x usort insert $a then $b if the function return 0
                    // PHP 5.x usort insert $b then $a if the function return 0
                    if (version_compare(PHP_VERSION, '7.0.0', 'le')) {
                        return 1;
                    } else {
                        return 0;
                    }
                } else if ($la < $lb) {
                    return 1;
                }

                return -1;
            }

            // Sort on score
            return ($sa < $sb) ? 1 : -1;
        });

        // We convert to have the correct xx_XX format for the "long" langs.
        $accepted = array_map(function($item) { 
            $lang = explode('-', trim($item[0]));
            if (1 === count($lang)) {
                return $lang[0];
            } 
            return $lang[0] . '_' . strtoupper($lang[1]);
        }, $accepted);

        foreach ($accepted as $lang) {
            if (in_array($lang, $available)) {
                return $lang;
            }
        }

        foreach ($accepted as $lang) {
            // for the xx-XX cases we may have only the "main" language
            // form like xx is available
            $lang = substr($lang, 0, 2);
            if (in_array($lang, $available)) {
                return $lang;
            }
        }

        // Requested language not found, fallback on first available in the application
        return $available[0];
    }

    /**
     * Given a key indexed array, do replacement using the %%key%% in
     * the string.
     */
    public static function sprintf($string, $words=array())
    {
        foreach ($words as $key=>$word) {
            $string = mb_ereg_replace('%%'.$key.'%%', $word, $string, 'm');
        }

        return $string;
    }

    public static function readPoFile($lang, $file)
    {
        if (isset(self::$loaded[$lang]) === false) {
            self::$loaded[$lang] = array();
        };

        $poHandler = new \photon\translation\po\FileHandler($file);
        $poParser = new \photon\translation\po\PoParser($poHandler);
        self::$loaded[$lang] += $poParser->parse();
        self::$plural_forms[$lang] = self::plural_to_php(implode("\n", $poParser->getHeaders()));
    }

    public static function readPoString($lang, $poText)
    {
        if (isset(self::$loaded[$lang]) === false) {
            self::$loaded[$lang] = array();
        };

        $poHandler = new \photon\translation\po\StringHandler($poText);
        $poParser = new \photon\translation\po\PoParser($poHandler);
        self::$loaded[$lang] += $poParser->parse();
        self::$plural_forms[$lang] = self::plural_to_php(implode("\n", $poParser->getHeaders()));
    }

    /**
     * Extract the plural form from the .po file and convert to PHP.
     *
     * @param $po Content of the po file as string (or at least the headers)
     * @return anonymous function
     */
    public static function plural_to_php($po)
    {
        $forms = '';

        // Find the "Plural-Forms: ...\n" string
        if (preg_match('/\"plural-forms: ([^\\\\]+)/i', $po, $matches)) {
            $forms = trim($matches[1]);
        } else {
            $forms = 'nplurals=2; plural=(n != 1);'; // English
        }

        // Add parentheses for the evaluation order.
        $out = '';
        $p = 0; // Number of currently open parentheses
        $l = strlen($forms);
        if (';' !== $forms[$l - 1]) {
            $forms .=  ';';
            $l++;
        }
        for ($i=0; $i<$l; $i++) {
            $ch = $forms[$i];
            switch ($ch) {
            case '?':
                $out .= ' ? (';
                $p++;
                break;
            case ':':
                $out .= ') : (';
                break;
            case ';':
                // End of expression, close the parentheses
                $out .= str_repeat(')', $p) . ';';
                $p = 0;
                break;
            default:
                $out .= $ch;
            }
        }
        // now, we convert the variables to php variables
        $out = str_replace(array('n', 'plural='), 
                           array('$n', '$plural='), 
                           $out);
        $out .= ' return (int) $plural;';

        return create_function('$n', $out);
    }
}
