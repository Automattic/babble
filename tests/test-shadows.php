<?php

class Test_Shadows extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_shadow_assumptions() {
		global $wpdb;

		if ( ! method_exists( $wpdb, 'get_col_length' ) ) {
			$this->markTestSkipped( 'The wpdb::get_col_length method does not exist' );
		}

		$post_type = $wpdb->get_col_length( $wpdb->posts, 'post_type' );
		$this->assertSame( 20, intval( $post_type['length'] ) );

		$term = $wpdb->get_col_length( $wpdb->terms, 'name' );
		$this->assertSame( 200, intval( $term['length'] ) );

		$taxonomy = $wpdb->get_col_length( $wpdb->term_taxonomy, 'taxonomy' );
		$this->assertSame( 32, intval( $taxonomy['length'] ) );
	}

	public function test_post_shadow_name() {

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->post->create_and_get( array(
			'post_type' => 'post',
		) );
		$uk = $this->create_post_translation( $en, 'en_GB' );

		$post_type    = 'post_en_gb';
		$uk_post_type = Babble_Post_Public::generate_shadow_post_type_name( $en->post_type, 'en_GB' );
		$uk_post      = get_post( $uk->ID );

		$this->assertSame( $post_type, $uk_post_type );
		$this->assertSame( $uk_post_type, $uk_post->post_type );

	}

	public function test_long_post_shadow_name() {

		# Register a post type with a twenty-character name
		$post_type = str_repeat( 'a', 20 );
		register_post_type( $post_type, array(
			'public' => true,
		) );

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->post->create_and_get( array(
			'post_type' => $post_type,
		) );
		$uk = $this->create_post_translation( $en, 'en_GB' );

		$uk_post_type = Babble_Post_Public::generate_shadow_post_type_name( $en->post_type, 'en_GB' );
		$uk_post      = get_post( $uk->ID );

		$this->assertTrue( strlen( $uk_post->post_type ) <= 20 );
		$this->assertSame( $uk_post_type, $uk_post->post_type );

	}

	public function test_taxonomy_shadow_name() {

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->category->create_and_get();
		$uk = $this->create_term_translation( $en, 'en_GB' );

		$tax_name    = 'category_en_gb';
		$uk_tax_name = Babble_Taxonomies::generate_shadow_taxonomy_name( $en->taxonomy, 'en_GB' );
		$uk_term     = get_term( $uk->term_id, $uk->taxonomy );

		$this->assertSame( $tax_name, $uk_tax_name );
		$this->assertSame( $uk_tax_name, $uk_term->taxonomy );

	}

	public function test_long_taxonomy_shadow_name() {

		# Register a taxonomy with a 32-character name
		$taxonomy = str_repeat( 'a', 32 );
		register_taxonomy( $taxonomy, 'post', array(
			'public' => true,
		) );

		$this->assertSame( 'en_US', get_locale() );

		$en = $this->factory->term->create_and_get( array(
			'taxonomy' => $taxonomy,
		) );
		$uk = $this->create_term_translation( $en, 'en_GB' );

		$uk_tax_name = Babble_Taxonomies::generate_shadow_taxonomy_name( $en->taxonomy, 'en_GB' );
		$uk_term     = get_term( $uk->term_id, $uk->taxonomy );

		$this->assertTrue( strlen( $uk_term->taxonomy ) <= 32 );
		$this->assertSame( $uk_tax_name, $uk_term->taxonomy );

	}

}
