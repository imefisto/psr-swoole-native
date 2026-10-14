# psr-swoole-native

Get PSR request from Swoole request and allow merging a PSR response into the Swoole response.

Install with:

composer require imefisto/psr-swoole-native

See how to use it in the examples folder.

## Version Compatibility

Starting from version 2.0.0, this library requires PHP 8.1 or greater. If you need support for PHP 7.x or PHP 8.0, please:

* Use version 1.x of this library, or
* Feel free to submit PRs for older PHP versions - contributions are welcome!

## Compatibility Matrix

These are the Swoole and PHP versions that version 2.2.0 of this library was tested with. The test suite runs on each of them in [CI](.github/workflows/tests.yml), using the official `phpswoole/swoole` images.

| Swoole Version | PHP Version | Status |
| -------------- | ----------- | ------ |
| 4.8.13         | 8.1.30      | ✅      |
| 5.1.8          | 8.2.29      | ✅      |
| 5.1.8          | 8.3.26      | ✅      |
| 6.0.2          | 8.4.14      | ✅      |
| 6.2.3          | 8.5.10      | ✅      |

PHP 7.x and 8.0 are not supported since 2.0.0 (see above).

## 🛠 Maintainer Guide: Release Workflow

If you're preparing a new release, you can use the provided `Makefile` to streamline the process.

### Common Commands

* ✅ **Validate the composer.json:**

  ```bash
  make validate
  ```

* ✅ **Run tests:**

  ```bash
  make test
  ```

* ✅ **Update dependencies:**

  ```bash
  make update
  ```

* 🚀 **Create a release:**

  ```bash
  make release
  ```

  This will:

  * Validate `composer.json`
  * Run the test suite
  * Create a Git tag based on the latest version in `CHANGELOG.md`
  * Push the tag to the repository

If you want to set the version manually:

```bash
make release VERSION=1.2.3
```

> 💡 **Tip:** Remember to update `CHANGELOG.md` with the new version before running `make release`.
