# Laravel app regression tests

The PHPUnit suite of the bitlitas.lt Laravel app (Laravel 13.40.0 on PHP
8.4.27). It runs in one throwaway container with no network, built from
the production image. Inside that container it starts three things:

- MySQL 5.7.44, the server of the `bitlitas-mysql` service, with the schema
  the migrations build;
- a stand-in for the full node;
- Apache with mod_php on port 8080, serving the app the way production does.

Then it runs PHPUnit 13.4.

## Running

From `web/bitlitas-web/`:

```sh
./runTests.sh                            # every suite
./runTests.sh --testsuite Server         # one suite: Platform, Feature or Server
./runTests.sh --filter NetworkSyncTest   # one class, or one test by name
./runTests.sh tests/Feature/Sync         # one folder or file
```

Each run does three things:

1. Builds the production `Dockerfile` from the working tree as
   `bitlitas-web-check`, so the suite tests what would ship. The running
   `bitlitas-web` image is never touched.
2. Adds `Dockerfile.tests` on top, as `bitlitas-web-tests`.
3. Starts the result with `--network none`, shaped like the `bitlitas-web`
   container of `docker-compose.yml`:
   - user 1000 on a read-only root filesystem;
   - the same tmpfs mounts: `/tmp`, and `storage/framework` with its
     `cache`, `sessions` and `views` folders;
   - `storage/logs` as a mount of its own: a tmpfs owned by 1000 here, a
     bind mount in production;
   - MySQL's data and socket on tmpfs mounts as well;
   - at most 2 GB and 2 CPUs.

Every argument goes to PHPUnit. When PHPUnit fails, `container/run.sh` also
prints what the server side logged:

- Apache's error log;
- the latest Laravel log entries;
- MySQL's errors;
- the stand-in node's PHP errors.

The host needs only docker. PHP, MySQL and the test tools live in the image.
Building the images needs the network, because composer installs the app's
vendor and PHPUnit. The test run itself never does.

## Layers

| Suite | Folder | What it checks | How |
|---|---|---|---|
| Platform | `Platform/` | The image as shipped: PHP, its extensions and settings; no dev packages and only the locked versions; the configuration and the `.env` the Dockerfile writes; the MySQL server and the schema; Apache's configuration — the front controller, the trusted proxies, the logs — and the user it runs as; what is writable and what is not; the templates and the files they link | Reads the container's files and processes, PHP's settings, and the database |
| Feature | `Feature/` | The home page and its figures, the sync from the node (`NetworkSync`, `GET /blokai`, `php artisan bitlitas:sync`), the node client, the route table | Laravel's in-process HTTP and console kernels; each test runs inside a transaction that is rolled back. The node is `FakeDaemon` in memory, or the stand-in over HTTP for `DaemonClientTest` |
| Server | `Server/` | What happens in front of the app: Apache's static files and front controller, the visitor's address behind any chain of proxies, what is never served, errors in production mode, the sync as production runs it | Requests to the real Apache on `127.0.0.1:8080`, carrying the headers the endpoint adds; rows are committed, and both tables emptied around every test |

## Layout

