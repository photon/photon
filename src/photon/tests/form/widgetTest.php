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

use \photon\test\TestCase;
use \photon\form\field;
use \photon\form\widget;
use \photon\form\Form;
use \photon\form\Invalid;

class WidgetTest extends TestCase
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
    
    public function testSelectInput()
    {
        $widget = new widget\SelectInput(array('choices'=>array('foo'=>1, 'bar'=>2, 'group'=> array('toto'=> 3, 'titi'=>4))));
        $expected = '<select name="select1">'."\n";
        $expected .= '<option value="1">foo</option>'."\n";
        $expected .= '<option value="2">bar</option>'."\n";
        $expected .= '<optgroup label="group">'."\n";
        $expected .= '<option value="3" selected="selected">toto</option>'."\n";
        $expected .= '<option value="4">titi</option>'."\n";
        $expected .= '</optgroup>'."\n";
        $expected .= '</select>';
        $result = (string) $widget->render('select1', 3);
        $this->assertEquals($expected, $result);
        $expected = '<select name="select1">'."\n";
        $expected .= '<option value="1">foo</option>'."\n";
        $expected .= '<option value="2">bar</option>'."\n";
        $expected .= '<optgroup label="group">'."\n";
        $expected .= '<option value="3">toto</option>'."\n";
        $expected .= '<option value="4">titi</option>'."\n";
        $expected .= '</optgroup>'."\n";
        $expected .= '</select>';
        $result = (string) $widget->render('select1',null);
        $this->assertEquals($expected, $result);
    }
    
    public function testSelectMultipleInput()
    {
        $widget = new widget\SelectMultipleInput(array('choices'=>array('foo'=>1, 'bar'=>2, 'group'=> array('toto'=> 3, 'titi'=>4))));
        $expected = '<select multiple="multiple" name="select1">'."\n";
        $expected .= '<option value="1">foo</option>'."\n";
        $expected .= '<option value="2">bar</option>'."\n";
        $expected .= '<optgroup label="group">'."\n";
        $expected .= '<option value="3" selected="selected">toto</option>'."\n";
        $expected .= '<option value="4">titi</option>'."\n";
        $expected .= '</optgroup>'."\n";
        $expected .= '</select>';
        $result = (string) $widget->render('select1', 3);
        $this->assertEquals($expected, $result);
        $expected = '<select multiple="multiple" name="select1">'."\n";
        $expected .= '<option value="1">foo</option>'."\n";
        $expected .= '<option value="2">bar</option>'."\n";
        $expected .= '<optgroup label="group">'."\n";
        $expected .= '<option value="3">toto</option>'."\n";
        $expected .= '<option value="4">titi</option>'."\n";
        $expected .= '</optgroup>'."\n";
        $expected .= '</select>';
        $result = (string) $widget->render('select1',null);
        $this->assertEquals($expected, $result);
        $data = array('bar'=> array('3'), 'toto'=> array('1'), 'titi'=> 5);
        $this->assertEquals(null, $widget->valueFromFormData('foo', $data));
        $this->assertEquals(array(5), $widget->valueFromFormData('titi', $data));
        $this->assertEquals(array('3'), $widget->valueFromFormData('bar', $data));
    }

    public function testTextareaInput()
    {
        $widget = new widget\TextareaInput();
        $this->assertEquals('<textarea cols="40" rows="10" name="content">my &lt;content&gt; is escaped</textarea>', (string) $widget->render('content', 'my <content> is escaped'));
        $widget = new widget\TextareaInput(array('cols' => '32'));
        $this->assertEquals('<textarea cols="32" rows="10" name="content">my &lt;content&gt; is escaped</textarea>', (string) $widget->render('content', 'my <content> is escaped'));
    }

    public function testEMailInput()
    {
        $field = new field\Email();
        $widget = $field->widget;

        $this->assertEquals('<input name="e1" type="email" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input name="e1" type="email" value="foo" />', (string) $widget->render('e1', 'foo'));
    }

    public function testHiddenEMailInput()
    {
        $field = new field\Email(array(
            'widget' => '\photon\form\widget\HiddenInput',
        ));
        $widget = $field->widget;

        $this->assertEquals('<input name="e1" type="hidden" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input name="e1" type="hidden" value="foo" />', (string) $widget->render('e1', 'foo'));
    }

    public function testIntegerInput()
    {
        $field = new field\IntegerNumber();
        $widget = $field->widget;
        $this->assertEquals('<input name="e1" type="number" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input name="e1" type="number" value="123" />', (string) $widget->render('e1', 123));
    }

    public function testFloatInput()
    {
        $field = new field\FloatNumber();
        $widget = $field->widget;
        $this->assertEquals('<input step="any" name="e1" type="number" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input step="any" name="e1" type="number" value="123" />', (string) $widget->render('e1', 123));
    }

    public function testIntegerBoundedInput()
    {
        $field = new field\IntegerNumber(array(
            'max_value' => 20,
        ));
        $widget = $field->widget;
        $this->assertEquals('<input max="20" name="e1" type="number" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input max="20" name="e1" type="number" value="123" />', (string) $widget->render('e1', 123));

        $field = new field\IntegerNumber(array(
            'min_value' => 10,
        ));
        $widget = $field->widget;
        $this->assertEquals('<input min="10" name="e1" type="number" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input min="10" name="e1" type="number" value="123" />', (string) $widget->render('e1', 123));

        $field = new field\IntegerNumber(array(
            'min_value' => 10,
            'max_value' => 20,
        ));
        $widget = $field->widget;
        $this->assertEquals('<input min="10" max="20" name="e1" type="number" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input min="10" max="20" name="e1" type="number" value="123" />', (string) $widget->render('e1', 123));
    }

    public function testHiddenIntegerInput()
    {
        $field = new field\IntegerNumber(array(
            'widget' => '\photon\form\widget\HiddenInput',
        ));
        $widget = $field->widget;
        $this->assertEquals('<input name="e1" type="hidden" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input name="e1" type="hidden" value="foo" />', (string) $widget->render('e1', 'foo'));
    }
    
    public function testIPv4Input()
    {
        $field = new field\IPv4();
        $widget = $field->widget;
        $this->assertEquals('<input pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}" name="e1" type="text" />', (string) $widget->render('e1', null));
        $this->assertEquals('<input pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}" name="e1" type="text" value="127.0.0.1" />', (string) $widget->render('e1', '127.0.0.1'));
    }

    public function testTimeZoneInput()
    {
        $field = new field\TimeZone();
        $widget = $field->widget;
        $widget->render('e1', null);
    }
}
