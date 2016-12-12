<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Performance PHP Framework.
# Copyright (C) 2010 Loic d'Anterroches and contributors.
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
 * Photon Shortcuts.
 *
 * Collection of small classes and functions used nearly everywhere in
 * the code. They are grouped in one namespace to ease the inclusion.
 */
namespace photon\shortcuts;

use photon\config\Container as Conf;
use photon\template as ptemplate;

class Template
{
    /**
     * Render a template file and an array as a reponse.
     *
     * @param $tmpl Template file name
     * @param $context Associative array for the context
     * @return Photon response with the rendered template
     */
    public static function RenderToResponse($tmpl, $context, $request=null)
    {
        $renderer = new ptemplate\Renderer($tmpl, Conf::f('template_folders'));
        $context = (null === $request) 
            ? new ptemplate\Context($context)
            : new ptemplate\ContextRequest($request, $context);

        return new \photon\http\Response($renderer->render($context));
    }

    /**
     * Render a template file and an array as a reponse.
     *
     * @param $tmpl Template file name
     * @param $context Associative array for the context
     * @return Rendered template as a string
     */
    public static function RenderToString($tmpl, $context)
    {
        $renderer = new ptemplate\Renderer($tmpl, Conf::f('template_folders'));
        $context = new ptemplate\Context($context);

        return $renderer->render($context);
    }
}

class Server
{
    static private $request = null;

    /**
     * Get the current HTTP context
     *
     * @return Photon request
     */
    public function getCurrentRequest()
    {
        return self::$request;
    }

    /**
     * Set the current HTTP context
     *
     * @param $request Photon request
     */
    public function setCurrentRequest(&$request)
    {
        self::$request = $request;
    }

    /**
     * Clear the current HTTP context
     *
     * @param $request Photon request
     */
    public function clearCurrentRequest()
    {
        self::$request = null;
    }


    /**
     * Get the current session handler
     *
     * @return Photon session handler
     */
    public function getCurrentSession()
    {
        if (self::$request === null) {
            return null;
        }

        if (isset(self::$request->session)) {
            return self::$request->session;
        }

        return null;
    }
}