| Path | What lives there |
|---|---|
| `TestCase.php` | Base of every test: the World, `fakeDaemon`, `console`, `logWrittenBy`, `whileSyncLockIsHeld` |
| `FeatureTestCase.php` | `TestCase` plus `DatabaseTransactions` |
| `ServerTestCase.php` | `TestCase` plus a `ServerClient`. Every test gets empty tables, a fresh stand-in node and a fresh throttle |
| `CreatesApplication.php` | The fresh app every test gets, built from `bootstrap/app.php` |
| `bootstrap.php` | PHPUnit's bootstrap: the app's autoloader, the test tools' one from `/opt/test-tools`, and the `Tests` namespace |
| `Support/World.php` | Builders for the rows: what `seedDB.sh` leaves, figures, single blocks, a run of the chain |
| `Support/Chain.php` | The stand-in chain, built like v0.11's: the genesis reward, the emission curve, a block a minute from 2018-01-15 |
| `Support/NodeEmulator.php` | bitlitasd v0.11's JSON-RPC answers, steered by one state array (busy, capped, damaged, failing, growing, …) |
| `Support/FakeDaemon.php` | The app's own `DaemonClient` with a `NodeEmulator` in place of curl |
| `Support/FakeNodeServer.php` | The stand-in node over HTTP: its state file and request log |
| `Support/ServerClient.php`, `ServerResponse.php` | curl for the real Apache, and what it answered |
| `Support/KnownDefects.php` | `pinDefect`, see Rules |
| `container/` | `run.sh`, the image's entrypoint; `my.cnf` for MySQL; `fake-node.php`, the stand-in node's router for `php -S`; and the composer files of the test tools |
| `Platform/`, `Feature/<Area>/`, `Server/` | The suites |

Five files outside `tests/` belong to the suite:

- `runTests.sh` runs everything.
- `Dockerfile.tests` builds the test image.
- `Dockerfile.tests.dockerignore` lets only `phpunit.xml` and `tests/` into
  that build.
- `phpunit.xml` names the three suites and their bootstrap.
- `.dockerignore` keeps all of them out of the production image.

## Rules

- **Offline.** Nothing reaches the network. The full node is a stand-in,
  steered per test: in memory (`FakeDaemon`) or on `127.0.0.1:18081`
  (`container/fake-node.php`), where `BITLITAS_DAEMON_URL` points. The
  page's CDN scripts, fonts and images are outside the suite; only the
  site's own files are checked.
- **Production-shaped.**
  - Apache runs with the environment `docker-compose.yml` gives
    `bitlitas-web` (`APP_DEBUG`, `APP_KEY`, `DB_*`, `BITLITAS_DAEMON_URL`),
    with test values, and with the `.env` the Dockerfile wrote.
  - The image carries no app key. In production `APP_KEY` comes from
    `LARAVEL_APP_KEY` in the stack's `.env`, which `runUpdateThisStack.sh`
    makes on its first run and keeps; `container/run.sh` exports a test
    key of its own.
  - The app's `vendor/` stays `--no-dev`. PHPUnit lives apart, in
    `/opt/test-tools`.
  - Everything runs as user 1000, Apache's user — MySQL, the stand-in
    node, Apache, PHPUnit — so every file a test makes the app write is
    owned as in production, and nothing is written outside the tmpfs
    mounts.
- **Every test builds its own rows** through the World. It starts from the
  empty tables the migrations build.
- **No bug is fixed from here.** A test that finds a defect does not change
  the app. It pins the defect in its folder's `*DefectsTest.php` with
  `pinDefect('id', fn)`:
  - The closure asserts the wanted behaviour.
  - While the defect lives, the test is reported as incomplete, tagged
    with its id and what it found, and the run stays green.
  - Once a fix makes the closure pass, the test fails with "no longer
    reproduces". That failure is the cue to drop the pin and move the test
    into its home file.
- **House style.** The banners and spacing follow the house-comments skill
  and its PHP 8.4 reference: a banner on every file, class and method; 2
  blank lines after the file banner, 8 before the class, 6 between members.
  From the repository root, this gate must print `-- 0 violation(s)`:

  ```sh
  python3 -B ../../.claude/skills/house-comments/scripts/house_check_php84.py --new tests
  ```

- **PHPUnit 13.** Data providers are named by `#[DataProvider]` attributes
  and are `public static`; PHPUnit 13 reads no annotations. `assertSee`
  escapes its needle unless told not to, so an HTML needle is passed with
  `false`. Commands run through `TestCase::console`, as `php artisan` runs
  them, rather than through Laravel's `artisan()` helper, which mocks the
  output.

## Pinned defects

None at the moment.

