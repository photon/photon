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


namespace photon\tests\template\rendererTest;

use photon\template as template;
use \photon\config\Container as Conf;
use photon\template\ContextRequest;

class TagFailure extends template\tag\Tag
{
    public function start($fail=true)
    {
        if ($fail) {
            throw new \Exception('Failure!');
        }
    }
}

class rendererTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->conf = Conf::dump();
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }

    public function testSimpleRenderer()
    {
        $renderer = new template\Renderer('data-template-simplest.html', 
                                          array(__dir__));
        $this->assertequals('Hello World!'."\n", $renderer->render());
    }

    public function testExampleTag()
    {
        $renderer = new template\Renderer('data-template-exampletag.html', 
                                          array(__dir__), null,
                                          array('tags' => 
                                                array('example' => '\\photon\\template\\tag\\Example')));
        $this->assertequals('Param1: , param2: foo<pre>Start: foo</pre>BarParam1: end foo'."\n", $renderer->render());
    }

    public function testExampleTagUrl()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home$#',
                                      'view' => array('\helloworld\views\Views', 'you'),
                                      'name' => 'home',
                                      ),
                                ));
        $renderer = new template\Renderer('data-template-tag-url.html', 
                                          array(__dir__));
        $this->assertequals('/home'."\n", $renderer->render());
    }

    public function testWriteFailure()
    {
        $renderer = new template\Renderer('data-template-tag-url.html', 
                                          array(__dir__));
        $renderer->template_content = '';
        $locked_file = Conf::f('tmp_folder') . '/no.write.access.here';
        touch($locked_file);
        chmod($locked_file, 0000);
        try {
            $renderer->write($locked_file);
        } catch (\photon\template\Exception $e) {
            chmod($locked_file, 0666);            
            unlink($locked_file);
            $this->setExpectedException('\photon\template\Exception');
            throw $e;
        }
        $this->fail(sprintf('Was able to write to %s', $locked_file));
    }

    public function testRenderFailure()
    {
        $renderer = new template\Renderer('data-template-failuretag.html', 
                                          array(__dir__), null,
                                          array('tags' => 
                                                array('failured' => '\photon\tests\template\rendererTest\TagFailure')));

        $renderer->render(new template\Context(array('fail' => false)));
        $this->setExpectedException('\Exception');
        $renderer->render(new template\Context(array('fail' => true)));

    }

    public function testContextRequest()
    {
        Conf::set('template_context_processors', array());
        $request = 'DUMMY';
        $cr = new ContextRequest($request, array('a' => 1));
        $this->assertequals((array) $cr->_vars, array('request' => 'DUMMY',
                                                      'a' => 1));
        // It is adding a new 'other' variable based on $req.
        Conf::set('template_context_processors', array(function($req) { return array('other' => $req . 'NOT');}));
        $cr = new ContextRequest($request, array('a' => 1));
        $this->assertequals((array) $cr->_vars, array('other' => 'DUMMYNOT',
                                                      'request' => 'DUMMY',
                                                      'a' => 1));

    }
}

