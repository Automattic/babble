<?php

/**
 * Translations and languages API.
 *
 * @package Babble
 * @since Alpha 1
 */

/*  Copyright 2013 Code for the People

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
 * Returns the current content language code.
 *
 * @FIXME: Currently does not check for language validity, though perhaps we should check that elsewhere and redirect?
 *
 * @return string A language code
 * @access public
 **/
function bbl_get_current_content_lang_code() {
	global $bbl_locale;
	return $bbl_locale->get_content_lang();
}

/**
 * Returns the current interface language code.
 *
 * @FIXME: Currently does not check for language validity, though perhaps we should check that elsewhere and redirect?
 *
 * @return string A language code
 * @access public
 **/
function bbl_get_current_interface_lang_code() {
	global $bbl_locale;
	return $bbl_locale->get_interface_lang();
}

/**
 * Returns the current (content) language code.
 *
 * @return string A language code
 * @access public
 **/
function bbl_get_current_lang_code() {
	return bbl_get_current_content_lang_code();
}

/**
 * Given a lang object or lang code, this checks whether the
 * language is public or not.
 *
 * @param string $lang_code A language code
 * @return boolean True if public
 * @access public
 **/
function bbl_is_public_lang( $lang_code ) {
	global $bbl_languages;
	return $bbl_languages->is_public_lang( $lang_code );
}

/**
 * Set the current (content) lang.
 *
 * @uses Babble_Locale::switch_to_lang to do the actual work
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
 * Get the terms which are the translations for the provided
 * term ID. N.B. The returned array of term objects (and false
 * values) will include the term for the term ID passed.
 *
 * @param int|object $term Either a WP Term object, or a term_id
 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
 * @access public
 **/
function bbl_get_term_translations( $term, $taxonomy ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_term_translations( $term, $taxonomy );
}

/**
 * Get the posts which are the translation jobs for the provided
 * term ID.
 *
 * @param int|object $term Either a WP Term object, or a term_id
 * @return array An array keyed by the site languages, each key containing a WP Post object
 * @access public
 **/
function bbl_get_term_jobs( $term, $taxonomy ) {
	global $bbl_jobs;
	return $bbl_jobs->get_term_jobs( $term, $taxonomy );
}

/**
 * Return the admin URL to create a new translation for a term in a
 * particular language.
 *
 * @param int|object $default_term The term in the default language to create a new translation for, either WP Post object or post ID
 * @param string $lang The language code
 * @param string $taxonomy The taxonomy
 * @return string The admin URL to create the new translation
 * @access public
 **/
function bbl_get_new_term_translation_url( $default_term, $lang, $taxonomy = null ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_new_term_translation_url( $default_term, $lang, $taxonomy );
}

/**
 * Returns the language code associated with a particular taxonomy.
 *
 * @param string $taxonomy The taxonomy to get the language for
 * @return string The lang code
 **/
function bbl_get_taxonomy_lang_code( $taxonomy ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_taxonomy_lang_code( $taxonomy );
}

/**
 * Return the base taxonomy (in the default language) for a
 * provided taxonomy.
 *
 * @param string $taxonomy The name of a taxonomy
 * @return string The name of the base taxonomy
 **/
function bbl_get_base_taxonomy( $taxonomy ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_base_taxonomy( $taxonomy );
}

/**
 * Returns the equivalent taxonomy in the specified language.
 *
 * @param string $taxonomy A taxonomy to return in a given language
 * @param string $lang_code The language code for the required language (optional, defaults to current)
 * @return string The taxonomy name
 **/
function bbl_get_taxonomy_in_lang( $taxonomy, $lang_code = null ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_taxonomy_in_lang( $taxonomy, $lang_code );
}

/**
 * Test whether a particular taxonomy is translated or not.
 *
 * @param string $taxonomy The name of the taxonomy to check
 * @return bool True if this is a translated taxonomy
 */
function bbl_is_translated_taxonomy( $taxonomy ) {
	return (bool) apply_filters( 'bbl_translated_taxonomy', true, $taxonomy );
}

