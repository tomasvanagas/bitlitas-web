#!/bin/sh
# -----------------------------------------------------------
#  [*] runTests.sh — regression test runner
#
#  Builds the PRODUCTION image from this folder under a tag
#  of its own (the same PHP, Apache and vendor that ship —
#  the running site's bitlitas-web image is left alone),
#  builds the test image on top of it
#  (Dockerfile.tests: MySQL 5.7.44, PHPUnit, the suite) and
#  runs the suite inside a throwaway container with no
#  network. The container is shaped like the bitlitas-web
#  one of docker-compose.yml: user 1000, a read-only root
#  filesystem, and the same tmpfs mounts of the same sizes —
#  Apache's run folder, /tmp, and storage/framework with its
#  cache, sessions and views folders. storage/logs and Apache's
#  /var/www/logs are tmpfs mounts owned by 1000 here, as
#  the bind mounts are in production, and MySQL keeps its
#  data and its socket on tmpfs mounts too. A test that
#  reaches out fails instead of passing by luck, and nothing
#  outside the container is touched. Extra arguments go to
#  PHPUnit — a suite, a test file, a --filter;
#  tests/README.md lists the suites and the defects found.
# -----------------------------------------------------------
set -e
cd "$(dirname "$0")"

sudo docker build -t bitlitas-web-check .
sudo docker build -f Dockerfile.tests -t bitlitas-web-tests .
sudo docker run --rm --name bitlitas-web-tests --network none --read-only \
  --memory=2g --memory-swap=2g --cpus=2 \
  --tmpfs /var/run/apache2:uid=1000,gid=1000,size=8m \
  --tmpfs /tmp:uid=1000,gid=1000,size=64m \
  --tmpfs /var/www/html/storage/framework:uid=1000,gid=1000,size=8m \
  --tmpfs /var/www/html/storage/framework/cache:uid=1000,gid=1000,size=64m \
  --tmpfs /var/www/html/storage/framework/sessions:uid=1000,gid=1000,size=8m \
  --tmpfs /var/www/html/storage/framework/views:uid=1000,gid=1000,size=16m \
  --tmpfs /var/www/html/storage/logs:uid=1000,gid=1000,mode=775 \
  --tmpfs /var/www/logs:uid=1000,gid=1000,mode=775 \
  --tmpfs /var/lib/mysql:uid=1000,gid=1000,size=512m \
  --tmpfs /run/mysqld:uid=1000,gid=1000 \
  bitlitas-web-tests "$@"
