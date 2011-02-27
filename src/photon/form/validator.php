<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
# Copyright (C) 2010-2011 Loic d'Anterroches and contributors.
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
 * Collection of validator classes.
 *
 * The classes are grouping together corresponding "themes". A
 * validator is a static method with this signature:
 *
 * bool Validator::whatever($to_be_validated, $options=array());
 */
namespace photon\form\validator;

/**
 * Internet related validation.
 *
 * Email, URL, etc.
 */
class Net
{
    /**
     * Validation of an email address.
     *
     * Comments stripped, check the source for the full discussion,
     * tests, etc.
     *
     * @author Cal Henderson <cal@iamcal.com>
     * @license CC Attribution-ShareAlike 2.5
     * @source http://code.iamcal.com/php/rfc822/
     */
    public static function email($email, $options=array())
    {
        $defaults = array(
                          // very strict by default
                          'allow_comments' => false,
                          'public_internet' => true, 
                          );
        $opts = array();
        foreach ($defaults as $k => $v) {
            $opts[$k] = isset($options[$k]) ? $options[$k] : $v;
        }
        $options = $opts;
        $no_ws_ctl = "[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x7f]";
        $alpha = "[\\x41-\\x5a\\x61-\\x7a]";
        $digit = "[\\x30-\\x39]";
        $cr = "\\x0d";
        $lf = "\\x0a";
        $crlf = "(?:$cr$lf)";
        $obs_char = "[\\x00-\\x09\\x0b\\x0c\\x0e-\\x7f]";
        $obs_text = "(?:$lf*$cr*(?:$obs_char$lf*$cr*)*)";
        $text = "(?:[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f]|$obs_text)";
        $text = "(?:$lf*$cr*$obs_char$lf*$cr*)";
        $obs_qp = "(?:\\x5c[\\x00-\\x7f])";
        $quoted_pair = "(?:\\x5c$text|$obs_qp)";
        $wsp = "[\\x20\\x09]";
        $obs_fws = "(?:$wsp+(?:$crlf$wsp+)*)";
        $fws = "(?:(?:(?:$wsp*$crlf)?$wsp+)|$obs_fws)";
        $ctext = "(?:$no_ws_ctl|[\\x21-\\x27\\x2A-\\x5b\\x5d-\\x7e])";
        $ccontent = "(?:$ctext|$quoted_pair)";
        $comment = "(?:\\x28(?:$fws?$ccontent)*$fws?\\x29)";
        $cfws = "(?:(?:$fws?$comment)*(?:$fws?$comment|$fws))";
        $outer_ccontent_dull = "(?:$fws?$ctext|$quoted_pair)";
        $outer_ccontent_nest = "(?:$fws?$comment)";
        $outer_comment = "(?:\\x28$outer_ccontent_dull*(?:$outer_ccontent_nest$outer_ccontent_dull*)+$fws?\\x29)";
        $atext = "(?:$alpha|$digit|[\\x21\\x23-\\x27\\x2a\\x2b\\x2d\\x2f\\x3d\\x3f\\x5e\\x5f\\x60\\x7b-\\x7e])";
        $atom = "(?:$cfws?(?:$atext)+$cfws?)";
        $qtext = "(?:$no_ws_ctl|[\\x21\\x23-\\x5b\\x5d-\\x7e])";
        $qcontent = "(?:$qtext|$quoted_pair)";
        $quoted_string = "(?:$cfws?\\x22(?:$fws?$qcontent)*$fws?\\x22$cfws?)";
        $quoted_string = "(?:$cfws?\\x22(?:$fws?$qcontent)+$fws?\\x22$cfws?)";
        $word = "(?:$atom|$quoted_string)";
        $obs_local_part = "(?:$word(?:\\x2e$word)*)";
        $obs_domain = "(?:$atom(?:\\x2e$atom)*)";
        $dot_atom_text = "(?:$atext+(?:\\x2e$atext+)*)";
        $dot_atom = "(?:$cfws?$dot_atom_text$cfws?)";
        $dtext = "(?:$no_ws_ctl|[\\x21-\\x5a\\x5e-\\x7e])";
        $dcontent = "(?:$dtext|$quoted_pair)";
        $domain_literal = "(?:$cfws?\\x5b(?:$fws?$dcontent)*$fws?\\x5d$cfws?)";
        $local_part = "(($dot_atom)|($quoted_string)|($obs_local_part))";
        $domain = "(($dot_atom)|($domain_literal)|($obs_domain))";
        $addr_spec = "$local_part\\x40$domain";

        $email_strip_comments = function($comment, $email, $replace='') {
            while (1) {
                $new = preg_replace("!$comment!", $replace, $email);
                if (strlen($new) == strlen($email)) {
                    return $email;
                }
                $email = $new;
            }
        };

        if (strlen($email) > 254) return false;

        if ($options['allow_comments']) {
            $email = $email_strip_comments($outer_comment, $email, "(x)");
        }
        if (!preg_match("!^$addr_spec$!", $email, $m)) {
            return false;
        }

        $bits = array(
                      'local' => isset($m[1]) ? $m[1] : '',
                      'local-atom' => isset($m[2]) ? $m[2] : '',
                      'local-quoted' => isset($m[3]) ? $m[3] : '',
                      'local-obs' => isset($m[4]) ? $m[4] : '',
                      'domain' => isset($m[5]) ? $m[5] : '',
                      'domain-atom' => isset($m[6]) ? $m[6] : '',
                      'domain-literal' => isset($m[7]) ? $m[7] : '',
                      'domain-obs' => isset($m[8]) ? $m[8] : '',
                      );

        if ($options['allow_comments']) {
            $bits['local'] = $email_strip_comments($comment, $bits['local']);
            $bits['domain'] = $email_strip_comments($comment, $bits['domain']);
        }

        if (strlen($bits['local']) > 64) return false;
        if (strlen($bits['domain']) > 255) return false;

        if (strlen($bits['domain-literal'])) {

            $Snum = "(\d{1,3})";
            $IPv4_address_literal = "$Snum\.$Snum\.$Snum\.$Snum";

            $IPv6_hex = "(?:[0-9a-fA-F]{1,4})";

            $IPv6_full = "IPv6\:$IPv6_hex(?:\:$IPv6_hex){7}";

            $IPv6_comp_part = "(?:$IPv6_hex(?:\:$IPv6_hex){0,7})?";
            $IPv6_comp = "IPv6\:($IPv6_comp_part\:\:$IPv6_comp_part)";

            $IPv6v4_full = "IPv6\:$IPv6_hex(?:\:$IPv6_hex){5}\:$IPv4_address_literal";

            $IPv6v4_comp_part = "$IPv6_hex(?:\:$IPv6_hex){0,5}";
            $IPv6v4_comp = "IPv6\:((?:$IPv6v4_comp_part)?\:\:(?:$IPv6v4_comp_part\:)?)$IPv4_address_literal";

            if (preg_match("!^\[$IPv4_address_literal\]$!", $bits['domain'], $m)) {

                if (intval($m[1]) > 255) return false;
                if (intval($m[2]) > 255) return false;
                if (intval($m[3]) > 255) return false;
                if (intval($m[4]) > 255) return false;

            } else {
                while (1) {

                    if (preg_match("!^\[$IPv6_full\]$!", $bits['domain'])) {
                        break;
                    }

                    if (preg_match("!^\[$IPv6_comp\]$!", $bits['domain'], $m)) {
                        list($a, $b) = explode('::', $m[1]);
                        $folded = (strlen($a) && strlen($b)) ? "$a:$b" : "$a$b";
                        $groups = explode(':', $folded);
                        if (count($groups) > 7) return false;
                        break;
                    }

                    if (preg_match("!^\[$IPv6v4_full\]$!", $bits['domain'], $m)) {

                        if (intval($m[1]) > 255) return false;
                        if (intval($m[2]) > 255) return false;
                        if (intval($m[3]) > 255) return false;
                        if (intval($m[4]) > 255) return false;
                        break;
                    }

                    if (preg_match("!^\[$IPv6v4_comp\]$!", $bits['domain'], $m)) {
                        list($a, $b) = explode('::', $m[1]);
                        $b = substr($b, 0, -1); # remove the trailing colon before the IPv4 address
                        $folded = (strlen($a) && strlen($b)) ? "$a:$b" : "$a$b";
                        $groups = explode(':', $folded);
                        if (count($groups) > 5) return false;
                        break;
                    }

                    return false;
                }
            }
        } else {


            $labels = explode('.', $bits['domain']);


            if ($options['public_internet']) {
                if (count($labels) == 1) return false;
            }




            foreach ($labels as $label) {

                if (strlen($label) > 63) return false;
                if (substr($label, 0, 1) == '-') return false;
                if (substr($label, -1) == '-') return false;
            }



            if ($options['public_internet']) {
                if (preg_match('!^[0-9]+$!', array_pop($labels))) return false;
            }
        }


        return true;
    }
}