## Fixed defects

Every pinned defect has been fixed. Each test now lives in its home file,
without the pin:

| Id | Home test | What was wrong | The fix |
|---|---|---|---|
| `display-errors` | `PhpRuntimeTest::testPhpShowsNoErrorsToVisitors` | The image had no `php.ini`, so PHP ran with `display_errors` On | The Dockerfile installs `php.ini-production` |
| `expose-php` | `ProtectionTest::testAnswersDoNotNamePhp`, `PhpRuntimeTest::testPhpNamesNoVersion` | Every answer carried `X-Powered-By: PHP/8.4.27` | `expose_php = Off` in that `php.ini` |
| `debug-by-default` | `ConfigTest::testDebugStaysOffWithoutAppDebug` | `config/app.php` turned debug mode on whenever `APP_DEBUG` was missing | `'debug' => env('APP_DEBUG', false)` |
| `session-cookie-secure` | `ProtectionTest::testCookiesAreSecure` | The session and XSRF cookies were not Secure | `config/session.php` makes them Secure unless `SESSION_SECURE_COOKIE` says otherwise |
| `account-routes` | `RouteInventoryTest::testAccountRoutesAreNotFound` | The scaffold's `api/user` answered 401, and 500 with a token | `routes/api.php` has no routes |
| `home-without-stats` | `HomePageTest::testPageOpensBeforeTheSeed` | The home page was a 500 until `seedDB.sh` had run | `HomeController` counts a missing figure or block as 0 |
| `mined-threshold-fractions` | `HomePageTest::testMinedCoinsAreShortened` | 999.5 coins read "0 tūkst." and 999,999.5 read "0 mln." | Steps at `>= 1000` and `>= 1000000` |
| `hash-rate-no-unit` | `HomePageTest::testHashRateIsShownInItsUnit` | Under 1,000 H/s the rate had no unit | " H/s" |
| `hash-rate-mega-unit` | `HomePageTest::testHashRateIsShownInItsUnit` | Millions of hashes read "mH/s" | "MH/s" |
| `description-typo` | `HomePageTest::testDescriptionSpellsKriptovaliuta` | The meta description wrote "kriptavaliuta" | "kriptovaliuta" in `core/layout.blade.php` |
| `html-lang` | `HomePageTest::testPageDeclaresItsLanguage` | Neither html tag of the layout declared the page Lithuanian | `lang="lt"` on both |

## Found but not pinned

These are noted but not pinned, either because they are outside this
repository, harmless today, or not testable here:

- **`seedDB.sh`'s first stand-in block is refused.** Its first `INSERT`
  into `blocks` names no `timestamp`, which has no default, so strict mode
  refuses it and only the second stand-in lands.
  `SchemaTest::testBlockWithoutATimestampIsRefused` holds the behaviour;
  the script lives in the stack, outside this repository.
- **MySQL 5.7 is out of support.** The `bitlitas-mysql` service runs
  `mysql:5.7`, MySQL 5.7.44, which gets no security fixes since October
  2023; the test container runs the same server, so the suite cannot show
  a difference. Moving it to MySQL 8.4 is a change to the stack and its
  data, outside this repository.
- **Glyphicons point at missing fonts.** `grid.css` declares the Glyphicons
  font from `../fonts/`, which is not shipped. No element uses a glyphicon,
  so no browser asks for it.
- **A chain reorganization goes unnoticed.** Blocks stored before one keep
  their old rewards and times, because the table keeps no hashes. The two
  branches' rewards differ only by fees.
- **Running `bitlitas:sync` as root can break the site's logging.** A
  `docker exec -u root` can leave storage files that only root can write,
  Laravel's log among them, and the site then fails on its next log line.
  `docker exec` without `-u` runs the command as 1000, Apache's user, as
  bitlitas-cron and `syncWebappFullnode.sh` do. The suite runs everything
  as 1000, so it cannot show this.