/**
 * Test whether a particular post type is translated or not.
 *
 * @param string $post_type The name of the post type to check
 * @return bool True if this is a translated post type
 */
function bbl_is_translated_post_type( $post_type ) {
	return (bool) apply_filters( 'bbl_translated_post_type', true, $post_type );
}

/**
 * Returns a taxonomy slug translated into a particular language.
 *
 * @param string $slug The slug to translate
 * @param string $lang_code The language code for the required language (optional, defaults to current)
 * @return string A translated slug
 **/
function bbl_get_taxonomy_slug_in_lang( $slug, $lang_code = null ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_slug_in_lang( $slug, $lang_code );
}

/**
 * Get the posts which are the translations for the provided
 * post ID. N.B. The returned array of post objects (and false
 * values) will include the post for the post ID passed.
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
 * @access public
 **/
function bbl_get_post_translations( $post ) {
	global $bbl_post_public;
	return $bbl_post_public->get_post_translations( $post );
}

/**
 * Get the posts which are the translation jobs for the provided
 * post ID.
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @return array Either an array keyed by the site languages, each key containing a WP Post object
 * @access public
 **/
function bbl_get_incomplete_post_jobs( $post ) {
	global $bbl_jobs;
	return $bbl_jobs->get_incomplete_post_jobs( $post );
}

/**
 * Returns the post ID for the post in the default language from which
 * this post was translated.
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @return int The ID of the default language equivalent post
 * @access public
 **/
function bbl_get_default_lang_post( $post ) {
	global $bbl_post_public;
	return $bbl_post_public->get_default_lang_post( $post );
}

/**
 * Return the language code for the language a given post is written for/in.
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @return string|object Either a language code, or a WP_Error object
 * @access public
 **/
function bbl_get_post_lang_code( $post ) {
	global $bbl_post_public;
	return $bbl_post_public->get_post_lang_code( $post );
}

/**
 * Return the admin URL to create a new translation for a post in a
 * particular language.
 *
 * @param int|object $default_post The post in the default language to create a new translation for, either WP Post object or post ID
 * @param string $lang The language code
 * @return string The admin URL to create the new translation
 * @access public
 **/
function bbl_get_new_post_translation_url( $default_post, $lang ) {
	global $bbl_post_public;
	return $bbl_post_public->get_new_post_translation_url( $default_post, $lang );
}

/**
 * Return the post type name for the equivalent post type for the
 * supplied original post type in the requested language.
 *
 * @param string $post_type The originating post type
 * @param string $lang_code The language code for the required language (optional, defaults to current)
 * @return string A post type name, e.g. "page" or "post"
 **/
function bbl_get_post_type_in_lang( $original_post_type, $lang_code = null ) {
	global $bbl_post_public;
	if ( is_null( $lang_code ) )
		$lang_code = bbl_get_current_lang_code();
	return $bbl_post_public->get_post_type_in_lang( $original_post_type, $lang_code );
}

add_filter( 'bbl_get_content_post_type', 'bbl_get_post_type_in_lang' );

/**
 * Is the query for a single page or translation or a single page?
 *
 * If the $page parameter is specified, this function will additionally
 * check if the query is for one of the pages specified.
 *
 * @see is_page()
 *
 * @param mixed $page Page ID, title, slug, or array of such.
 * @return bool
 */
function bbl_is_page( $page = '' ) {
	$base_page = bbl_get_post_in_lang( get_the_ID(), bbl_get_default_lang_code() );
	if ( ! $page )
		return 'page' == $base_page->post_type;
	if ( is_int( $page ) )
		return $page == $base_page->ID;
	if ( $page == $base_page->post_name )
		return true;
	if ( $page == $base_page->post_title )
		return true;
	if ( $page == (string) $base_page->ID )
		return true;
	return false;
}

/**
 * Returns the post in a particular language
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @param string $lang_code The language code for the required language
 * @param boolean $fallback If true: if a post is not available, fallback to the default language content (defaults to true)
 * @return object|boolean The WP Post object, or if $fallback was false and no post then returns false
 **/
