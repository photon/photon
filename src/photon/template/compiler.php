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

/**
 * Photon Templating Engine Compiler.
 *
 * The work of the compiler is to convert a set of templates into a
 * nice ready to consume string. The string is of course a valid piece
 * of PHP code.
 *
 * At the end, the string can either be cached on the disk or in
 * memory. This is up to you.
 */
namespace photon\template\compiler;

use photon\config\Container as Conf;
use photon\event\Event;

class Exception extends \Exception {}

/**
 * Compile a template file to PHP.
 * 
 * The important elements of the compiler are the include extends
 * block and superblock directives. They cannot be handled in a linear
 * way like the rest of the elements, they are more like nodes.
 *
 * The compiler uses the PHP parser directly, which means it is fast
 * and robust. As the output is a PHP file ready for inclusion, it is
 * also fast and robust at runtime.
 *
 * @see http://php.net/manual/en/tokens.php
 * 
 * @credit Copyright (C) 2006 Laurent Jouanneau.
 */
class Compiler
{
    /** 
     * Store the literal blocks. 
     **/
    protected $_literals;
    
    /** 
     * Variables. 
     *
     * Authorized names for the variables. For example, as a designer
     * could put something like: {$class} in a template, you need to
     * have the T_CLASS token as authorized variable.
     */
    protected $_vartype = array(T_CHARACTER, T_CONSTANT_ENCAPSED_STRING, 
                                T_DNUMBER, T_ENCAPSED_AND_WHITESPACE, 
                                T_LNUMBER, T_OBJECT_OPERATOR, T_STRING, 
                                T_WHITESPACE, T_ARRAY, T_CLASS, T_PRIVATE, 
                                T_LIST);

    /** 
     * Assignation operators. 
     */
    protected $_assignOp = array(T_AND_EQUAL, T_DIV_EQUAL, T_MINUS_EQUAL, 
                                 T_MOD_EQUAL, T_MUL_EQUAL, T_OR_EQUAL, 
                                 T_PLUS_EQUAL, T_PLUS_EQUAL, T_SL_EQUAL, 
                                 T_SR_EQUAL, T_XOR_EQUAL);

    /** 
     * Operators. 
     */
    protected  $_op = array(T_BOOLEAN_AND, T_BOOLEAN_OR, T_EMPTY, T_INC, 
                            T_ISSET, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, 
                            T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL,
                            T_IS_SMALLER_OR_EQUAL, T_LOGICAL_AND, T_LOGICAL_OR,
                            T_LOGICAL_XOR, T_SR, T_SL, T_DOUBLE_ARROW);

    /**
     * Authorized elements in variables.
     */
    protected $_allowedInVar;

    /**
     * Authorized elements in expression.
     */
    protected $_allowedInExpr;

    /**
     * Authorized elements in assignation.
     */
    protected $_allowedAssign;

    /**
     * Output filters.
     *
     * These default filters are merged with the 'template_modifiers' defined
     * in the configuration of the application.
     */
    protected $_modifier = array('upper' => '\\mb_strtoupper', 
                                 'lower' => '\\mb_strtolower',
                                 'count' => '\\count',
                                 'md5' => '\\md5',
                                 'sha1' => '\\sha1',
                                 'escxml' => '\\htmlspecialchars', 
                                 'strip_tags' => '\\strip_tags', 
                                 'escurl' => '\\rawurlencode',
                                 'capitalize' => '\\ucwords',
                                 'debug' => '\\print_r', 
                                 'fulldebug' => '\\var_export',
                                 'trim' => '\\trim',
                                 'ltrim' => '\\ltrim',
                                 'rtrim' => '\\rtrim',
                                 'safe' => '\\photon\\template\\Modifier::safe',
                                 'date' => '\\photon\\template\\Modifier::dateFormat',
                                 'ftime' => '\\photon\\template\\Modifier::strftime',

                                 /*
                                 'nl2br' => '\\todo',
                                 'dump' => '\\todo_Template_varExport', 
                                 'escape' => '\\Photon_Template_htmlspecialchars',
                                 'unsafe' => '\\Photon_Template_unsafe',


                                 'time' => '\\Photon_Template_timeFormat',
                                 'dateago' => '\\Photon_Template_dateAgo',
                                 'timeago' => '\\Photon_Template_timeAgo',
                                 'email' => '\\Photon_Template_safeEmail',
                                 'first' => '\\Photon_Template_first',
                                 'last' => '\\Photon_Template_last',
                                 */
                                 );

