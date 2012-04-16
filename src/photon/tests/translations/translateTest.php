<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
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


namespace photon\tests\translations\translate;
use \photon\translation\Translation;
include_once __DIR__ . '/../../translation.php';

class TranslateTest extends \PHPUnit_Framework_TestCase
{
    public function testGetPluralForm()
    {
        $po = file_get_contents(__DIR__ . '/../data/fr.po');
        $french = \photon\translation\plural_to_php($po);
        $this->assertEquals(1, $french(2));
        $this->assertEquals(0, $french(1));
        $this->assertEquals(0, $french(0));
        $default = \photon\translation\plural_to_php('');
        $this->assertEquals(1, $default(2));
        $this->assertEquals(0, $default(1));
        $this->assertEquals(1, $default(0));
        $po = '"Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2) ';
        $russian = \photon\translation\plural_to_php($po);
        $this->assertEquals(1, $russian(2));
        $this->assertEquals(0, $russian(1));
        $this->assertEquals(2, $russian(0));
    }

    public function testPluralLocale()
    {
        $this->assertEquals('plural', _n('singular', 'plural', 2));
        Translation::$plural_forms['en'] = function ($n) { return (int) ($n != 1); };
        $this->assertEquals('plural', _n('singular', 'plural', 2));
        unset(Translation::$plural_forms['en']);
        $str = 'singular#plural';
        Translation::$loaded['en'] = array($str =>
                                                               array('Singular',
                                                                     'Plural'));
        $this->assertEquals('Singular', _n('singular', 'plural', 1));
        $this->assertEquals('Plural', _n('singular', 'plural', 2));
        unset(Translation::$loaded['en']);
    }

    public function testLoadPoFile()
    {
        $hash = Translation::readPoFile(__DIR__ . '/../data/fr.po');
        $this->assertEquals(array('seconde', 'secondes'),
                            $hash['second#seconds']);
    }

    public function testParseErrorPo()
    {
        $po = '
# French translations of Photon.
#
msgid ""
msgstr ""
"Project-Id-Version: Photon\n"
"X-Poedit-SourceCharset: utf-8\n"
"X-Poedit-Basepath: ../../../\n"

msgid "search stats"
foobar "stats de l\'index"

#: Photon/Search/Stats.php:34
msgid "search stats"
msgstr "stats de l\'index"';
        $this->assertEquals(false, Translation::parsePoContent($po));

        $po = '
# French translations of Photon.
#

bingo

msgid ""
msgstr ""
"Project-Id-Version: Photon\n"
"X-Poedit-SourceCharset: utf-8\n"
"X-Poedit-Basepath: ../../../\n"

msgid "search stats"
msgstr "stats de l\'index\n"

#: Photon/Search/Stats.php:34
msgid "search stats 32"
msgstr "stats de l\'index"';
        $this->assertEquals(false, Translation::parsePoContent($po));
    }


    public function testLoadLocale()
    {
        $pos = Translation::loadLocale('fr', array(), array('/doesnotexist'), false);
        $this->assertEquals(0, count($pos));
    }

    public function testLoadLocaleWithPhoton()
    {
        $pos = Translation::loadLocale('fr', array('dummyapp'), 
                                       array(realpath(__DIR__ . '/../data/')), true);
        $this->assertEquals(2, count($pos));
    }

    public function testSetLocale()
    {
        $locales = shell_exec('locale -a');
        if (false === strpos($locales, 'fr_FR')) {
            $this->markTestSkipped('fr_FR locale not available.');
            return;
        }
        $current = setlocale(LC_CTYPE, 0);
        Translation::setLocale('fr');
        $new = setlocale(LC_CTYPE, 0);
        $this->assertEquals('fr_FR.UTF-8', $new);
        Translation::setLocale($current);
        $new = setlocale(LC_CTYPE, 0);
        $this->assertEquals($current, $new);
    }

    public function testSprintf()
    {
        $rep = Translation::sprintf('Foo %%bar%% %%coffee%%.',
                                    array('bar' => 'BAR',
                                          'coffee' => 'tea'));
        $this->assertEquals('Foo BAR tea.', $rep);
    }

    public function testAcceptedLanguage()
    {
        $lang = Translation::getAcceptedLanguage(array('fr', 'en', 'de'),
                                                 'da, en-gb;q=0.8, en;q=0.7');
        $this->assertEquals('en', $lang);

        $lang = Translation::getAcceptedLanguage(array('fr', 'en', 'en_DK', 'de'),
                                                 'da, en-gb;q=0.8, en-dk;q=0.7, en;q=0.7');
        $this->assertEquals('en_DK', $lang);

        $lang = Translation::getAcceptedLanguage(array('fr', 'en', 'en_DK', 'de'),
                                                 'de-at, fr-fr');
        $this->assertEquals('de', $lang);

        $lang = Translation::getAcceptedLanguage(array('fr', 'en', 'en_DK', 'de'),
                                                 'jp-jp');
        $this->assertEquals('fr', $lang);

        $lang = Translation::getAcceptedLanguage(array('fr', 'en', 'en_DK', 'de'),
                                                 '');
        $this->assertEquals('fr', $lang);
    }


}

