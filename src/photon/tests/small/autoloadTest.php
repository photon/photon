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


namespace photon\tests\small\autoloadTest;

use \photon\test\TestCase;
use \photon\config\Container as Conf;

class autoloadTest extends TestCase
{
    public function testAutoLoadFunc()
    {
        $this->assertSame(false, \photonLoadFunction('\foo\bar\Bing::bong'));
        $this->assertSame(null, \photonLoadFunction('htmlspecialchars'));
        $this->assertSame(true, \photonLoadFunction('\photon\tests\small\simple\just_testing'));
        $this->assertSame('OK', \photon\tests\small\simple\just_testing());
    }
}

