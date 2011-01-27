<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
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
 * Tags for the Photon Templating Engine.
 *
 * Tags allow developers to easily extend the functionnalities of the
 * template engine. They can be used to write PHP code into the
 * template or run PHP code at runtime.
 */
namespace photon\template\tag;

/**
 * A tag get the current template context at runtime.
 *
 * A tag can have up to 4 methods:
 *
 * Run time methods:
 *
 * - start([$mixed, [...]]): run at the tag start {yourtag [$mixed, [...]]}
 *
 * - end([$mixed, [...]]): run at the tag end {/yourtag [$mixed, [...]]}
 *
 * Compile time methods:
 *
 * - genStart(): returns a PHP fragment to be put at the place of the
 *   tag. The PHP fragment will be executed after the call to start() if
 *   any.
 *
 * - genEnd(): returns a PHP fragment to be put at the place of the
 *   closing tag. The PHP fragment will be executed before the call to
 *   end() if any.
 *
 * It requires at least one method to do something, either start or
 * genStart. Most of the tags are only defining the start() method.
 */
class Tag
{
    /**
     * Runtime context. Nothing in it at compilation time.
     */
    protected $context; 
    
    /**
     * Constructor.
     *
     * @param $context Context object (null)
     */
    function __construct($context=null)
    {
        $this->context = $context;
    }
}

/**
 * Example tag, to know what you can do with.
 *
 * It is fully documented for you to take as example.
 */
class Example extends Tag
{
    function start($param1, $param2='foo')
    {
        $to_show = sprintf('Param1: %s, param2: %s', $param1, $param2);
        \photon\template\Renderer::secho($to_show);
    }

    function end($param1='end foo')
    {
        $to_show = sprintf('Param1: %s', $param1);
        \photon\template\Renderer::secho($to_show);
    }

    /**
     * Return a piece of PHP which will be evaluated in the template
     * at runtime.
     */
    function genStart()
    {
        return '$example = "foo"; echo("<pre>Start: $example</pre>");';
    }

    /**
     * Return a piece of PHP which will be evaluated in the template
     * at runtime.
     *
     * In the template `$t` contains the context. So
     * `$t->_vars->hello` contains the value of what is displayed when
     * you put {$hello} in your template. This call emulates the
     * {$hello} call.
     */
    function genEnd()
    {
        return '\\photon\\template\\Renderer::secho($t->_vars->hello); ';
    }
}


/**
 * Display the URL of a view.
 *
 */
class Url extends Tag
{
    /**
     * Display the URL of a view.
     *
     * @param $view View name
     * @param $params Parameters for the view (array())
     * @param $get_params Extra get parameters (array())
     */
    function start($view, $params=array(), $get_params=array())
    {
        echo \photon\core\URL::forView($view, $params, $get_params);
    }
}
