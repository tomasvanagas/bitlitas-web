#!/bin/sh
# -----------------------------------------------------------
#  [*] run.sh — the test container's entrypoint
#
#  Brings up inside the one throwaway container what the
#  production stack gives the app — the MySQL 5.7.44 of the
#  bitlitas-mysql service holding the schema the migrations
#  build, a stand-in for the full node that bitlitas-exit
#  forwards to, and nginx with php-fpm under supervisord the
#  way the image starts them — then runs PHPUnit over the
#  suite as www-data, the user php-fpm serves the site as;
#  every argument goes to PHPUnit (a suite, a file, a
#  --filter). The app's environment is the one
#  docker-compose.yml gives bitlitas-web, with test values:
#  the local database and the stand-in node. MySQL keeps its
#  data on the tmpfs runTests.sh mounts. When PHPUnit fails,
#  what the server side logged is printed under its report —
#  nginx's and php-fpm's errors, the first line of the latest
#  Laravel log entries, MySQL's errors and the stand-in
#  node's log. Dockerfile.tests makes this script the image's
#  entrypoint.
# -----------------------------------------------------------
set -e

APP=/var/www/html
CNF=$APP/tests/container/my.cnf
LOGS=/var/log/bitlitas-tests
NODE=/tmp/fake-node
MYSQLD="/opt/mysql/lib/ld-linux-x86-64.so.2 --library-path /opt/mysql/lib /opt/mysql/bin/mysqld --defaults-file=$CNF"

export APP_DEBUG=false
export DB_HOST=127.0.0.1
export DB_DATABASE=bitlitas
export DB_USERNAME=bitlitas
export DB_PASSWORD=bitlitas-tests
export BITLITAS_DAEMON_URL=http://127.0.0.1:18081

mkdir -p $LOGS $NODE
chown www-data:www-data $LOGS $NODE
chown mysql /var/lib/mysql /run/mysqld


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
su -s /bin/sh mysql -c "$MYSQLD --initialize-insecure"
su -s /bin/sh mysql -c "$MYSQLD > /dev/null 2>&1 &"
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
su -s /bin/sh www-data -c "php $APP/artisan migrate --force" > $LOGS/migrate.out


# The full node's stand-in, steered by the tests through $NODE
su -s /bin/sh www-data -c "php -S 127.0.0.1:18081 $APP/tests/container/fake-node.php >> $LOGS/fake-node.log 2>&1 &"
wait_for 18081


# nginx and php-fpm, started the way the production image starts them
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf > $LOGS/supervisord.out 2>&1 &
wait_for 80


# The suite, as www-data — su's own options end at --, so every
# argument after the user reaches PHPUnit; its failures come
# with the server side's logs
status=0
su -s /bin/sh -c 'cd /var/www/html && exec php /opt/test-tools/vendor/bin/phpunit "$@"' -- www-data sh "$@" || status=$?
if [ $status -ne 0 ]; then
    if [ -s /var/log/nginx/error.log ]; then
        echo
        echo "----- nginx error log, last lines"
        tail -n 20 /var/log/nginx/error.log
    fi
    errors=$(cat /tmp/php-fpm-stderr*.log 2>/dev/null | grep -v ' NOTICE: ' | tail -n 20)
    if [ -n "$errors" ]; then
        echo
        echo "----- php-fpm errors, last lines"
        echo "$errors"
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
