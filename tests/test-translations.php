<?php

class Test_Translations extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_post_translations() {

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->post->create_and_get();
		$uk = $this->create_post_translation( $en, 'en_GB' );
		$fr = $this->create_post_translation( $en, 'fr_FR' );

		// Ensure translations are correctly fetched
		$translations = bbl_get_post_translations( $en->ID );

		$this->assertEquals( array(
			'en_US' => get_post( $en->ID ),
			'en_GB' => get_post( $uk->ID ),
			'fr_FR' => get_post( $fr->ID ),
		), $translations );

		// Test it again to ensure translation caching is correct
		$translations = bbl_get_post_translations( $en->ID );

		$this->assertEquals( array(
			'en_US' => get_post( $en->ID ),
			'en_GB' => get_post( $uk->ID ),
			'fr_FR' => get_post( $fr->ID ),
		), $translations );

	}

}
