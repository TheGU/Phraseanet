#!/usr/bin/env bash

set -e

USAGE="First argument should be install or update, followed by optional verbosity for commands"

if test $# -lt 1; then echo "$USAGE" && exit -1; fi
case "$1" in
    install)
        INSTALL_MODE="install";
        ;;
    update)
        INSTALL_MODE="update";
        ;;
    *)
        echo "Wrong mode."
	echo "$USAGE"
        exit -1
esac
shift
VERBOSITY=$@

set -x
mysql -uroot -ptoor -e 'SET @@global.sql_mode=STRICT_ALL_TABLES;'
mysql -uroot -ptoor -e 'CREATE SCHEMA IF NOT EXISTS ab_test;CREATE SCHEMA IF NOT EXISTS db_test;'
if ! ./bin/developer system:uninstall > /dev/null 2>&1
then
    rm -f config/configuration.yml config/configuration-compiled.php
fi
./bin/setup system:install --email=test@phraseanet.com --password=test --db-user=root --db-template=en --db-password=toor --databox=db_test --appbox=ab_test --server-name=http://127.0.0.1 -y $VERBOSITY
case "$INSTALL_MODE" in
    update)
        ./bin/developer ini:reset --email=test@phraseanet.com --password=test --run-patches --no-setup-dbs $VERBOSITY
        php resources/hudson/cleanupSubdefs.php $VERBOSITY
        ;;
    install)
        ;;
esac
./bin/developer ini:setup-tests-dbs $VERBOSITY
./bin/console searchengine:index:create $VERBOSITY
./bin/developer phraseanet:regenerate-sqlite $VERBOSITY
