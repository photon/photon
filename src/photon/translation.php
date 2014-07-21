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
        $lang5 = $lang.'_'.strtoupper($lang);

        $new_locale = setlocale(LC_TIME, 
                                array($lang.'.UTF-8', $lang5.'.UTF-8', 
                                      $lang, $lang5));
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
        $loaded = array();

        self::$loaded[$lang] = array();

        if ($photon) {
            $pofile = sprintf('%s/locale/%s/photon.po', __DIR__, $lang); 
            if (file_exists($pofile)) {
                self::$loaded[$lang] += self::readPoFile($pofile);
                $loaded[] = $pofile;
            }
        }

        foreach ($locale_folders as $locale_folder) {
            foreach ($path_folders as $path_folder) {
                $pofile = sprintf('%s/%s/%s.po', 
                                  $path_folder, $locale_folder, $lang);
                if (file_exists($pofile)) {
                    self::$loaded[$lang] += self::readPoFile($pofile);
                    $loaded[] = $pofile;
                    break;
                }
            }
        }
        if (count($loaded)) {
            self::$plural_forms[$lang] = plural_to_php(file_get_contents($loaded[0]));
        }

        return $loaded;
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
        if (0 === strlen($accepted)) {

            return $available[0];
        }
        // We get and sort the accepted in priority order
        $accepted = array_map(function($item) { return explode(';', $item); }, 
                              explode(',', $accepted));
        usort($accepted, 
              function($a, $b) {
                  $sa = (count($a) == 1) ? 1.0 : (float) substr($a[1], 2);
                  $sb = (count($b) == 1) ? 1.0 : (float) substr($b[1], 2);
                  if ($sa == $sb) {
                      return 1;
                  }
                  return ($sa < $sb) ? 1 : -1;
              });
        // We convert to have the correct xx_XX format for the "long" langs.
        $accepted = array_map(function($item) { 
                $lang = explode('-', trim($item[0]));
                if (1 === count($lang)) {
                    return $lang[0];
                } 
                return $lang[0] . '_' . strtoupper($lang[1]);
            }, 
            $accepted);
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

    public static function readPoFile($file)
    {
        return self::parsePoContent(file_get_contents($file));
    }

    /**
     * Read a .po file.
     *
     * Based on the work by Matthias Bauer with some little cosmetic fixes.
     *
     * @source http://wordpress-soc-2007.googlecode.com/svn/trunk/moeffju/php-msgfmt/msgfmt-functions.php
     * @copyright 2007 Matthias Bauer
     * @author Matthias Bauer
     * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License 2.1
     * @license http://opensource.org/licenses/apache2.0.php Apache License 2.0
     */
    public static function parsePoContent($fc)
    {
        // normalize newlines
        $fc = str_replace(array("\r\n", "\r"), array("\n", "\n"), $fc);


        $hash = array(); // results array
        $temp = array();
        // state
        $state = null;
        $fuzzy = false;

        // iterate over lines
        foreach (explode("\n", $fc) as $line) {
            $line = trim($line);
            if ($line === '')
                continue;
            if (false === strpos($line, ' ')) {
                $key = $line;
                $data = '';
            } else { 
                list($key, $data) = explode(' ', $line, 2);
            }
            switch ($key) {
            case '#,' : // flag...
                $fuzzy= in_array('fuzzy', preg_split('/,\s*/', $data));
            case '#' : // translator-comments
            case '#.' : // extracted-comments
            case '#:' : // reference...
            case '#|' : // msgid previous-untranslated-string
            case '#~' : // deprecated translations
                // start a new entry
                if (sizeof($temp) && array_key_exists('msgid', $temp) && array_key_exists('msgstr', $temp)) {
                    if (!$fuzzy)
                        $hash[]= $temp;
                    $temp= array ();
                    $state= null;
                    $fuzzy= false;
                }
                break;
            case 'msgctxt' :
                // context
            case 'msgid' :
                // untranslated-string
            case 'msgid_plural' :
                // untranslated-string-plural
                $state= $key;
                $temp[$state]= $data;
                break;
            case 'msgstr' :
                // translated-string
                $state= 'msgstr';
                $temp[$state][]= $data;
                break;
            default :
                if (strpos($key, 'msgstr[') !== False) {
                    // translated-string-case-n
                    $state= 'msgstr';
                    $temp[$state][]= $data;
                } else {
                    // continued lines
                    switch ($state) {
                    case 'msgctxt' :
                    case 'msgid' :
                    case 'msgid_plural' :
                        $temp[$state] .= "\n" . $line;
                        break;
                    case 'msgstr' :
                        $temp[$state][sizeof($temp[$state]) - 1] .= "\n" . $line;
                        break;
                    default :
                        // parse error
                        return False;
                    }
                }
                break;
            }
        }

        // add final entry
        if ($state == 'msgstr')
            $hash[] = $temp;

        // Cleanup data, merge multiline entries, reindex hash for ksort
        $temp = $hash;
        $hash = array ();
        foreach ($temp as $entry) {
            foreach ($entry as &$v) {
                $v = poCleanHelper($v);
                if ($v === False) {
                    // parse error
                    return False;
                }
            }
            if (isset($entry['msgid_plural'])) {
                $hash[$entry['msgid'].'#'.$entry['msgid_plural']]= $entry['msgstr'];
            } else {
                $hash[$entry['msgid']]= $entry['msgstr'];
            }
        }
        return $hash;
    }
}


