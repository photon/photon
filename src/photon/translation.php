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
 * Translation utility. 
 *
 * This class provides utilities to load and cache translation
 * strings. The functions using the values are directly available when
 * loading Pluf. They are __ and _n for simple translations and for
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

class Translation
{
    /**
     * By default, we use English.
     */
    public static $current_lang = 'en'; 
    public static $plural_forms = array();

    public static function loadSetLocale($lang)
    {
        self::$current_lang = $lang;
        setlocale(LC_TIME, array($lang.'.UTF-8',
                                 $lang.'_'.strtoupper($lang).'.UTF-8',
                                 $lang,
                                 $lang.'_'.strtoupper($lang)));

        if (isset($GLOBALS['_PX_locale'][$lang])) {
            return; // We consider that it was already loaded.
        }
        $GLOBALS['_PX_locale'][$lang] = array();
        foreach (Pluf::f('installed_apps') as $app) {
            if (false != ($pofile=Pluf::fileExists($app.'/locale/'.$lang.'/'.strtolower($app).'.po'))) {
                $GLOBALS['_PX_locale'][$lang] += Pluf_Translation::readPoFile($pofile);
            }
        }
    }

    public static function getLocale()
    {
        return self::$current_lang;
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
     * @return mixed Language 2 or 5 letter iso code or false
     */
    public static function getAcceptedLanguage($available, $accepted)
    {
        $acceptedlist = explode(',', $accepted);
        foreach ($acceptedlist as $lang) {
            $lang = explode(';', $lang);
            $lang = trim($lang[0]);
            if (in_array($lang, $available)) {
                return $lang;
            }
            // for the xx-XX cases we may have only the "main" language
            // form like xx is available
            $lang = substr($lang, 0, 2);
            if (in_array($lang, $available)) {
                return $lang;
            }
        }
        return false;
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
    public static function readPoFile($file)
    {
        if (false !== ($hash=self::getCachedFile($file))) {
            return $hash;
        }
        // read .po file
        $fc = file_get_contents($file);
        // normalize newlines
        $fc = str_replace(array("\r\n", "\r"), array("\n", "\n"), $fc);

        // results array
        $hash = array ();
        // temporary array
        $temp = array ();
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
        self::cacheFile($file, $hash);
        return $hash;
    }

    /**
     * Load optimized version of a language file if available.
     *
     * @return mixed false or array with value
     */
    public static function getCachedFile($file)
    {
        $phpfile = Pluf::f('tmp_folder').'/Pluf_L10n-'.md5($file).'.phps';
        if (file_exists($phpfile) 
            && (filemtime($file) < filemtime($phpfile))) {
            return include $phpfile;
        }
        return false;
    }

    /**
     * Cache an optimized version of a language file.
     *
     * @param string File
     * @param array Parsed hash
     */
    public static function cacheFile($file, $hash)
    {
        $phpfile = Conf::f('tmp_folder').'/photon_translation-'.md5($file).'.phps';
        file_put_contents($phpfile, 
                          '<?php return '.var_export($hash, true).'; ?>',
                          LOCK_EX);
        @chmod($phpfile, 0666);
    }
}


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
