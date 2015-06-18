<?php
/**
 * Class for handling support for JSON REST API
 *
 * @package Babble
 * @since 1.5
 */
class Babble_WP_JSON extends Babble_Plugin {

	public function __construct() {
		$this->add_filter( 'json_prepare_post', null, 10, 3 );
	}

	public function json_prepare_post( $_post, $post, $context ) {
		if ( bbl_is_translated_post_type( $post['post_type'] ) ) {
			$lang = bbl_get_post_lang_code( (object) $post );

			$posts = bbl_get_post_translations( $post );
			unset( $posts[ $lang ] );

			$_post['meta']['language'] = $lang;
			$_post['meta']['links']['translations'] = array();

			foreach ( $posts as $locale => $post_data ) {
				$_post['meta']['links']['translations'][ $locale ] = json_url( '/posts/' . $post_data->ID );
			}
		}

		return $_post;
	}

}

global $bbl_wp_json;
$bbl_wp_json = new Babble_WP_JSON();
