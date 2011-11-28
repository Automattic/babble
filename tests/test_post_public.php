<?php

class WP_Test_Hello_Dolly extends WP_UnitTestCase {

	var $plugin_slug = 'babble';

	function setUp() {
		parent::setUp();
		global $bbl_languages;
		$bbl_languages->active_langs = array( 'fr' => 'fr_FR', 'en' => 'en_US' );
	}

	function tearDown() {
		parent::tearDown();
		
	}



	/**
	 * undocumented function
	 *
	 **/
	function test_get_post_type_in_lang() {
		global $bbl_post_public;
		$bbl_post_public->get_post_type_in_lang( 'post', 'fr_FR' );
	}
}