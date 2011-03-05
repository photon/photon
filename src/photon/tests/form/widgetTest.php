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


namespace photon\tests\form\widgetTest;


use \photon\form\field;
use \photon\form\widget;
use \photon\form\Form;
use \photon\form\Invalid;

class WidgetTest extends \PHPUnit_Framework_TestCase
{
    protected $timezone;
    public function setUp()
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    public function tearDown()
    {
        date_default_timezone_set($this->timezone);
    }

    public function testWidget()
    {
        $widget = new widget\Widget();
        $this->setExpectedException('\photon\form\widget\Exception');        
        $widget->render('foo', 'bar');
    }

    public function testCheckboxInput()
    {
        $widget = new widget\CheckboxInput();
        $in_data = array(
                         'on' => 'on',
                         'null' => null,
                         'off' => 'off',
                         'foo' => 'foo',
                         );
        $out_data = array(
                          'on' => true,
                          'null' => null,
                          'off' => false,
                          'foo' => 'foo',
                          );
        foreach ($in_data as $key => $val) {
            $this->assertSame($out_data[$key],
                              $widget->valueFromFormData($key, $in_data));
        }
        $this->assertSame(false, $widget->valueFromFormData('bar', $in_data));
        $this->assertEquals('<input name="on" type="checkbox" checked="checked" value="on" />', (string) $widget->render('on', 'on'));
    }

    public function testDatetimeInput()
    {
        $widget = new widget\DatetimeInput();
        $datetime = new \photon\datetime\DateTime('2000-01-01');
        $this->assertEquals('<input name="datetime" type="text" value="2000-01-01 00:00" />', (string) $widget->render('datetime', $datetime));
        $this->assertEquals('<input name="datetime" type="text" />', (string) $widget->render('datetime', null));
    }

    public function testPasswordInput()
    {
        $widget = new widget\PasswordInput();
        $this->assertEquals('<input name="password1" type="password" />', (string) $widget->render('password1', null));
        $this->assertEquals('<input name="password1" type="password" value="foo" />', (string) $widget->render('password1', 'foo'));
        $widget = new widget\PasswordInput(array('render_value'=>false));
        $this->assertEquals('<input name="password1" type="password" />', (string) $widget->render('password1', 'foo'));
    }

    public function testFileInput()
    {
        $widget = new widget\FileInput();
        $this->assertEquals('<input name="file1" type="file" />', (string) $widget->render('file1', null));
        $this->assertEquals('<input name="file1" type="file" />', (string) $widget->render('file1', 'foo'));
    }

    public function testTextareaInput()
    {
        $widget = new widget\TextareaInput();
        $this->assertEquals('<textarea cols="40" rows="10" name="content">my &lt;content&gt; is escaped</textarea>', (string) $widget->render('content', 'my <content> is escaped'));
        $widget = new widget\TextareaInput(array('cols' => '32'));
        $this->assertEquals('<textarea cols="32" rows="10" name="content">my &lt;content&gt; is escaped</textarea>', (string) $widget->render('content', 'my <content> is escaped'));
    }
}
