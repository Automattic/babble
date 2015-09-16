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
		global $locale;

		$bbl_locale = Babble::get( 'locale' );
		$locale = null;
		$bbl_locale->content_lang = null;
		$bbl_locale->interface_lang = null;

		// ugh
		remove_filter( 'home_url', array( $bbl_locale, 'home_url' ), null, 2 );

		return parent::go_to( $url );
	}

	protected function create_post_translation( WP_Post $origin, $lang_code, $args = array() ) {

		$default_args = array(
			'post_title'   => false,
			'post_name'    => false,
			'post_content' => false,
			'post_status'  => false,
		);
		$args = wp_parse_args( $args, $default_args );

		$post = Babble::get( 'post_public' )->initialise_translation( $origin, $lang_code );
		$post->post_status  = ( false === $args['post_status'] ) ?  'publish'  : $args['post_status'];
		$post->post_title   = ( false === $args['post_title'] ) ?   rand_str() : $args['post_title'];
		$post->post_name    = ( false === $args['post_name'] ) ?    rand_str() : $args['post_name'];
		$post->post_content = ( false === $args['post_content'] ) ? rand_str() : $args['post_content'];
		wp_update_post( $post );

		return $post;

	}

	protected function create_term_translation( $origin, $lang_code ) {

		$term = Babble::get( 'taxonomies' )->initialise_translation( $origin, $origin->taxonomy, $lang_code );

		return $term;

	}

	protected function install_languages() {

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
			'ar' => 'ar',
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

		Babble::get( 'languages' )->initiate();

	}

	/**
	 * This resets post types as though they were created
	 * in the context of the locale specified. This allows
	 * us to switch locale context during a test and not
	 * have the
	 *
	 * @param string $locale A locale string
	 */
	protected function set_post_types_to_locale( $locale ) {
		$ptos = get_post_types( array( 'public' => true ) );
		foreach ( $ptos as $post_type => $object ) {
			$post_type_obj = get_post_type_object( $post_type );
			$post_type_obj->exclude_from_search = ( bbl_get_post_type_lang_code( $post_type ) != $locale );
		}
	}

}
