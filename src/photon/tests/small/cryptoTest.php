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

use \photon\test\TestCase;
use \photon\config\Container as Conf;
use \photon\crypto\Crypt;
use \photon\crypto\Sign;

class cryptoTest extends TestCase
{
    protected $conf;

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

        $encrypted = Crypt::encrypt($data, $key);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = Crypt::decrypt($encrypted, $key);
        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptDecryptDefaultKey()
    {
        $data = 'very secret';
        $encrypted = Crypt::encrypt($data);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = Crypt::decrypt($encrypted);
        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptNoKey()
    {
        $conf = Conf::dump();
        unset($conf['secret_key']);
        Conf::load($conf);

        $this->setExpectedException('\photon\crypto\Exception');
        $encrypted = Crypt::encrypt('data');
    }

    public function testDecryptNoKey()
    {
        $conf = Conf::dump();
        unset($conf['secret_key']);
        Conf::load($conf);

        $this->setExpectedException('\photon\crypto\Exception');
        $decrypted = Crypt::decrypt('crypted');
    }
}
