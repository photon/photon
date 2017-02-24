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


namespace photon\tests\datetimeTest;

use \photon\test\TestCase;
use \photon\config\Container as Conf;
use \photon\datetime\Date;
use \photon\datetime\DateTime;

class DatetimeTest extends TestCase
{
    public function testDate()
    {
        $date = Date::fromFormat('y-m-d', '99-01-01');
        $this->assertEquals(1999, $date->y);
        $date = Date::fromFormat('Y-m-d', '2001-01-01');
        $this->assertEquals(2001, $date->y);
        $date = Date::fromFormat('y-m-d', '30-01-01');
        $this->assertEquals(2030, $date->y);
        $date = Date::fromFormat('y-m-d', '69-01-01');
        $this->assertEquals(2069, $date->y);
        $date = Date::fromFormat('y-m-d', '70-01-01');
        $this->assertEquals(1970, $date->y);
        $this->assertEquals('1970-01-01', (string) $date);
    }

    public function testDateTime()
    {
        $datetime = DateTime::fromFormat('y-m-d', '99-01-01');
        $this->assertEquals('1999-01-01 00:00:00', (string) $datetime);
    }
}
