<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
# Copyright (C) 2014 William MARTIN and contributors.
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

/*
 *  The function http_parse_cookie is only available in PHP extension php_http
 *  in version : pecl_http >= 0.20.0 && pecl_http < 2.0.0
 */

// php_http PECL extension > 2
if (function_exists('http_parse_cookie') === false && class_exists('http\Cookie') === true) {

    /*
     *  Create a wrapper from php_http 2.x object
     */
    function http_parse_cookie($cookieString)
    {
        $cookie = new \http\Cookie($cookieString);
        return array(
            'cookies' => $cookie->getCookies(),
            'expires' => $cookie->getExpires(),
            'domain' => $cookie->getDomain(),
            'path' => $cookie->getPath(),
            'flags' => $cookie->getFlags(),
        );
    }
}

if (function_exists('http_parse_cookie') === false) {
    throw new \Exception('The function http_parse_cookie is missing');
}

