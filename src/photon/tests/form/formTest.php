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

use \photon\test\TestCase;
use \photon\form\field;
use \photon\form\Form;
use \photon\form\Invalid;

class Simple extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['login'] = new field\Varchar(
                                      array('required' => true,
                                            'label' => 'Your login',
                                            'max_length' => 15,
                                            'min_length' => 3,
                                            'help_text' => 'The login must...',
                                            'widget_attrs' => array(
                                                       'maxlength' => 15,
                                                       'size' => 10,
                                                                    ),
                                            ));
    }

    public function clean_login()
    {
        if (preg_match('/[^A-Za-z0-9]/', $this->cleaned_data['login'])) {
            throw new Invalid(sprintf('Invalid login: %s', $this->cleaned_data['login']));
        }
        return $this->cleaned_data['login'];
    }

    public function clean()
    {
        if ('hello' === $this->cleaned_data['login']) {
            throw new Invalid(sprintf('Invalid login: %s', $this->cleaned_data['login']));
        }
        return $this->cleaned_data;
    }
}

class Minimal extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['login'] = new field\Varchar(
                                      array('required' => true,
                                            'max_length' => 15,
                                            'min_length' => 3,
                                            'widget_attrs' => array(
                                                       'maxlength' => 15,
                                                       'size' => 10,
                                                                    ),
                                            ));
    }
}

class Hidden extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['hidden'] = new field\IntegerNumber(
                     array('widget' => '\photon\form\widget\HiddenInput',
                           'required' => true));
    }
}

class HiddenAnd extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['hidden'] = new field\IntegerNumber(
                     array('widget' => '\photon\form\widget\HiddenInput',
                           'required' => true));
        $this->fields['login'] = new field\Varchar(
                                      array('required' => true,
                                            'max_length' => 15,
                                            'min_length' => 3,
                                            'widget_attrs' => array(
                                                       'maxlength' => 15,
                                                       'size' => 10,
                                                                    ),
                                            ));

    }
}

class NotRequired extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['i'] = new field\IntegerNumber();
    }
}

class WithFieldsets extends Form
{
    public function initFields($extra=array())
    {
        $this->fields['a'] = new field\IntegerNumber(array(
            'required' => true,
            'min_value' => 1,
            'max_value' => 1,
        ));

        $this->fieldsets['Section 1'] = array(
            'i' => new field\IntegerNumber(array(
                'required' => true,
                'min_value' => 2,
                'max_value' => 2,
            )),
            'j' => new field\IntegerNumber(array(
                'required' => false,
                'min_value' => 3,
                'max_value' => 3,
            )),
        );

        $this->fieldsets['Section 2'] = array(
            'k' => new field\IntegerNumber(array(
                'required' => true,
                'min_value' => 4,
                'max_value' => 4,
            )),
            'l' => new field\IntegerNumber(array(
                'required' => false,
                'min_value' => 5,
                'max_value' => 5,
            )),
        );
    }
}

class FormTest extends TestCase
{
    public function testNotImplemented()
    {
        $this->setExpectedException('\photon\form\Exception');
        $form = new Form();
    }

    public function testInvalid()
    {
        $inv = new Invalid(array('mes1', 'mes2'));
        $this->assertEquals(array('mes1', 'mes2'), $inv->messages);
    }

    public function testMinimal()
    {
        $form = new Minimal();
        $this->assertSame(false, $form->isValid());
        $form = new Minimal(array('login' => 'abcdefgh'));
        $this->assertSame(true, $form->isValid());
    }

    public function testMinimalRender()
    {
        $form = new Minimal();
        $this->assertEquals('<p><label for="id_login">Login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" /></p>', (string) $form->render_p());
        $this->assertEquals('<li><label for="id_login">Login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" /></li>', (string) $form->render_ul());
        $this->assertEquals('<tr><th><label for="id_login">Login:</label></th><td><input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" /></td></tr>', (string) $form->render_table());
        foreach ($form as $name => $field) {
            $this->assertEquals('login', $name);
        }
        $this->assertEquals('photon\form\BoundField', get_class($field));
        $foo = $form['login'];
        $this->assertEquals('photon\form\field\Varchar', get_class($foo));
        $foo = $form->field('login');
        $this->assertEquals('photon\form\BoundField', get_class($foo));
        $foo = $form->f->login;
        $this->assertEquals('photon\form\BoundField', get_class($foo));
        $this->assertEquals(false, isset($form['badone']));
        $this->assertEquals(true, isset($form['login']));
        unset($form['login']);
        $this->assertEquals(false, isset($form['login']));
        $form['login'] = $foo;
        $this->assertEquals(true, isset($form['login']));
        $this->setExpectedException('\photon\form\Exception');
        $foo = $form['badone'];
    }

