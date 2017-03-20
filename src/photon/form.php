<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
# Copyright (C) 2010-2011 Loic d'Anterroches and contributors.
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

/**
 * Form validation.
 *
 * Form management is like templates, a core part of a web
 * application. This is why it is available directly in the core.
 */
namespace photon\form;

use photon\template\SafeString;

class Exception extends \Exception {}

/**
 * Used for validation errors.
 *
 * It is a bit different than normal exception as it can store an
 * array of messages.
 */
class Invalid extends \Exception
{
    public $messages = array();

    public function __construct($message=null, $code=0, $previous=null)
    {
        if (is_array($message)) {
            $this->messages = $message;
            $message = null;
        } else {
            $this->messages = array($message);
        }
        // Even so the code can officially be other than an
        // integer/long, someone is enforcing this as integer/long
        // below in the stack :/
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}

/**
 * Form generation and validation class.
 *
 * The form handling is heavily inspired by the Django form handling.
 */
class Form implements \Iterator, \ArrayAccess
{
    /**
     * The fields of the form.
     *
     * They are the fully populated \photon\form\field\* of the form.
     * You define them in the initFields method.
     */
    public $fields = array();

    /**
     * The fieldsets of the form.
     *
     * Fieldsets allow to organize the form in sections.
     * They are the fully populated \photon\form\field\* of the form.
     * You define them in the initFields method.
     */
    public $fieldsets = array();

    /*
     *  Internal list of fields of the Form
     *  We merge fields & fieldsets into a single array
     *  to easilly implement \Iterator, \ArrayAccess interfaces
     */
    public $_fields = array();

    /**
     * Prefix for the names of the fields.
     */
    public $prefix = '';
    public $id_fields = 'id_%s';
    public $data = array();
    public $cleaned_data = array();
    public $errors = array();
    public $is_bound = false;
    public $f = null;
    public $label_suffix = ':';

    protected $is_valid = null;

    function __construct($data=null, $extra=array(), $label_suffix=null)
    {
        if ($data !== null) {
            $this->data = $data;
            $this->is_bound = true;
        }
        if ($label_suffix !== null) $this->label_suffix = $label_suffix;

        $this->initFields($extra);
        $this->initInternalList();
        $this->f = new FieldProxy($this);
    }

    private function initInternalList()
    {
        foreach ($this->fields as $name => &$field) {
            $this->_fields[$name] = $field;
        }
        unset($field);

        foreach ($this->fieldsets as $title => $fieldsets) {
            foreach ($fieldsets as $name => &$field) {
                $this->_fields[$name] = $field;
            }
        }
        unset($field);
    }

    function initFields($extra=array())
    {
        throw new Exception('Definition of the fields not implemented.');
    }

    /**
     * Add the prefix to the form names.
     *
     * @param string Field name.
     * @return string Field name or field name with form prefix.
     */
    function addPrefix($field_name)
    {
        return ('' !== $this->prefix)
            ? $this->prefix . '-' . $field_name
            : $field_name;
    }

