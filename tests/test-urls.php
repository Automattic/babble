<?php

class Test_URLs extends Babble_UnitTestCase {

	protected $langs = array();

	public function setUp() {
		global $wp_rewrite, $bbl_languages;

		parent::setUp();

		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		if ( file_exists( $file = ABSPATH . 'wp-admin/includes/translation-install.php' ) ) {
			require_once $file;
		}

		if ( ! function_exists( 'wp_download_language_pack' ) ) {
			$this->markTestSkipped( 'The wp_download_language_pack() function does not exist.' );
			return;
		}

		$languages = array(
			'fr' => 'fr_FR',
			'uk' => 'en_GB',
		);

		foreach ( $languages as $url_prefix => $lang ) {
			$download = wp_download_language_pack( $lang );
			$this->assertNotEmpty( $download );
			$this->langs[ $lang ] = $download;
		}

		$active_langs = array_merge( array(
			'en' => 'en_US',
		), $languages );
		$public_langs = array_values( $active_langs );
		$lang_prefs = array();
		$langs = array();

		$lang_prefs['en'] = (object) array(
			'display_name' => 'en_US',
			'url_prefix'   => 'en',
		);
		$langs['en_US'] = (object) array(
			'name'           => 'en_US',
			'code'           => 'en_US',
			'url_prefix'     => 'en',
			'text_direction' => 'ltr',
			'display_name'   => 'en_US',
		);

		foreach ( $languages as $url_prefix => $lang ) {
			$lang_prefs[ $url_prefix ] = (object) array(
				'display_name' => $lang,
				'url_prefix'   => $url_prefix,
			);
			$langs[ $lang ] = (object) array(
				'name'           => $lang,
				'code'           => $lang,
				'url_prefix'     => $url_prefix,
				'text_direction' => 'ltr',
				'display_name'   => $lang,
			);
		}

		$default_lang = 'en_US';

		$option = get_option( 'babble-languages', array() );

		$option['langs']        = $langs;
		$option['active_langs'] = $active_langs;
		$option['public_langs'] = $public_langs;
		$option['lang_prefs']   = $lang_prefs;
		$option['default_lang'] = $default_lang;

		update_option( 'babble-languages', $option );

		$bbl_languages->initiate();

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