    /**
     * After the compilation is completed, this contains the list of
     * modifiers used in the template. The GetCompiledTemplate method
     * will add a series of photonLoadFunction at the top to preload
     * these modifiers.
     */
    public $_usedModifiers = array();

    /**
     * Default allowed extra tags/functions. 
     *
     * These default tags are merged with the 'template_tags' defined
     * in the configuration of the application.
     */
    protected $_allowedTags = array(
                                    'url' => '\\photon\\template\\tag\\Url',
                                    /*
                                    'aurl' => '\\Photon_Template_Tag_Rurl',
                                    'media' => '\\Photon_Template_Tag_MediaUrl',
                                    'amedia' => '\\Photon_Template_Tag_RmediaUrl',
                                    'aperm' => '\\Photon_Template_Tag_APerm',
                                    'getmsgs' => '\\Photon_Template_Tag_Messages',
                                    */
                                    );
    /**
     * During compilation, all the tags are created once so to query
     * their interface easily.
     */
    protected $_extraTags = array();

    /**
     * The block stack to see if the blocks are correctly closed.
     */
    protected $_blockStack = array();

    /**
     * Special stack for the translation handling in blocktrans.
     */
    protected $_transStack = array();
    protected $_transPlural = false;

    /**
     * Current template source file.
     */
    protected $_sourceFile;

    /**
     * All the source files touched by this compilation.
     */
    public $sourceFiles = array();

    /**
     * Current tag.
     */
    protected $_currentTag;

    /**
     * Template folders.
     */
    public $templateFolders = array();

    /**
     * Template content. It can be set directly from a string.
     */
    public $templateContent = '';

    /**
     * The extend blocks.
     */
    public $_extendBlocks = array();

    /**
     * The extended template.
     */
    public $_extendedTemplate = '';

    /**
     * Construct the compiler.
     *
     * @param $template_file string Basename of the template file
     * @param $folders array Source folders of the templates
     * @param $options array 
     */
    function __construct($template_file, $folders, $options=array())
    {
        $this->_sourceFile = $template_file;
        $this->templateFolders = $folders;

        $options = array_merge(array('load' => true,
                                     'tags' => array(),
                                     'modifiers' => array()),
                              $options);

        $this->_allowedTags = array_merge($this->_allowedTags,
                                          $options['tags'],
                                          Conf::f('template_tags', array()));
        Event::send('\photon\template\compiler\Compiler::construct_load_tags', null, $this->_allowedTags);

        $this->_modifier = array_merge($this->_modifier, 
                                       $options['modifiers'],
                                       Conf::f('template_modifiers', array()));
        Event::send('\photon\template\compiler\Compiler::construct_load_modifiers', null, $this->_modifier);

        foreach ($this->_allowedTags as $name=>$model) {
            $this->_extraTags[$name] = new $model();
        }

        $this->_allowedInVar = array_merge($this->_vartype, $this->_op);
        $this->_allowedInExpr = array_merge($this->_vartype, $this->_op);
        $this->_allowedAssign = array_merge($this->_vartype, $this->_assignOp, 
                                            $this->_op);

        if ($options['load']) {
            $this->sourceFiles[] = $this->loadTemplateFile($this->_sourceFile);
        }
    }

