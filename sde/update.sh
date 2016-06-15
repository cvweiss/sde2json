#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR

wget -qq "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2.md5" -O check.md5

if ! cmp installed.md5 check.md5 >/dev/null 2>&1
then
    rm -rf sde-*
    rm mysql-latest.tar.bz2
    rm mysql-latest.tar

    wget "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2"
    bunzip2 "mysql-latest.tar.bz2"
    tar xf mysql-latest.tar

    cd sde-*
    mysql -usde -psde sde < sde-*.sql

    cd $DIR
    wget -qq "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2.md5" -O installed.md5

    $DIR/../src
    php publish.php
fi

rm check.md5
