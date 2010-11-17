<?php 
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
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
 * Collection of response objects.
 */
namespace photon\http\response;
use photon\http\Response;

class NotFound extends Response
{
    function __construct($request)
    {
        $mimetype = 'text/plain';
        $content = sprintf('The requested URL %s was not found on this server.'."\n"
                           .'Please check the URL and try again.'."\n\n".'404 - Not Found',
                           str_replace(array('&',     '"',      '<',    '>'),
                                       array('&amp;', '&quot;', '&lt;', '&gt;'),
                                       $request->path));
        parent::__construct($content, $mimetype);
        $this->status_code = 404;
    }
}