    /**
     * Compile the template into a PHP code.
     *
     * @return string PHP code of the compiled template.
     */
    function compile() 
    {
        $this->compileBlocks();
        $tplcontent = $this->templateContent;
        // Remove the template comments
        $tplcontent = preg_replace('!{\*(.*?)\*}!s', '', $tplcontent);
        // Remove PHP code
        $tplcontent = preg_replace('!<\?php(.*?)\?>!s', '', $tplcontent);
        // Catch the litteral blocks and put them in the
        // $this->_literals stack
        preg_match_all('!{literal}(.*?){/literal}!s', $tplcontent, $_match);
        $this->_literals = $_match[1];
        $tplcontent = preg_replace("!{literal}(.*?){/literal}!s", '{literal}', $tplcontent);
        // Core regex to parse the template
        $result = preg_replace_callback('/{((.).*?)}/s', 
                                        array($this, '_callback'), 
                                        $tplcontent);
        if (count($this->_blockStack)) {
            throw new Exception(sprintf(__('End tag of a block missing: %s'), end($this->_blockStack)));
        }
        // Clean the output
        $result = str_replace(array('?><?php', '<?php ?>', '<?php  ?>'), 
                              '', 
                              $result);  
        // To avoid the triming of the \n after a php closing tag.
        return str_replace("?>\n", "?>\n\n", $result);
    }

    /**
     * Get a cleaned compile template.
     *
     */
    function getCompiledTemplate()
    {
        $result = $this->compile();
        $code = array();
        foreach ($this->_usedModifiers as $modifier) {
            $code[] = '\photonLoadFunction(\''.$modifier.'\'); ';
        }
        if (count($code)) {

            return '<?php ' . implode("\n", $code) . '?>' 
                .$result;
        } else {

            return $result;
        }
    }

    /**
     * Parse the extend blocks.
     *
     * If the current template extends another, it finds the extended
     * template and grabs the defined blocks and compile them.
     */
    function compileBlocks()
    {
        $tplcontent = $this->templateContent;
        $this->_extendedTemplate = '';
        // Match extends on the first line of the template
        if (preg_match("!{extends\s['\"](.*?)['\"]}!", $tplcontent, $_match)) {
            $this->_extendedTemplate = $_match[1];
        }
        // Get the blocks in the current template
        $cnt = preg_match_all("!{block\s(\S+?)}(.*?){/block}!s", $tplcontent, $_match);
        // Compile the blocks
        for ($i=0; $i<$cnt; $i++) {
            if (!isset($this->_extendBlocks[$_match[1][$i]]) 
                or false !== strpos($this->_extendBlocks[$_match[1][$i]], '~~{~~superblock~~}~~')) {
                $compiler = clone($this);
                $compiler->templateContent = $_match[2][$i];
                $_tmp = $compiler->compile();
                $this->updateModifierStack($compiler);
                if (!isset($this->_extendBlocks[$_match[1][$i]])) {
                    $this->_extendBlocks[$_match[1][$i]] = $_tmp;
                } else {
                    $this->_extendBlocks[$_match[1][$i]] = str_replace('~~{~~superblock~~}~~', $_tmp, $this->_extendBlocks[$_match[1][$i]]);
                }
            }
        }
        if (strlen($this->_extendedTemplate) > 0) {
            // The template of interest is now the extended template
            // as we are not in a base template
            $this->sourceFiles[] = $this->loadTemplateFile($this->_extendedTemplate);
            $this->_sourceFile = $this->_extendedTemplate;
            $this->compileBlocks(); //It will recurse to the base template.
        } else {
            // Replace the current blocks by a place holder
            if ($cnt) {
                $this->templateContent = preg_replace("!{block\s(\S+?)}(.*?){/block}!s", "{block $1}", $tplcontent, -1); 
            }
        }
    }

    /**
     * Load a template file.
     *
     * The path to the file to load is relative and the file is found
     * in one of the $templateFolders array of folders.
     *
     * @param string Relative path of the file to load.
     */
    function loadTemplateFile($file)
    {
        // FUTURE: Very small security check, need better when online editing.
        if (strpos($file, '..') !== false) {
            throw new Exception(sprintf(__('Template file contains invalid characters: %s.'), $file));
        }
        foreach ($this->templateFolders as $folder) {
            $full_path = $folder . '/' . $file;
            if (file_exists($full_path)) {
                $this->templateContent = file_get_contents($full_path);
                return $full_path;
            }
        }
        throw new Exception(sprintf(__('Template file not found: %s.'), $file));
    }

