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
     * Set the default empty value for a field.
     *
     * @param mixed Value
     * @return mixed Value
     */
    function setDefaultEmpty($value) 
    {
        if (in_array($value, $this->empty_values, true) && !$this->multiple) {
            $value = '';
        }
        if (in_array($value, $this->empty_values, true) && $this->multiple) {
            $value = array();
        }

        return $value;
    }

    /**
     * Multi-clean a value.
     *
     * If you are getting multiple values, you need to go through all
     * of them and validate them against the requirements. This will
     * do that for you. Basically, it is cloning the field, marking it
     * as not multiple and validate each value. It will throw an
     * exception in case of failure.
     *
     * If you are implementing your own field which could be filled by
     * a "multiple" widget, you need to perform a check on
     * $this->multiple.
     *
     * @see \photon\form\field\Integer::clean
     *
     * @param array Values
     * @return array Values
     */
    public function multiClean($value)
    {
        $field = clone($this);
        $field->multiple = false;
        reset($value);
        while (list($i, $val) = each($value)) {
            $value[$i] = $field->clean($val);
        }
        reset($value);

        return $value;        
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
    public $max_value = null;
    public $min_value = null;

    public function __construct($params=array())
    {
        $this->error_messages['invalid'] = __('Enter a number.');
        parent::__construct($params);
        $this->validators[] = function ($value) {
            return validator\Net::email($value);
        };
    }


    public function toPhp($value)
    {

        parent::clean($value);
        if (in_array($value, $this->empty_values, true)) {
            $value = '';
        }
        if (!is_numeric($value)) {
            throw new Invalid($this->error_messages['invalid']);
        }
        $value = (float) $value;
        if ($this->max_value !== null and $this->max_value < $value) {
            throw new Invalid(sprintf(__('Ensure this value is less than or equal to %s.'), $this->max_value));
        }
        if ($this->min_value !== null and $this->min_value > $value) {
            throw new Invalid(sprintf(__('Ensure this value is greater than or equal to %s.'), $this->min_value));
        }
        return $value;
    }
}


class File extends Field
{
    public $widget = '\photon\form\widget\FileInput';
    public $move_function = 'Pluf_Form_Field_File_moveToUploadFolder';
    public $max_size = 2097152; // 2MB
    public $move_function_params = array();

