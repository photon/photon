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
namespace photon\form\field;

include_once __DIR__ . '/../locale/en/formats.php';

use photon\locale\en\formats as en_formats;
use photon\form\validator;
use photon\form\Invalid;

/**
 * Default form field.
 *
 * A form field is providing a defined set of methods and properties
 * to be used in the rendering of the fields in forms, in the
 * conversion of the data from the user input to a form usable by the
 * models.
 */
class Field
{
    /**
     * Store the name of the class.
     */
    public $class = '\photon\form\Field';

    /**
     * Widget. The way to "present" the field to the user.
     */
    public $widget = '\photon\form\widget\TextInput';
    public $label = ''; /**< The label of the field. */
    public $required = false; /**< Allowed to be blank. */
    public $help_text = ''; /**< Help text for the field. */
    public $initial = ''; /**< Default value when empty. */
    public $choices = null; /**< Predefined choices for the field. */

    /** 
     * Associative array of possible error messages.
     *
     * The base 'required' and 'invalid' are automatically set in the
     * constructor.
     */
    public $error_messages = array(); 

    /*
     * Following member variables are more for internal cooking.
     */
    public $hidden_widget = '\photon\form\widget\HiddenInput';
    public $value = ''; /**< Current value of the field. */
    /**
     * Returning multiple values (select multiple etc.)
     */
    public $multiple = false; 
    protected $empty_values = array('', null, array());

    /**
     * Array of callables validating a value.
     */
    public $validators = array();

    /**
     * Constructor.
     *
     * Example:
     * $field = new Your_Field(array('required'=>true, 
     *                               'widget'=>'\photon\form\widget\TextInput',
     *                               'initial'=>'your name here',
     *                               'label'=>__('Your name'),
     *                               'help_text'=>__('You are?'));
     *
     * @param array Params of the field.
     */
    function __construct($params=array())
    {
        // We basically take the parameters, for each one we grab the
        // corresponding member variable and populate the $default
        // array with. Then we merge with the values given in the
        // parameters and update the member variables.
        // This allows to pass extra parameters likes 'min_size'
        // etc. and update the member variables accordingly. This is
        // practical when you extend this class with your own class.
        $default = array();
        foreach ($params as $key=>$in) {
            if ($key !== 'widget_attrs')
                // Here on purpose it will fail if a parameter not
                // needed for this field is passed.
                $default[$key] = $this->$key; 
        }
        $m = array_merge($default, $params);
        foreach ($params as $key=>$in) {
            if ($key !== 'widget_attrs')
                $this->$key = $m[$key];
        }
        // Set the default error messages
        $this->error_messages = array_merge(
                        array('required' => __('This field is required.'),
                              'invalid' => __('Enter a valid value.')),
                        $this->error_messages);
        // Set the widget to be an instance and not the string name.
        $widget_name = $this->widget;
        $attrs = (isset($params['widget_attrs']))
            ? $params['widget_attrs']
            : array();
        $widget = new $widget_name($attrs);
        $attrs = $this->widgetAttrs($widget);
        if (count($attrs)) {
            $widget->attrs = array_merge($widget->attrs, $attrs);
        }
        $this->widget = $widget;
    }

    /**
     * Validate some possible input for the field.
     *
     * @param mixed Value to clean.
     * @return mixed Cleaned data or throw a Invalid exception.
     */
    function clean($value)
    {
        $value = $this->toPhp($value);
        $this->validate($value);
        $this->runValidators($value);
        return $value;
    }

    /**
     * Convert a submitted value to the corresponding PHP value.
     *
     * For example "1","True","y" to true.
     *
     * @param string $value
     * @return mixed PHP value
     */
    public function toPhp($value)
    {
        return $value;
    }

    /**
     * Check if a value is valid for this kind of field.
     *
     * For example, if you have a select, if the value is in the
     * select list. The base is just to check that if the field is
     * required, the value is not "empty".
     *
     * Through an Invalid exception on error.
     *
     * @param mixed $value Value from self::toPhp()
     * @return void
     */
    public function validate($value)
    {
        if ($this->required && in_array($value, $this->empty_values, true)) {
            throw new Invalid($this->error_messages['required'], 'required');
        }
    }

    /**
     * Run the list of validators.
     *
     * All the validators are run and all the errors collected.
     */
    public function runValidators($value)
    {
        // if the value is empty, there is nothing to validate
        if (in_array($value, $this->empty_values, true)) {
            return;
        }
        $errors = array();
        foreach ($this->validators as $v) {
            try {
                $v($value);
            } catch (Invalid $e) {
                $code =  $e->getCode();
                if ($code && isset($this->error_messages[$code])) {
                    $errors[] = $this->error_messages[$code];
                } else {
                    $errors = array_merge($errors, $e->messages);
                }
            }
        }
        if (count($errors)) {
            throw new Invalid($errors);
        }
    }
    
    /**
     * Returns the HTML attributes to add to the field.
     *
     * @param object Widget
     * @return array HTML attributes.
     */
    public function widgetAttrs($widget)
    {
        return array();
    }
}

class Varchar extends Field
{
    public $widget = '\photon\form\widget\TextInput';
    public $max_length = null;
    public $min_length = null;

    public function __construct($params=array())
    {
        parent::__construct($params);
        // we add the min/max length validators
        if (null !== $this->min_length) {
            $min = $this->min_length;
            $this->validators[] = function ($value) use ($min) {
                return validator\Text::minLength($value, $min);
            };
        }
        if (null !== $this->max_length) {
            $max = $this->max_length;
            $this->validators[] = function ($value) use ($max) {
                return validator\Text::maxLength($value, $max);
            };
        }
    }

