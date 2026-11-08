#!/bin/sh
# -----------------------------------------------------------
#  [*] runTests.sh — regression test runner
#
#  Builds the PRODUCTION image from this folder under a tag
#  of its own (the same PHP, nginx, php-fpm and vendor that
#  ship — the running site's bitlitas-web image is left
#  alone), builds the test image on top of it
#  (Dockerfile.tests: MySQL 5.7.44, PHPUnit, the suite) and
#  runs the suite inside a throwaway container with no
#  network. The container is shaped like the bitlitas-web
#  one of docker-compose.yml: a writable root filesystem,
#  and storage/logs as a mount of its own — a tmpfs owned by
#  www-data here, as the bind mount is in production. MySQL
#  keeps its data on a tmpfs too. A test that reaches out
#  fails instead of passing by luck, and nothing outside the
#  container is touched. Extra arguments go to PHPUnit — a
#  suite, a test file, a --filter; tests/README.md lists the
#  suites and the pinned defects.
# -----------------------------------------------------------
set -e
cd "$(dirname "$0")"

sudo docker build -t bitlitas-web-check .
sudo docker build -f Dockerfile.tests -t bitlitas-web-tests .
sudo docker run --rm --name bitlitas-web-tests --network none \
  --memory=2g --memory-swap=2g --cpus=2 \
  --tmpfs /var/www/html/storage/logs:uid=82,gid=82,mode=775 \
  --tmpfs /var/lib/mysql:size=512m \
  --tmpfs /run/mysqld \
  bitlitas-web-tests "$@"
