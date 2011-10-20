<?php

/**
 * Translations and languages API.
 *
 * @package Babble
 * @since Alpha 1
 */

/*  Copyright 2011 Simon Wheatley

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
 * Returns the current language code.
 *
 * @FIXME: Currently does not check for language validity, though perhaps we should check that elsewhere and redirect?
 *
 * @return string A language code
 * @access public
 **/
function sil_get_current_lang_code() {
	// Outside the admin area, it's a WP Query Variable
	if ( ! is_admin() )
		return get_query_var( 'lang' ) ? get_query_var( 'lang' ) : bbl_get_default_lang_code();
	// In the admin area, it's a GET param
	$current_user = wp_get_current_user();
	return get_user_meta( $current_user->ID, 'bbl_admin_lang', true ) ? get_user_meta( $current_user->ID, 'bbl_admin_lang', true ) : bbl_get_default_lang_code();
}

/**
 * Set the current lang.
 * 
 * @uses Babble_Locale::switch_lang to do the actual work
 * @see switch_to_blog for similarities
 *
 * @param string $lang The language code to switch to 
 * @return void
 **/
function bbl_switch_to_lang( $lang ) {
	global $bbl_locale;
	$bbl_locale->switch_to_lang( $lang );
}

/**
 * Restore the previous lang.
 * 
 * @uses Babble_Locale::restore_lang to do the actual work
 * @see restore_current_blog for similarities
 *
 * @return void
 **/
function bbl_restore_lang() {
	global $bbl_locale;
	$bbl_locale->restore_lang();
}

/**
 * Get the posts which are the translations for the provided 
 * post ID. N.B. The returned array of post objects (and false 
 * values) will include the post for the post ID passed.
 * 
 * @FIXME: Should I filter out the post ID passed?
 *
 * @param int|object $post Either a WP Post object, or a post ID 
 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
 * @access public
 **/
function bbl_get_post_translations( $post ) {
	global $sil_post_types, $sil_lang_map;
	$post = get_post( $post );
	// @FIXME: Is it worth caching here, or can we just rely on the caching in get_objects_in_term and get_posts?
	$transid = sil_get_transid( $post );
	if ( is_wp_error( $transid ) )
		error_log( "Error getting transid: " . print_r( $transid, true ) );
	$post_ids = get_objects_in_term( $transid, 'post_translation' );
	// Get all the translations in one cached DB query
	$posts = get_posts( array( 'include' => $post_ids, 'post_type' => 'any' ) );
	$translations = array();
	foreach ( $posts as & $post ) {
		if ( isset( $sil_lang_map[ $post->post_type ] ) )
			$translations[ $sil_lang_map[ $post->post_type ] ] = $post;
		else
			$translations[ bbl_get_default_lang_code() ] = $post;
	}
	return $translations;
}

/**
 * Returns the post ID for the post in the default language from which 
 * this post was translated.
 *
 * @param int|object $post Either a WP Post object, or a post ID 
 * @return int The ID of the default language equivalent post
 * @access public
 **/
function sil_get_default_lang_post( $post ) {
	$post = get_post( $post );
	$translations = bbl_get_post_translations( $post->ID );
	if ( isset( $translations[ bbl_get_default_lang_code() ] ) )
		return $translations[ bbl_get_default_lang_code() ];
	return false;
}

/**
 * Return the language code for the language a given post is written for/in.
 *
 * @param int|object $post Either a WP Post object, or a post ID 
 * @return string|object Either a language code, or a WP_Error object
 * @access public
 **/
function bbl_get_post_lang( $post ) {
	global $sil_lang_map;
	$post = get_post( $post );
	if ( ! $post )
		return new WP_Error( 'invalid_post', __( 'Invalid Post' ) );
	if ( isset( $sil_lang_map[ $post->post_type ] ) )
		return $sil_lang_map[ $post->post_type ];
	return bbl_get_default_lang_code();
}

/**
 * Return the admin URL to create a new translation in a
 * particular language.
 *
 * @param int|object $default_post The post in the default language to create a new translation for, either WP Post object or post ID
 * @param string $lang The language code 
 * @return string The admin URL to create the new translation
 * @access public
 **/
function sil_get_new_translation_url( $default_post, $lang ) {
	$default_post = get_post( $default_post );
	bbl_switch_to_lang( $lang );
	$transid = sil_get_transid( $default_post );
	$url = admin_url( '/post-new.php' );
	$url = add_query_arg( array( 'post_type' => $default_post->post_type, 'sil_transid' => $transid, 'lang' => $lang ), $url );
	bbl_restore_lang();
	return $url;
}

/**
 * Return the active language objects for the current site. A
 * language object looks like:
 * 'ar' => 
 * 		object(stdClass)
 * 			public 'names' => string 'Arabic'
 * 			public 'code' => string 'ar'
 * 			public 'url_prefix' => string 'ar'
 * 			public 'text_direction' => string 'rtl'
 * 
 * @uses Babble_Languages::get_active_langs to do the actual work
 *
 * @return array An array of Babble language objects
 **/
function bbl_get_active_langs() {
	global $bbl_languages;
	return $bbl_languages->get_active_langs();
}

/**
 * Returns the current language object, respecting any
 * language switches; i.e. if your request was for
 * Arabic, but the language is currently switched to
 * French, this will return French.
 *
 * @return object A Babble language object
 **/
function bbl_get_current_lang() {
	global $bbl_languages;
	return $bbl_languages->get_current_lang();
}

/**
 * Returns the default language code for this site.
 *
 * @return string A language code, e.g. "he_IL"
 **/
function bbl_get_default_lang_code() {
	global $bbl_languages;
	return $bbl_languages->get_default_lang_code();
}

/**
 * Checks whether either the provided language code, 
 * if provided, or the current language code are
 * the default language.
 * 
 * i.e. is this language the default language
 *
 * n.b. the current language could have been switched
 * using bbl_switch_to_lang
 *
 * @param string $lang_code The language code to check (optional) 
 * @return bool True if the default language
 **/
function bbl_is_default_lang( $lang_code = null ) {
	if ( is_null( $lang_code ) )
		$lang_code = bbl_get_current_lang();
	error_log( "Lang: $lang_code->code | " . bbl_get_default_lang_code() );
	return ( bbl_get_default_lang_code() == $lang_code->code );
}

/**
 * Returns the default language code for this site.
 *
 * @return string The language URL prefix set by the admin, e.g. "de"
 **/
function bbl_get_default_lang_url_prefix() {
	global $bbl_languages;
	$code = $bbl_languages->get_default_lang_code();
	return $bbl_languages->get_url_prefix_from_code( $code );
}

/**
 * Returns the language code for the provided URL prefix.
 *
 * @param string $url_prefix The URL prefix to find the language code for 
 * @return string The language code, or false
 **/
function bbl_get_lang_from_prefix( $url_prefix ) {
	global $bbl_languages;
	return $bbl_languages->get_code_from_url_prefix( $url_prefix );
}

?>