    /**
     * Check if the form is valid.
     *
     * It is also encoding the data in the form to be then saved.  It
     * is very simple as it leaves the work to the field. It means
     * that you can easily extend this form class to have a more
     * complex validation procedure like checking if a field is equals
     * to another in the form (like for password confirmation) etc.
     *
     * @param array Associative array of the request
     * @return array Array of errors
     */
    function isValid()
    {
        if ($this->is_valid !== null) {

            return $this->is_valid;
        }
        $this->cleaned_data = array();
        $this->errors = array();
        $form_methods = get_class_methods($this);

        // Check fields
        foreach ($this->fields as $name => $field) {
            $value = $field->widget->valueFromFormData($this->addPrefix($name),
                                                       $this->data);
            try {
                $this->cleaned_data[$name] = $field->clean($value);
                $m = 'clean_' . $name;
                if (in_array($m, $form_methods) || isset($this->$m)) {
                    $this->cleaned_data[$name] = $this->$m();
                }
            } catch (Invalid $e) {
                if (!isset($this->errors[$name])) {
                    $this->errors[$name] = array();
                }
                $this->errors[$name] = array_merge($this->errors[$name],
                                                   $e->messages);
                if (isset($this->cleaned_data[$name])) {
                    unset($this->cleaned_data[$name]);
                }
            }
        }

        // Check each fieldsets
        foreach ($this->fieldsets as $title => $fieldsets) {
            foreach ($fieldsets as $name => $field) {
                $value = $field->widget->valueFromFormData($this->addPrefix($name),
                                                           $this->data);
                try {
                    $this->cleaned_data[$name] = $field->clean($value);
                    $m = 'clean_' . $name;
                    if (in_array($m, $form_methods) || isset($this->$m)) {
                        $this->cleaned_data[$name] = $this->$m();
                    }
                } catch (Invalid $e) {
                    if (!isset($this->errors[$name])) {
                        $this->errors[$name] = array();
                    }
                    $this->errors[$name] = array_merge($this->errors[$name],
                                                       $e->messages);
                    if (isset($this->cleaned_data[$name])) {
                        unset($this->cleaned_data[$name]);
                    }
                }
            }
        }

        if (empty($this->errors)) {
            try {
                $this->cleaned_data = $this->clean();
            } catch (Invalid $e) {
                if (!isset($this->errors['__all__'])) {
                    $this->errors['__all__'] = array();
                }
                $this->errors['__all__'] = array_merge($this->errors['__all__'],
                                                       $e->messages);
            }
        }
        if (empty($this->errors)) {
            $this->is_valid = true;

            return true;
        }
        // as some errors, we do not have cleaned data available.
        $this->failed();
        $this->cleaned_data = array();
        $this->is_valid = false;

        return false;
    }

    /**
     * Form wide cleaning function. That way you can check that if an
     * input is given, then another one somewhere is also given,
     * etc. If the cleaning is not ok, your method must throw a
     * \photon\form\Invalid exception.
     *
     * @return array Cleaned data.
     */
    public function clean()
    {
        return $this->cleaned_data;
    }

    /**
     * Method just called after the validation if the validation
     * failed.  This can be used to remove uploaded
     * files. $this->['cleaned_data'] will be available but of course
     * not fully populated and with possible garbage due to the error.
     *
     */
    public function failed()
    {
    }

    /**
     * Get initial data for a given field.
     *
     * @param string Field name.
     * @return string Initial data or '' of not defined.
     */
    public function initial($name)
    {
        if (isset($this->_fields[$name])) {
            return $this->_fields[$name]->initial;
        }

        return '';
    }

    /**
     * Get the top errors.
     */
    public function render_top_errors()
    {
        $top_errors = (isset($this->errors['__all__']))
            ? $this->errors['__all__']
            : array();
        $this->htmlspecialchars($top_errors);

        return new SafeString($this->render_errors_as_html($top_errors), true);
    }

    public function render_errors_as_html($errors)
    {
        $tmp = array();
        foreach ($errors as $err) {
            $tmp[] = '<li>' . $err . '</li>';
        }

        return '<ul class="errorlist">' . implode("\n", $tmp) . '</ul>';
    }

    private function htmlspecialchars(&$items)
    {
        array_walk($items, function (&$item, $key) {
            $item = htmlspecialchars($item, ENT_COMPAT, 'UTF-8');
        });
    }

    /**
     * Get the top errors.
     */
    public function get_top_errors()
    {
        return (isset($this->errors['__all__']))
            ? $this->errors['__all__']
            : array();
    }

