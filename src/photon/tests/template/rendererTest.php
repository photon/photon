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

use \photon\test\TestCase;
use photon\template as template;
use \photon\config\Container as Conf;
use photon\template\ContextRequest;
use photon\event\Event;

class TagFailure extends template\tag\Tag
{
    public function start($fail=true)
    {
        if ($fail) {
            throw new \Exception('Failure!');
        }
    }
}

class LocalModifier
{
    public static function hexa($in)
    {
        return new template\SafeString(dechex($in), true);
    }
}

class LocalTag extends template\tag\Tag
{
    public function start()
    {
        echo "E=mc²";
    }
}

class LocalCompiler
{
    public static function setupTags($signal, &$params)
    {
        $params['relativity'] = '\photon\tests\template\rendererTest\LocalTag';
    }

    public static function setupModifiers($signal, &$params)
    {
        $params['hexa'] = '\photon\tests\template\rendererTest\LocalModifier::hexa';
    }
}

class rendererTest extends TestCase
{
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

    public function testCustomModifier()
    {
        $modifiers = Conf::f('template_modifiers', array());
        $modifiers['hexa'] = '\photon\tests\template\rendererTest\LocalModifier::hexa';
        Conf::set('template_modifiers', $modifiers);

        $renderer = new template\Renderer('data-template-custom-modifier.html', 
                                          array(__dir__));
        $this->assertequals("deadbeaf\n", $renderer->render(new template\Context(array('value' => 0xdeadbeaf))));
        Conf::set('template_modifiers', array());
    }

    public function testCustomTag()
    {
        $tags = Conf::f('template_tags', array());
        $tags['relativity'] = '\photon\tests\template\rendererTest\LocalTag';
        Conf::set('template_tags', $tags);

        $renderer = new template\Renderer('data-template-custom-tag.html', 
                                          array(__dir__));
        $this->assertequals("E=mc²\n", $renderer->render());
        Conf::set('template_tags', array());
    }

    public function testConfigureCompilerFromEvent()
    {
        Event::connect('\photon\template\compiler\Compiler::construct_load_tags',
                      '\photon\tests\template\rendererTest\LocalCompiler::setupTags');

        Event::connect('\photon\template\compiler\Compiler::construct_load_modifiers',
                      '\photon\tests\template\rendererTest\LocalCompiler::setupModifiers');

        $renderer = new template\Renderer('data-template-custom-tag.html', 
                                          array(__dir__));
        $this->assertequals("E=mc²\n", $renderer->render());

        $renderer = new template\Renderer('data-template-custom-modifier.html', 
                                          array(__dir__));
        $this->assertequals("deadbeaf\n", $renderer->render(new template\Context(array('value' => 0xdeadbeaf))));
    }

    public function testMessagesTagNoSession()
    {
        $request = (object) array();
        $renderer = new template\Renderer('data-template-message-tag.html', 
                                          array(__dir__));
        $ctx = new template\ContextRequest($request, array());
        $out = $renderer->render($ctx);
        $this->assertequals("\n", $out);
    }
    
    public function testMessagesTagNoMessage()
    {
        $request = (object) array(
            'session' => array(),
        );
        $renderer = new template\Renderer('data-template-message-tag.html', 
                                          array(__dir__));
        $ctx = new template\ContextRequest($request, array());
        $out = $renderer->render($ctx);
        $this->assertequals("\n", $out);
    }

    public function testMessagesTag()
    {
        $request = (object) array(
            'session' => array(
                '_msg' => 'custom-error-class|CUSTOM-ERROR-MESSAGE',
            ),
        );
        $renderer = new template\Renderer('data-template-message-tag.html', 
                                          array(__dir__));
        $ctx = new template\ContextRequest($request, array());
        $out = $renderer->render($ctx);
        
        // Check if both class and the error message are in the output
        $this->assertRegExp('/custom-error-class/', $out);
        $this->assertRegExp('/CUSTOM-ERROR-MESSAGE/', $out);
    }

    public function testEventTagNoRequest()
    {
        $renderer = new template\Renderer('data-template-event-tag.html', 
                                          array(__dir__));
        $ctx = new template\Context(array());
        $out = $renderer->render($ctx);
        $this->assertequals("\n", $out);
    }
    
    public function testEventTagNotConnected()
    {
        $request = (object) array();
        $renderer = new template\Renderer('data-template-event-tag.html', 
                                          array(__dir__));
        $ctx = new template\ContextRequest($request, array());
        $out = $renderer->render($ctx);
        $this->assertequals("\n", $out);
    }
}
