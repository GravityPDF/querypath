# QueryPath Examples

Runnable examples covering the QueryPath API. Each one lives in its own directory
with an `index.php` you can run directly:

```bash
composer install
php examples/hello-world/index.php
```

Most of them print HTML, so they also work if you point a web server at this
directory:

```bash
php -S localhost:8000 -t examples
```

**[Read the QuickStart guide](quickstart-guide.md)** — it introduces the library
and indexes every example in this directory with a note on what each one covers.

New to QueryPath? Start with [hello-world](hello-world/index.php).

## Checking they all still work

The offline examples are part of the unit test suite, so they run on every pull
request across all supported PHP versions. To run the whole set — including the
ones that call third-party services — by hand:

```bash
composer run test:examples           # all of them
composer run test:examples:network   # only the ones that need a remote service
```

An example passes if it exits cleanly, emits no PHP diagnostic, and produces a
reasonable amount of output.
