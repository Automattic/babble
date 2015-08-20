#!/usr/bin/env bash

php -v
which php
echo $PATH
ls -la
php -S localhost:8000 -t vendor/wordpress -d disable_functions=mail &
sleep 2
curl -v localhost:8000
./bin/behat --profile=travis
