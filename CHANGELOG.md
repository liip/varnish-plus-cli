Changelog
=========

2.x
===

2.0.0
-----

* Drop support for PHP 7 and PHP 8.0
* Only provide CLI for PHP 8.1 or newer as varnish-plus-cli.phar
* Upgraded the bundled libraries to new versions
* Added client `varnish-controller:deploy` for the new Varnish Controller
  * The Varnish Controller additionally needs the organization parameter
  * The filename must have the `.vcl` extension, it is no longer magically added
  * In addition to flags to the command, this command also takes configuration from environment variables starting with `VARNISH_CONTROLLER_`
* Renamed the VAC deployment command from `vcl:deploy` to `vac:deploy`.
  * In addition to flags to the command, this command also takes configuration from environment variables starting with `VAC_`

1.x
===

1.1.1
-----

* Cleaned up build pipeline. No functionality changes.

1.1.0
-----

* Support PHP 8 with the `varnish-plus-cli.8.0.phar` release artifact
* Drop support for PHP < 7.4
* Fixed deployment

1.0.2
-----

* bumped guzzlehttp/psr to 1.8.5

1.0.1
-----

* fixed handling when verify-tls parameter is not specified

1.0.0
-----

Initial release
