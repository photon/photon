<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
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
 * Tools and class to facilitate the testing of your code.
 */
namespace photon\test;

use photon\config\Container as Conf;

/**
 * TestCase automatically loading the configuration for each test.
 *
 * It is an abstract class not te be picked as real test by PHPUnit.
 *
 * @codeCoverageIgnore
 */

if (class_exists('\PHPUnit_Framework_TestCase')) {
/**
 *  PHPUnit legacy version
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $conf;

    public function setup()
    {
        $this->conf = Conf::dump();

        if (isset($_ENV['photon.config'])) {
            Conf::load(include $_ENV['photon.config']);
        }
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }
}
} else {
/**
 *  PHPUnit 6.0+ use namespaces and types
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected $conf;

    public function setUp(): void
    {
        $this->conf = Conf::dump();

        if (isset($_ENV['photon.config'])) {
            Conf::load(include $_ENV['photon.config']);
        }
    }

    public function tearDown(): void
    {
        Conf::load($this->conf);
    }
}
}

/**
 * Utility functions to emulate HTTP requests.
 */
class HTTP
{
    /**
     * Generate a base request.
     */
    public static function baseRequest($method='GET', $path='/', $query='', $body='', $params=array(), $headers=array())
    {
        list($uri, $query) = self::getUriQuery($path, $params);
        $_headers = array(
            'VERSION' => 'HTTP/1.1',
            'METHOD' => $method,
            'URI' => $uri,
            'QUERY' => $query,
            'PATH' => $path,
            'URL_SCHEME' => 'http',
            'host' => 'test.example.com',
        );
        $headers = array_merge($_headers, $headers);
        $msg = new \photon\mongrel2\Message('dummy', 'dummy',
                                            $path, (object) $headers, $body);
        return new \photon\http\Request($msg);
    }

    public static function getUriQuery($path, $params)
    {
        $uri = $path;
        $query = '';
        if (count($params)) {
            $query = http_build_query($params);
            $uri .= '?' . $query;
        }
        return array($uri, $query);
    }
}