    function _callback($matches) 
    {
        list(,$tag, $firstcar) = $matches;
        if (!preg_match('/^\$|[\'"]|[a-zA-Z\/]$/', $firstcar)) {
            throw new Exception(sprintf(__('Invalid tag syntax: %s'), $tag));
        }
        $this->_currentTag = $tag;
        if (in_array($firstcar, array('$', '\'', '"'))) {
            if ('blocktrans' !== end($this->_blockStack)) {
                return '<?php \photon\template\Renderer::secho('.$this->_parseVariable($tag).'); ?>';
            } else {
                $tok = explode('|', $tag);
                $this->_transStack[substr($tok[0], 1)] = $this->_parseVariable($tag);
                return '%%'.substr($tok[0], 1).'%%';
            }
        } else {
            if (!preg_match('/^(\/?[a-zA-Z0-9_]+)(?:(?:\s+(.*))|(?:\((.*)\)))?$/', $tag, $m)) {
                throw new Exception(sprintf(__('Invalid function syntax: %s'), $tag));
            }
            if (count($m) == 4){
                // Optionally uses parenthesis around the arguments
                $m[2] = $m[3];
            }
            if (!isset($m[2])) {
                $m[2] = '';
            }
            if ($m[1] == 'ldelim') {
                return '{';
            }
            if($m[1] == 'rdelim') {
                return '}';
            }
            if ($m[1] != 'include') {
                return '<?php ' . $this->_parseFunction($m[1], $m[2]) . '?>';
            } else {
                return $this->_parseFunction($m[1], $m[2]);
            }
        }
    }

    /**
     * Parse a template variable.
     *
     * A template variable starts with $ and can be modified with
     * pipes. Each modifier can have one extra argument. Modifiers can
     * be chained. For examples:
     *
     * {$variable} {$variable|lower|title} {$variable|date:"YMD"|upper}
     *
     */
    function _parseVariable($expr)
    {
        $tok = explode('|', $expr);
        $res = $this->_parseFinal(array_shift($tok), $this->_allowedInVar);
        foreach ($tok as $modifier) {
            if (!preg_match('/^(\w+)(?:\:(.*))?$/', $modifier, $m)) {
                throw new Exception(sprintf(__('Invalid modifier syntax: (%s) %s'), $this->_currentTag, $modifier));
            }
            $targs = array($res);
            if (isset($m[2])) {
                $res = $this->_modifier[$m[1]] . '(' . $res . ',' . $m[2] .')';
            } elseif (isset($this->_modifier[$m[1]])) {
                $res = $this->_modifier[$m[1]] . '(' . $res .')';
            } else {
                throw new Exception(sprintf(__('Unknown modifier: (%s) %s'), $this->_currentTag, $m[1]));
            }
            if (!in_array($this->_modifier[$m[1]], $this->_usedModifiers)) {
                $this->_usedModifiers[] = $this->_modifier[$m[1]];
            }
        }

        return $res;
    }

