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
 * Multipart parser for the uploaded files.
 *
 * This module provides all the machinery to parse a POST request with
 * multipart encoding. It returns the equivalent of the traditional
 * $_POST super global and a $_FILES with special file handlers
 * instead of real files.
 *
 * It is inspired by the Djange multipartparser.py
 */
namespace photon\http\multipartparser;

class Exception extends \Exception
{
}

/**
 * Core class to parse a multipart request.
 *
 * The request headers are needed to correctly get the encoding and
 * the boundary.
 */
class MultiPartParser
{
    public $boundary; /**< The payload boundary. */
    public $body; /**< The payload handler. */
    protected $start_offset; /**< Offset where the body start. */

    /**
     * Initialization.
     *
     * @param $headers The request headers
     * @param &$body File handler set at the start of the body
     */
    public function __construct($headers, &$body)
    {
        if (0 !== strpos($headers->{'content-type'}, 'multipart/')) {
            throw new Exception(sprintf('Invalid Content-Type: %s.',
                                        $headers->{'content-type'}));
        }
        $ctype = http_parse_params($headers->{'content-type'});
        $this->boundary = self::getHeaderOption('boundary', $ctype);
        if (null == $this->boundary) {
            throw new Exception('Invalid multipart boundary.');
        }
        $this->body = $body;
    }

    public function parse()
    {
        $fields = array();
        $iterator = new BoundaryIter($this->body, $this->boundary);
        while (false !== ($part=$iterator->getPart())) {
            // The headers from the multipart parsing are a bit crappy
            // as http_parse_params returns a lot of junk "data
            // structure", we need to take the time to clean the mess
            // because in fact, we need only a very limited set of
            // information.
            $type = 'FIELD';
            $field = array();
            $start = $part[1];
            $end = $part[2];
            foreach ($part[0] as $key=>$val) {
                $params = http_parse_params($val);
                if ($key === 'Content-Disposition') {
                    // Here we get if we have a POST or a FILE
                    if (null !== self::getHeaderOption('filename', $params)) {
                        $type = 'FILE';
                        $field['size'] = $end - $start;
                        $field['data'] = new FileStreamWrapper($this->body,
                                                               $start, $end);
                    } else {
                        $data = new FileStreamWrapper($this->body,
                                                      $start, $end);
                        $field['data'] = $data->read();
                    }
                    $field['name'] = self::getHeaderOption('name', $params);
                    $field['of_type'] = $type;
                }
                if ($key === 'Content-Type') {
                    $field['type'] = (isset($params->params[0])) ? 
                        $params->params[0] : null;
                }
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Given a parsed header and the option, returns the option or null.
     *
     */
    public static function getHeaderOption($option, $parsed_header)
    {
        foreach ($parsed_header->params as $entry) {
            if (is_array($entry)) {
                foreach ($entry as $key => $value) {
                    if ($option == $key) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }
}

/**
 * A Producer that is sensitive to boundaries.
 *
 * Will happily yield bytes until a boundary is found. Will yield the
 * bytes before the boundary, throw away the boundary bytes
 * themselves, and push the post-boundary bytes back on the stream.
 *
 * The future calls to .next() after locating the boundary will raise a
 * StopIteration exception.
 */
class BoundaryIter
{
    public $stream;
    public $boundary;
    public $rollback;
    public $offset;

    public function __construct(&$stream, $boundary)
    {
        $this->stream = $stream;
        $this->boundary = $boundary;
        $this->rollback = strlen($boundary) + 6;
        $this->sep = '--' . $this->boundary;
        $this->end_sep = '--' . $this->boundary . '--';
    }

    /**
     * Returns the headers and start/end data indexes.
     *
     * It set the offset at the start of the next boundary.
     */
    public function getPart()
    {
        $start = null;
        $end = null;
        $headers = array();
        // We try to read the first boundary, if not available we are
        // at the end of the stream.
        $boundary = fread($this->stream, strlen($this->end_sep));
        if ($boundary == $this->end_sep) {
            // End boundary.
            fseek($this->stream, -2, SEEK_CUR); // Before the --
            return false;
        }
        if ($this->sep != substr($boundary, 0, strlen($this->sep))) {
            // Bad boundary.
            return false;
        }
        // Now, we are at the start of the headers.  We consider a
        // maximum of 1024 bytes in the file header, if more, it
        // failed.
        // Split at \r\n\r\n as it is the end of headers mark
        list($headers, $rest) = explode("\r\n\r\n",
                                        fread($this->stream, 1024),
                                        2);
        $headers = http_parse_headers($headers);
        // We now, know where the data starts and we set ourself there
        fseek($this->stream, -strlen($rest), SEEK_CUR);
        $start = ftell($this->stream);
        // We look for the end of the data chunk
        $old_offset = $start;
        while (!feof($this->stream)) {
            $chunk = fread($this->stream, 8192);
            // Do we have a boundary in the $chunk?
            $pos = strpos($chunk, $this->sep);
            if (false !== $pos) {
                // Yes, this is the end of data chunk,
                fseek($this->stream, -strlen($chunk)+$pos, SEEK_CUR);
                $end = ftell($this->stream) - 2; // remove the \r\n
                break;
            } else {
                // No, we rewind the stream to prevent missed boundary
                // on the limit
                fseek($this->stream, -$this->rollback, SEEK_CUR);
                if ($old_offset == ftell($this->stream)) {
                    // End of the stream as going backward/forward and
                    // stay in place
                    break;
                }
                $old_offset = ftell($this->stream);
            }
        }

        if (null !== $start && null !== $end && $headers) {
            return array($headers, $start, $end);
        }

        return false;
    }
}

/**
 * Easy access to a given file in the POST body.
 *
 * The POST payload is stored in a php://temp/maxmemory:5242880
 * stream. To avoid duplication of data, the wrapper knows where the
 * file starts and ends and can be used to manipulate the data. That
 * is, you can use it to copy the data on the disk or where you want.
 */
class FileStreamWrapper
{
    public $start_offset;
    public $end_offset;
    public $body; /**< File descriptor of the temp storage. */

    public function __construct($body, $start, $end)
    {
        $this->start_offset = $start;
        $this->end_offset = $end;
        $this->body = $body;
    }

    /**
     * Retrieve all the data in one go.
     */
    public function read()
    {
        if ($this->end_offset <= $this->start_offset) {
            return '';
        }
        $current_offset = ftell($this->body);
        fseek($this->body, $this->start_offset, SEEK_SET);
        $len = $this->end_offset - $this->start_offset;
        if (8193 > $len) {
            $data = fread($this->body, $len);
            fseek($this->body, $current_offset, SEEK_SET);

            return $data;
        }
        $nchunks = (int) floor($len / 8192.0);
        $lchunk = $len % 8192;
        $i = 0;
        $content = '';
        while ($i < $nchunks) {
            $content .= fread($this->body, 8192);
            ++$i;
        }
        $content .= fread($this->body, $lchunk);
        fseek($this->body, $current_offset, SEEK_SET);

        return $content;
    }
}
