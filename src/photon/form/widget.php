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
 * Form widgets.
 *
 * Form widgets are classes used to render the form elements. They can
 * render complex elements with multiple input fields.
 */
namespace photon\form\widget;

use photon\template\SafeString;

class Exception extends \Exception {};

/**
 * Base class to display a form field.
 *
 */
class Widget
{
    public $is_hidden = false; /**< Is an hidden field? */
    public $needs_multipart_form = false; /**< Do we need multipart? */
    public $input_type = ''; /**< Input type of the field. */
    public $attrs = array(); /**< HTML attributes for the widget. */

    public function __construct($attrs=array())
    {
        $this->attrs = $attrs;
    }

    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param mixed Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array())
    {
        throw new Exception('Not Implemented.');
    }

    /**
     * Build the list of attributes for the form.
     * It should be called this way:
     * $this->buildAttrs(array('name'=>$name, 'type'=>$this->input_type),
     *                   $extra_attrs);
     *
     * @param array Contains the name and type attributes.
     * @param array Extra attributes, like 'class' for example.
     * @return array The attributes for the field.
     */
    protected function buildAttrs($attrs, $extra_attrs=array())
    {
        return array_merge($this->attrs, $attrs, $extra_attrs);
    }

    /**
     * A widget can split itself in multiple input form. For example
     * you can have a datetime value in your model and you use 2
     * inputs one for the date and one for the time to input the
     * value. So the widget must know how to get back the values from
     * the submitted form.
     *
     * @param string Name of the form.
     * @param array Submitted form data.
     * @return mixed Value or null if not defined.
     */
    public function valueFromFormData($name, $data)
    {
        if (isset($data[$name])) {
            return $data[$name];
        }
        return null;
    }

    /**
     * Returns the HTML ID attribute of this Widget for use by a
     * <label>, given the ID of the field. Returns None if no ID is
     * available.
     *
     * This hook is necessary because some widgets have multiple HTML
     * elements and, thus, multiple IDs. In that case, this method
     * should return an ID value that corresponds to the first ID in
     * the widget's tags.
     */
    public function idForLabel($id)
    {
        return $id;
    }
}

/**
 * Base class for all the input widgets. (Except radio and checkbox).
 */
class Input extends Widget
{
    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param mixed Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array())
    {
        $final_attrs = $this->buildAttrs(array('name' => $name, 
                                                'type' => $this->input_type),
                                         $extra_attrs);
                                         
        if ($value !== null && $value !== '') {
            $final_attrs['value'] = $value;
        }
        
        return new SafeString('<input'.widget_attrs($final_attrs).' />', true);
    }
}

/**
 * Simple input of type text.
 */
class TextInput extends Input
{
    public $input_type = 'text';
}

/**
 * Simple checkbox.
 */
class CheckboxInput extends Widget
{
    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param mixed Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array())
    {
        $final_attrs = $this->buildAttrs(array('name' => $name, 
                                               'type' => 'checkbox'),
                                         $extra_attrs);
        if ((bool)$value) {
            // We consider that if a value can be boolean casted to
            // true, then we check the box.
            $final_attrs['checked'] = 'checked';
        }
        if (!in_array($value, array('', true, false, null), true)) {
            // Strict comparison, maybe a checkbox is providing a list
            // of elements like the computers you have.
            $final_attrs['value'] = $value;
        }
        return new SafeString('<input'.widget_attrs($final_attrs).' />', true);
    }

    /**
     * A non checked checkbox is simply not returned in the form array.
     *
     * @param string Name of the form field
     * @param array Submitted form data
     * @return mixed Value or null if not defined
     */
    public function valueFromFormData($name, $data)
    {
        if (!array_key_exists($name, $data)) {
            // Not submitted, false
            return false;
        }
        $value = $data[$name];
        if (in_array($value, array('on', 'off'))) {
            return (strlen($value) === 2);
        }
        return $value;
    }
}



/**
 * Simple input of type datetime.
 */
class DatetimeInput extends Input
{
    public $input_type = 'text';
    public $format = 'Y-m-d H:i'; // '2006-10-25 14:30' by default do
                                  // not show the seconds.

    public function render($name, $value, $extra_attrs=array())
    {
        if (is_object($value) 
            && 'photon\datetime\DateTime' === get_class($value)) {
            $value = $value->format($this->format);
        }
        return parent::render($name, $value, $extra_attrs);
    }
}

/**
 * Simple input of type file.
 */
class FileInput extends Input
{
    public $input_type = 'file';
    public $needs_multipart_form = true;

    public function render($name, $value, $extra_attrs=array())
    {
        $value = '';
        return parent::render($name, $value, $extra_attrs);
    }
}

/**
 * Simple input of type text.
 */
class HiddenInput extends Input
{
    public $input_type = 'hidden';
    public $is_hidden = true;
}

/**
 * Simple input of type text.
 */
class PasswordInput extends Input
{
    public $input_type = 'password';
    public $render_value = true;