    /**
     * Validate some possible input for the field.
     *
     * @param mixed Input
     * @return string Path to the file relative to 'upload_path'
     */
    function clean($value)
    {
        parent::clean($value);
        if (is_null($value) and !$this->required) {
            return ''; // no file
        } elseif (is_null($value) and $this->required) {
            throw new Invalid(__('No files were uploaded. Please try to send the file again.'));
        }
        $errors = array();
        $no_files = false;
        switch ($value['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
            throw new Invalid(sprintf(__('The uploaded file is too large. Reduce the size of the file to %s and send it again.'),
                      Pluf_Utils::prettySize(ini_get('upload_max_filesize'))));
            break;
        case UPLOAD_ERR_FORM_SIZE:
            throw new Invalid(sprintf(__('The uploaded file is too large. Reduce the size of the file to %s and send it again.'),
                      Pluf_Utils::prettySize($_REQUEST['MAX_FILE_SIZE'])));
            break;
        case UPLOAD_ERR_PARTIAL:
            throw new Invalid(__('The upload did not complete. Please try to send the file again.'));
            break;
        case UPLOAD_ERR_NO_FILE:
            if ($this->required) {
                throw new Invalid(__('No files were uploaded. Please try to send the file again.'));
            } else {
                return ''; // no file
            }
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            throw new Invalid(__('The server has no temporary folder correctly configured to store the uploaded file.'));
            break;
        case UPLOAD_ERR_EXTENSION:
            throw new Invalid(__('The uploaded file has been stopped by an extension.'));
            break;
        default:
            throw new Invalid(__('An error occured when upload the file. Please try to send the file again.'));
        }
        if ($value['size'] > $this->max_size) {
            throw new Invalid(sprintf(__('The uploaded file is to big (%1$s). Reduce the size to less than %2$s and try again.'), 
                                        Pluf_Utils::prettySize($value['size']),
                                        Pluf_Utils::prettySize($this->max_size)));
        }
        // copy the file to the final destination and updated $value
        // with the final path name. 'final_name' is relative to
        // Pluf::f('upload_path')
        Pluf::loadFunction($this->move_function);
        // Should throw a Invalid exception if error or the
        // value to be stored in the database.
        return call_user_func($this->move_function, $value, 
                              $this->move_function_params);
    }
}

/**
 * Default move function. The file name is sanitized.
 *
 * In the extra parameters, options can be used so that this function is
 * matching most of the needs:
 *
 *  * 'upload_path': The path in which the uploaded file will be
 *                   stored.  
 *  * 'upload_path_create': If set to true, try to create the
 *                          upload path if not existing.
 *
 *  * 'upload_overwrite': Set it to true if you want to allow overwritting.
 *
 *  * 'file_name': Force the file name to this name and do not use the
 *                 original file name. If this name contains '%s' for
 *                 example 'myid-%s', '%s' will be replaced by the
 *                 original filename. This can be used when for
 *                 example, you want to prefix with the id of an
 *                 article all the files attached to this article.
 *
 * If you combine those options, you can dynamically generate the path
 * name in your form (for example date base) and let this upload
 * function create it on demand.
 * 
 * @param array Upload value of the form.
 * @param array Extra parameters. If upload_path key is set, use it. (array())
 * @return string Name relative to the upload path.
 */
function Pluf_Form_Field_File_moveToUploadFolder($value, $params=array())
{
    $name = Pluf_Utils::cleanFileName($value['name']);
    $upload_path = Pluf::f('upload_path', '/tmp');
    if (isset($params['file_name'])) {
        if (false !== strpos($params['file_name'], '%s')) {
            $name = sprintf($params['file_name'], $name);
        } else {
            $name = $params['file_name'];
        }
    }
    if (isset($params['upload_path'])) {
        $upload_path = $params['upload_path'];
    }
    $dest = $upload_path.'/'.$name;
    if (isset($params['upload_path_create']) 
        and !is_dir(dirname($dest))) {
        if (false == @mkdir(dirname($dest), 0777, true)) {
            throw new Invalid(__('An error occured when creating the upload path. Please try to send the file again.'));
        }
    }
    if ((!isset($params['upload_overwrite']) or $params['upload_overwrite'] == false) and file_exists($dest)) {
        throw new Invalid(sprintf(__('A file with the name "%s" has already been uploaded.'), $name));
    }
    if (@!move_uploaded_file($value['tmp_name'], $dest)) {
        throw new Invalid(__('An error occured when uploading the file. Please try to send the file again.'));
    } 
    @chmod($dest, 0666);
    return $name;
}






/**
 * Add ReCaptcha control to your forms.
 *
 * You need first to get a ReCaptcha account, create a domain and get
 * the API keys for your domain. Check http://recaptcha.net/ for more
 * information.
 *
 * The recaptcha field needs to know the IP address of the user
 * submitting the form and if the request is made over SSL or
 * not. This means that you need to provide the $request object in the
 * extra parameters of your form.
 *
 * To add the ReCaptcha field to your form, simply add the following
 * to your form object (note the use of $extra['request']):
 *
 * <pre>
 * $ssl = (!empty($extra['request']->SERVER['HTTPS']) 
 *         and $extra['request']->SERVER['HTTPS'] != 'off');
 *
 * $this->fields['recaptcha'] = new Pluf_Form_Field_ReCaptcha(
 *                       array('required' => true,
 *                               'label' => __('Please solve this challenge'),
 *                               'privkey' => 'PRIVATE_RECAPTCHA_KEY_HERE',
 *                               'remoteip' => $extra['request']->remote_addr,
 *                               'widget_attrs' => array(
 *                                      'pubkey' => 'PUBLIC_RECAPTCHA_KEY_HERE',
 *                                      ),
 *                                      ));
 * </pre>
 *
 * Then in your template, you simply need to add the ReCaptcha field:
 * 
 * <pre>
 * {if $form.f.recaptcha.errors}{$form.f.recaptcha.fieldErrors}{/if}
 * {$form.f.recaptcha|safe}
 * </pre>
 *
 * Based on http://recaptcha.googlecode.com/files/recaptcha-php-1.10.zip
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Mike Crawford
 *   Ben Maurer
 */
class ReCaptcha extends Field
{
    public $widget = '\photon\form\widget\ReCaptcha';
    public $privkey = '';
    public $remoteip = '';
    public $extra_params = array();

    public function clean($value)
    {
        // will throw the Invalid exception in case of
        // error.
        self::checkAnswer($this->privkey, $this->remoteip, 
                          $value[0], $value[1], $this->extra_params);
        return $value;
    }