    function _parseFunction($name, $args)
    {
        switch ($name) {
        case 'if':
            $res = 'if ('.$this->_parseFinal($args, $this->_allowedInExpr).'): ';
            array_push($this->_blockStack, 'if');
            break;
        case 'else':
            if (end($this->_blockStack) != 'if') {
                throw new Exception(sprintf(__('End tag of a block missing: %s'), end($this->_blockStack)));
            }
            $res = 'else: ';
            break;
        case 'elseif':
            if (end($this->_blockStack) != 'if') {
                throw new Exception(sprintf(__('End tag of a block missing: %s'), end($this->_blockStack)));
            }
            $res = 'elseif('.$this->_parseFinal($args, $this->_allowedInExpr).'):';
            break;
        case 'foreach':
            $res = 'foreach ('.$this->_parseFinal($args, array_merge(array(T_AS, T_DOUBLE_ARROW, T_STRING, T_OBJECT_OPERATOR, T_LIST, $this->_allowedAssign, '[', ']')), array(';','!')).'): ';
            array_push($this->_blockStack, 'foreach');
            break;
        case 'while':
            $res = 'while('.$this->_parseFinal($args,$this->_allowedInExpr).'):';
            array_push($this->_blockStack, 'while');
            break;
        case '/foreach':
        case '/if':
        case '/while':
            $short = substr($name,1);
            if(end($this->_blockStack) != $short){
                throw new Exception(sprintf(__('End tag of a block missing: %s'), end($this->_blockStack)));
            }
            array_pop($this->_blockStack);
            $res = 'end'.$short.'; ';
            break;
        case 'assign':
            $res = $this->_parseFinal($args, $this->_allowedAssign) . '; ';
            break;
        case 'literal':
            if (count($this->_literals)) {
                $res = '?>' . array_shift($this->_literals) . '<?php ';
            } else {
                throw new Exception(__('End tag of a block missing: literal'));
            }
            break;
        case '/literal':
            throw new Exception(__('Start tag of a block missing: literal'));
            break;
        case 'block':
            $res = '?>' . $this->_extendBlocks[$args] . '<?php ';
            break;
        case 'superblock':
            $res = '?>~~{~~superblock~~}~~<?php ';
            break;
        case 'trans':
            $argfct = $this->_parseFinal($args, $this->_allowedAssign); 
            $res = 'echo(__(' . $argfct . '));';
            break;
        case 'blocktrans':
            array_push($this->_blockStack, 'blocktrans');
            $res = '';
            $this->_transStack = array();
            if ($args) {
                $this->_transPlural = true;
                $_args = $this->_parseFinal($args, $this->_allowedAssign,
                                             array(';', '[', ']'), true);
                $res .= '$_btc='.trim(array_shift($_args)).'; '; 
            }
            $res .= 'ob_start(); ';
            break;
        case '/blocktrans':
            $short = substr($name,1);
            if (end($this->_blockStack) != $short) {
                throw new Exception(sprintf(__('End tag of a block missing: %s'), end($this->_blockStack)));
            }
            $res = '';
            if ($this->_transPlural) {
                $res .= '$_btp=ob_get_contents(); ob_end_clean(); echo(';
                $res .= '\photon\translation\Translation::sprintf(_n($_bts, $_btp, $_btc), array(';
                $_tmp = array();
                foreach ($this->_transStack as $key=>$_trans) {
                    $_tmp[] = '\'' . addslashes($key) . '\' => \photon\template\Renderer::sreturn(' . $_trans . ')';
                }
                $res .= implode(', ', $_tmp);
                unset($_trans, $_tmp);
                $res .= ')));';
                $this->_transStack = array();
            } else {
                $res .= '$_bts=ob_get_contents(); ob_end_clean(); ';
                if (count($this->_transStack) == 0) {
                    $res .= 'echo(__($_bts)); ';
                } else {
                    $res .= 'echo(\photon\translation\Translation::sprintf(__($_bts), array(';
                    $_tmp = array();
                    foreach ($this->_transStack as $key=>$_trans) {
                        $_tmp[] = '\'' . addslashes($key) . '\' => \photon\template\Renderer::sreturn(' . $_trans . ')';
                    }
                    $res .= implode(', ', $_tmp);
                    unset($_trans, $_tmp);
                    $res .= '))); ';
                    $this->_transStack = array();
                }
            }
            $this->_transPlural = false;
            array_pop($this->_blockStack);
            break;
        case 'plural':
            $res = '$_bts=ob_get_contents(); ob_end_clean(); ob_start(); ';
            break;
        case 'include':
            // FUTURE: Will need some security check, when online editing.
            $argfct = preg_replace('!^[\'"](.*)[\'"]$!', '$1', $args);
            $_comp = new Compiler($argfct, $this->templateFolders);
            $res = $_comp->compile();
            $this->updateModifierStack($_comp);
            break;
        default:
            $_end = false;
            $oname = $name;
            if (substr($name, 0, 1) == '/') {
                $_end = true;
                $name = substr($name, 1);
            }
            // FUTURE: Here we could allow custom blocks.
            // Here we start the template tag calls at the template tag
            // {tag ...} is not a block, so it must be a function.
            if (!isset($this->_allowedTags[$name])) {
                throw new Exception(sprintf(__('The function tag "%s" is not allowed.'), $name));
            }
            $argfct = $this->_parseFinal($args, $this->_allowedAssign);
            // $argfct is a string that can be copy/pasted in the PHP code
            // but we need the array of args.
            $res = '';
            if (isset($this->_extraTags[$name])) {
                if (false == $_end) {
                    if (method_exists($this->_extraTags[$name], 'start')) {
                        $res .= '$_etag = new ' . $this->_allowedTags[$name] . '($t); $_etag->start(' . $argfct . '); ';
                    }
                    if (method_exists($this->_extraTags[$name], 'genStart')) {
                        $res .= $this->_extraTags[$name]->genStart();
                    }
                } else {
                    if (method_exists($this->_extraTags[$name], 'genEnd')) {
                        $res .= $this->_extraTags[$name]->genEnd();
                    }
                    if (method_exists($this->_extraTags[$name], 'end')) {
                        $res .= '$_etag = new ' . $this->_allowedTags[$name] . '($t); $_etag->end(' . $argfct . '); ';
                    }
                }
            }
            if ($res == '') {
                throw new Exception(sprintf(__('The function tag "{%s ...}" is not supported.'), $oname));
            }
        }

        return $res;
    }

