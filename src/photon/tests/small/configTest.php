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


namespace photon\tests\small\configTest;

use \photon\test\TestCase;
use \photon\config\Container as Conf;

class configTest extends TestCase
{
    public function testSimpleSet()
    {
        Conf::set('foo', 'bar');
        $this->assertequals('bar', Conf::f('foo'));
    }

    public function testDumpLoad()
    {
        $this->assertequals(true, Conf::f('debug'));        
        $conf = Conf::dump();
        $new_conf = array('debug' => false);
        Conf::load($new_conf);
        $this->assertequals(false, Conf::f('debug'));        
        Conf::load($conf);
        $this->assertequals(true, Conf::f('debug'));        
    }

    public function testPf()
    {
        Conf::set('mail_host', '127.0.0.1');
        $mail = Conf::pf('mail_', true);
        $this->assertArrayHasKey('host', $mail);
        $this->assertequals($mail['host'], '127.0.0.1');
        
        Conf::set('mail_port', 1234);
        $mail = Conf::pf('mail_', false);
        $this->assertequals(count($mail), 2);
        $this->assertArrayHasKey('mail_host', $mail);
        $this->assertArrayHasKey('mail_port', $mail);
    }
}

