
# Photon 0.2 - xxxxxx LANG="en_EN.UTF-8" date -u -R xxxxxxxx

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
