# Babble Unit Tests

## Setting up

1. Clone this git repository on your local machine.
2. Install [Composer](https://getcomposer.org/) if you don't already have it.
3. Run `composer install` to fetch all the dependencies.
4. Install the test environment by executing:

        ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass>

  Ensure you use a separate test database (eg. `wp_tests`) because, just like the WordPress test suite, the database will be wiped clean with every test run.

## Running the unit tests

To run the unit tests, just execute:

    ./vendor/bin/phpunit

## Running the acceptance tests

Babble uses [Behat](http://behat.org) for acceptance testing. This requires a web server to be running and able to accept requests.

First, edit `behat.yml` and change the database connection `db`, `username`, and `password` fields in the `default` block as appropriate.

Start PHP's built-in web server by executing the following:

    sudo php -S localhost:8000 -t vendor/wordpress -d disable_functions=mail &

To run the tests, execute the following:

    ./bin/behat
