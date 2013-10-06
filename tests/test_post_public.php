<?php

// NOTE THAT WE NEED TO (PROBABLY) COMPLETELY REDO OUR TESTS

class WP_Test_Babble extends WP_UnitTestCase {

	var $plugin_slug = 'babble';

	function setUp() {
		parent::setUp();
		
		$languages = unserialize( 'a:6:{s:15:"available_langs";a:13:{s:2:"ar";O:8:"stdClass":4:{s:5:"names";s:6:"Arabic";s:4:"code";s:2:"ar";s:10:"url_prefix";s:2:"ar";s:14:"text_direction";s:3:"rtl";}s:5:"de_DE";O:8:"stdClass":4:{s:5:"names";s:6:"German";s:4:"code";s:5:"de_DE";s:10:"url_prefix";s:2:"de";s:14:"text_direction";s:3:"ltr";}s:5:"en_US";O:8:"stdClass":4:{s:5:"names";s:7:"English";s:4:"code";s:5:"en_US";s:10:"url_prefix";s:2:"en";s:14:"text_direction";s:3:"ltr";}s:5:"es_ES";O:8:"stdClass":4:{s:5:"names";s:7:"Spanish";s:4:"code";s:5:"es_ES";s:10:"url_prefix";s:2:"es";s:14:"text_direction";s:3:"ltr";}s:5:"fa_IR";O:8:"stdClass":4:{s:5:"names";s:7:"Persian";s:4:"code";s:5:"fa_IR";s:10:"url_prefix";s:2:"fa";s:14:"text_direction";s:3:"rtl";}s:5:"fr_FR";O:8:"stdClass":4:{s:5:"names";s:6:"French";s:4:"code";s:5:"fr_FR";s:10:"url_prefix";s:2:"fr";s:14:"text_direction";s:3:"ltr";}s:5:"hi_IN";O:8:"stdClass":4:{s:5:"names";s:5:"Hindi";s:4:"code";s:5:"hi_IN";s:10:"url_prefix";s:2:"hi";s:14:"text_direction";s:3:"ltr";}s:2:"ja";O:8:"stdClass":4:{s:5:"names";s:8:"Japanese";s:4:"code";s:2:"ja";s:10:"url_prefix";s:2:"ja";s:14:"text_direction";s:3:"ltr";}s:5:"pt_BR";O:8:"stdClass":4:{s:5:"names";s:10:"Portuguese";s:4:"code";s:5:"pt_BR";s:10:"url_prefix";s:2:"pt";s:14:"text_direction";s:3:"ltr";}s:5:"ru_RU";O:8:"stdClass":4:{s:5:"names";s:7:"Russian";s:4:"code";s:5:"ru_RU";s:10:"url_prefix";s:2:"ru";s:14:"text_direction";s:3:"ltr";}s:2:"tr";O:8:"stdClass":4:{s:5:"names";s:7:"Turkish";s:4:"code";s:2:"tr";s:10:"url_prefix";s:2:"tr";s:14:"text_direction";s:3:"ltr";}s:2:"ur";O:8:"stdClass":4:{s:5:"names";s:4:"Urdu";s:4:"code";s:2:"ur";s:10:"url_prefix";s:2:"ur";s:14:"text_direction";s:3:"rtl";}s:5:"zh_CN";O:8:"stdClass":4:{s:5:"names";s:7:"Chinese";s:4:"code";s:5:"zh_CN";s:10:"url_prefix";s:2:"zh";s:14:"text_direction";s:3:"ltr";}}s:12:"active_langs";a:13:{s:2:"ar";s:2:"ar";s:2:"de";s:5:"de_DE";s:2:"en";s:5:"en_US";s:2:"es";s:5:"es_ES";s:2:"fa";s:5:"fa_IR";s:2:"fr";s:5:"fr_FR";s:2:"hi";s:5:"hi_IN";s:2:"ja";s:2:"ja";s:2:"pt";s:5:"pt_BR";s:2:"ru";s:5:"ru_RU";s:2:"tr";s:2:"tr";s:2:"ur";s:2:"ur";s:2:"zh";s:5:"zh_CN";}s:5:"langs";a:13:{s:2:"ar";O:8:"stdClass":5:{s:5:"names";s:6:"Arabic";s:4:"code";s:2:"ar";s:10:"url_prefix";s:2:"ar";s:14:"text_direction";s:3:"rtl";s:12:"display_name";s:14:"العربية";}s:5:"de_DE";O:8:"stdClass":5:{s:5:"names";s:6:"German";s:4:"code";s:5:"de_DE";s:10:"url_prefix";s:2:"de";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:7:"Deutsch";}s:5:"en_US";O:8:"stdClass":5:{s:5:"names";s:7:"English";s:4:"code";s:5:"en_US";s:10:"url_prefix";s:2:"en";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:7:"English";}s:5:"es_ES";O:8:"stdClass":5:{s:5:"names";s:7:"Spanish";s:4:"code";s:5:"es_ES";s:10:"url_prefix";s:2:"es";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:8:"Español";}s:5:"fa_IR";O:8:"stdClass":5:{s:5:"names";s:7:"Persian";s:4:"code";s:5:"fa_IR";s:10:"url_prefix";s:2:"fa";s:14:"text_direction";s:3:"rtl";s:12:"display_name";s:13:"فارسی‎";}s:5:"fr_FR";O:8:"stdClass":5:{s:5:"names";s:6:"French";s:4:"code";s:5:"fr_FR";s:10:"url_prefix";s:2:"fr";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:9:"Français";}s:5:"hi_IN";O:8:"stdClass":5:{s:5:"names";s:5:"Hindi";s:4:"code";s:5:"hi_IN";s:10:"url_prefix";s:2:"hi";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:5:"Hindi";}s:2:"ja";O:8:"stdClass":5:{s:5:"names";s:8:"Japanese";s:4:"code";s:2:"ja";s:10:"url_prefix";s:2:"ja";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:9:"日本語";}s:5:"pt_BR";O:8:"stdClass":5:{s:5:"names";s:10:"Portuguese";s:4:"code";s:5:"pt_BR";s:10:"url_prefix";s:2:"pt";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:10:"Português";}s:5:"ru_RU";O:8:"stdClass":5:{s:5:"names";s:7:"Russian";s:4:"code";s:5:"ru_RU";s:10:"url_prefix";s:2:"ru";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:14:"Русский";}s:2:"tr";O:8:"stdClass":5:{s:5:"names";s:7:"Turkish";s:4:"code";s:2:"tr";s:10:"url_prefix";s:2:"tr";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:8:"Türkçe";}s:2:"ur";O:8:"stdClass":5:{s:5:"names";s:4:"Urdu";s:4:"code";s:2:"ur";s:10:"url_prefix";s:2:"ur";s:14:"text_direction";s:3:"rtl";s:12:"display_name";s:8:"اردو";}s:5:"zh_CN";O:8:"stdClass":5:{s:5:"names";s:7:"Chinese";s:4:"code";s:5:"zh_CN";s:10:"url_prefix";s:2:"zh";s:14:"text_direction";s:3:"ltr";s:12:"display_name";s:12:"简体中文";}}s:12:"default_lang";s:5:"en_US";s:10:"lang_prefs";a:13:{s:2:"ar";O:8:"stdClass":2:{s:12:"display_name";s:14:"العربية";s:10:"url_prefix";s:2:"ar";}s:5:"de_DE";O:8:"stdClass":2:{s:12:"display_name";s:7:"Deutsch";s:10:"url_prefix";s:2:"de";}s:5:"en_US";O:8:"stdClass":2:{s:12:"display_name";s:7:"English";s:10:"url_prefix";s:2:"en";}s:5:"es_ES";O:8:"stdClass":2:{s:12:"display_name";s:8:"Español";s:10:"url_prefix";s:2:"es";}s:5:"fa_IR";O:8:"stdClass":2:{s:12:"display_name";s:13:"فارسی‎";s:10:"url_prefix";s:2:"fa";}s:5:"fr_FR";O:8:"stdClass":2:{s:12:"display_name";s:9:"Français";s:10:"url_prefix";s:2:"fr";}s:5:"hi_IN";O:8:"stdClass":2:{s:12:"display_name";s:5:"Hindi";s:10:"url_prefix";s:2:"hi";}s:2:"ja";O:8:"stdClass":2:{s:12:"display_name";s:9:"日本語";s:10:"url_prefix";s:2:"ja";}s:5:"pt_BR";O:8:"stdClass":2:{s:12:"display_name";s:10:"Português";s:10:"url_prefix";s:2:"pt";}s:5:"ru_RU";O:8:"stdClass":2:{s:12:"display_name";s:14:"Русский";s:10:"url_prefix";s:2:"ru";}s:2:"tr";O:8:"stdClass":2:{s:12:"display_name";s:8:"Türkçe";s:10:"url_prefix";s:2:"tr";}s:2:"ur";O:8:"stdClass":2:{s:12:"display_name";s:8:"اردو";s:10:"url_prefix";s:2:"ur";}s:5:"zh_CN";O:8:"stdClass":2:{s:12:"display_name";s:12:"简体中文";s:10:"url_prefix";s:2:"zh";}}s:12:"public_langs";a:9:{i:0;s:2:"ar";i:1;s:5:"de_DE";i:2;s:5:"en_US";i:3;s:5:"es_ES";i:4;s:5:"fr_FR";i:5;s:2:"ja";i:6;s:5:"pt_BR";i:7;s:2:"tr";i:8;s:5:"zh_CN";}}' );
		update_option( 'babble-languages', $languages );

	}

	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	function initiate() {
		global $bbl_languages;
		$bbl_languages->initiate();
	}

