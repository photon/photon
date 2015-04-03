
# Photon 0.5 - xxxxxx LANG="en_EN.UTF-8" date -u -R xxxxxxxx

## New Features
- Add worker to stream large payload without filling the RAM (https://github.com/photon/worker-download)

## Changes
- Refactor code about mongrel2 connection, and add support for request the control port
- hnu init create now a very simple project in the current folder
- Answer are now publish only on one server, mulitple PUB sockets are created for out-going message.
  Mongrel2 connection ID are too weak (integer auto-increment), and the handler can previouly publish
  a answer to multiple client request due mongrel2 connection ID colission.
- server_conf configuration key are changed, old style declaration throw an execption.
  Example of new configuration :

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
- Rewrite Runner class to detect when worker are away, ans don't block the handler
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

## Particules
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
