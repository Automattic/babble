<?php

class Babble_UnitTestCase extends WP_UnitTestCase {

	function setUp() {

		parent::setUp();

		// Force the install/upgrade routines for each Babble class to run (ugh)
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		do_action( 'admin_init' );

	}

}