function bbl_get_post_in_lang( $post, $lang_code, $fallback = true ) {
	global $bbl_post_public;
	return $bbl_post_public->get_post_in_lang( $post, $lang_code, $fallback );
}

/**
 * Returns the term in a particular language
 *
 * @param int|object $term Either a term object, or a term ID
 * @param string $taxonomy The term taxonomy
 * @param string $lang_code The language code for the required language
 * @param boolean $fallback If true: if a term is not available, fallback to the default language content (defaults to true)
 * @return object|boolean The term object, or if $fallback was false and no term then returns false
 **/
function bbl_get_term_in_lang( $term, $taxonomy, $lang_code, $fallback = true ) {
	global $bbl_taxonomies;
	return $bbl_taxonomies->get_term_in_lang( $term, $taxonomy, $lang_code, $fallback );
}

/**
 * Returns a post_type slug translated into a particular language.
 *
 * @param string $slug The slug to translate
 * @param string $lang_code The language code for the required language (optional, defaults to current)
 * @return string A translated slug
 **/
function bbl_get_post_type_slug_in_lang( $slug, $lang_code = null ) {
	global $bbl_post_public;
	$lang = bbl_get_lang( $lang_code );
	return $bbl_post_public->get_slug_in_lang( $slug, $lang );
}

/**
 * Echoes the title of a post, in the requested language (if available).
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @param string $lang_code The code for the language the title is requested in
 * @param bool $fallback Whether to provide a fallback title in the default language if the requested language is unavailable (defaults to false)
 * @return void
 **/
function bbl_the_title_in_lang( $post = null, $lang_code = null, $fallback = false ) {
	echo bbl_get_the_title_in_lang( $post, $lang_code, $fallback );
}

/**
 * Returns the title of a post, in the requested language (if available).
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @param string $lang_code The code for the language the title is requested in
 * @param bool $fallback Whether to provide a fallback title in the default language if the requested language is unavailable (defaults to false)
 * @return void
 **/
function bbl_get_the_title_in_lang( $post = null, $lang_code = null, $fallback = false ) {
	$post = get_post( $post );
	if ( is_null( $lang_code ) )
		$lang_code = bbl_get_current_lang_code();

	// Hopefully we find the post in the right language
	if ( $lang_post = bbl_get_post_in_lang( $post, $lang_code, $fallback ) )
		return apply_filters( 'bbl_the_title_in_lang', get_the_title( $lang_post->ID ), $lang_code );

	// We have failed…
	return '';
}

/**
 * Echoes the permalink of a post, in the requested language (if available).
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @param string $lang_code The code for the language the title is requested in
 * @param bool $fallback Whether to provide a fallback title in the default language if the requested language is unavailable (defaults to false)
 * @return void
 **/
function bbl_the_permalink_in_lang( $post = null, $lang_code = null, $fallback = false ) {
	echo bbl_get_the_permalink_in_lang( $post, $lang_code, $fallback );
}

/**
 * Returns the permalink of a post, in the requested language (if available).
 *
 * @param int|object $post Either a WP Post object, or a post ID
 * @param string $lang_code The code for the language the title is requested in
 * @param bool $fallback Whether to provide a fallback title in the default language if the requested language is unavailable (defaults to false)
 * @return void
 **/
function bbl_get_the_permalink_in_lang( $post = null, $lang_code = null, $fallback = false ) {
	$post = get_post( $post );
	if ( is_null( $lang_code ) )
		$lang_code = bbl_get_current_lang_code();

	// Hopefully we find the post in the right language
	if ( $lang_post = bbl_get_post_in_lang( $post, $lang_code, $fallback ) )
		return apply_filters( 'bbl_permalink_in_lang', get_permalink( $lang_post->ID ), $lang_code );

	// We have failed…
	return '';
}

/**
 * Returns the link to a post type in a particular language.
 *
 * @param string $post_type A post type for which you want a translated archive link
 * @param string $lang_code The code for the language the link is requested in
 * @return void
 **/
