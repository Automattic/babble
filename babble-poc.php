<?php
 
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
 * Rough proof of concept functions. Nothing of any permanence.
 *
 * @package Babble
 * @subpackage Proof of Concept
 * @since Alpha 0
 */

/**
 * Hooks the WP admin_bar_menu action 
 *
 * @param object $wp_admin_bar The WP Admin Bar, passed by reference
 * @return void
 * @access private
 **/
function sil_admin_bar_menu( $wp_admin_bar ) {
	global $wp, $sil_post_types, $sil_lang_map, $bbl_locale;

	// @FIXME: Not sure this is the best way to specify languages
	$alt_langs = bbl_get_active_langs();

	// Remove the current language
	foreach ( $alt_langs as $i => & $alt_lang )
		if ( $alt_lang->code == sil_get_current_lang_code() )
			unset( $alt_langs[ $i ] );

	$current_lang = bbl_get_current_lang();
	$args = array(
		'id' => 'sil_languages',
		'title' => $current_lang->names,
		'href' => '#',
		'parent' => false,
		'meta' => false
	);
	$wp_admin_bar->add_menu( $args );

	$screen = is_admin() ? get_current_screen() : false;
	
	// Create a handy flag for whether we're editing a post
	$editing_post = false;
	if ( is_admin() )
		$editing_post = ( is_admin() && 'post' == $screen->base );
	
	// Create a handy flag for whether we're editing a term
	$editing_term = false;
	if ( is_admin() )
		$editing_term = ( is_admin() && 'edit-tags' == $screen->base );
	
	if ( is_singular() || is_single() || $editing_post ) {
		// error_log( "Get translations" );
		$translations = bbl_get_post_translations( get_the_ID() );
	} else if ( $editing_term ) {
		error_log( "Get term translations" );
		$term = get_term( (int) @ $_REQUEST[ 'tag_ID' ], $screen->taxonomy );
		$translations = bbl_get_term_translations( $term->term_id, $screen->taxonomy );
	}

	foreach ( $alt_langs as $i => & $alt_lang ) {
		$title = sprintf( __( 'Switch to %s', 'sil' ), $alt_lang->names );
		if ( is_admin() ) {
			if ( $editing_post ) {
				if ( isset( $translations[ $alt_lang->code ]->ID ) ) { // Translation exists
					$href = add_query_arg( array( 'lang' => $alt_lang->code, 'post' => $translations[ $alt_lang->code ]->ID ) );
				} else { // Translation does not exist
					$default_post = $translations[ bbl_get_default_lang_code() ];
					$href = bbl_get_new_post_translation_url( $default_post, $alt_lang->code );
					$title = sprintf( __( 'Create for %s', 'sil' ), $alt_lang->names );
				}
			} else if ( $editing_term ) {
				if ( isset( $translations[ $alt_lang->code ]->term_id ) ) { // Translation exists
					$args = array( 
						'lang' => $alt_lang->code, 
						'taxonomy' => $translations[ $alt_lang->code ]->taxonomy, 
						'tag_ID' => $translations[ $alt_lang->code ]->term_id 
					);
					$href = add_query_arg( $args );
				} else { // Translation does not exist
					$default_term = $translations[ bbl_get_default_lang_code() ];
					$href = bbl_get_new_term_translation_url( $default_term, $alt_lang->code, $screen->taxonomy );
					$title = sprintf( __( 'Create for %s', 'sil' ), $alt_lang->names );
				}
			} else {
				$href = add_query_arg( array( 'lang' => $alt_lang->code ) );
			}
		} else if ( is_singular() || is_single() ) {
			if ( isset( $translations[ $alt_lang->code ]->ID ) ) { // Translation exists
				bbl_switch_to_lang( $alt_lang->code );
				$href = get_permalink( $translations[ $alt_lang->code ]->ID );
				bbl_restore_lang();
			} else { // Translation does not exist
				// Generate a URL to create the translation
				$default_post = $translations[ bbl_get_default_lang_code() ];
				$href = bbl_get_new_post_translation_url( $default_post, $alt_lang->code );
				$title = sprintf( __( 'Create for %s', 'sil' ), $alt_lang->names );
			}
			// error_log( "Lang ($lang) HREF ($href)" );
		} else if ( is_front_page() ) { // is_front_page works for language homepages
			// error_log( "Removing home_url filter" );
			remove_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
			$href = home_url( "$alt_lang->url_prefix/" );
			// error_log( "Adding home_url filter" );
			add_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
		}
		$args = array(
			'id' => "sil_languages_{$alt_lang->url_prefix}",
			'href' => $href,
			'parent' => 'sil_languages',
			'meta' => false,
			'title' => $title,
		);
		$wp_admin_bar->add_menu( $args );
	}
}
add_action( 'admin_bar_menu', 'sil_admin_bar_menu', 100 );

?>