    public function __construct($attrs=array())
    {
        $this->render_value = (isset($attrs['render_value'])) ? $attrs['render_value'] : $this->render_value;
        unset($attrs['render_value']);
        parent::__construct($attrs);
    }

    public function render($name, $value, $extra_attrs=array())
    {
        if ($this->render_value === false) {
            $value = '';
        }
        return parent::render($name, $value, $extra_attrs);
    }
}


/**
 * Simple checkbox with grouping.
 */
class SelectInput extends Widget
{
    public $choices = array();

    public function __construct($attrs=array())
    {
        $this->choices = $attrs['choices'];
        unset($attrs['choices']);
        parent::__construct($attrs);
    }

    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param mixed Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @param array Extra choices (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array(), 
                           $choices=array())
    {
        $output = array();
        if ($value === null) {
            $value = '';
        }
        $final_attrs = $this->buildAttrs(array('name' => $name), $extra_attrs);
        $output[] = '<select'.widget_attrs($final_attrs).'>';
        $groups = $this->choices + $choices;
        foreach($groups as $option_group => $c) {
            if (!is_array($c)) {
                $subchoices = array($option_group => $c);
            } else {
                $output[] = '<optgroup label="'.htmlspecialchars($option_group, ENT_COMPAT, 'UTF-8').'">';
                $subchoices = $c;
            }
            foreach ($subchoices as $option_label=>$option_value) {
                $selected = ($option_value == $value) ? ' selected="selected"':'';
                $output[] = sprintf('<option value="%s"%s>%s</option>',
                                    htmlspecialchars($option_value, ENT_COMPAT, 'UTF-8'),
                                    $selected, 
                                    htmlspecialchars($option_label, ENT_COMPAT, 'UTF-8'));
            }
            if (is_array($c)) {
                $output[] = '</optgroup>';
            }
        }
        $output[] = '</select>';
        return new SafeString(implode("\n", $output), true);
    }
}


/**
 * Simple checkbox.
 */
class SelectMultipleInput extends Widget
{
    public $choices = array();

    public function __construct($attrs=array())
    {
        $this->choices = $attrs['choices'];
        unset($attrs['choices']);
        parent::__construct($attrs);
    }

    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param array Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @param array Extra choices (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array(), 
                           $choices=array())
    {
        $output = array();
        if ($value === null) {
            $value = array();
        } else if (is_array($value) === false) {
            $value = array($value);
        }
        $final_attrs = $this->buildAttrs(array('name' => $name/*.'[]'*/), 
                                         $extra_attrs);
        $output[] = '<select multiple="multiple"'
            .widget_attrs($final_attrs).'>';
        $groups = $this->choices + $choices;
        foreach($groups as $option_group => $c) {
            if (!is_array($c)) {
                $subchoices = array($option_group => $c);
            } else {
                $output[] = '<optgroup label="'.htmlspecialchars($option_group, ENT_COMPAT, 'UTF-8').'">';
                $subchoices = $c;
            }
            foreach ($subchoices as $option_label=>$option_value) {
                $selected = in_array($option_value, $value) ? ' selected="selected"':'';
                $output[] = sprintf('<option value="%s"%s>%s</option>',
                                    htmlspecialchars($option_value, ENT_COMPAT, 'UTF-8'),
                                    $selected, 
                                    htmlspecialchars($option_label, ENT_COMPAT, 'UTF-8'));
            }
            if (is_array($c)) {
                $output[] = '</optgroup>';
            }
        }
        $output[] = '</select>';
        return new SafeString(implode("\n", $output), true);
    }

    public function valueFromFormData($name, $data)
    {
        if (isset($data[$name]) and is_array($data[$name])) {
            return $data[$name];
        } elseif (isset($data[$name])) {
            return array($data[$name]);
        }
        return null;
    }

}

/**
 * Textarea.
 */
class TextareaInput extends Widget
{

    public function __construct($attrs=array())
    {
        $this->attrs = array_merge(array('cols' => '40', 'rows' => '10'), 
                                   $attrs);
    }

    /**
     * Renders the HTML of the input.
     *
     * @param string Name of the field.
     * @param mixed Value for the field, can be a non valid value.
     * @param array Extra attributes to add to the input form (array())
     * @return string The HTML string of the input.
     */
    public function render($name, $value, $extra_attrs=array())
    {
        if ($value === null) $value = '';
        $final_attrs = $this->buildAttrs(array('name' => $name),
                                         $extra_attrs);

        return new SafeString(
                       sprintf('<textarea%s>%s</textarea>',
                               widget_attrs($final_attrs),
                               \photon\template\Renderer::sreturn($value)),
                       true);
    }
}


/**
 * Convert an array in a string ready to use for HTML attributes.
 *
 * As all the widget will extend the Pluf_Form_Widget class, it means
 * that this function is available directly in the extended class.
 */
function widget_attrs($attrs)
{
    $_tmp = array();
    foreach ($attrs as $attr=>$val) {
        $val = \photon\template\Renderer::sreturn($val);
        $_tmp[] = $attr.'="'.$val.'"';
    }

    return ' '.implode(' ', $_tmp);
}

