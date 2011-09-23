<?php

/*
Plugin Name: Translations Proof of Concept
Plugin URI: http://simonwheatley.co.uk/wordpress/tpoc
Description: Translation proof of concept
Version: 0.1
Author: Simon Wheatley
Author URI: http://simonwheatley.co.uk//wordpress/
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

// @FIXME: Move into class property when creating the actual plugin
define( 'SIL_LANG_REGEX', '|^[^/]+|i' );

/**
 * Hooks the WP pre_update_option_rewrite_rules filter to add
 * the prefix to the rewrite rule regexes to deal with the
 * virtual language dir.
 * 
 * @param array $langs The language codes
 * @return array An array of language codes utilised for this site. 
 **/
function sil_rewrite_rules_filter( $rules ){
	// Add a prefix to the URL to pick up the virtual sub-dir specifying
	// the language. The redirect portion can and should remain perfectly
	// ignorant of it though, as we change it in parse_request.
    foreach( (array) $rules as $regex => $query )
		$new_rules[ '[a-zA-Z_]+/' . $regex ] = $query;
	error_log( "New rules: " . print_r( $new_rules, true ) );
    return $new_rules;
}
add_filter( 'pre_update_option_rewrite_rules', 'sil_rewrite_rules_filter' );

/**
 * Hooks the WP locale filter to switch locales whenever we gosh darned want.
 *
 * @param string $locale The locale 
 * @return string The locale
 **/
function sil_locale( $locale ) {
	// Don't bother doing this in admin
	if ( is_admin() )
		return;
	
	// @FIXME: Copying a huge hunk of code from WP->parse_request here, feels ugly.
	// START: Huge hunk of WP->parse_request
	if ( isset($_SERVER['PATH_INFO']) )
		$pathinfo = $_SERVER['PATH_INFO'];
	else
		$pathinfo = '';
	$pathinfo_array = explode('?', $pathinfo);
	$pathinfo = str_replace("%", "%25", $pathinfo_array[0]);
	$req_uri = $_SERVER['REQUEST_URI'];
	$req_uri_array = explode('?', $req_uri);
	$req_uri = $req_uri_array[0];
	$self = $_SERVER['PHP_SELF'];
	$home_path = parse_url(home_url());
	if ( isset($home_path['path']) )
		$home_path = $home_path['path'];
	else
		$home_path = '';
	$home_path = trim($home_path, '/');

	// Trim path info from the end and the leading home path from the
	// front.  For path info requests, this leaves us with the requesting
	// filename, if any.  For 404 requests, this leaves us with the
	// requested permalink.
	$req_uri = str_replace($pathinfo, '', $req_uri);
	$req_uri = trim($req_uri, '/');
	$req_uri = preg_replace("|^$home_path|", '', $req_uri);
	$req_uri = trim($req_uri, '/');
	$pathinfo = trim($pathinfo, '/');
	$pathinfo = preg_replace("|^$home_path|", '', $pathinfo);
	$pathinfo = trim($pathinfo, '/');
	$self = trim($self, '/');
	$self = preg_replace("|^$home_path|", '', $self);
	$self = trim($self, '/');

	// The requested permalink is in $pathinfo for path info requests and
	//  $req_uri for other requests.
	if ( ! empty($pathinfo) && !preg_match('|^.*' . $wp_rewrite->index . '$|', $pathinfo) ) {
		$request = $pathinfo;
	} else {
		// If the request uri is the index, blank it out so that we don't try to match it against a rule.
		if ( $req_uri == $wp_rewrite->index )
			$req_uri = '';
		$request = $req_uri;
	}
	// END: Huge hunk of WP->parse_request

	// @FIXME: Should probably check the available languages here
	// error_log( "Locale (before): $locale for request ($request)" );
	// @FIXME: Should I be using $GLOBALS['request] here? Feels odd.
	if ( preg_match( SIL_LANG_REGEX, $request, $matches ) )
		$locale = $matches[ 0 ];
	error_log( "Locale (after): $locale" );
	return $locale;
}
add_filter( 'locale', 'sil_locale' );

