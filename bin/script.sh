#!/usr/bin/env bash

function version {
	echo "$@" | gawk -F. '{ printf("%03d%03d%03d\n", $1,$2,$3); }';
}

phpv=(`php -v`)
ver=${phpv[1]}

if [ "$(version "$ver")" -gt "$(version "5.4")" ]; then

	php -S localhost:80 -t vendor/wordpress -d disable_functions=mail &
	./bin/behat --profile=travis

fi
