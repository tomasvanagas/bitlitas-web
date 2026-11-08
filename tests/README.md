# Laravel app regression tests

The PHPUnit suite of the bitlitas.lt Laravel app (Laravel 13.40.0 on PHP
8.4.27). It runs in one throwaway container with no network, built from
the production image. Inside that container it starts three things:

- MySQL 5.7.44, the server of the `bitlitas-mysql` service, with the schema
  the migrations build;
- a stand-in for the full node;
- nginx with php-fpm, serving the app the way production does.

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
   - a writable root filesystem;
   - `storage/logs` as a mount of its own: a tmpfs owned by www-data here,
     a bind mount in production;
   - at most 2 GB and 2 CPUs.

Every argument goes to PHPUnit. When PHPUnit fails, `container/run.sh` also
prints what the server side logged:

- nginx's and php-fpm's errors;
- the latest Laravel log entries;
- MySQL's errors;
- the stand-in node's PHP errors.

The host needs only docker. PHP, MySQL and the test tools live in the image.
Building the images needs the network, because composer installs the app's
vendor and PHPUnit. The test run itself never does.

## Layers

| Suite | Folder | What it checks | How |
|---|---|---|---|
| Platform | `Platform/` | The image as shipped: PHP, its extensions and php-fpm's settings; no dev packages and only the locked versions; the configuration and the `.env` the Dockerfile writes; the MySQL server and the schema; nginx and supervisord; the templates and the files they link | Reads the container's files, `php-fpm -tt` and `-i`, and the database |
| Feature | `Feature/` | The home page and its figures, the sync from the node (`NetworkSync`, `GET /blokai`, `php artisan bitlitas:sync`), the node client, the route table | Laravel's in-process HTTP and console kernels; each test runs inside a transaction that is rolled back. The node is `FakeDaemon` in memory, or the stand-in over HTTP for `DaemonClientTest` |
| Server | `Server/` | What happens in front of the app: nginx's static files and front controller, what is never served, errors in production mode, the sync as production runs it | Requests to the real nginx on `127.0.0.1:80`, carrying the headers the endpoint adds; rows are committed, and both tables emptied around every test |

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
| `Support/ServerClient.php`, `ServerResponse.php` | curl for the real nginx, and what it answered |
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
  - php-fpm runs with the environment `docker-compose.yml` gives
    `bitlitas-web` (`APP_DEBUG`, `DB_*`, `BITLITAS_DAEMON_URL`), with test
    values, and with the `.env` the Dockerfile wrote.
  - The app's `vendor/` stays `--no-dev`. PHPUnit lives apart, in
    `/opt/test-tools`.
  - PHPUnit runs as www-data, php-fpm's user, so every file a test makes
    the app write is owned as in production.
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

Each defect below is pinned by a test that reproduces it, and none is
fixed. The fix column is a suggestion.

| Id | Test | Defect | Fix |
|---|---|---|---|
| `display-errors` | `PlatformDefectsTest::testPhpFpmShowsNoErrorsToVisitors` | The image has no `php.ini`, so php-fpm runs with `display_errors` On. An error PHP raises before Laravel's handler prints into the page with the server's paths | Dockerfile: `mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"` |
| `fpm-listens-everywhere` | `PlatformDefectsTest::testPhpFpmListensOnlyForNginx` | The official image's `zz-docker.conf` sets `listen = 9000` on every interface. Any container on `bitlitas-isolated` could send php-fpm FastCGI requests of its own, though nginx in the same container is its only client | Dockerfile: `sed -i 's/^listen = 9000$/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/zz-docker.conf` |
| `debug-by-default` | `PlatformDefectsTest::testDebugStaysOffWithoutAppDebug` | `config/app.php` defaults debug mode to true. Only `APP_DEBUG=false` in `docker-compose.yml` keeps the code, stack trace and request of every error away from visitors | `'debug' => env('APP_DEBUG', false)` |
| `home-without-stats` | `SiteDefectsTest::testPageOpensBeforeTheSeed` | The home page is a 500 until `seedDB.sh` has run: `HomeController` reads the figures and the last block without checking that they exist | Fall back to 0 for a missing figure or block |
| `mined-threshold-fractions` | `SiteDefectsTest::testFractionsJustUnderAStepKeepTheirScale` | 999.5 coins read "0 tūkst." and 999,999.5 read "0 mln.". The mined figure has decimals, but the steps are `> 999` and `<= 999999` | Steps at `>= 1000` and `>= 1000000` |
| `hash-rate-no-unit` | `SiteDefectsTest::testSmallHashRateNamesItsUnit` | Under 1,000 H/s the rate has no unit. The live site shows a bare "19" | Append " H/s" |
| `hash-rate-mega-unit` | `SiteDefectsTest::testMillionsOfHashesAreMegahashes` | Millions of hashes read "mH/s" (milli-hashes) | "MH/s" |
| `description-typo` | `SiteDefectsTest::testDescriptionSpellsKriptovaliuta` | The meta description writes "kriptavaliuta" | "kriptovaliuta" in `core/layout.blade.php` |
| `html-lang` | `SiteDefectsTest::testPageDeclaresItsLanguage` | Neither html tag of the layout declares the page Lithuanian | `lang="lt"` on both |
| `account-routes` | `RouteDefectsTest::testAccountRoutesAreNotFound` | The scaffold's `api/user` is routed, though the app has no accounts: a 401 without a token, and a 500 with one, since the token guard looks for an `App\User` model that does not exist | Drop the `api/user` route |
| `expose-php` | `ServerDefectsTest::testAnswersDoNotNamePhp` | Every answer of the app carries `X-Powered-By: PHP/8.4.27` | `php.ini` with `expose_php = Off` |
| `session-cookie-secure` | `ServerDefectsTest::testCookiesAreSecure` | The session and XSRF cookies set on every page are not Secure, though the site is HTTPS-only | `SESSION_SECURE_COOKIE=true` in `docker-compose.yml` |

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
- **The `/blokai` throttle is shared by everyone.** It counts every visitor
  as one, because `TrustProxies` trusts no proxy.
  `SyncOverHttpTest::testThrottleCountsEveryVisitorAsOne` holds the
  behaviour. The limit protects the node for the whole site, but one
  visitor can use it up for a minute; `php artisan bitlitas:sync` is not
  throttled.
- **A chain reorganization goes unnoticed.** Blocks stored before one keep
  their old rewards and times, because the table keeps no hashes. The two
  branches' rewards differ only by fees.
- **Running `bitlitas:sync` as root can break the site's logging.** It can
  leave storage files that only root can write, Laravel's log among them,
  and php-fpm then fails on its next log line. `routes/console.php` says
  to run the command as www-data. The suite runs as www-data, so it cannot
  show this.