    /*

    -------
    if:        op, autre, var
    foreach:   T_AS, T_DOUBLE_ARROW, T_VARIABLE
    for:       autre, fin_instruction
    while:     op, autre, var
    assign:    T_VARIABLE puis assign puis autre, ponctuation, T_STRING
    echo:      T_VARIABLE/@locale@ puis autre + ponctuation
    modificateur: serie de autre séparé par une virgule

    tous : T_VARIABLE

    */

    function _parseFinal($string, $allowed=array(), 
                         $exceptchar=array(';'), $getAsArray=false)
    {
        $tokens = token_get_all('<?php '.$string.'?>');
        $result = '';
        $first = true;
        $inDot = false;
        $firstok = array_shift($tokens);
        $afterAs = false;
        $f_key = '';
        $f_val = '';
        $results = array();

        // From the original implementation, there could be a bug in
        // the PHP parsere where sometimes the first token is not a
        // T_OPEN_TAG. The next control is checking that. I was not
        // able to reproduce it, this is why I have commented it out,
        // but I keep it for possible future reference.
        /*
         * if ($firstok == '<' && $tokens[0] == '?' && is_array($tokens[1])
         *     && $tokens[1][0] == T_STRING && $tokens[1][1] == 'php') {
         *     array_shift($tokens);
         *     array_shift($tokens);
         * }
         */
        foreach ($tokens as $tok) {
            if (is_array($tok)) {
                list($type, $str) = $tok;
                $first = false;
                if($type == T_CLOSE_TAG){
                    continue;
                }
                if ($type == T_AS) {
                    $afterAs = true;
                }
                if ($type == T_STRING && $inDot) {
                    $result .= $str;
                } elseif ($type == T_VARIABLE) {
                    $result .= '$t->_vars->'.substr($str, 1);
                } elseif ($type == T_WHITESPACE || in_array($type, $allowed)) {
                    $result .= $str;
                } else {
                    throw new Exception(sprintf(__('Invalid syntax: (%s) %s.'), $this->_currentTag, $str.' tokens'.var_export($tokens, true)));
                }
            } else {
                if (in_array($tok, $exceptchar)) {
                    throw new Exception(sprintf(__('Invalid character: (%s) %s.'), $this->_currentTag, $tok));
                } elseif ($tok == '.') {
                    $inDot = true;
                    $result .= '->';
                } elseif ($tok == '~') {
                    $result .= '.';
                } elseif ($tok =='[') {
                    $result.=$tok;
                } elseif ($tok ==']') {
                    $result.=$tok;
                } elseif ($getAsArray && $tok == ',') {
                    $results[]=$result;
                    $result='';
                } else {
                    $result .= $tok;
                }
                $first = false;
            }
        }
        if (!$getAsArray) {
            return $result;
        } else {
            if ($result != '') {
                $results[] = $result;
            }
            return $results;
        }
    }

    /**
     * Update the current stack of modifiers from another compiler.
     */
    protected function updateModifierStack($compiler)
    {
        foreach ($compiler->_usedModifiers as $_um) {
            if (!in_array($_um, $this->_usedModifiers)) {
                $this->_usedModifiers[] = $_um;
            }
        }
        // The other compiler was a clone of the current one and then
        // it went through the blocks, this means it collected more
        // source files but it includes the ones of the current compiler.
        $this->sourceFiles = $compiler->sourceFiles;
    }
}
