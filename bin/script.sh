#!/usr/bin/env bash

php -v
ls -la
php -S localhost:8000 -t vendor/wordpress -d disable_functions=mail &
sleep 2
curl -v localhost:8000
./bin/behat --profile=travis
