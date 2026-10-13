# CLAUDE.md

Guidance for working on `imefisto/psr-swoole-native`.

## What this library is

A bridge between Swoole's HTTP server and PSR-7/PSR-17:

- `Request` / `ServerRequest` implement `RequestInterface` / `ServerRequestInterface` **on top of** a `Swoole\Http\Request`.
- `ResponseMerger::toSwoole()` copies a PSR-7 response (status, headers, cookies, body) into a `Swoole\Http\Response`. It never calls `end()`: the caller does.
- `PsrRequestFactory` builds either request type from a Swoole request plus PSR-17 factories (URI, stream, uploaded file). Any PSR-17 implementation works; tests use `nyholm/psr7`.

## The core design rule: lazy, not hydrated

Other bridges copy everything from the Swoole request into a new PSR-7 object up front. This library does not. Each getter reads the Swoole object (`$swooleRequest->server`, `->header`, `->get`, `->post`, `->cookie`, `->files`, `->rawContent()`) only when it is called. Local state exists only for:

1. values set through `with*()` methods (stored on the clone), and
2. memoization of something expensive or stateful the first time it is built (URI, body stream, uploaded files, protocol version).

Keep it that way:

- Do not read Swoole data in constructors and do not add "hydrate" steps.
- Typed properties are left **uninitialized** (or `null`) to mean "not overridden, fall back to Swoole". Use `??` / `??=` against them. Initialize a property only when an empty default is semantically the same as "not set", as with `$attributes = []`.
- `with*()` must `clone` and change only the clone. Swoole's request object is shared by every clone, so never write to `$this->swooleRequest`.
- Headers: `$headers` stays `null` until the first header mutation. After that it holds the full, modified list. Lookups are case-insensitive through `findHeaderKey()`, and the original casing is preserved.
- Swoole leaves properties `null` when there is no data (no headers, no files, no post). Always use `?? []` when reading them.

## Commands

```bash
composer install
vendor/bin/phpunit                    # or: composer test / make test
vendor/bin/phpstan analyse            # or: composer analyse (level 5, config in phpstan.neon.dist)
vendor/bin/rector process --dry-run   # rector.php targets PHP 8.1
make validate                         # composer validate --strict
```

The test suite **needs the `swoole` extension loaded**, because the test doubles extend the real `Swoole\Http\Request` / `Response` classes. `swoole/ide-helper` provides IDE stubs only.

### Testing across PHP/Swoole versions

CI (`.github/workflows/tests.yml`) runs the suite in `phpswoole/swoole:<swoole>-php<php>` containers, from Swoole 4.8 on PHP 8.1 up to Swoole 6.2 on PHP 8.5. To reproduce one cell locally without touching your `vendor/`:

```bash
docker run --rm -v "$PWD":/src:ro phpswoole/swoole:6.2-php8.5-alpine sh -c \
  'mkdir /w && cd /src && tar --exclude=./vendor --exclude=./.git -cf - . | tar -xf - -C /w && cd /w \
   && composer update -q && vendor/bin/phpunit'
```

`phpunit.xml` fails on PHP deprecations and warnings. A new PHP version that deprecates something we use shows up as a failing test, not as noise.

The sibling repo [psr-swoole-native-tests](https://github.com/imefisto/psr-swoole-native-tests) runs a real Swoole server against the library. The README compatibility matrix comes from there.

## Tests

- PHPUnit 10.5, with doc-comment metadata (`@test`, and `@covers` on every test class, which is required by `requireCoverageMetadata`). Match the existing style rather than switching to attributes piecemeal.
- `MockedResponseFactory::create()` returns `MockedResponse` (Swoole ≥ 5, typed `cookie()` signature) or `LegacyMockedResponse` (Swoole 4.8). Both record calls: use `countCalls('write')` and `call('cookie', $index)`.
- `SwooleRequestBuilderTrait::buildSwooleRequest()` builds a `MockedRequest` whose `rawContent()` is derived from `post`.
- Prefer real `nyholm/psr7` responses over PHPUnit mocks for new `ResponseMerger` tests (see `ResponseMergerHeadersTest`). Be careful with mocked `StreamInterface`: auto-generated `eof()` returns `false`, so read loops never end.
- Avoid timing-dependent assertions. For time-based values such as cookie expiry from `Max-Age`, capture `time()` before and after the call and assert a range.

## Compatibility constraints

- PHP `^8.1`. Promoted and `readonly` properties, `str_contains`, and `??=` are fine. Nothing newer than 8.1 in `src/`.
- `psr/http-message` `^1.1 || ^2.0`. 1.0 is **not** supported: our methods declare parameter types (`string $name`), which are incompatible with 1.0's untyped interface and cause a fatal error at class load. Return types follow 2.0, which is covariant-compatible with 1.1.
- `psr/http-factory` `^1.1`. 1.0.x triggers "implicitly nullable parameter" deprecations on PHP 8.4+.
- Swoole 4.8 → 6.x. `Swoole\Http\Response::cookie()` changed signature in 5.0, and the code must work with both.
- `src/` has no `declare(strict_types=1)`. Adding it changes behavior for callers, for example `rawContent()` returning `false`, so don't add it casually.

## ResponseMerger behavior worth knowing

- Multi-value headers are joined with `", "`. `Set-Cookie` is removed from the header list and sent through `Swoole\Http\Response::cookie()` instead, after parsing with `dflydev/fig-cookies`.
- Cookie expiry: `Max-Age` wins over `Expires`. `Max-Age<=0` is sent as expiry `1`, because Swoole treats `0` as a session cookie. Cookies without `SameSite` are sent as `SameSite=lax`. Attributes that Swoole's `cookie()` does not accept, such as `Partitioned`, are dropped.
- Body: a plain-file stream is sent with `sendfile()`, a pipe is streamed in `BUFFER_SIZE` chunks, and anything else is written with `write()`.

## Known gaps / open issues

- #22: `getParsedBody()` only returns Swoole's `post`, so JSON bodies give `null`.
- #23: bodies larger than Swoole's `output_buffer_size` fail. PR #24 adds an opt-in `autoChunkSize` to `ResponseMerger`.
- `getUploadedFiles()` assumes a flat `files` array. Multi-file fields (`name="f[]"`) are not normalized into a nested tree yet.
- `getUri()` has no scheme. It appends `:80` when the `Host` header has no port, and takes user-info from a `Basic` `Authorization` header.
- `getServerParams()` merges the worker's `$_SERVER` with the Swoole `server` array (keys upper-cased).

## Releases

- `CHANGELOG.md` follows Keep a Changelog. **`make release` tags `v<first "## [x.y.z]" heading>`**, so don't leave an `## [Unreleased]` heading on top when releasing, or the tag will be `vUnreleased`.
- `make release` runs `validate` and `test`, then tags and pushes. Update the changelog and the README compatibility matrix first.
