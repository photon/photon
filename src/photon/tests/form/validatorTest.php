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


namespace photon\tests\form\validatorTest;

use \photon\form\Invalid;
use \photon\form\validator;

class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testEmail()
    {
        $goods = array('me@you.com', 'foo@internet.toto.tetet.com',
                       'me+123@mail.com');
        $bads = array('me @youec.com', 'me@localhost', 'me@com.123');
        foreach ($goods as $good) {
            try {
                $isValid = validator\Net::email($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($isValid, true);
        }
        foreach ($bads as $bad) {
            try {
                $email = validator\Net::email($bad);
            } catch (Invalid $e) {
                continue;
            }
            $this->fail(sprintf('This value should be bad: %s.', $bad));
        }
    }

    public function testEmails()
    {
        $xml = simplexml_load_file(__DIR__ . '/emails.xml', 'SimpleXMLElement', 
                                   LIBXML_NONET);
        foreach ($xml->t as $test) {
            $email = (string) $test->a;
            $res = ((string) $test->r == 't');
            try {
                $w = validator\Net::email($email,  array(
                                                         'allow_comments' => true,
                                                         'public_internet' => false));

            } catch (Invalid $e) {
                if (!$res) {
                    continue;
                } 
                $this->fail(sprintf('This value should be good: %s.', $email));
            }
            if (!$res) {
                $this->fail(sprintf('This value should be bad: %s.', $email));
            }
        }
    }
    
    public function testIP()
    {
        $inputs = array(
            array('ip' => "192.168.0.1", 'flags' => null, 'res' => true),
            array('ip' => "192.168.1.1", 'flags' => FILTER_FLAG_IPV4, 'res' => true),
            array('ip' => "192.168.2.1", 'flags' => FILTER_FLAG_IPV6, 'res' => false),
            array('ip' => "::1", 'flags' => null, 'res' => true),
            array('ip' => "dead:beaf::1", 'flags' => FILTER_FLAG_IPV4, 'res' => false),
            array('ip' => "dead:beaf::2", 'flags' => FILTER_FLAG_IPV6, 'res' => true),
        );
        
        foreach ($inputs as $i) {
            $ip = $i['ip'];
            $flags = $i['flags'];
            $res = $i['res'];
            try {
                $w = validator\Net::ipAddress($ip, $flags);
            } catch (Invalid $e) {
                if (!$res) {
                    continue;
                } 
                $this->fail(sprintf('This value should be good: %s.', $ip));
            }
            if (!$res) {
                $this->fail(sprintf('This value should be bad: %s.', $ip));
            }
        }
    }
    
    public function testMAC()
    {
        $inputs = array(
            array('mac' => '', 'res' => false),
            array('mac' => '00:11:22:33:44:55', 'res' => true),
            array('mac' => '00-11-22-33-44-55', 'res' => true),
            array('mac' => '001122334455', 'res' => true),
            array('mac' => '0011223344', 'res' => false),
            array('mac' => '00:11:22:33::55', 'res' => false),
            array('mac' => '00:11:22:33', 'res' => false),
            array('mac' => '00:ae:fe:23:45:F4', 'res' => true),
        );
        
        foreach ($inputs as $i) {
            $mac = $i['mac'];
            $res = $i['res'];
            try {
                $w = validator\Net::macAddress($mac);
            } catch (Invalid $e) {
                if (!$res) {
                    continue;
                } 
                $this->fail(sprintf('This value should be good: %s.', $mac));
            }
            if (!$res) {
                $this->fail(sprintf('This value should be bad: %s.', $mac));
            }
        }
    }
}
