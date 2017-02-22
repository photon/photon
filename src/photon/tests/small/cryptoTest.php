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


namespace photon\tests\small\cryptoTest;

use \photon\config\Container as Conf;

use \photon\crypto\Crypt;
use \photon\crypto\Sign;

class cryptoTest extends \PHPUnit_Framework_TestCase
{
    protected $conf;

    public function setUp()
    {
        $this->conf = Conf::dump();
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }

    public function testSimpleSign()
    {
        $my_string = 'AAAAABBBBBCCCCCDDDDD';
        $signed = Sign::signs($my_string, 'my-key');
        $this->assertEquals(0, strpos($signed, $my_string));
        $this->assertEquals('AAAAABBBBBCCCCCDDDDD.BwaUb-rznw8ZNplw7Zo2wAhoR84', 
                            $signed);
        $this->assertEquals($my_string, Sign::unsigns($signed, 'my-key'));
    }

    public function testCompressSign()
    {
        $my_string = str_repeat('AAAAABBBBBCCCCCDDDDD', 20);
        $signed = Sign::dumps($my_string, 'my-key');
        $this->assertEquals('.', substr($signed, 0, 1));
        $recover = Sign::loads($signed, 'my-key');
        $this->assertEquals($my_string, $recover);
    }

    public function testBadSignature()
    {
        $string = 'AAAAABBBBBCCCCCDDDDD';
        $signed = 'AAAAABBBBBCCCCCDDDDD.BwaUb-rznw8ZNplw7Zo2wAhoR84'; 
        $this->assertEquals($string, Sign::unsigns($signed, 'my-key'));
        $this->setExpectedException('\photon\crypto\Exception');
        Sign::unsigns($signed . 'BAD', 'my-key');
    }

    public function testNoSignature()
    {
        $string = 'AAAAABBBBBCCCCCDDDDD';
        $signed = 'AAAAABBBBBCCCCCDDDDD.BwaUb-rznw8ZNplw7Zo2wAhoR84'; 
        $this->assertEquals($string, Sign::unsigns($signed, 'my-key'));
        $this->setExpectedException('\photon\crypto\Exception');
        Sign::unsigns($string, 'my-key');
    }

    public function testEncryptDecrypt()
    {
        $key = 'foobar';
        $data = 'very secret';
        $iv = Crypt::getiv();
        $encrypted = Crypt::encrypt($data, $key, $iv);
        $this->assertNotEquals($data, $encrypted);
        $decrypted = Crypt::decrypt($encrypted, $key, $iv);
        $this->assertEquals($data, $decrypted);
    }
}
