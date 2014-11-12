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


namespace photon\tests\template\modifierTest;

use \photon\config\Container as Conf;
use \photon\template as template;
use \photon\template\Modifier as Modifier;

class modifierTest extends \PHPUnit_Framework_TestCase
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

    public function testSafeString()
    {
        $unsafe = '<p>Hello';
        $safe = new template\SafeString($unsafe);
        $this->assertEquals('&lt;p&gt;Hello', (string) $safe);
        $raw = template\SafeString::markSafe($unsafe);
        $this->assertEquals($unsafe, (string) $raw);
        $kept = new template\SafeString($safe);
        $this->assertEquals('&lt;p&gt;Hello', (string) $kept);
    }

    public function testContext()
    {
        $ctx = new template\Context(array('a' => 'b'));
        $this->assertEquals('b', $ctx->get('a'));
        $this->assertEquals('', $ctx->get('b'));
        $ctx->set('b', 'c');
        $this->assertEquals('c', $ctx->get('b'));
    }

    public function testContextVars()
    {
        $ctx = new template\ContextVars(array('a' => 'b'));
        $this->assertEquals('b', $ctx->a);
        $this->assertEquals('', $ctx->b);
        $ctx->b = 'cdef';
        $this->assertEquals('cdef', $ctx->b);
        $this->assertNotEquals(false, strpos((string) $ctx, 'cdef'));
    }

    public function testModifierSafe()
    {
        $in = '<p>string</p>';
        $out = '<p>string</p>';
        $mod = Modifier::safe($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testModifierNl2br()
    {
        $in = '<p>string
</p>';
        $out = '&lt;p&gt;string<br />
&lt;/p&gt;';
        $mod = Modifier::nl2br($in);
        $this->assertEquals($out, (string) $mod);
        $in = new template\SafeString($in);
        $mod = Modifier::nl2br($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testModifierVarExport()
    {
        $in = '123';
        $out = '<pre>\'123\'</pre>';
        $mod = Modifier::varExport($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testModifierFirst()
    {
        $in = array('123', '234');
        $out = '123';
        $mod = Modifier::first($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testModifierLast()
    {
        $in = array('123', '234');
        $out = '234';
        $mod = Modifier::last($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testModifierSafeEmail()
    {
        $in = 'me@example.com';
        $out = '%6d%65%40%65%78%61%6d%70%6c%65%2e%63%6f%6d';
        $mod = Modifier::safeEmail($in);
        $this->assertEquals($out, (string) $mod);
    }

    public function testRendererSreturn()
    {
        $in = '<p>string
</p>';
        $out = '&lt;p&gt;string
&lt;/p&gt;';

        $mod = template\Renderer::sreturn($in);
        $this->assertEquals($out, (string) $mod);
        $mod = Modifier::safe($mod);
        $this->assertEquals($out, (string) template\Renderer::sreturn($mod));
    }

    public function testStrftime()
    {
        setlocale(LC_ALL, 'fr_FR.UTF-8');
        $in = 1234567890;
        $out = Modifier::strftime($in, '%d/%m/%Y %H:%M:%S');
        $this->assertEquals($out, '14/02/2009 00:31:30');
    }
    
    public function testDateFormat()
    {
        setlocale(LC_ALL, 'C.UTF-8');
        $in = '2009-02-14 00:31:30';
        $out = Modifier::dateFormat($in, '%b %e, %Y');
        $this->assertEquals($out, 'Feb 14, 2009');
    }
}
