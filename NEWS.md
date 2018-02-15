# Photon 2.1.1 - Wed, 10 Jan 2018 22:27:00 +0000

## Changes
- Always close the mongrel2 connection after a HTTP/1.0 request
- Respect the connection header in HTTP/1.1 request

# Photon 2.1.0 - Wed, 10 Jan 2018 11:42:00 +0000

## New Features
- Rename photon\db\MongoDB (pecl mongodb)
- Update \photon\core\Dispatcher to be more reusable (i.e second dispatch layer in apps)
- Add some new HTTP response: 406, 408, 409, 410, 411, 413, 414, 415, 417, 426
- Update the Security middleware to support : Content-Security-Policy, X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

## Changes
- Rename photon\db\MongoDB to photon\db\Mongo (pecl mongo)
- Allow to serve from phar a file starting by /
- Allow a database connection to be not cached
- Various fix

# Photon 2.0.0 - Thu, 06 Apr 2017 14:41:33 +0000

## New Features
- Add support for PHP 7.0 and 7.1
- Use PO parser from https://github.com/raulferras/PHP-po-parser  
  Source code is duplicated because the last version is not tagged yet.
- A translation middleware to setup language from : session, cookie, or http headers.  
  Enable it by adding `\photon\middleware\translation` in your middleware configuration.
- Allow custom pharstub from user
- Add automatic decode of json body, in $request->JSON

## Changes
- Tests are executed with the last version of PHPUnit 5.x, the 6.x release require to drop support of PHP 5.6
- The class \photon\form\field\Float, the name is reserved in PHP 7.x so we rename it FloatNumber
- HSTS force https redirect, while ssl_redirect is not set
- HSTS redirect to the same path
- All photon tests case must extends \photon\test\TestCase
- Fix \photon\translation\Translation::getAcceptedLanguage to works with PHP 5.x and 7.x
- The class \photon\crypto\Crypt do not use mcrypt anymore, we use openssl configured with AES-256-CBC.  
  Previous data encoded can not be decoded with the new class.  
  Usage of the mcrypt ext (deprecated in PHP 7.1, will be removed in PHP 7.2).
- Cleanup function outside classes call only one time in a class

## Removes
- Remove of the class \photon\crypto\Hash.  
  The class photon\auth\ConfigBackend use now built-in php function : password_hash and password_verify
- Remove of the class \photon\commandline\Parser.  
  We recommend to use a CLI Parser from a dedicated project like nategood/commando
- Cleanup PHP Pear stuff
- The project init command. The template is still available in a dedicated repo (photon/project-template)

# Photon 1.1.0 - Wed, 08 Jun 2016 09:29:33 +0000

## Changes
- Add form field EUI-48
- Add form field EUI-64

# Photon 1.0.2 - Thu, 18 Feb 2016 10:52:35 +0000

## Changes
- Update PEAR Console CommandLine to version 1.2.1
- Add warning when running with xdebug enable
- Add some new HTTP code (102, 208, 226, 418, 426, 428, 429, 431, 508, 510, 511)

## Bugfixes
- Fix travis setup

# Photon 1.0.1 - Fri, 11 Dec 2015 07:53:12 +0000

## Changes
- Update PEAR Console CommandLine to version 1.2.1

# Photon 1.0.0 - Sat, 05 Dec 2015 10:55:46 +000

## New Features
- Add form field to select a timezone

## Changes
- Add HTTP Code 308 : Permanent Redirect
- Remove dead code about broker
- Add a function "checkPHP" in \photon\Base\manager to log warning and recommendation about the php.ini content.
- Add PHP 5.6 in Travis tests
- Remove hardcoded ignore of "config*" file during phar packaging, use pharignore to filter them.
- Do not force a explicit alias for generated phar

## Bugfixes
- Middleware Security, do not process false response
- Middleware Session, ensure session exists before use it.
- Remove PHP notice if the view do not have name

# Photon 0.5 - Fri, 03 Apr 2015 13:34:56 +0000