function bbl_get_post_type_archive_link_in_lang( $post_type, $lang_code = null ) {
	if ( is_null( $lang_code ) )
		$lang_code = bbl_get_current_lang_code();
	bbl_switch_to_lang( $lang_code );
	$lang_post_type = bbl_get_post_type_in_lang( $post_type, $lang_code );
	$link = get_post_type_archive_link( $lang_post_type );
	bbl_restore_lang();
	return apply_filters( 'bbl_post_type_archive_link_in_lang', $link );
}

/**
 * Return the base post type (in the default language) for a
 * provided post type.
 *
 * @param string $post_type The name of a post type
 * @return string The name of the base post type
 **/
function bbl_get_base_post_type( $post_type ) {
	global $bbl_post_public;
	return $bbl_post_public->get_base_post_type( $post_type );
}

/**
 * Return all the base post types (in the default language).
 *
 * @return array An array of post_type objects
 **/
function bbl_get_base_post_types() {
	global $bbl_post_public;
	return $bbl_post_public->get_base_post_types();
}

/**
 * Returns an array of all the shadow post types associated with
 * this post type.
 *
 * @param string $base_post_type The post type to look up shadow post types for
 * @return array The names of all the related shadow post types
 **/
function bbl_get_shadow_post_types( $base_post_type ) {
	global $bbl_post_public;
	return $bbl_post_public->get_shadow_post_types( $base_post_type );
}

/**
 * Return the active language objects for the current site. A
 * language object looks like:
 * 'ar' =>
 * 		object(stdClass)
 * 			public 'name' => string 'Arabic'
 * 			public 'code' => string 'ar'
 * 			public 'url_prefix' => string 'ar'
 * 			public 'text_direction' => string 'rtl'
 * 			public 'display_name' => string 'Arabic'
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
 * Returns the requested language object.
 *
 * @param string $code A language code, e.g. "fr_BE"
 * @return object|boolean A Babble language object
 **/
function bbl_get_lang( $lang_code ) {
	global $bbl_languages;
	return $bbl_languages->get_lang( $lang_code );
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
 * Returns the default language for this site.
 *
 * @return object A language object
 **/
function bbl_get_default_lang() {
	global $bbl_languages;
	return $bbl_languages->get_default_lang();
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
		$lang = bbl_get_current_lang();
	else if ( is_string( $lang_code ) ) // In case someone passes a lang object
		$lang = bbl_get_lang( $lang_code );
	return ( bbl_get_default_lang_code() == $lang->code );
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

/**
 * Returns the language code for the provided URL prefix.
 *
 * @param string $lang_code The language code to look up
 * @return string The language URL prefix set by the admin, e.g. "de"
 **/
function bbl_get_prefix_from_lang_code( $lang_code ) {
	global $bbl_languages;
	return $bbl_languages->get_url_prefix_from_code( $lang_code );
}

/**
 * Returns the switch links for the current content.
 *
 * @param string $id_prefix A prefix to the ID for each item
 * @return array An array of admin menu nodes
 **/
function bbl_get_switcher_links( $id_prefix = '' ) {
	global $bbl_switcher_menu;
	return $bbl_switcher_menu->get_switcher_links( $id_prefix );
}

/**
 * Start logging for Babble
 *
 * @return void
 **/
function bbl_start_logging() {
	global $bbl_log;
	$bbl_log->logging = true;
}

/**
 * Stop logging for Babble
 *
 * @return void
 **/
function bbl_stop_logging() {
	global $bbl_log;
	$bbl_log->logging = false;
}

/**
 * Log a message.
 *
 * @param string $msg Log this message
 * @param bool $force If false, logging must have been initiated with bbl_start_logging
 * @return void
 **/
function bbl_log( $msg, $force = false ) {
	global $bbl_log;
	if ( $bbl_log || $force ) {
		$bbl_log->log( $msg );
	}
}

/**
 * Whether Babble is logging right now.
 *
 * @return boolean True for yes, natch
 **/
function bbl_is_logging() {
	global $bbl_log;
	return $bbl_log->logging;
}

?>