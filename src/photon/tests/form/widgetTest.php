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

}