## New Features
- Add worker to stream large payload without filling the RAM (https://github.com/photon/worker-download)

## Changes
- Refactor code about mongrel2 connection, and add support for request the control port
- hnu init create now a very simple project in the current folder
- Answer are now publish only on one server, mulitple PUB sockets are created for out-going message.
  Mongrel2 connection ID are too weak (integer auto-increment), and the handler can previouly publish
  a answer to multiple client request due mongrel2 connection ID colission.
- server_conf configuration key are changed, old style declaration throw an execption.  
  Example of new configuration

          'server_conf' => array(
              array(
                  'pub_addr'  => 'tcp://127.0.0.1:9014',
                  'pull_addr' => 'tcp://127.0.0.1:9015',
                  'ctrl_addr' => 'tcp://127.0.0.1:9999',
              ),
              array(
                  'pub_addr'  => 'tcp://127.0.0.1:8014',
                  'pull_addr' => 'tcp://127.0.0.1:8015',
                  'ctrl_addr' => 'tcp://127.0.0.1:9998',
              ),
          ),


# Photon 0.4 - Sat, 28 Mar 2015 12:00:00 +0000

## New Features
- Add security middleware (SSL redirect, HTTP Strict Transport Security, Public Key Pinning)


# Photon 0.3 - Thu, 08 Jan 2015 16:00:00 +0000

## Changes
- Handle mongrel2 disconnect message earlier to avoid Middleware execution.  
  An event is emitted.
- Rewrite Runner class to detect when worker are away, and don't block the handler
- Increase code coverage of unit tests
- Source code are mirrored on Github under the organizsation "photon"
- Units tests are executed by Travis CI for all branch, and pull request on Github
- Add phpunit configuration file to avoid to use the "hnu selftest" command
- Allow multiple .pharignore in subfolder, rules can be local to package installed by composer

## New Features
- Add hnu command "show-config" to show the config file on the standard output. Usefull to show phar packaged configuration
- Set photon version to the current commit id, when create phar from a photon version not installed by PEAR
- Add template tag to emit a event
- Check each generated template for syntax error.  
  Phar can't be build if a template have a syntax error.
- Add other common HTTP answers:
    - 201 Created
    - 202 Accepted
    - 204 No Content
    - 400 Bad Request
    - 501 Not Implemented
    - 503 Service Unavailable
- Add WebDav MultiStatus answer (HTTP code 207)
- Add composer support, and publish it on packagist under "photon/photon"
- Add support of PECL HTTP serie 2.x
- Add doxygen configuration file to produce some technicals documentations
- Add Database helper to create Memcached client

## Bugfixes
- Issue 845: there is no reference sign on a function call
- Ensure all photon source are compressed in the PHAR
- Don't add .pharignore in the PHAR, related to PHP bug https://bugs.php.net/bug.php?id=64931

## Particules
- Add MongoDB session storage (https://github.com/photon/session-mongodb)
- Add Memcached session storage (https://github.com/photon/session-memcached)
- Add Markdown support for template (https://github.com/photon/template-markdown)


# Photon 0.2 - Mon, 11 Apr 2011 13:10:23 +0000

## Changes
- Added underscore as authorized file path (Asset view)
- Renamed the installed_apps key to tested_components

## New Features
- Add form fields and validators: IPv4, IPv6, MacAddress
- Add support of PostgreSQL with PDO
- Add support of closures for the clean_FIELD methods (Form)
- Add the support of multiple front-end Mongrel2 servers
- Add some templates tag and modifier: getmsgs, date
- Allow register of custom tag & modifier from config or by event
- Add a CSRF middleware
- Add startup and shutdown callbacks.
- Add mail support with auto-configuration from config
- Add a event sytem
- Add some common HTTP answer: 303, 405
- Add support of PHAR packing
- Add hook before generate an error 500
- Add hnu pot command

## Bugfixes
- Avoid recompression when not needed. (Gz middleware)
- Do not try to load empty cookies
- Fix some API changes of ZMQ
- Avoid send Content-Length if the answer is chunked encoded
- Form not parsed if content-type have a charset field


# Photon 0.1 - Fri, 11 Mar 2011 17:35:58 UTC

First public release (Beta)


# Photon 0.0.1 - Fri, 17 Feb 2011 12:00:00 UTC

First release