	function tearDown() {
		parent::tearDown();
		
	}
	
	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	function create_default_language_post() {
		$post_data = array(
			'post_author' => 1,
			'post_content' => 'English page content',
			'post_title' => 'English page title',
			'post_status' => 'publish',
			'post_type' => 'page',
		);
		$result = wp_insert_post( $post_data, true );
		$this->assertFalse( is_wp_error( $result ), 'Create default language post failed' );
		return $result;
	}
	
	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	function create_translation_post( $origin_id ) {
		throw new exception( 'Hello' );
		global $bbl_post_public;
		$transid = $bbl_post_public->get_transid( $origin_id );
		error_log( "SW: Transid $transid" );
		$_GET[ 'bbl_origin_id' ] = $origin_id;
		$_GET[ 'bbl_transid' ] = $transid;
		bbl_switch_to_lang( 'fr_FR' );
		$post_data = array(
			'post_author' => 1,
			'post_content' => 'French page content',
			'post_title' => 'French page title',
			'post_status' => 'auto-draft',
			'post_type' => 'page',
		);
		$result = wp_insert_post( $post_data, true );
		$this->assertFalse( is_wp_error( $result ), 'Create default language post failed' );
		return $result;
	}

	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	function get_duplicated_metadata() {
		global $wpdb;
		$sql = "SELECT COUNT(*) AS count, post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key IN ( '_extmedia-youtube', '_extmedia-duration', '_thumbnail_id', '_wp_trash_meta_time', '_wp_page_template', '_wp_trash_meta_status' ) GROUP BY post_id, meta_key, meta_value HAVING count > 1 ORDER BY count, post_id, meta_key";
		$results = (array) $wpdb->get_results( $sql );
		return $results;
	}
	
	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	function test_post_meta_over_syncing() {
		$this->initiate();

		// Test we have clean initial data
		$this->assertEquals( count( $this->get_duplicated_metadata() ), 0, 'Duplicated metadata before test begins.' );

		// Test creating initial default language post
		error_log( "SW: create_default_language_post --------------------" );
		$default_post_id = $this->create_default_language_post();

		// Test setting page template
		$tpl = 'p-home.php';
		// @TODO: This update post call doesn't work, as get_page_templates is not defined, how would I do this?
		// $post_data = array(
		// 	'ID' => $default_post_id,
		// 	'page_template' => $tpl,
		// );
		// $this->assertEquals( wp_update_post( $post_data, true ), $default_post_id, 'Failed to update page template' );

		$this->assertEquals( count( $this->get_duplicated_metadata() ), 0, 'Duplicated metadata after creating translation.' );
	
		// Test creating translation content
		error_log( "SW: create_translation_post --------------------" );
		$translation_post_id = $this->create_translation_post( $default_post_id );
		error_log( "SW: --------------------" );
		error_log( "SW: Default post ID: $default_post_id" );
		$this->assertEquals( count( $this->get_duplicated_metadata() ), 0, 'Duplicated metadata after creating translation.' );

		// @TODO: Split meta syncing into a separate test
		update_post_meta( $default_post_id, '_wp_page_template',  $tpl );
		$this->assertEquals( get_post_meta( $default_post_id, '_wp_page_template', true ), $tpl, 'Page template is wrong on default' );
		$this->assertEquals( get_post_meta( $translation_post_id, '_wp_page_template', true ), $tpl, 'Page template is wrong on translation' );

		// Test updating translation
		$post_data = array(
			'ID' => $translation_post_id,
			'post_content' => 'French content updated',
		);
		$this->assertEquals( wp_update_post( $post_data, true ), $translation_post_id, 'Failed to update translation' );
		$this->assertEquals( count( $this->get_duplicated_metadata() ), 0, 'Duplicated metadata after updating translation.' );

		// Test trashing translation
		$this->assertTrue( (bool) wp_trash_post( $post_data, true ), 'Failed to update translation' );
		$this->assertEquals( count( $this->get_duplicated_metadata() ), 0, 'Duplicated metadata after updating translation.' );
		$post = get_post( $translation_post_id );
		error_log( "SW: Translated post: " . print_r( $post, true ) );
	}

}
