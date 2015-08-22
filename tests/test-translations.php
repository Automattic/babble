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

		// Delete a translation
		$deleted = wp_delete_post( $uk->ID, true );
		// https://core.trac.wordpress.org/ticket/32991
		$this->assertNotEmpty( $deleted );

		// Ensure translations are correctly updated
		$translations = bbl_get_post_translations( $en->ID );

		$this->assertEquals( array(
			'en_US' => get_post( $en->ID ),
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

		// Delete a translation
		$deleted = wp_delete_term( $fr->term_id, $fr->taxonomy );
		$this->assertTrue( $deleted );

		// Ensure translations are correctly updated
		$translations = bbl_get_term_translations( $en->term_id, $en->taxonomy );

		$this->assertEquals( array(
			'en_US' => get_term( $en->term_id, $en->taxonomy ),
			'en_GB' => get_term( $uk->term_id, $uk->taxonomy ),
			'ar'    => get_term( $ar->term_id, $ar->taxonomy ),
		), $translations );

	}

	public function test_canonical_content_fallback() {
		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->post->create_and_get();
		// In normal operation, the create_empty_translations method is called on an immediate single cron job
		$GLOBALS['bbl_jobs']->create_empty_translations($en->ID);
		$fr = bbl_get_post_in_lang( $en->ID, 'fr_FR', true );

		// @FIXME: These tests fail due to the interaction of Babble_Post_Public::get_post_in_lang and Babble_Jobs::create_empty_translations
		$this->assertSame( $en->post_title,   $fr->post_title );
		$this->assertSame( $en->post_content, $fr->post_content );

	}

}
