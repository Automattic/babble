<?php

class Babble_UnitTestCase extends WP_UnitTestCase {

	function setUp() {
		global $wp_rewrite;

		parent::setUp();

		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertNotEmpty( $wp_rewrite->wp_rewrite_rules() );

		create_initial_post_types();
		create_initial_taxonomies();

		// Force the install/upgrade routines for each Babble class to run (ugh)
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		do_action( 'admin_init' );

	}

	function tearDown() {
		parent::tearDown();

		// reset language QVs so there's no pollution across tests:
		$this->go_to( get_option( 'home' ) . '/en/' );
	}

	public function go_to( $url ) {
		global $locale, $bbl_locale;
		$locale = null;
		$bbl_locale->content_lang = null;
		$bbl_locale->interface_lang = null;

		// ugh
		remove_filter( 'home_url', array( $bbl_locale, 'home_url' ), null, 2 );

		return parent::go_to( $url );
	}

	protected function create_post_translation( WP_Post $origin, $lang_code ) {
		global $bbl_post_public;

		$post = $bbl_post_public->initialise_translation( $origin, $lang_code );
		$post->post_status = 'publish';
		$post->post_title  = rand_str();
		$post->post_name   = rand_str();
		wp_update_post( $post );

		return $post;

	}

	protected function create_term_translation( $origin, $lang_code ) {
		global $bbl_taxonomies;

		$term = $bbl_taxonomies->initialise_translation( $origin, $origin->taxonomy, $lang_code );

		return $term;

	}

	protected function install_languages() {
		global $bbl_languages;

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

}
