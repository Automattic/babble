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
 * @author Simon Wheatley
 **/
function sil_get_current_lang_code() {
	// Outside the admin area, it's a WP Query Variable
	if ( ! is_admin() )
		return get_query_var( 'lang' ) ? get_query_var( 'lang' ) : SIL_DEFAULT_LANGUAGE;
	// In the admin area, it's a GET param
	return @ $_GET[ 'lang' ] ? $_GET[ 'lang' ] : SIL_DEFAULT_LANGUAGE;
}

/**
 * Get the posts which are the translations for the provided 
 * post ID. N.B. The returned array of post objects (and false 
 * values) will include the post for the post ID passed.
 * 
 * @FIXME: Should I filter out the post ID passed?
 *
 * @param int $post_id The post ID to get the translations for 
 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
 * @author Simon Wheatley
 **/
function sil_get_post_translations( $post_id ) {
	global $sil_post_types, $sil_lang_map;
	$translation_ids = (array) wp_get_object_terms( $post_id, 'post_translation', array( 'fields' => 'ids' ) );
	// "There can be only one" (so we'll just drop the others)
	$translation_id = $translation_ids[ 0 ];
	$post_ids = get_objects_in_term( $translation_id, 'post_translation' );
	// Get all the translations in one cached DB query
	$posts = get_posts( array( 'include' => $post_ids, 'post_type' => 'any' ) );
	$translations = array();
	foreach ( $posts as & $post ) {
		if ( isset( $sil_lang_map[ $post->post_type ] ) )
			$translations[ $sil_lang_map[ $post->post_type ] ] = $post;
		else
			$translations[ SIL_DEFAULT_LANGUAGE ] = $post;
	}
	return $translations;
}

/**
 * Get the language specific permalink for a particular post.
 *
 * DEPRECATED.
 *
 * @param int|object $post Either the WP Post object or a post ID 
 * @param string $lang The language code to get the translation for
 * @return object|string The permalink for the translated post, or a WP_Error if it doesn't exist
 * @author Simon Wheatley
 **/
function sil_get_translation_permalink( $post, $lang ) {
	_doing_it_wrong( __FUNCTION__, 'We should be working this out transparently for the theme author, so they can just call get_permalink and have it work.', 1 );
	$post = get_post( $post );
	if ( ! $post )
		return new WP_Error( 'invalid_post', __( 'Invalid Post' ) );
	// @FIXME: Check the language is valid for this site
	$sequestered_lang = get_query_var( 'lang' );
	set_query_var( 'lang', $lang );
	$permalink = get_permalink( $post->ID );
	set_query_var( 'lang', $sequestered_lang );
	return $permalink;
}

?>