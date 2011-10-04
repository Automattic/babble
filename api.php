<?php

/**
 * Translations and languages API.
 *
 * @package WordPress
 * @subpackage Languages
 * @since ???
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
		return get_query_var( 'lang' ) ? get_query_var( 'lang' ) : SIL_DEFAULT_LANG;
	// In the admin area, it's a GET param
	$current_user = wp_get_current_user();
	return get_user_meta( $current_user->ID, 'bbl_admin_lang', true ) ? get_user_meta( $current_user->ID, 'bbl_admin_lang', true ) : SIL_DEFAULT_LANG;
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
	global $babble_locale;
	$babble_locale->switch_to_lang( $lang );
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
	global $babble_locale;
	$babble_locale->restore_lang();
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
function sil_get_post_translations( $post ) {
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
			$translations[ SIL_DEFAULT_LANG ] = $post;
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
	$translations = sil_get_post_translations( $post->ID );
	if ( isset( $translations[ SIL_DEFAULT_LANG ] ) )
		return $translations[ SIL_DEFAULT_LANG ];
	return false;
}

/**
 * Return the language code for the language a given post is written for/in.
 *
 * @param int|object $post Either a WP Post object, or a post ID 
 * @return string|object Either a language code, or a WP_Error object
 * @access public
 **/
function sil_get_post_lang( $post ) {
	global $sil_lang_map;
	$post = get_post( $post );
	if ( ! $post )
		return new WP_Error( 'invalid_post', __( 'Invalid Post' ) );
	if ( isset( $sil_lang_map[ $post->post_type ] ) )
		return $sil_lang_map[ $post->post_type ];
	return SIL_DEFAULT_LANG;
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
 * 			public 'code_short' => string 'ar'
 * 			public 'text_direction' => string 'rtl'
 * 
 * @uses Babble_Languages::get_active_langs to do the actual work
 *
 * @return array An array of language objects
 **/
function bbl_get_active_langs() {
	global $babble_languages;
	return $babble_languages->get_active_langs();
}

?>