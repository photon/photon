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


namespace photon\tests\form\formTest;

include_once __DIR__ . '/../../locale/fr/formats.php';

use photon\locale\fr\formats as fr_formats;

use \photon\form\field;
use \photon\form\Form;
use \photon\form\Invalid;

class FieldTest extends \PHPUnit_Framework_TestCase
{
    public function testVarcharField()
    {
        $field = new field\Field();
        $this->assertEquals('abc', $field->clean('abc'));

        $params = array('required' => true,
                        'max_length' => 15,
                        'min_length' => 3,
                        'widget_attrs' => array('maxlength' => 15,
                                                'size' => 10),
                        );
        $field = new field\Varchar($params);
        $wrong_values = array(array('', 'required'), 
                              array('ab', 0), // through validators
                              array('1234567890123456', 0)); // through validators
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v[0]);
            } catch (Invalid $e) {
                $this->assertEquals($v[1], $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s', $v));
        }
        $this->assertEquals('abc', $field->clean('abc'));
    }

    public function testBooleanField()
    {
        $params = array('required' => true);
        $field = new field\Boolean($params);
        $wrong_values = array(array('', 'required'));
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v[0]);
            } catch (Invalid $e) {
                $this->assertEquals($v[1], $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s', $v));
        }
        $field = new field\Boolean();
        $this->assertEquals(true, $field->clean('abc'));
        $this->assertEquals(false, $field->clean(''));
        $this->assertEquals(false, $field->clean(null));
        $this->assertEquals(true, $field->clean('y'));
        $this->assertEquals(false, $field->clean('False'));
    }

    public function testDateField()
    {
        $field = new field\Date();
        $wrong_values = array('foobar', '-123/-23/-23');
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v);
            } catch (Invalid $e) {
                $this->assertEquals(0, $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s', $v));
        }
        $goods = array(array('1999-12-24', '1999-12-24'),
                       array('12/25/99', '1999-12-25'),
                       array('12/26/1999', '1999-12-26'),
                       );
        foreach ($goods as $good) {
            $date = $field->clean($good[0]);
            $this->assertEquals($good[1], $date->format('Y-m-d'));
        }
        $this->assertEquals('', $field->clean(''));
        $date = new \photon\datetime\Date();
        $this->assertSame($date, $field->clean($date));
        $fr = array('input_formats' => fr_formats\DATE_INPUT_FORMATS);
        $field = new field\Date($fr);
        $wrong_values = array('12/25/99', '12/26/1999');
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v);
            } catch (Invalid $e) {
                $this->assertEquals(0, $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s = %s', $v, $nv->format('Y-m-d')));
        }
        $goods = array(array('1999-12-24', '1999-12-24'),
                       array('25/12/99', '1999-12-25'),
                       array('26/12/1999', '1999-12-26'),
                       );
        foreach ($goods as $good) {
            $date = $field->clean($good[0]);
            $this->assertEquals($good[1], $date->format('Y-m-d'));
        }
    }

    public function testDatetimeField()
    {
        $field = new field\Datetime();
        $wrong_values = array('foobar', '-123/-23/-23');
        $wrong_values = array();
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v);
            } catch (Invalid $e) {
                $this->assertEquals(0, $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s', $v));
        }
        $goods = array(array('1999-12-24 12:23:34', '1999-12-24 12:23:34'),
                       array('12/25/99 12:23', '1999-12-25 12:23:00'),
                       array('12/26/1999 12:24', '1999-12-26 12:24:00'),
                       );
        foreach ($goods as $good) {
            $date = $field->clean($good[0]);
            $this->assertEquals($good[1], $date->format('Y-m-d H:i:s'));
        }
        $this->assertEquals('', $field->clean(''));
        $date = new \photon\datetime\DateTime();
        $this->assertSame($date, $field->clean($date));

        $fr = array('input_formats' => fr_formats\DATETIME_INPUT_FORMATS);
        $field = new field\DateTime($fr);
        $wrong_values = array('12/21/99 12:00', '12/21/1999 34:12');
        foreach ($wrong_values as $v) {
            try {
                $nv = $field->clean($v);
            } catch (Invalid $e) {
                $this->assertEquals(0, $e->getCode());
                continue;
            }
            $this->fail(sprintf('This value should be wrong: %s = %s', $v, $nv->format('Y-m-d')));
        }
        $goods = array(array('1979-12-24 23:00', '1979-12-24 23:00:00'),
                       array('25/12/1932', '1932-12-25 00:00:00'),
                       array('26/12/1987', '1987-12-26 00:00:00'),
                       );
        foreach ($goods as $good) {
            try {
                $date = $field->clean($good[0]);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good[0]));
            }
            $this->assertEquals($good[1], $date->format('Y-m-d H:i:s'));
        }
    }

    public function testEmailField()
    {
        $field = new field\Email();
        $goods = array('me@you.com', '', 'foo@internet.toto.tetet.com',
                       'me+123@mail.com');
        $bads = array('me @youec.com', 'me@localhost');
        foreach ($goods as $good) {
            try {
                $email = $field->clean($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($email, $good);
        }
        foreach ($bads as $bad) {
            try {
                $email = $field->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }
    }

    public function testIntegerField()
    {
        $field = new field\Integer();
        $goods = array('1', '', '2', 234);
        $bads = array('++123', 'me@localhost');
        foreach ($goods as $good) {
            try {
                $pgood = $field->clean($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($good, $pgood);
        }
        foreach ($bads as $bad) {
            try {
                $bad = $field->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }

        $minmax = new field\Integer(array('min_value' => -123,
                                          'max_value' => 12));
        $bads = array(-124, 123);
        $goods = array(-123, 12, 0);
        foreach ($goods as $good) {
            try {
                $pgood = $minmax->clean($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($good, $pgood);
        }
        foreach ($bads as $bad) {
            try {
                $bad = $minmax->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }

    }

    public function testFloatField()
    {
        $field = new field\Float();
        $goods = array('1', '', '2', 234.234, '123789.987');
        $bads = array('++123', 'me@localhost');
        foreach ($goods as $good) {
            try {
                $pgood = $field->clean($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($good, $pgood);
        }
        foreach ($bads as $bad) {
            try {
                $bad = $field->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }

        $minmax = new field\Float(array('min_value' => -123.0,
                                        'max_value' => 12.0));
        $bads = array(-124.0, 123.0);
        $goods = array(-123.0, 12.0, 0.0);
        foreach ($goods as $good) {
            try {
                $pgood = $minmax->clean($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($good, $pgood);
        }
        foreach ($bads as $bad) {
            try {
                $bad = $minmax->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }
    }

    public function testFileField()
    {
        $p_small = array('size' => 123,
                         'data' => (object) array('dummy' => true),
                         'name' => 'uploadfield',
                         'filename' => 'small_file.txt',
                         'of_type' => 'FILE',
                         'type' => 'text/plain');
        $p_big = array('size' => 123123123,
                       'data' => (object) array('dummy' => true),
                       'filename' => 'big_file.mpg',
                       'name' => 'uploadfile',
                       'of_type' => 'FILE',
                       'type' => 'video/mp4');
        $no_size = $p_small;
        unset($no_size['size']);
        $no_filename = $p_small;
        unset($no_filename['filename']);
        $filename_empty = $p_small;
        $filename_empty['filename'] = '';
        $size_empty = $p_small;
        $size_empty['size'] = 0;


        $field = new field\File();
        $data = $field->clean($p_small);
        $this->assertEquals($p_small, $data);
        $data = $field->clean($p_big);
        $this->assertEquals($p_big, $data);
        $this->assertEquals(null, $field->clean(''));
        $bads = array($no_size, $no_filename, $filename_empty, $size_empty);
        foreach ($bads as $bad) {
            try {
                $bad = $field->clean($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }
        $field = new field\File(array('max_length' => 12));
        $catched = false;
        try {
            $bad = $field->clean($p_small);
        } catch (Invalid $e) {
            $catched = true;
        }
        if (!$catched) {
            $this->fail('The name is too long.');
        }
    }
}