    /**
     * Helper function to render the form.
     *
     * See render_p() for a usage example.
     *
     * @credit Django Project (http://www.djangoproject.com/)
     * @param string Normal row.
     * @param string Error row.
     * @param string Row ender.
     * @param string Fieldset starter
     * @param string Fieldset ender
     * @param string Help text HTML.
     * @param bool Should we display errors on a separate row.
     * @return string HTML of the form.
     */
    protected function htmlOutput($normal_row, $error_row, $row_ender,
                                  $fieldsetsStart, $fieldsetsEnd,
                                  $help_text_html, $errors_on_separate_row)
    {
        $top_errors = (isset($this->errors['__all__'])) ? $this->errors['__all__'] : array();
        $this->htmlspecialchars($top_errors);
        $output = array();
        $hidden_fields = array();

        foreach ($this->fields as $name => $field) {
            $this->fieldOutput($output, $top_errors, $hidden_fields, $field, $name,
                               $normal_row, $error_row, $row_ender, $help_text_html, $errors_on_separate_row);
        }

        foreach ($this->fieldsets as $title => $fieldsets) {
            $output[] = sprintf($fieldsetsStart, $title);
            foreach ($fieldsets as $name => $field) {
                $this->fieldOutput($output, $top_errors, $hidden_fields, $field, $name,
                                   $normal_row, $error_row, $row_ender, $help_text_html, $errors_on_separate_row);
            }
            $output[] = sprintf($fieldsetsEnd, $title);
        }

        $output = array_filter($output, function($entry) {
            if ($entry == '') {
                return false;
            }
            return true;
        });

        if (count($top_errors)) {
            $errors = sprintf($error_row,
                              $this->render_errors_as_html($top_errors));
            array_unshift($output, $errors);
        }
        if (count($hidden_fields)) {
            $_tmp = '';
            foreach ($hidden_fields as $hd) {
                $_tmp .= $hd->render_w();
            }
            if (count($output) > count($hidden_fields)) {
                $last_row = array_pop($output);
                $last_row = substr($last_row, 0, -strlen($row_ender))
                    . $_tmp . $row_ender;
                $output[] = $last_row;
            } else {
                $output[] = $_tmp;
            }

        }

        return new SafeString(implode("\n", $output), true);
    }

    /**
     * Helper function to render a field.
     *
     * @return string HTML of the field.
     */
    protected function fieldOutput(&$output, &$top_errors, &$hidden_fields,
                                   &$field, $name,
                                   $normal_row, $error_row, $row_ender,
                                   $help_text_html, $errors_on_separate_row)
    {
            $bf = new BoundField($this, $field, $name);
            $bf_errors = $bf->errors;
            $this->htmlspecialchars($bf_errors);
            if ($field->widget->is_hidden) {
                foreach ($bf_errors as $_e) {
                    $top_errors[] = sprintf(__('(Hidden field %1$s) %2$s'),
                                            $name, $_e);
                }
                $hidden_fields[] = $bf; // Not rendered
            } else {
                if ($errors_on_separate_row && count($bf_errors)) {
                    $output[] = sprintf($error_row, $this->render_errors_as_html($bf_errors));
                }
                $label = htmlspecialchars($bf->label, ENT_COMPAT, 'UTF-8');
                if ($this->label_suffix) {
                    if (!in_array(mb_substr($label, -1, 1),
                                  array(':','?','.','!'))) {
                        $label .= $this->label_suffix;
                    }
                }
                $label = $bf->labelTag($label);
                if (strlen($bf->help_text)) {
                    // $bf->help_text can contains HTML and is not
                    // escaped.
                    $help_text = sprintf($help_text_html, $bf->help_text);
                } else {
                    $help_text = '';
                }
                $errors = '';
                if (!$errors_on_separate_row && count($bf_errors)) {
                    $errors = $this->render_errors_as_html($bf_errors);
                }
                $output[] = sprintf($normal_row, $errors, $label,
                                    $bf->render_w(), $help_text);
            }
    }

    /**
     * Render the form as a list of paragraphs.
     */
    public function render_p()
    {
        return $this->htmlOutput('<p>%1$s%2$s %3$s%4$s</p>',
                                 '%s',
                                 '</p>',
                                 '<fieldset><legend>%s</legend>',
                                 '</fieldset>',
                                 ' %s',
                                 true);
    }

