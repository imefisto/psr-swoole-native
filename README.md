# psr-swoole-native
Get PSR request from Swoole request and allow merging a PSR response into the Swoole response.

Install with:

composer require imefisto/psr-swoole-native

See how to use it in the examples folder.

## Version Compatibility

Starting from version 2.0.0, this library requires PHP 8.1 or greater. If you need support for PHP 7.x or PHP 8.0, please:

- Use version 1.x of this library, or
- Feel free to submit PRs for older PHP versions - contributions are welcome!

## Compatibility Matrix

This table contains the list of Swoole and PHP versions that have been tested with the last version of this library and its current status. The tests were run with [imefisto/psr-swoole-native-tests](https://github.com/imefisto/psr-swoole-native-tests).

| Swoole Version | PHP Version | Status |
| ---------------|-------------|--------|
| 4.8.13         | 7.3.33      | ❌     |
| 4.8.13         | 7.4.33      | ❌     |
| 4.8.13         | 8.0.30      | ❌     |
| 4.8.13         | 8.1.30      | ✅     |
| 4.8.13         | 8.2.24      | ✅     |
| 5.1.6          | 8.0.30      | ❌     |
| 5.1.6          | 8.1.31      | ✅     |
| 5.1.6          | 8.2.26      | ✅     |
| 5.1.6          | 8.3.14      | ✅     |
| 6.0.0          | 8.1.31      | ✅     |
| 6.0.0          | 8.2.27      | ✅     |
| 6.0.0          | 8.4.1       | ✅     |