// /**
//  * Translation middleware.
//  *
//  * Load the translation of the website based on the useragent.
//  */
// class Middleware
// {
//     /**
//      * Process the request.
//      *
//      * Find which language to use. By priority:
//      * 1. a session value
//      * 2. a cookie
//      * 3. the browser Accept-Language header
//      *
//      * @param $request The request
//      * @return bool false
//      */
//     function process_request(&$request)
//     {
//         $lang = false;
//         if (!empty($request->session)) {
//             $lang = $request->session->getData('language', false);
//             if ($lang && !in_array($lang, Conf::f('languages', array('en')))) {
//                 $lang = false;
//             }
//         }
//         if ($lang === false && !empty($request->COOKIE[Conf::f('lang_cookie', 'lang')])) {
//             $lang = $request->COOKIE[Conf::f('lang_cookie', 'lang')];
//             if ($lang && !in_array($lang, Conf::f('languages', array('en')))) {
//                 $lang = false;
//             }
//         }
//         if ($lang === false) {
//             // will default to 'en'
//             $lang = Translation::getAcceptedLanguage(Conf::f('languages', array('en')));
//         }
//         Translation::loadSetLocale($lang);
//         $request->language_code = $lang;
//         return false;
//     }

//     /**
//      * Process the response of a view.
//      *
//      */
//     function process_response($request, $response)
//     {
//         $vary_h = array();
//         if (!empty($response->headers['Vary'])) {
//             $vary_h = preg_split('/\s*,\s*/', $response->headers['Vary'],
//                                  -1, PREG_SPLIT_NO_EMPTY);
//         }
//         if (!in_array('accept-language', $vary_h)) {
//             $vary_h[] = 'accept-language';
//         }
//         $response->headers['Vary'] = implode(', ', $vary_h);
//         $response->headers['Content-Language'] = $request->language_code;
//         return $response;
//     }
// }

function poCleanHelper($x) 
{
	if (is_array($x)) {
		foreach ($x as $k => $v) {
			$x[$k] = poCleanHelper($v);
		}
	} else {
		if ($x[0] == '"') {
			$x = substr($x, 1, -1);
        }
		$x = str_replace("\"\n\"", '', $x);
		$x = str_replace('$', '\\$', $x);
		$x = @eval("return \"$x\";");
	}
	return $x;
}


/**
 * Extract the plural form from the .po file and convert to PHP.
 *
 * Thank you the latest PHP version, this function returns a function :)
 *
 * @param $po Content of the po file as string (or at least the headers)
 * @return anonymous function
 */
function plural_to_php($po)
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