    /**
     * Render the form as a list without the <ul></ul>.
     */
    public function render_ul($fieldsetTitleClass='photonFieldsetTitle')
    {
        return $this->htmlOutput('<li>%1$s%2$s %3$s%4$s</li>',
                                 '<li>%s</li>',
                                 '</li>',
                                 '<li><span class="' . $fieldsetTitleClass . '">%s</span></li>',
                                 '',
                                 ' %s',
                                 false);
    }

    /**
     * Render the form as a table without <table></table>.
     */
    public function render_table($fieldsetTitleClass='photonFieldsetTitle')
    {
        return $this->htmlOutput('<tr><th>%2$s</th><td>%1$s%3$s%4$s</td></tr>',
                                 '<tr><td colspan="2">%s</td></tr>',
                                 '</td></tr>',
                                 '<tr><td colspan="2"><span class="' . $fieldsetTitleClass . '">%s</span></td></tr>',
                                 '',
                                 '<br /><span class="helptext">%s</span>',
                                 false);
    }

    /**
     * Render the form as a list a div, using bootstrap class.
     */
    public function render_bootstrap($leftSize='col-lg-2',
                                     $rightSize='col-lg-10',
                                     $fieldsetTitleClass='photonFieldsetTitle')
    {
        // Automatic add form-control class on each input
        $in = array(
            'photon\form\field\Varchar',
            'photon\form\field\Date',
            'photon\form\field\Datetime',
            'photon\form\field\Email',
            'photon\form\field\Integer',
            'photon\form\field\IntegerNumber',
            'photon\form\field\Float',
            'photon\form\field\FloatNumber',
            'photon\form\field\IPv4',
            'photon\form\field\IPv6',
            'photon\form\field\IPv4v6',
            'photon\form\field\MacAddress',
        );
        foreach($this->_fields as $k => $v) {
            if (in_array(get_class($v), $in) === true) {
                $v->widget->attrs = array_merge($v->widget->attrs, array('class' => 'form-control'));
            }
        }

        return $this->htmlOutput('<div class="form-group clearfix"><label class="' . $leftSize . ' control-label">%2$s</label><div class="' . $rightSize . '">%3$s%4$s</div></div>',
                                 '<div class="clearfix"></div><div class="form-group"><div class="alert alert-danger">%s</div></div>',
                                 '</div>',
                                 '<div><span class="' . $fieldsetTitleClass . '">%s</span>',
                                 '</div>',
                                 '<span class="help-block">%s</span>',
                                 true);
    }

    /**
     * Overloading of the get method.
     *
     * The overloading is to be able to use property call in the
     * templates.
     */
    function __get($prop)
    {
        return $this->$prop();
    }

    /**
     * Magic call for the clean methods.
     *
     */
    public function __call($method, $args)
    {
        if ($this->$method instanceof \Closure) {
            return call_user_func_array($this->$method, $args);
        }
        throw new \Exception($method . ' is undefined.');
    }

    /**
     * Get a given field by key.
     */
    public function field($key)
    {
        return new BoundField($this, $this->_fields[$key], $key);
    }

    /**
     * Iterator method to iterate over the fields.
     *
     * Get the current item.
     */
 	public function current()
    {
        $field = current($this->_fields);
        $name = key($this->_fields);

        return new BoundField($this, $field, $name);
    }

 	public function key()
    {
        return key($this->_fields);
    }

 	public function next()
    {
        next($this->_fields);
    }

 	public function rewind()
    {
        reset($this->_fields);
    }

 	public function valid()
    {
        // We know that the boolean false will not be stored as a
        // field, so we can test against false to check if valid or
        // not.
        return (false !== current($this->_fields));
    }

    public function offsetUnset($index)
    {
        unset($this->_fields[$index]);
    }

    public function offsetSet($index, $value)
    {
        $this->_fields[$index] = $value;
    }

    public function offsetGet($index)
    {
        if (!isset($this->_fields[$index])) {
            throw new Exception(sprintf('Undefined index: %s.', $index));
        }

        return $this->_fields[$index];
    }