/**
 * Hooks the WP sil_languages filter. Temporary for POC.
 *
 * @param array $langs The language codes
 * @return array An array of language codes utilised for this site. 
 **/
function sil_languages( $langs ) {
	$langs[] = 'de_DE';
	// $langs[] = 'he_IL';
	return $langs;
}
add_filter( 'sil_languages', 'sil_languages' );

/**
 * Hooks the WP registered_post_type action. 
 * 
 * N.B. THIS HOOK IS NOT YET IMPLEMENTED
 *
 * @param string $post_type The post type which has just been registered. 
 * @param array $args The arguments with which the post type was registered
 * @return void
 **/
function sil_registered_post_type( $post_type, $args ) {
	// When we turn this into classes we can avoid a global here
	global $sil_syncing; 

	// Don't bother with non-public post_types for now
	// @FIXME: This may need to change for menus?
	if ( ! $args->public )
		return;
	
	if ( $sil_syncing )
		return;
	$sil_syncing = true;
	
	// @FIXME: Not sure this is the best way to specify languages
	$langs = apply_filters( 'sil_languages', array( 'en' ) );
	
	// Lose the default language as the existing post_types are English
	// @FIXME: Need to specify the default language somewhere
	$default_lang = array_search( 'en', $langs );
	unset( $langs[ $default_lang ] );
	
	// $args is an object at this point, but register_post_type needs an array
	$args = get_object_vars( $args );
	// @FIXME: Is it reckless to convert ALL object instances in $args to an array?
	foreach ( $args as $key => & $arg ) {
		if ( is_object( $arg ) )
			$arg = get_object_vars( $arg );
		// Don't set any args reserved for built-in post_types
		if ( '_' == substr( $key, 0, 1 ) )
			unset( $args[ $key ] );
	}
	
	$args[ 'supports' ] = array(
		'title',
		'editor',
		'comments',
		'revisions',
		'trackbacks',
		'author',
		'excerpt',
		'page-attributes',
		'thumbnail',
		'custom-fields'
	);
	
	foreach ( $langs as $lang ) {
		// @FIXME: Note currently we are in danger of a post_type name being longer than 20 chars
		// Perhaps we need to create some kind of map like (post_type) + (lang) => (shadow translated post_type)
		$post_type = $post_type . "_$lang";
	
		foreach ( $args[ 'labels' ] as & $label )
			$label = "$label ($lang)";

		$result = register_post_type( $post_type, $args );
		if ( is_wp_error( $result ) )
			error_log( "Error creating shadow post_type for $post_type: " . print_r( $result, true ) );
	}

	$sil_syncing = false;
}
add_action( 'registered_post_type', 'sil_registered_post_type', null, 2 );

/**
 * Hooks the WP admin_init action late.
 * 
 * Temporary for POC.
 *
 * @return void
 **/
function sil_admin_init_late() {
	if ( is_admin() ) {
		$post_types = get_post_types();
		foreach ( $post_types as $post_type )
			add_meta_box( 'sil_translation_ref', 'Translation Reference', 'sil_translation_ref', $post_type );
	}
}
add_action( 'admin_init', 'sil_admin_init_late', 20 );

/**
 * Callback function to provide HTML for the translation reference ID.
 * 
 * Temporary for POC.
 *
 * @return void
 * @author Simon Wheatley
 **/
function sil_translation_ref() {
	$default_post = get_post_meta( get_the_ID(), '_sil_default_post', true );
?>
	<p><label for="sil_default_post">Default lang post:</label> <input type="text" name="sil_default_post" value="<?php echo esc_attr( $default_post ); ?>" />
	</p>
	<p class="description"><strong>Temporary for Proof of Concept:</strong> add the ID of the post/page/whatever in the default language that this post/page/whatever is a translation of. If you are looking at the default language, this box should be blank.</p>
<?php
}

/**
 * Hooks the WP save_post action 
 *
 * @param int $post_id The ID of the post 
 * @param object $post The post
 * @return void
 **/
function sil_save_post( $post_id ) {
	$default_post = @ $_POST[ 'sil_default_post' ];
	update_post_meta( $post_id, '_sil_default_post', $default_post );
}
add_action( 'save_post', 'sil_save_post' );

