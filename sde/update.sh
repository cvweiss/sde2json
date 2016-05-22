#!/bin/bash

rm -rf sde-*
rm mysql-latest.tar.bz2
rm mysql-latest.tar

wget "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2"
bunzip2 "mysql-latest.tar.bz2"
tar xf mysql-latest.tar

cd sde-*
mysql -usde -psde sde < sde-*.sql
