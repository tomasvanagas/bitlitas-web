#!/bin/sh
# -----------------------------------------------------------
#  [*] run.sh — the test container's entrypoint
#
#  Brings up inside the one throwaway container what the
#  production stack gives the app — the MySQL 5.7.44 of the
#  bitlitas-mysql service holding the schema the migrations
#  build, a stand-in for the full node that bitlitas-exit
#  forwards to, and Apache on port 8080 the way the image
#  starts it — then runs PHPUnit over the suite; every
#  argument goes to PHPUnit (a suite, a file, a --filter).
#  Everything runs as the container's user, 1000, the user
#  Apache serves the site as, on a read-only root
#  filesystem, so every file goes to one of the tmpfs mounts
#  runTests.sh gives the container: MySQL's data and socket,
#  Laravel's storage, Apache's run folder and logs, and /tmp
#  for the rest. The app's environment is the one
#  docker-compose.yml gives bitlitas-web, with test values:
#  a key of its own, the local database and the stand-in
#  node. When PHPUnit fails, what the server side logged is
#  printed under its report — the tail of Apache's error
#  log, the first line of the latest Laravel log entries,
#  MySQL's errors and the stand-in node's log.
#  Dockerfile.tests makes this script the image's
#  entrypoint.
# -----------------------------------------------------------
set -e

APP=/var/www/html
CNF=$APP/tests/container/my.cnf
LOGS=/tmp/bitlitas-tests
NODE=/tmp/fake-node
MYSQLD="/opt/mysql/lib/ld-linux-x86-64.so.2 --library-path /opt/mysql/lib /opt/mysql/bin/mysqld --defaults-file=$CNF"

export APP_DEBUG=false
export APP_KEY=base64:Yml0bGl0YXMtcmVncmVzc2lvbi10ZXN0cy1rZXktMzI=
export DB_HOST=127.0.0.1
export DB_DATABASE=bitlitas
export DB_USERNAME=bitlitas
export DB_PASSWORD=bitlitas-tests
export BITLITAS_DAEMON_URL=http://127.0.0.1:18081

mkdir -p $LOGS $NODE


# Waits until something answers on the port, or gives up
wait_for() {
    for i in $(seq 1 60); do
        php -r "exit(@fsockopen('127.0.0.1', $1) ? 0 : 1);" && return 0
        sleep 0.5
    done
    echo "run.sh: nothing answers on port $1" >&2
    exit 2
}


# MySQL: a fresh data directory on tmpfs, then the database and
# user the official image creates from MYSQL_DATABASE, MYSQL_USER
# and MYSQL_PASSWORD
$MYSQLD --initialize-insecure
$MYSQLD > /dev/null 2>&1 &
wait_for 3306
php <<'PHP'
<?php
$root = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock', 'root', '');
$database = getenv('DB_DATABASE');
$user = $root->quote(getenv('DB_USERNAME'));
$root->exec("CREATE DATABASE `$database`");
$root->exec("CREATE USER $user@'%' IDENTIFIED BY " . $root->quote(getenv('DB_PASSWORD')));
$root->exec("GRANT ALL ON `$database`.* TO $user@'%'");
PHP


# The schema, as seedDB.sh builds it in production
php $APP/artisan migrate --force > $LOGS/migrate.out


# The full node's stand-in, steered by the tests through $NODE
php -S 127.0.0.1:18081 $APP/tests/container/fake-node.php >> $LOGS/fake-node.log 2>&1 &
wait_for 18081


# Apache, started the way the production image starts it
apache2-foreground > $LOGS/apache.out 2>&1 &
wait_for 8080


# The suite; its failures come with the server side's logs
status=0
(cd $APP && php /opt/test-tools/vendor/bin/phpunit "$@") || status=$?
if [ $status -ne 0 ]; then
    if [ -s /var/www/logs/error.log ]; then
        echo
        echo "----- Apache error log, last lines"
        tail -n 20 /var/www/logs/error.log
    fi
    if [ -s $APP/storage/logs/laravel.log ]; then
        echo
        echo "----- Laravel log, the latest entries (first line of each)"
        grep -E '^\[[0-9]{4}-' $APP/storage/logs/laravel.log | tail -n 10 | cut -c1-400
    fi
    if grep -q '\[ERROR\]' /run/mysqld/mysql.err 2>/dev/null; then
        echo
        echo "----- MySQL errors"
        grep '\[ERROR\]' /run/mysqld/mysql.err | tail -n 10
    fi
    errors=$(grep -E 'PHP (Fatal|Parse|Warning|Notice|Deprecated)' $LOGS/fake-node.log 2>/dev/null | tail -n 10)
    if [ -n "$errors" ]; then
        echo
        echo "----- the stand-in node's PHP errors, last lines"
        echo "$errors"
    fi
fi
exit $status
