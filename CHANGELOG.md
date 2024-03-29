# Change Log


All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0] - 2024-03-05

- Removed builds for abandoned guzzle5 and guzzle6 adapters
- Support PSR-17 factories

## [3.0.1] - 2021-03-21

- Allow to be installed with Guzzle PSR-7 2.0

## [3.0.0] - 2020-10-01

- Only support HTTPlug 2.0 and PSR-18
- HTTPlug 2.0 is now optional (only require it if you need to test async)
- HttpClientTest now relies only on PSR-18 (no need for HTTPlug)
- Added support for PHPUnit 8 and 9

## [2.0.1] - 2018-12-27

- Use `__toString()` instead of `getContents()`

## [2.0.0] - 2018-11-03


## [1.0.0] - 2018-11-03

### Changed

- Don't test `TRACE` requests with request bodies, as they're not valid requests according to the [RFC](https://tools.ietf.org/html/rfc7231#section-4.3.8).
- Make the test suite PHPUnit 6 compatible


## [0.6.2] - 2017-07-10


## [0.6.1] - 2017-07-10


## [0.6.0] - 2017-05-29


## [0.5.1] - 2016-07-18

### Fixed

- Old name


## [0.5.0] - 2016-07-18

### Changed

- Renamed to client-integration-tests
- Improved pacakge


## [0.4.0] - 2016-03-02

### Removed

- Discovery dependency


## [0.3.1] - 2016-02-11

### Changed

- Updated message dependency


## [0.3.0] - 2016-01-21

### Changed

- Updated discovery dependency


## [0.2.0] - 2016-01-13

### Changed

- Updated to latest HTTPlug version
- Updated package files


## 0.1.0 - 2015-06-12

### Added

- Initial release


[Unreleased]: https://github.com/php-http/client-integration-tests/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/php-http/client-integration-tests/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/php-http/client-integration-tests/compare/v0.6.2...v1.0.0
[0.6.2]: https://github.com/php-http/client-integration-tests/compare/v0.6.1...v0.6.2
[0.6.1]: https://github.com/php-http/client-integration-tests/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/php-http/client-integration-tests/compare/v0.5.1...v0.6.0
[0.5.1]: https://github.com/php-http/client-integration-tests/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/php-http/client-integration-tests/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/php-http/client-integration-tests/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/php-http/client-integration-tests/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/php-http/client-integration-tests/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/php-http/client-integration-tests/compare/v0.1.0...v0.2.0
