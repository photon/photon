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


namespace photon\tests\small\coreurlTest;

use \photon\config\Container as Conf;
use \photon\core\URL as URL;

class coreurlTest extends \PHPUnit_Framework_TestCase
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

    public function testUrlGenerate()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => array('\helloworld\views\Views', 'you'),
                                      'name' => 'home',
                                      ),
                                ));
        $this->assertequals('/home/', URL::generate('/home/'));
        $this->assertequals('/home/?p=foo', URL::generate('/home/',
                                                          array('p' => 'foo')
                                                          ));
        $this->assertequals('/home/?p=foo&b=bar', URL::generate('/home/',
                                                                array('p' => 'foo',
                                                                      'b' => 'bar'), false
                                                          ));
        $this->assertequals('/home/?p=foo&amp;b=bar', URL::generate('/home/',
                                                                array('p' => 'foo',
                                                                      'b' => 'bar')
                                                          ));
    }

    public function testUrlBuildReverse()
    {
        $tests = array(
                       // array( result, regex, params )
                       array('/home/', '#^/(.+)/$#', array('home')),
                       array('/home/', '#^/(.*)/$#', array('home')),
                       array('/home/1234', '#^/(.*)/(\d+)$#', array('home', 1234)),
                       );
        foreach ($tests as $t) {
            $this->assertequals($t[0], URL::buildReverse($t[1], $t[2]));
        }
    }

    public function testUrlReverse()
    {
        $views = array(
                       array('regex' => '#^/home/(.+)/$#',
                             'view' => array('\helloworld\views\Views', 'you'),
                             'name' => 'home',
                             ),
                       );

        $tests = array(
                       // array( result, regex, params )
                       array('/home/photon/', 'home', array('photon')),
                       );
        foreach ($tests as $t) {
            $this->assertequals($t[0], URL::reverse($views, $t[1], $t[2]));
        }
    }

    public function testViewNotFound()
    {
        $views = array(
                       array('regex' => '#^/home/(.+)/$#',
                             'view' => array('\helloworld\views\Views', 'you'),
                             'name' => 'home',
                             ),
                       );
        $this->setExpectedException('\photon\core\Exception');
        URL::reverse($views, 'not_available');
    }


    public function testViewInSub()
    {
        $views = array(array('regex' => '#^/hello$#',
                             'sub' => array(
                                            array('regex' => '#^/home/(.+)/$#',
                                                  'view' => array('\helloworld\views\Views', 'you'),
                                                  'name' => 'home',
                                                  ),
                                            )),
                       array('regex' => '#^/foo/(.+)/$#',
                             'view' => array('\helloworld\views\Views', 'you'),
                             'name' => 'foo_bar',
                             ),
                       );
        $tests = array(
                       // array( result, regex, params )
                       array('/hello/home/photon/', 'home', array('photon')),
                       array('/foo/bar/', 'foo_bar', array('bar')),
                       );
        foreach ($tests as $t) {
            $this->assertequals($t[0], URL::reverse($views, $t[1], $t[2]));
        }
        $this->setExpectedException('\photon\core\Exception');
        URL::reverse($views, 'not_available');
    }
}

