<?php

class Test_Translations extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_post_translations() {
		global $wpdb;

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->post->create_and_get();
		$uk = $this->create_post_translation( $en, 'en_GB' );
		$fr = $this->create_post_translation( $en, 'fr_FR' );

		// Ensure translations are correctly fetched
		$translations = bbl_get_post_translations( $en->ID );
		$queries      = $wpdb->num_queries;

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

		$this->assertEquals( $wpdb->num_queries, $queries );

		// Add a new translation
		$ar = $this->create_post_translation( $en, 'ar' );

		// Ensure translations are correctly updated
		$translations = bbl_get_post_translations( $en->ID );

		$this->assertEquals( array(
			'en_US' => get_post( $en->ID ),
			'en_GB' => get_post( $uk->ID ),
			'fr_FR' => get_post( $fr->ID ),
			'ar'    => get_post( $ar->ID ),
		), $translations );

	}

	public function test_term_translations() {
		global $wpdb;

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->term->create_and_get( array(
			'taxonomy' => 'category',
			'name'     => 'hello',
		) );
		$uk = $this->create_term_translation( $en, 'en_GB' );
		$fr = $this->create_term_translation( $en, 'fr_FR' );

		$terms = get_terms( 'category', array(
			'hide_empty' => false,
		) );
		$term = get_term( $en->term_id, 'category' );

		// Ensure translations are correctly fetched
		$translations = bbl_get_term_translations( $en->term_id, $en->taxonomy );
		$queries      = $wpdb->num_queries;

		$this->assertEquals( array(
			'en_US' => get_term( $en->term_id, $en->taxonomy ),
			'en_GB' => get_term( $uk->term_id, $uk->taxonomy ),
			'fr_FR' => get_term( $fr->term_id, $fr->taxonomy ),
		), $translations );

		// Test it again to ensure translation caching is correct
		$translations = bbl_get_term_translations( $en->term_id, $en->taxonomy );

		$this->assertEquals( array(
			'en_US' => get_term( $en->term_id, $en->taxonomy ),
			'en_GB' => get_term( $uk->term_id, $uk->taxonomy ),
			'fr_FR' => get_term( $fr->term_id, $fr->taxonomy ),
		), $translations );

		$this->assertEquals( $wpdb->num_queries, $queries );

		// Add a new translation
		$ar = $this->create_term_translation( $en, 'ar' );

		// Ensure translations are correctly updated
		$translations = bbl_get_term_translations( $en->term_id, $en->taxonomy );

		$this->assertEquals( array(
			'en_US' => get_term( $en->term_id, $en->taxonomy ),
			'en_GB' => get_term( $uk->term_id, $uk->taxonomy ),
			'fr_FR' => get_term( $fr->term_id, $fr->taxonomy ),
			'ar'    => get_term( $ar->term_id, $ar->taxonomy ),
		), $translations );

	}

}
