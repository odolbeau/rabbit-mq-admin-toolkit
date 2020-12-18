# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [5.1.0]

### Added

* Support PHP 8

## [5.0.1]

### Added

* Support swarrot 4.x

## [5.0.0]

* Removed deprecated `publishMessage` method from VhostManager.
* Add strict type hinting

## [4.1.0]

### Added

* Support symfony 5

### Removed

* Support for symfony < 4.3
* Support for PHP < 7.2

### Changed

* Use swarrot to send messages instead of the VhostManager.
* Use PSR-4 instead of PSR-1

### Fixed

* Now support / in vhost name
* Deal with incorrect / empty queues config

## [4.0.2]

### Added

* Support symfony 4

## [4.0.1]

### Added

* Support swarrot 3.x

## [4.0.0]

If you update you project from 3.x version and if you use retry queues, take care!
You'll need to remove all previous retry queues. You can use this command:

`./rabbit queue:remove vhost -P "#.*retry_[1-3]\$#"`

If you have more than 3 retries, update the regex accordingly.

### Changed

- Improve DL / retry / delay exchanges creation (avoid useless duplicates)
- Change retry queues name from `queue_retry_{attempt}` to `queue_retry_{ttl}`

## [3.2.0]

### Changed

- Bump symfony/console minimum requirement from 2.4 to 2.7

### Added

- Add Symfony3 & php7 compatibility
- Create CHANGELOG
- Start to unit test project

### Fixed

- Deal with empty bindings
- Correct file permissions

## [3.1.2] - 2016-10-14

### Fixed

- Do not deal with `queues` configuration when empty.

## [3.1.1] - 2015-12-29

### Fixed

- Use the appropriate flag for options expecting a value
- Update installation instructions

## [3.1.0] - 2015-12-17

### Fixed

- Remove composer.phar
- Remove the dependency on the symfony/filesystem component
- Add the PHP version requirement
- Fix the branch alias for master
- Clean code

## [3.0.0] - 2015-06-24

## [2.1.0] - 2015-04-16

## [2.0.2] - 2015-04-01

## [2.0.1] - 2015-01-19

## [2.0.0] - 2015-01-15

## [1.0.4] - 2015-01-15

## [1.0.3] - 2014-09-29

## [1.0.2] - 2014-09-12

## [1.0.1] - 2014-09-09

## [1.0.0] - 2014-09-06

[Unreleased]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v5.1.0...HEAD
[5.1.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v5.0.1...v5.1.0
[5.0.1]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v5.0.0...v5.0.1
[5.0.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v4.1.0...v5.0.0
[4.1.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v4.0.2...v4.1.0
[4.0.2]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v4.0.1...v4.0.2
[4.0.1]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v4.0.0...v4.0.1
[4.0.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v3.2.0...v4.0.0
[3.2.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v3.1.2...v3.2.0
[3.1.2]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v3.1.1...v3.1.2
[3.1.1]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v3.1.0...v3.1.1
[3.1.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v2.1.0...v3.0.0
[2.1.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v2.0.2...v2.1.0
[2.0.2]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v1.0.4...v2.0.0
[1.0.4]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/odolbeau/rabbit-mq-admin-toolkit/compare/v1.0.0...v1.0.1