    public function toPhp($value)
    {
        // The spaces at the end in a form is always an issue when
        // evaluating if a value is required or not. So, we take the
        // step to trim them. We trim only space, tabs, nul byte and
        // vertical tab, not the carriage return and new lines.
        $value = rtrim($value, " \t\x0B\0");
        if (in_array($value, $this->empty_values, true)) {
            return '';
        }
        return $value;
    }

    public function widgetAttrs($widget)
    {
        if ($this->max_length !== null and 
            in_array(get_class($widget), 
                     array('photon\form\widget\TextInput', 
                           'photon\form\widget\PasswordInput'))) {
            return array('maxlength'=>$this->max_length);
        }
        return array();
    }
}




class Boolean extends Field
{
    public $widget = '\photon\form\widget\CheckboxInput';

    public function toPhp($value)
    {
        $value = (in_array($value, array('off', 'n', 'false', 'False', '0'), true))
            ? false : (bool) $value;
        if (!$value and $this->required) {
            // This is because false is not in the empty value list.
            throw new Invalid($this->error_messages['required'], 'required');
        }
        return $value;
    }
}

/**
 * Date input field.
 *
 * The format of the date and thus the format in which we can expect
 * the user to input the date varies depending of the user locale. The
 * input formats for the given locale are loaded from the
 * photon\locale\LC_lc\formats namespace.
 */
class Date extends Varchar
{
    public $input_formats = array();

    public function __construct($params=array())
    {
        $this->input_formats = en_formats\DATE_INPUT_FORMATS;
        $this->error_messages['invalid'] = __('Enter a valid date.');
        parent::__construct($params);
    }

    public function toPhp($value)
    {
        if (in_array($value, $this->empty_values, true)) {
            return '';
        }
        if (is_object($value) 
            && 'photon\datetime\Date' === get_class($value)) {
            return $value;
        }
        foreach (explode('||', $this->input_formats) as $format) {
            if (false !== ($date = \photon\datetime\Date::fromFormat($format, $value))) {
                return $date;
            }
        }
        throw new Invalid($this->error_messages['invalid']);
    }
}

class Datetime extends Varchar
{
    public $input_formats = array();

    public function __construct($params=array())
    {
        $this->input_formats = en_formats\DATETIME_INPUT_FORMATS;
        $this->error_messages['invalid'] = __('Enter a valid date and time.');
        parent::__construct($params);
    }

    public function toPhp($value)
    {
        if (in_array($value, $this->empty_values, true)) {
            return '';
        }
        if (is_object($value) 
            && 'photon\datetime\DateTime' === get_class($value)) {
            return $value;
        }
        foreach (explode('||', $this->input_formats) as $format) {
            $date = \photon\datetime\DateTime::fromFormat($format, $value);
            if (false !== $date) {
                return $date;
            }
        }
        throw new Invalid($this->error_messages['invalid']);
    }
}

class Email extends Varchar
{
    public function __construct($params=array())
    {
        parent::__construct($params);
        $this->validators[] = function ($value) {
            return validator\Net::email($value);
        };
    }

    public function clean($value)
    {
        $value = trim($this->toPhp($value));        
        return parent::clean($value);
    }
}

class Integer extends Varchar
{
    public $max_value = null;
    public $min_value = null;

    public function __construct($params=array())
    {
        $this->error_messages['invalid'] = __('Enter a whole number.');
        parent::__construct($params);
        // We add the min/max value validators
        if (null !== $this->min_value) {
            $min = $this->min_value;
            $this->validators[] = function ($value) use ($min) {
                return validator\Numeric::minValue($value, $min);
            };
        }
        if (null !== $this->max_value) {
            $max = $this->max_value;
            $this->validators[] = function ($value) use ($max) {
                return validator\Numeric::maxValue($value, $max);
            };
        }
    }

    public function toPhp($value)
    {
        if (is_int($value)) {
            return $value;
        }
        if (in_array($value, $this->empty_values, true)) {
            return null;
        }
        if (!preg_match('/^([+\-]{0,1}\d+)$/', $value)) {
            throw new Invalid($this->error_messages['invalid']);
        }
        return (int) $value;
    }
}


class Float extends Integer
{
    public function __construct($params=array())
    {
        $this->error_messages['invalid'] = __('Enter a number.');
        parent::__construct($params);
    }


    public function toPhp($value)
    {
        if (is_float($value)) {
            return $value;
        }
        if (in_array($value, $this->empty_values, true)) {
            return null;
        }
        // Here we are too lax, but we force to float down the line.
        if (!is_numeric($value)) {
            throw new Invalid($this->error_messages['invalid']);
        }
        return (float) $value;
    }
}


class File extends Field
{
    public $widget = '\photon\form\widget\FileInput';
    public $max_size = null;
    public $max_length = null;

    public function __construct($params=array())
    {
        $this->error_messages['invalid'] = __('No file was submitted. Check the encoding type on the form.');
        $this->error_messages['required'] = __('No file was submitted.');
        $this->error_messages['empty'] = __('The submitted file is empty.');
        $this->error_messages['max_length'] = __('Ensure this filename has at most %1$d characters (it has %1$d).');
        parent::__construct($params);
    }

    public function toPhp($value)
    {
        if (in_array($value, $this->empty_values, true)) {
            return null;
        }
        if (!isset($value['filename']) || !isset($value['size'])) {
            throw new Invalid($this->error_messages['invalid']);
        }
        $filename = $value['filename'];
        $size = $value['size'];

        if (null !== $this->max_length 
            && strlen($filename) > $this->max_length) {
            throw new Invalid(sprintf($this->error_messages['max_length'],
                                      $this->max_length, strlen($filename)));
        }
        if (0 === strlen($filename)) {
            throw new Invalid($this->error_messages['invalid']);
        }
        if (0 === $size) {
            throw new Invalid($this->error_messages['empty']);
        }
        return $value;
    }
}

