<?php

class Test_Requests extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_home_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/en/' );

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'en_US', $wp->query_vars['lang'] );
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );

		$this->go_to( get_option( 'home' ) . '/fr/' );

		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertSame( 'fr_FR', $wp->query_vars['lang'] );
		$this->assertSame( 'fr',    $wp->query_vars['lang_url_prefix'] );

		$this->go_to( get_option( 'home' ) . '/uk/' );

		$this->assertSame( 'en_GB', get_locale() );
		$this->assertSame( 'en_GB', $wp->query_vars['lang'] );
		$this->assertSame( 'uk',    $wp->query_vars['lang_url_prefix'] );

	}

	public function test_permalink_requests() {
		global $wp;

		$en = $this->factory->post->create_and_get();
		$uk = $this->create_post_translation( $en, 'en_GB' );
		$fr = $this->create_post_translation( $en, 'fr_FR' );

		$this->go_to( get_permalink( $en ) );

		$this->assertSame( 'en_US',        get_locale() );
		$this->assertSame( 'en_US',        $wp->query_vars['lang'] );
		$this->assertSame( 'en',           $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( $en->post_name, $wp->query_vars['name'] );

		$this->go_to( get_permalink( $fr ) );

		$this->assertSame( 'fr_FR',        get_locale() );
		$this->assertSame( 'fr_FR',        $wp->query_vars['lang'] );
		$this->assertSame( 'fr',           $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( $fr->post_name, $wp->query_vars['name'] );

		$this->go_to( get_permalink( $uk ) );

		$this->assertSame( 'en_GB',        get_locale() );
		$this->assertSame( 'en_GB',        $wp->query_vars['lang'] );
		$this->assertSame( 'uk',           $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( $uk->post_name, $wp->query_vars['name'] );

	}

	public function test_archive_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/en/2003/01/24/' );

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'en_US', $wp->query_vars['lang'] );
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( '2003',  $wp->query_vars['year'] );
		$this->assertSame( '01',    $wp->query_vars['monthnum'] );
		$this->assertSame( '24',    $wp->query_vars['day'] );

		$this->go_to( get_option( 'home' ) . '/fr/1984/02/' );

		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertSame( 'fr_FR', $wp->query_vars['lang'] );
		$this->assertSame( 'fr',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( '1984',  $wp->query_vars['year'] );
		$this->assertSame( '02',    $wp->query_vars['monthnum'] );
		$this->assertFalse( isset( $wp->query_vars['day'] ) );

		$this->go_to( get_option( 'home' ) . '/uk/2000/' );

		$this->assertSame( 'en_GB', get_locale() );
		$this->assertSame( 'en_GB', $wp->query_vars['lang'] );
		$this->assertSame( 'uk',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( '2000',  $wp->query_vars['year'] );
		$this->assertFalse( isset( $wp->query_vars['monthnum'] ) );
		$this->assertFalse( isset( $wp->query_vars['day'] ) );

	}

	public function test_feed_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/en/feed/' );

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'en_US', $wp->query_vars['lang'] );
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'feed',  $wp->query_vars['feed'] );

		$this->go_to( get_option( 'home' ) . '/fr/feed/' );

		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertSame( 'fr_FR', $wp->query_vars['lang'] );
		$this->assertSame( 'fr',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'feed',  $wp->query_vars['feed'] );

		$this->go_to( get_option( 'home' ) . '/uk/feed/' );

		$this->assertSame( 'en_GB', get_locale() );
		$this->assertSame( 'en_GB', $wp->query_vars['lang'] );
		$this->assertSame( 'uk',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'feed',  $wp->query_vars['feed'] );

	}

	public function test_search_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/en/?s=babble' );

		$this->assertSame( 'en_US',  get_locale() );
		$this->assertSame( 'en_US',  $wp->query_vars['lang'] );
		$this->assertSame( 'en',     $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'babble', $wp->query_vars['s'] );

		$this->go_to( get_option( 'home' ) . '/fr/?s=babble' );

		$this->assertSame( 'fr_FR',  get_locale() );
		$this->assertSame( 'fr_FR',  $wp->query_vars['lang'] );
		$this->assertSame( 'fr',     $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'babble', $wp->query_vars['s'] );

		$this->go_to( get_option( 'home' ) . '/uk/?s=babble' );

		$this->assertSame( 'en_GB',  get_locale() );
		$this->assertSame( 'en_GB',  $wp->query_vars['lang'] );
		$this->assertSame( 'uk',     $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'babble', $wp->query_vars['s'] );

	}

	public function test_term_requests() {
		global $wp;

		$en = $this->factory->term->create_and_get( array(
			'taxonomy' => 'category',
			'name'     => 'hello',
		) );
		$uk = $this->create_term_translation( $en, 'en_GB' );
		$fr = $this->create_term_translation( $en, 'fr_FR' );

		$this->go_to( get_term_link( $en ) );

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'en_US', $wp->query_vars['lang'] );
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );
		$this->assertEquals( array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => 'hello',
			),
		), $wp->query_vars['tax_query'] );

		$this->go_to( get_term_link( $fr ) );

		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertSame( 'fr_FR', $wp->query_vars['lang'] );
		$this->assertSame( 'fr',    $wp->query_vars['lang_url_prefix'] );
		$this->assertEquals( array(
			array(
				'taxonomy' => 'category_fr_fr',
				'field'    => 'slug',
				'terms'    => 'hello-fr_fr',
			),
		), $wp->query_vars['tax_query'] );

		$this->go_to( get_term_link( $uk ) );

		$this->assertSame( 'en_GB', get_locale() );
		$this->assertSame( 'en_GB', $wp->query_vars['lang'] );
		$this->assertSame( 'uk',    $wp->query_vars['lang_url_prefix'] );
		$this->assertEquals( array(
			array(
				'taxonomy' => 'category_en_gb',
				'field'    => 'slug',
				'terms'    => 'hello-en_gb',
			),
		), $wp->query_vars['tax_query'] );

	}

	public function test_author_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/en/author/admin/' );

		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( 'en_US', $wp->query_vars['lang'] );
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'admin', $wp->query_vars['author_name'] );

		$this->go_to( get_option( 'home' ) . '/fr/author/admin/' );

		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertSame( 'fr_FR', $wp->query_vars['lang'] );
		$this->assertSame( 'fr',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'admin', $wp->query_vars['author_name'] );

		$this->go_to( get_option( 'home' ) . '/uk/author/admin/' );

		$this->assertSame( 'en_GB', get_locale() );
		$this->assertSame( 'en_GB', $wp->query_vars['lang'] );
		$this->assertSame( 'uk',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( 'admin', $wp->query_vars['author_name'] );

	}

	public function test_robots_txt_requests() {
		global $wp;

		$this->go_to( get_option( 'home' ) . '/robots.txt' );

		$this->assertSame( 'en_US', get_locale() );
		// $this->assertSame( 'en_US', $wp->query_vars['lang'] ); // ¯\_(ツ)_/¯
		$this->assertSame( 'en',    $wp->query_vars['lang_url_prefix'] );
		$this->assertSame( '1',     $wp->query_vars['robots'] );
		$this->assertFalse( isset( $wp->query_vars['name'] ) );

		$this->go_to( get_option( 'home' ) . '/en/robots.txt' );

		$this->assertSame( 'en_US',  get_locale() );
		$this->assertSame( 'en_US',  $wp->query_vars['lang'] );
		$this->assertSame( 'en',     $wp->query_vars['lang_url_prefix'] );
		$this->assertFalse( isset( $wp->query_vars['robots'] ) );
		// @TODO Why is this `pagename`, but translated QVs below use `name`?
		//       Might be indicative of a problem somewhere.
		$this->assertSame( 'robots.txt', $wp->query_vars['pagename'] );

		$this->go_to( get_option( 'home' ) . '/fr/robots.txt' );

		$this->assertSame( 'fr_FR',  get_locale() );
		$this->assertSame( 'fr_FR',  $wp->query_vars['lang'] );
		$this->assertSame( 'fr',     $wp->query_vars['lang_url_prefix'] );
		$this->assertFalse( isset( $wp->query_vars['robots'] ) );
		$this->assertSame( 'robots.txt', $wp->query_vars['name'] );

		$this->go_to( get_option( 'home' ) . '/uk/robots.txt' );

		$this->assertSame( 'en_GB',  get_locale() );
		$this->assertSame( 'en_GB',  $wp->query_vars['lang'] );
		$this->assertSame( 'uk',     $wp->query_vars['lang_url_prefix'] );
		$this->assertFalse( isset( $wp->query_vars['robots'] ) );
		$this->assertSame( 'robots.txt', $wp->query_vars['name'] );

	}

}
