<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation in version 2.1.
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


namespace photon\tests\eventTest;

use \photon\test\TestCase;
use photon\config\Container as Conf;
use photon\event\Event;

class StaticAction
{
    public static function inc($signal, &$data)
    {
        $data++;
    }
}

class EventTest extends TestCase
{
    public function testEventWithoutSender()
    {
        $i = 0;
        Event::connect('\photon\tests\eventTest\EventTest::testEventWithoutSender',
                       '\photon\tests\eventTest\StaticAction::inc');
        
        Event::send('\photon\tests\eventTest\EventTest::testEventWithoutSender', null, $i);
        $this->assertequals($i, 1);
        Event::send('\photon\tests\eventTest\EventTest::testEventWithoutSender', null, $i);
        Event::send('\photon\tests\eventTest\EventTest::testEventWithoutSender', null, $i);
        $this->assertequals($i, 3);
    }

    public function testEventWithSender()
    {
        $obj_1 = new \stdClass();
        $obj_1->data = "A";
        $obj_2 = new \stdClass();
        $obj_2->data = "B";
        $obj_3 = new \stdClass();
        $obj_3->data = "C";
    
        Event::connect('\photon\tests\eventTest\EventTest::testEventWithSender',
                       '\photon\tests\eventTest\StaticAction::inc', $obj_1); 
        Event::connect('\photon\tests\eventTest\EventTest::testEventWithSender',
                       '\photon\tests\eventTest\StaticAction::inc', $obj_2);  

        $i = 0;
        $j = 0;
        Event::send('\photon\tests\eventTest\EventTest::testEventWithSender', $obj_1, $i);
        $this->assertequals($i, 1);
        $this->assertequals($j, 0);

        Event::send('\photon\tests\eventTest\EventTest::testEventWithSender', $obj_2, $j);
        $this->assertequals($i, 1);
        $this->assertequals($j, 1);

        Event::send('\photon\tests\eventTest\EventTest::testEventWithSender', null, $i);
        Event::send('\photon\tests\eventTest\EventTest::testEventWithSender', $obj_3, $j);
        $this->assertequals($i, 1);
        $this->assertequals($j, 1);
   }
}
