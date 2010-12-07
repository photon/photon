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
include_once __DIR__ . '/../../http/multipartparser.php';

class ParserTest extends \PHPUnit_Framework_TestCase
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
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'r+b');
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
}

