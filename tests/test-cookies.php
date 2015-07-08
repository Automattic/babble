<?php

class Test_Cookies extends Babble_UnitTestCase {

	public function setUp() {
		parent::setUp();

		$this->install_languages();
	}

	public function test_single_site_cookie_path() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Test not applicable on multisite' );
		}

		$this->assertSame( '/', Babble_Locale::get_cookie_path() );

	}

	/**
	 * @group multisite
	 */
	public function test_multisite_cookie_path() {

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test only runs on multisite' );
		}

		$this->assertSame( '/', Babble_Locale::get_cookie_path() );

		$blog1 = $this->factory->blog->create_and_get();
		$blog2 = $this->factory->blog->create_and_get();

		switch_to_blog( $blog1->blog_id );

		$this->assertSame( $blog1->path, Babble_Locale::get_cookie_path() );

		switch_to_blog( $blog2->blog_id );

		$this->assertSame( $blog2->path, Babble_Locale::get_cookie_path() );

		restore_current_blog();
		restore_current_blog();

		$this->assertSame( '/', Babble_Locale::get_cookie_path() );

	}


}
