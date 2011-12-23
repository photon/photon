<?php 
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
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
 * Collection of middleware.
 */
namespace photon\middleware;

/**
 * This middleware compresses content if the browser allows gzip
 * compression.  It sets the Vary header accordingly, so that caches
 * will base their storage on the Accept-Encoding header.
 *
 * It is a rewrite of the corresponding Django middleware.
 */
class Gzip
{
    public function process_response($request, $response)
    {
        // It's not worth compressing non-OK or really short responses.
        if (!$response || $response->status_code != 200 || strlen($response->content) < 200) {

            return $response;
        }
        // We patch already because if we have IE being the first
        // asking through a proxy, the proxy could cache without
        // taking into account the Accept-Encoding as it may not be
        // applied for it and thus a good browser afterward would not
        // benefit from the gzip version.
        \photon\http\HeaderTool::updateVary($response, 
                                            array('Accept-Encoding'));

        // Avoid gzipping if we've already got a content-encoding.
        if (isset($response->headers['Content-Encoding'])) {

            return $response;
        }

        // MSIE have issues with gzipped respones of various content types.
        if (false !== strpos(strtolower($request->getHeader('user-agent')), 'msie')) {
            $ctype = strtolower($response->headers['Content-Type']);
            if (0 !== strpos($ctype, 'text/') 
                || false !== strpos($ctype, 'javascript')) {

                return $response;
            }
        }

        if (!preg_match('/\bgzip\b/i', $request->getHeader('accept-encoding'))) {
            
            return $response;
        }

        $response->content = gzencode($response->content);
        $response->headers['Content-Encoding'] = 'gzip';
        $response->headers['Content-Length'] = strlen($response->content);

        return $response;
    }
}
