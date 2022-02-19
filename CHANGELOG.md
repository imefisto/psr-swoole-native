# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2022-02-19
### Added
- Calls sendfile when body is a stream with an uri. ([#6](https://github.com/imefisto/psr-swoole-native/pull/6))

## [1.0.1] - 2021-11-24
### Fixed
- Fix case when server listens port 80

## [1.0.0] - 2021-08-26
### Added
- Added support for SameSite attribute on cookies
### Removed
- Dropped support for PHP 7.1

## [0.0.7] - 2021-06-22
### Fixed
- Fixed bugs from immutability
- Fixed withHeader must preserve casing

## [0.0.6] - 2021-06-21
### Added
- Improved Mutation Score Indicator (MSI)

## [0.0.5] - 2021-06-19
### Added
- Added support for body with null ([#4](https://github.com/imefisto/psr-swoole-native/issues/4))

## [0.0.4] - 2021-06-17
### Fixed
- Fixed dependency for php8 support ([#3](https://github.com/imefisto/psr-swoole-native/issues/3))

## [0.0.3] - 2021-06-13
### Fixed
- Fixed status always is 200 ([#2](https://github.com/imefisto/psr-swoole-native/issues/2))

## [0.0.2] - 2020-07-01
### Fixed
- Fix header retrieval

## [0.0.1] - 2020-04-11
### Added 
- Initial release
