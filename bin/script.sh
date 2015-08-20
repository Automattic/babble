#!/usr/bin/env bash

function version {
	echo "$@" | gawk -F. '{ printf("%03d%03d%03d\n", $1,$2,$3); }';
}

phpv=(`php -v`)
ver=${phpv[1]}
echo $(version "$ver")

if [ "$(version "$ver")" -gt "$(version "5.4")" ]; then

	php -S localhost:8000 -t vendor/wordpress -d disable_functions=mail &
	./bin/behat --profile=travis

fi
