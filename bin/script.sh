#!/usr/bin/env bash

php -S localhost:8000 -t vendor/wordpress -d disable_functions=mail &
./bin/behat --profile=travis