    public function testBoundField()
    {
        $form = new HiddenAnd();
        $bf = new \photon\form\BoundField($form, $form['login'], 'login');
        $this->assertEquals('<label for="id_login" class="test">Login</label>',
                            (string) $bf->labelTag(null, array('class' => 'test')));

        $form = new HiddenAnd();
        $form->id_fields = 'dummy_';
        $bf = new \photon\form\BoundField($form, $form['login'], 'login');
        $this->assertEquals('<label for="login" class="test">Login</label>',
                            (string) $bf->labelTag(null, array('class' => 'test')));

        $form = new HiddenAnd();
        $form->id_fields = '';
        $bf = new \photon\form\BoundField($form, $form['login'], 'login');
        $this->assertEquals('<label for="" class="test">Login</label>',
                            (string) $bf->labelTag(null, array('class' => 'test')));

        $form = new HiddenAnd();
        $bf = new \photon\form\BoundField($form, $form['login'], 'login');
        $this->assertEquals('<input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" />',
                            (string) $bf);
        $this->assertEquals('<input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" />',
                            (string) $bf->render_w);
        // forced empty list
        $this->assertEquals('<ul class="errorlist"></ul>',
                            (string) $bf->fieldErrors());
    }

    public function testMinimalErrorRender()
    {
        $form = new Minimal(array('login' => '12'));
        $this->assertSame(false, $form->isValid());
        $this->assertEquals('<ul class="errorlist"><li>Ensure this value has at least 3 characters (it has 2).</li></ul>
<p><label for="id_login">Login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" value="12" /></p>', (string) $form->render_p());
        $this->assertEquals('<li><ul class="errorlist"><li>Ensure this value has at least 3 characters (it has 2).</li></ul><label for="id_login">Login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" value="12" /></li>', (string) $form->render_ul());
        $this->assertEquals('<tr><th><label for="id_login">Login:</label></th><td><ul class="errorlist"><li>Ensure this value has at least 3 characters (it has 2).</li></ul><input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" value="12" /></td></tr>', (string) $form->render_table);
    }

    public function testTopErrors()
    {
        $form = new Minimal(array('login' => '12123'));
        $this->assertSame(true, $form->isValid());
        $this->assertSame(array(), $form->get_top_errors());
        $form = new Simple(array('login' => 'hello'));
        $this->assertSame(false, $form->isValid());
        $this->assertSame(1, count($form->get_top_errors()));
        $this->assertEquals('<ul class="errorlist"><li>Invalid login: hello</li></ul>',
                            (string) $form->render_top_errors);
        $this->assertEquals('<ul class="errorlist"><li>Invalid login: hello</li></ul>
<p><label for="id_login">Your login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" value="hello" /> The login must...</p>', (string) $form->render_p());

    }

    public function testSimpleForm()
    {
        $form = new Simple();
        $this->assertSame(false, $form->isValid());
        $form = new Simple(array('login' => 'abcdefgh'));
        $this->assertSame(true, $form->isValid());
        $form = new Simple(array('login' => 'ab'));
        $this->assertSame(false, $form->isValid());
        $form = new Simple(array('login' => 'absaonetuhasnoteuhasonteuhaaoen'));
        $this->assertSame(false, $form->isValid());
        $form = new Simple(array('login' => 'hel!lo'));
        $this->assertSame(false, $form->isValid());
        $form = new Simple(array('login' => 'hello'));
        $this->assertSame(false, $form->isValid());
        $this->assertSame(false, $form->isValid());
    }

    public function testHidden()
    {
        $form = new Hidden();
        $this->assertSame(false, $form->isValid());
        $form = new Hidden(array('hidden' => 'abc'));
        $this->assertSame(false, $form->isValid());
        $this->assertEquals('<ul class="errorlist"><li>(Hidden field hidden) Enter a whole number.</li></ul>
<input required="required" name="hidden" type="hidden" id="id_hidden" value="abc" />', (string) $form->render_p());
    }

    public function testHiddenAnd()
    {
        $form = new HiddenAnd();
        $this->assertSame(false, $form->isValid());
        $form = new HiddenAnd(array('hidden' => 'abc'));
        $this->assertSame(false, $form->isValid());
        $this->assertEquals('<ul class="errorlist"><li>(Hidden field hidden) Enter a whole number.</li></ul>
<ul class="errorlist"><li>This field is required.</li></ul>
<p><label for="id_login">Login:</label> <input maxlength="15" size="10" required="required" name="login" type="text" id="id_login" /><input required="required" name="hidden" type="hidden" id="id_hidden" value="abc" /></p>', (string) $form->render_p());
    }

    public function testNotRequiredField()
    {
        $form = new NotRequired();
        $this->assertEquals('<p><label for="id_i">I:</label> <input name="i" type="number" id="id_i" /></p>', (string) $form->render_p());
    }

    public function testWithFieldsets()
    {
        // Ensure Array interfaces works with fieldsets
        $form = new WithFieldsets();
        $this->assertArrayHasKey('a', $form);
        $this->assertArrayHasKey('i', $form);
        $this->assertArrayHasKey('j', $form);
        $this->assertArrayHasKey('k', $form);
        $this->assertArrayHasKey('l', $form);
        $this->assertArrayNotHasKey('kk', $form);

        // Ensure required fields in fieldsets are checked
        $this->assertSame(false, $form->isValid());

        // Filled with corrects values
        $form = new WithFieldsets(array(
            'a' => 1,
            'i' => 2,
            'j' => 3,
            'k' => 4,
            'l' => 5,
        ));
        $this->assertSame(true, $form->isValid());

        // Filled with bad values
        $form = new WithFieldsets(array(
            'a' => 1,
            'i' => 2,
            'j' => 3,
            'k' => 4,
            'l' => 55,
        ));
        $this->assertSame(false, $form->isValid());
    }

    public function testWithFieldsetsRender()
    {
        $form = new WithFieldsets();
        $html = (string) $form->render_p();
        $valid = '<p><label for="id_a">A:</label> <input required="required" min="1" max="1" name="a" type="number" id="id_a" /></p>
<fieldset><legend>Section 1</legend>
<p><label for="id_i">I:</label> <input required="required" min="2" max="2" name="i" type="number" id="id_i" /></p>
<p><label for="id_j">J:</label> <input min="3" max="3" name="j" type="number" id="id_j" /></p>
</fieldset>
<fieldset><legend>Section 2</legend>
<p><label for="id_k">K:</label> <input required="required" min="4" max="4" name="k" type="number" id="id_k" /></p>
<p><label for="id_l">L:</label> <input min="5" max="5" name="l" type="number" id="id_l" /></p>
</fieldset>';
        $this->assertSame($html, $valid);
        
        $html = (string) $form->render_ul();
        $valid = '<li><label for="id_a">A:</label> <input required="required" min="1" max="1" name="a" type="number" id="id_a" /></li>
<li><span class="photonFieldsetTitle">Section 1</span></li>
<li><label for="id_i">I:</label> <input required="required" min="2" max="2" name="i" type="number" id="id_i" /></li>
<li><label for="id_j">J:</label> <input min="3" max="3" name="j" type="number" id="id_j" /></li>
<li><span class="photonFieldsetTitle">Section 2</span></li>
<li><label for="id_k">K:</label> <input required="required" min="4" max="4" name="k" type="number" id="id_k" /></li>
<li><label for="id_l">L:</label> <input min="5" max="5" name="l" type="number" id="id_l" /></li>';
        $this->assertSame($html, $valid);
        
        $html = (string) $form->render_table();
        $valid = '<tr><th><label for="id_a">A:</label></th><td><input required="required" min="1" max="1" name="a" type="number" id="id_a" /></td></tr>
<tr><td colspan="2"><span class="photonFieldsetTitle">Section 1</span></td></tr>
<tr><th><label for="id_i">I:</label></th><td><input required="required" min="2" max="2" name="i" type="number" id="id_i" /></td></tr>
<tr><th><label for="id_j">J:</label></th><td><input min="3" max="3" name="j" type="number" id="id_j" /></td></tr>
<tr><td colspan="2"><span class="photonFieldsetTitle">Section 2</span></td></tr>
<tr><th><label for="id_k">K:</label></th><td><input required="required" min="4" max="4" name="k" type="number" id="id_k" /></td></tr>
<tr><th><label for="id_l">L:</label></th><td><input min="5" max="5" name="l" type="number" id="id_l" /></td></tr>';
        $this->assertSame($html, $valid);

        $html = (string) $form->render_bootstrap();
    }
}
