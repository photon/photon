<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010-2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as
# published by the Free Software Foundation in version 2.1.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public
# License along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


/**
 * Command line utility functions.
 */
namespace photon\commandline;

class Exception extends \Exception {}

/**
 * A lexical analyzer class for simple shell-like syntaxes.
 *
 * Very simple, the goal is just to be able to tokenize in a PHP
 * compatible way a command string to then pass it to a PEAR library
 * like Console_CommandLine.
 */
class Parser
{
    public $cmd = ''; /**< Original command */
    public $pushback = array(); /**< Elements in the stack */
    public $stream = array(); /**< Multibytes splitted characters in $cmd */
    public $state = '';
    public $token = ''; /**< Current token */
    public $whitespace = array(' ', "\t");
    public $escape = '\\';
    public $quotes = '\'"';
    public $escapedquotes = '"';

    public function __construct($cmd)
    {
        $this->cmd = $cmd;
        $this->stream = preg_split('/(?<!^)(?!$)/u', $cmd);
    }

    /**
     * Returns an array of tokens.
     */
    public function parse()
    {
        $out = array();
        while ('' !== ($token = $this->readToken())) {
            $out[] = $token;
        }

        return $out;
    }

    public function state_single($char, $quoted=false)
    {
        $nstate = '\'';
        if ($quoted) {
            $quoted = false;

            return array($nstate, $char, $quoted);
        }
        if ($char === '\\') {
            $char = '';
            $quoted = true;
        } elseif ($char === '\'') {
            $nstate = 'a';
            $char = '';
        }

        return array($nstate, $char, $quoted);
    }

    public function state_double($char, $quoted=false)
    {
        $nstate = '"';
        if ($quoted) {
            $quoted = false;

            return array($nstate, $char, $quoted);
        }
        if ($char === '\\') {
            $char = '';
            $quoted = true;
        } elseif ($char === '"') {
            $nstate = 'a';
            $char = '';
        }

        return array($nstate, $char, $quoted);
    }

    public function state_space($char, $quoted=false)
    {
        $quoted = false;
        if (in_array($char, $this->whitespace)) {
            $char = '';
            $nstate = ' ';
        } elseif ($char === '\\') {
            $char = '';
            $quoted = true;
            $nstate = 'a';
        } elseif ($char === '"') {
            $nstate = '"';
            $char = '';
        } elseif ($char === '\'') {
            $nstate = '\'';
            $char = '';
        } else {
            $nstate = 'a';
        }

        return array($nstate, $char, $quoted);
    }

    public function state_text($char, $quoted=false)
    {
        $nstate = 'a';
        if ($quoted) {
            $quoted = false;

            return array($nstate, $char, $quoted);
        }
        if ($char === '"') {
            $nstate = '"';
            $char = '';
        } elseif ($char === '\'') {
            $nstate = '\'';
            $char = '';
        } elseif (in_array($char, $this->whitespace)) {
            $nstate = ' ';
            $char = '';
        } elseif ($char === '\\') {
            $char = '';
            $quoted = true;
        }

        return array($nstate, $char, $quoted);
    }

    public function readToken()
    {
        $quoted = false;
        $token = '';
        // states:
        // A: quoted with '     = /'/
        // B: quoted with "     = /"/
        // C: in white space    = / /
        // D: in text           = /a/
        // states transitions:
        // A -> D
        // B -> D
        // C -> A,B,D
        // D -> A,B,C
        // Token pop
        // D -> C or end of string in D
        // First state is C
        $state = ' '; // in space
        $scalls = array(' ' => 'state_space',
                        '"' => 'state_double',
                        '\'' => 'state_single',
                        'a' => 'state_text');

        while (true) {
            $next_char = array_shift($this->stream);
            if ($next_char === null) {
                break;
            }
            list($nstate, $char, $quoted) =
                call_user_func(array($this, $scalls[$state]),
                               $next_char, $quoted);
            if ($state === 'a' && $nstate === ' ') {

                return $token;
            }
            $state = $nstate;
            $token .= $char;
        }
        if ($state !== 'a' && strlen($token)) {
            throw new Exception('Premature end of command.');
        }
        return $token;
    }
}