    public function offsetExists($index)
    {
        return (isset($this->_fields[$index]));
    }
}

/**
 * A class to store field, widget and data.
 *
 * Used when rendering a form.
 */
class BoundField
{
    public $form = null;
    public $field = null;
    public $name = null;
    public $html_name = null;
    public $label = null;
    public $help_text = null;
    public $errors = array();

    public function __construct($form, $field, $name)
    {
        $this->form = $form;
        $this->field = $field;
        $this->name = $name;
        $this->html_name = $this->form->addPrefix($name);
        if ($this->field->label == '') {
            $this->label = mb_ereg_replace('/\_/', '/ /', $this->mb_ucfirst($name));
        } else {
            $this->label = $this->field->label;
        }
        $this->help_text = ($this->field->help_text) ? $this->field->help_text : '';
        if (isset($this->form->errors[$name])) {
            $this->errors = $this->form->errors[$name];
        }
    }

    private function mb_ucfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function render_w($widget=null, $attrs=array())
    {
        if ($widget === null) {
            $widget = $this->field->widget;
        }
        $id = $this->autoId();
        if ($id && !array_key_exists('id', $attrs)
            && !array_key_exists('id', $widget->attrs)) {
            $attrs['id'] = $id;
        }
        if (!$this->form->is_bound) {
            $data = $this->form->initial($this->name);
        } else {
            $data = $this->field->widget->valueFromFormData($this->html_name, $this->form->data);
        }

        return $widget->render($this->html_name, $data, $attrs);
    }

    /**
     * Returns the HTML of the label tag.  Wraps the given contents in
     * a <label>, if the field has an ID attribute. Does not
     * HTML-escape the contents. If contents aren't given, uses the
     * field's HTML-escaped label. If attrs are given, they're used as
     * HTML attributes on the <label> tag.
     *
     * @param string Content of the label, will not be escaped (null).
     * @param array Extra attributes.
     * @return string HTML of the label.
     */
    public function labelTag($contents=null, $attrs=array())
    {
        $contents = ($contents)
            ? $contents
            : htmlspecialchars($this->label);
        $widget = $this->field->widget;
        $id = (isset($widget->attrs['id']))
            ? $widget->attrs['id']
            : $this->autoId();
        $_tmp = array();
        foreach ($attrs as $attr=>$val) {
            $_tmp[] = $attr . '="' . $val . '"';
        }
        if (count($_tmp)) {
            $attrs = ' ' . implode(' ', $_tmp);
        } else {
            $attrs = '';
        }

        return new SafeString(sprintf('<label for="%s"%s>%s</label>',
                                      $widget->idForLabel($id), $attrs, $contents), true);
    }


    /**
     * Calculates and returns the ID attribute for this BoundField, if
     * the associated Form has specified auto_id. Returns an empty
     * string otherwise.
     *
     * @return string Id or empty string if no auto id defined.
     */
    public function autoId()
    {
        $id_fields = $this->form->id_fields;
        if (false !== strpos($id_fields, '%s')) {

            return sprintf($id_fields, $this->html_name);
        } elseif ($id_fields) {

            return $this->html_name;
        }

        return '';
    }

    /**
     * Return HTML to display the errors.
     */
    public function fieldErrors()
    {
        return new SafeString($this->form->render_errors_as_html($this->errors), true);
    }

    /**
     * Overloading of the property access to access labelTag,
     * fieldErrors and render_w as properties.
     *
     * It will fail and backtrace if you try to get a non existing
     * property with no corresponding method, which is fine.
     */
    public function __get($prop)
    {
        return $this->$prop();
    }


    /**
     * Render as string.
     */
    public function __toString()
    {
        return (string) $this->render_w();
    }
}

/**
 * Field proxy to access a form field through {$form.f.fieldname} in a
 * template.
 */
class FieldProxy
{
    protected $form = null;

    public function __construct(&$form)
    {
        $this->form = $form;
    }

    /**
     * No control are performed. If you access a non existing field it
     * will simply throw an error.
     */
    public function __get($field)
    {
        return new BoundField($this->form, $this->form->_fields[$field], $field);
    }
}

