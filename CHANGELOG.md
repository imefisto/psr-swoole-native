# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.1] - 2026-10-13
### Added
- Example of a framework-less server with FastRoute (`examples/fastroute.php`).

### Fixed
- The Slim 4 example could not run: it was missing an import and loaded the wrong autoloader path.

## [2.2.0] - 2026-10-13
### Added
- `ServerRequest::getParsedBody()` decodes JSON bodies for `application/json` and `+json` content types into arrays. An empty, invalid or scalar JSON body still gives `null`. Form posts and values set with `withParsedBody()` take precedence as before ([#22](https://github.com/imefisto/psr-swoole-native/issues/22))

### Fixed
- `getUploadedFiles()` threw a `TypeError` for multi-file fields such as `f[]` or `f[x][y]`. It now returns the nested tree PSR-7 requires ([#27](https://github.com/imefisto/psr-swoole-native/issues/27))
- `getUploadedFiles()` threw when a form contained an empty file input. Such inputs now give an uploaded file that carries its error code, `UPLOAD_ERR_NO_FILE`.

## [2.1.0] - 2026-10-13
### Added
- `ResponseMerger` accepts an optional `autoChunkSize`, which writes the body in chunks of that size. Use it to stay below Swoole's `output_buffer_size` ([#23](https://github.com/imefisto/psr-swoole-native/issues/23), [#24](https://github.com/imefisto/psr-swoole-native/pull/24))
- Tested on PHP 8.4 and 8.5. CI runs from Swoole 4.8 with PHP 8.1 to Swoole 6.2 with PHP 8.5 ([#25](https://github.com/imefisto/psr-swoole-native/pull/25))

### Changed
- Requires `psr/http-message` `^1.1 || ^2.0`. Version 1.0 never worked, because the library fails to load against its untyped interfaces.
- Requires `psr/http-factory` `^1.1`. Version 1.0.x emits deprecations on PHP 8.4+.
- Pipes are detected with `fstat()` instead of relying on an empty body size.
- Header values with several entries are joined with `, ` instead of `; `.
- `getProtocolVersion()` reports the protocol of the Swoole request, e.g. `2` or `1.0`, instead of always `1.1`.

### Fixed
- Cookie `Max-Age` was ignored. Persistent cookies became session cookies, and `Max-Age=0` could not delete a cookie.
- `withoutHeader()` failed with a `TypeError` when the header was stored with different casing.
- `withHeader()` and `withAddedHeader()` are now case-insensitive, so a header can no longer appear twice.
- `withMethod('PATCH')` threw an exception.
- A request without headers raised an error in `getHeaders()`.
- `getBody()` returned a new stream on each call, so writes to it were lost. `getUploadedFiles()` reopened every file on each call.
- `getAttribute()` returned the default for an attribute explicitly set to `null`.
- Bodies whose PSR-7 stream is not backed by a PHP resource are written instead of being dropped.

## [2.0.2] - 2025-06-13
### Changed
- Updated version on composer.

## [2.0.1] - 2025-06-13
### Fixed
- Remove deprecations for set cookie.

## [2.0.0] - 2024-01-09
### Added
- Added PsrRequestFactory
### Removed
- Dropped support for PHP 7.x and PHP 8.0
- Removed deprecations for compatibility up to PHP 8.4

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
