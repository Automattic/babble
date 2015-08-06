<?php

class Test_Templates extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	// Note that these tests must run before the static
	// front page is setup (or teardown/setup should occur)
	public function test_posts_front_page() {

		$this->go_to( get_option( 'home' ) . '/en/' );
		$this->assertTrue( is_front_page() );

		$this->go_to( get_option( 'home' ) . '/fr/' );
		$this->assertTrue( is_front_page() );

		$this->go_to( get_option( 'home' ) . '/zz/' );
		$this->assertFalse( is_front_page() );

	}

	public function test_static_front_page() {

		$en = $this->factory->post->create_and_get( array( 'post_type' => 'page', 'post_title' => 'Front Page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $en->ID );
		$uk = $this->create_post_translation( $en, 'en_GB' );
		$fr = $this->create_post_translation( $en, 'fr_FR' );

		$this->go_to( get_option( 'home' ) . '/en/' );
		$this->assertTrue( is_front_page() );

		$this->go_to( get_option( 'home' ) . '/fr/' );
		$this->assertTrue( is_front_page() );

		global $template;

		$this->go_to( get_option( 'home' ) . '/zz/' );
		$this->assertFalse( is_front_page() );

	}

}
