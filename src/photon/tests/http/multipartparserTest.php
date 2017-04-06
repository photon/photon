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



namespace photon\tests\http\multipartparser;

use \photon\test\TestCase;

class ParserTest extends TestCase
{
    public function testVideoUploadFile()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'r');
        $boundary = '---------------------------10102754414578508781458777923';
        $iterator = new \photon\http\multipartparser\BoundaryIter($datafile, $boundary);
        $i = 0;
        $parts = array();
        while (false !== ($parts[]=$iterator->getPart())) {
            $i++;
        }
        fclose($datafile);
        $this->assertEquals(3, $i);
        $this->assertEquals('form-data; name="upload"; filename="shortest_video.mp4"', $parts[2][0]['Content-Disposition']);
        unset($parts);
    }

    public function testVideoUploadContent()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'rb');
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
        $fields = $parser->parse();
        $this->assertEquals(3, count($fields));
        $this->assertEquals('title', $fields[0]['name']);
        $this->assertEquals('upload', $fields[1]['name']);
        $this->assertEquals('upload', $fields[2]['name']);
        fclose($datafile);
    }

    public function testBadEncodingHeader()
    {
        $headers = (object) array('content-type' => 'form-encoded; boundary=---------------------------10102754414578508781458777923');
        $datafile = 'dummy';
        $this->setExpectedException('\photon\http\multipartparser\Exception');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
    }

    public function testBadBoundary()
    {
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=');
        $datafile = 'dummy';
        $this->setExpectedException('\photon\http\multipartparser\Exception');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
    }

    public function testBoundaryNotMatching()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'rb');
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------WILLNOTMATCH78508781458777923');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
        $fields = $parser->parse();
        $this->assertEquals(0, count($fields));
        fclose($datafile);
    }

    public function testNoBoundaryEnd()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload.corrupted', 'rb');
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
        $fields = $parser->parse();
        $this->assertEquals(2, count($fields));
        fclose($datafile);
    }

    public function testReadDataFields()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'rb');
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
        $fields = $parser->parse();
        $this->assertEquals(3, count($fields));
        $this->assertEquals(1032, strlen($fields[1]['data']));
        $this->assertEquals(11784, strlen($fields[2]['data']));
        fclose($datafile);
    }

    public function testReadSmallDataFields()
    {
        $datafile = fopen(__DIR__ . '/../data/small.upload', 'rb');
        $headers = (object) array('content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923');
        $parser = new \photon\http\multipartparser\MultiPartParser($headers, 
                                                                   $datafile);
        $fields = $parser->parse();
        $this->assertEquals(1, count($fields));
        $this->assertEquals(19, strlen($fields[0]['data']));
        fclose($datafile);
    }

}

