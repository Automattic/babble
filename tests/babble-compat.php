<?php

/*
 * Because we are using the WordPress tests from trunk, we may occasionally
 * find functions are called which are not included in the version of
 * WordPress which we are testing Babble against. This file conditionally
 * defines these functions so that the tests will not fatal.
 *
 * A function can be removed from this file once the minimum version we
 * test against is greater than the `@since` of the function.
 *
 * The minimum version we test against is defined in `/.travis.yml`, in the
 * `env:` section.
 */
