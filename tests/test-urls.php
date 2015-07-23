<?php

class Test_URLs extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_home_url() {
		$switched = bbl_switch_to_lang( 'fr_FR' );

		$this->assertTrue( $switched );

		// The `query_vars` filter is only triggered once a query has been made, so we need to trigger one.
		$this->go_to( home_url( '/test/' ) );
		$home_url = trailingslashit( home_url() );

		$this->assertContains( '/fr/', $home_url );

		$switched = bbl_switch_to_lang( 'en_GB' );

		$this->assertTrue( $switched );

		$home_url = trailingslashit( home_url() );

		$this->assertContains( '/uk/', $home_url );

		// switch back to fr_FR
		bbl_restore_lang();

		$home_url = trailingslashit( home_url() );

		$this->assertContains( '/fr/', $home_url );

		// switch back to en_US
		bbl_restore_lang();
	}

}
