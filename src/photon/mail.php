<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010-2012 Loic d'Anterroches and contributors.
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
 * Photon mail.
 *
 * Mail handling classes.
 */
namespace photon\mail;

use photon\config\Container as Conf;

/**
 * Generate and send multipart emails.
 *
 * This class is just a small wrapper around the PEAR packages Mail
 * and Mail/mime.
 *
 * Class to easily generate multipart emails. It supports embedded
 * images within the email. It can be used to send a text with 
 * possible attachments. 
 *
 * The encoding of the message is utf-8 by default.
 *
 * Usage example:
 * <code>
 * $email = new EMail('from_email@example.com', 'to_email@example.com', 
 *                   'Subject of the message');
 * $img_id = $email->addAttachment('/var/www/html/img/pic.jpg', 'image/jpg');
 * $email->addTextMessage('Hello world!');
 * $email->addHtmlMessage('<html><body>Hello world!</body></html>');
 * $email->sendMail(); 
 * </code>
 *
 * The configuration parameters are the one for Mail::factory with the
 * 'mail_' prefix not to conflict with the other parameters.
 *
 * @see http://pear.php.net/manual/en/package.mail.mail.factory.php
 *
 * 'mail_backend' - 'mail', 'smtp' or 'sendmail' (default 'mail').
 *
 * List of parameter for the backends:
 *
 *  mail backend
 * --------------
 *
 * If safe mode is disabled, an array with all the 'mail_*' parameters
 * excluding 'mail_backend' will be passed as the fifth argument to
 * the PHP mail() function. The elements will be joined as a
 * space-delimited string.
 *
 *  sendmail backend
 * ------------------
 *
 * 'mail_sendmail_path' - The location of the sendmail program on the
 *                        filesystem. Default is /usr/bin/sendmail
 * 'sendmail_args' - Additional parameters to pass to the
 *                   sendmail. Default is -i
 *
 *  smtp backend
 * --------------
 *
 * 'mail_host' - The server to connect. Default is localhost
 * 'mail_port' - The port to connect. Default is 25
 * 'mail_auth' - Whether or not to use SMTP authentication. Default is
 *               FALSE
 *
 * 'mail_username' - The username to use for SMTP authentication.
 * 'mail_password' - The password to use for SMTP authentication.
 * 'mail_localhost' - The value to give when sending EHLO or
 *                    HELO. Default is localhost
 * 'mail_timeout' - The SMTP connection timeout. Default is NULL (no
 *                  timeout)
 * 'mail_verp' - Whether to use VERP or not. Default is FALSE
 * 'mail_debug' - Whether to enable SMTP debug mode or not. Default is
 *                FALSE
 * 'mail_persist' - Indicates whether or not the SMTP connection
 *                  should persist over multiple calls to the send() 
 *                  method.
 *
 * If you are doing some testing, you should use the smtp backend 
 * together with fakemail: http://www.lastcraft.com/fakemail.php
 */
class EMail
{
    public $headers = array();
    public $message;
    public $encoding = 'utf-8';
    public $to_address = '';

    /**
     * Construct the base email.
     *
     * @param string The email of the sender.
     * @param string The destination email.
     * @param string The subject of the message.
     * @param string Encoding of the message ('UTF-8')
     * @param string End of line type ("\n")
     */
    function __construct($src, $dest, $subject, $encoding='UTF-8', $crlf="\n")
    {
        // Note that the Pluf autoloader will correctly load this PEAR
        // object.
        $this->message = new \Mail_mime($crlf); 
        $this->message->_build_params['html_charset'] = $encoding;
        $this->message->_build_params['text_charset'] = $encoding;
        $this->message->_build_params['head_charset'] = $encoding;        
        $this->message->_build_params['ignore-iconv'] = true;        

        
        $this->to_address = $dest;
        $this->headers = array('From' => $src,
                               'To' => $dest,
                               'Date' => date(DATE_RFC2822),
                               'Subject' => $subject,
                               );
    }

    /**
     * Add the base plain text message to the email.
     *
     * @param string The message
     */
    function addTextMessage($msg)
    {
        $this->message->setTXTBody($msg);
    }

    /**
     * Set the return path for the email.
     *
     * @param string Email
     */
    function setReturnPath($email)
    {
        $this->headers['Return-Path'] = $email;
    }

    /**
     * Add headers to an email.
     *
     * @param array Array of headers
     */
    function addHeaders($hdrs)
    {
        $this->headers = array_merge($this->headers, $hdrs);
    }

    /**
     * Add the alternate HTML message to the email.
     *
     * @param string The HTML message
     */
    function addHtmlMessage($msg)
    {
        $this->message->setHTMLBody($msg);
    }

    /**
     * Add an attachment to the message.
     *
     * The file to attach must be available on disk and you need to
     * provide the mimetype of the attachment manually.
     *
     * @param string Path to the file to be added.
     * @param string Mimetype of the file to be added ('text/plain').
     * @return bool True.
     */
    function addAttachment($file, $ctype='text/plain')
    {
        $this->message->addAttachment($file, $ctype);
    }

    /**
     * Effectively sends the email.
     */
    function sendMail()
    {
        $body = $this->message->get();
        $hdrs = $this->message->headers($this->headers);

        $params = Conf::pf('mail_', true); // strip the prefix 'mail_'
        unset($params['backend']);
        $gmail = new \Mail();
        $mail = $gmail->factory(Conf::f('mail_backend', 'mail'),
                                $params);
        if (Conf::f('send_emails', true)) {
            $mail->send($this->to_address, $hdrs, $body);
        }
    }
}
