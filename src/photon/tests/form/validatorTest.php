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
        $bads = array('me @youec.com', 'me@localhost');
        foreach ($goods as $good) {
            try {
                $email = validator\Net::email($good);
            } catch (Invalid $e) {
                $this->fail(sprintf('This value should be good: %s.', $good));
            }
            $this->assertEquals($email, $good);
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
}