/**
 * Hooks the WP parse_request action 
 *
 * @param object $wp WP object, passed by reference (so no need to return)
 * @return void
 **/
function sil_parse_request( $wp ) {
	
	// Do NOT mess around in the admin area (for the moment)
	if ( is_admin() )
		return;
	
	// Check the language
 	// @FIXME: If we want to cater for non-pretty permalinks we need to handle a GET param here to specify lang
	// @FIXME: Would explode be more efficient here?
	if ( preg_match( SIL_LANG_REGEX, $wp->request, $matches ) )
		$lang = $matches[ 0 ];
	else
		return; // Bail. Not sure what could trigger this though (site root URL?)

	// If we're asking for the default content, it's fine
	if ( 'en' == $lang )
		return;

	// Sequester the original query, in case we need it to get the default content later
	$wp->query_vars[ 'sil_original_query' ] = $wp->query_vars;

	// Now swap the query vars so we get the content in the right language post_type
	
	// @FIXME: Do I need to change $wp->matched query? I think $wp->matched_rule is fine?
	// @FIXME: Danger of post type slugs clashing??
	if ( isset( $wp->query_vars[ 'pagename' ] ) && $wp->query_vars[ 'pagename' ] ) {
		// Substitute post_type for 
		$wp->query_vars[ 'name' ] = $wp->query_vars[ 'pagename' ];
		$wp->query_vars[ "page_$lang" ] = $wp->query_vars[ 'pagename' ];
		$wp->query_vars[ 'post_type' ] = "page_$lang";
		unset( $wp->query_vars[ 'page' ] );
		unset( $wp->query_vars[ 'pagename' ] );
	} elseif ( isset( $wp->query_vars[ 'year' ] ) ) { 
		// @FIXME: This is not a reliable way to detect queries for the 'post' post_type.
		$wp->query_vars[ 'post_type' ] = "post_$lang";
	} elseif ( isset( $wp->query_vars[ 'post_type' ] ) ) { 
		$wp->query_vars[ 'post_type' ] = $wp->query_vars[ 'post_type' ] . "_$lang";
	}

	// error_log( "Query vars: " . print_r( $wp, true ) );
}
add_action( 'parse_request', 'sil_parse_request' );

/**
 * Returns the post ID for the post in the default language from which 
 * this post was translated.
 *
 * @param int $post_id The post ID to look up the default language equivalent of 
 * @return int The ID of the default language equivalent post
 * @author Simon Wheatley
 **/
function sil_get_default_lang_post_id( $post_id ) {
	return get_post_meta( $post_id, '_sil_default_post', true );
}

/**
 * Hooks the WP the_posts filter. 
 * 
 * Check the post_title, post_excerpt, post_content and substitute from
 * the default language where appropriate.
 *
 * @param array $posts The posts retrieved by WP_Query, passed by reference 
 * @return array The posts
 **/
function sil_the_posts( $posts ) {
	$subs_index = array();
	foreach ( $posts as & $post )
		if ( empty( $post->post_title ) || empty( $post->post_excerpt ) || empty( $post->post_content ) )
			$subs_index[ $post->ID ] = sil_get_default_lang_post_id( $post->ID );
	$subs_posts = get_posts( array( 'include' => array_values( $subs_index ), 'post_status' => 'publish' ) );
	// @FIXME: Check the above get_posts call results are cached somewhere… I think they are
	foreach ( $posts as & $post ) {
		// @FIXME: I'm assuming this get_post call is cached, which it seems to be
		$default_post = get_post( $subs_index[ $post->ID ] );
		if ( empty( $post->post_title ) )
			$post->post_title = 'Fallback: ' . $default_post->post_title;
		if ( empty( $post->post_excerpt ) )
			$post->post_excerpt = "Fallback excerpt\n\n" . $default_post->post_excerpt;
		if ( empty( $post->post_content ) )
			$post->post_content = "Fallback content\n\n" . $default_post->post_content;
	}
	return $posts;
}
add_action( 'the_posts', 'sil_the_posts' );



?>