    /**
     * Submits an HTTP POST to a reCAPTCHA server
     *
     * @param string Host
     * @param string Path
     * @param array Data
     * @param int port (80
     * @return array response
     */
    public static function httpPost($host, $path, $data, $port=80) 
    {

        $req = self::qsencode($data);
        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        if (false === ($fs=@fsockopen($host, $port, $errno, $errstr, 10))) {
            throw new Invalid(__('Cannot connect to the reCaptcha server for validation.'));
        }
        fwrite($fs, $http_request);
        $response = '';
        while (!feof($fs)) {
            $response .= fgets($fs, 1160); // One TCP-IP packet
        }
        fclose($fs);
        return explode("\r\n\r\n", $response, 2);
    }

    /**
     * Encodes the given data into a query string format
     *
     * @param array Array of string elements to be encoded
     * @return string Encoded request
     */
    public static function qsencode($data) 
    {
        $d = array();
        foreach ($data as $key => $value) {
            $d[] = $key.'='.urlencode(stripslashes($value));
        }
        return implode('&', $d);
    }

    /**
     * Calls an HTTP POST function to verify if the user's guess was correct
     * @param string $privkey
     * @param string $remoteip
     * @param string $challenge
     * @param string $response
     * @param array $extra_params an array of extra variables to post to the server
     * @return ReCaptchaResponse
     */
    public static function checkAnswer($privkey, $remoteip, $challenge, $response, $extra_params=array())
    {
        if ($privkey == '') {
            throw new Invalid(__('To use reCAPTCHA you must set your API key.'));
        }
        if ($remoteip == '') {
            throw new Invalid(__('For security reasons, you must pass the remote ip to reCAPTCHA.'));
        }
        //discard spam submissions
        if (strlen($challenge) == 0 || strlen($response) == 0) {
            return false;
        }

        $response = self::httpPost('api-verify.recaptcha.net', '/verify',
                                   array(
                                         'privatekey' => $privkey,
                                         'remoteip' => $remoteip,
                                         'challenge' => $challenge,
                                         'response' => $response
                                         ) + $extra_params
                                   );

        $answers = explode("\n", $response[1]);
        if (trim($answers[0]) == 'true') {
            return true;
        } else {
            throw new Invalid($answers[1]);
        }
    }
}


class Slug extends Varchar
{
    /**
     * Name of the widget to use for build the forms.
     *
     * @var string
     */
    public $widget = '\photon\form\widget\TextInput';

    /**
     * Minimum size of field.
     *
     * Default to 1.
     *
     * @var int
     **/
    public $min_size = 1;

    /**
     * Maximum size of field.
     *
     * Default to 50.
     *
     * @var int
     **/
    public $max_size = 50;

    protected $_error_messages = array();

    public function __construct($params=array())
    {
        if (in_array($this->help_text, $this->empty_values, true)) {
            $this->help_text = __('The &#8220;slug&#8221; is the URL-friendly'.
                                  ' version of the name, consisting of '.
                                  'letters, numbers, underscores or hyphens.');
        }
        $this->_error_messages = array(
            'min_size' => __('Ensure this value has at most %1$d characters (it has %2$d).'),
            'max_size' => __('Ensure this value has at least %1$d characters (it has %2$d).')
        );

        parent::__construct($params);
    }

    /**
     * Removes any character not allowed and valid the size of the field.
     *
     * @see Pluf_Form_Field::clean()
     * @throws Invalid If the lenght of the field has not a valid size.
     */
    public function clean($value)
    {
        parent::clean($value);
        if ($value) {
            $value = Pluf_DB_Field_Slug::slugify($value);
            $len   = mb_strlen($value, Pluf::f('encoding', 'UTF-8'));
            if ($this->max_size < $len) {
                throw new Invalid(sprintf($this->_error_messages['max_size'],
                                                    $this->max_size,
                                                    $len));
            }
            if ($this->min_size > $len) {
                throw new Invalid(sprintf($this->_error_messages['min_size'],
                                                    $this->min_size,
                                                    $len));
            }
        }
        else
            $value = '';

        return $value;
    }

    /**
     * @see Pluf_Form_Field::widgetAttrs()
     */
    public function widgetAttrs($widget)
    {
        $attrs = array();
        if (!isset($widget->attrs['maxlength'])) {
            $attrs['maxlength'] = $this->max_size;
        } else {
            $this->max_size = $widget->attrs['maxlength'];
        }

        return $attrs;
    }
}


class Url extends Varchar
{
    public $widget = '\photon\form\widget\TextInput';

    public function clean($value)
    {
        parent::clean($value);
        if (in_array($value, $this->empty_values, true)) {
            return '';
        }
        if (!Pluf_Utils::isValidUrl($value)) {
            throw new Invalid(__('Enter a valid address.'));
        }
        return $value;
    }
}



