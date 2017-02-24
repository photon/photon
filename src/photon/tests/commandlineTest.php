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


namespace photon\tests\commandlineTest;

use \photon\test\TestCase;
use \photon\commandline\Parser;

class CommandlineTest extends TestCase
{
    public function testParseCommand()
    {
        $parser = new Parser('--foo="bar"');
        $this->assertEquals(array('--foo=bar'), $parser->parse());

        $parser = new Parser('--foo="bar" -bing borg --');
        $this->assertEquals(array('--foo=bar', '-bing', 'borg', '--'), $parser->parse());

        $parser = new Parser('--foo="bar" -bing borg --foo="bar');
        $this->setExpectedException('\photon\commandline\Exception');
        $parser->parse();
    }

    public function testParseCommandSeries()
    {
        $tests = array(
            '--foo="bar"' => array('--foo=bar'),
            '--foo="\'bar"' => array('--foo=\'bar'),
            '--foo=\'bar\'' => array('--foo=bar'),
            '--foo=\\\'bar' => array('--foo=\'bar'),
            '--foo="bar\"boo"' => array('--foo=bar"boo'),
            "--foo='bar\\\"boo'" => array('--foo=bar"boo'),
            "--foo='bar\\\"boo'  boo" => array('--foo=bar"boo', 'boo'),
            'bing  "bong"' => array('bing', 'bong'),
            "bing  'bong'" => array('bing', 'bong'),
            "bing  \\ 'bong'" => array('bing', ' bong'),
                       );
        foreach ($tests as $cmd => $res) {
            $parser = new Parser($cmd);
            $this->assertEquals($res, $parser->parse());
        }
    }
}
