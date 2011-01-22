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
}

