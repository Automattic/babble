<?php

/**
 * Functions and (mainly) hooks which don't fit in the various 
 * classes for whatever reason. Consider these various things
 * Private access, for this plugin only, please.
 *
 * Will try to keep the functions in here to an absolute minumum,
 * and to justify the presence of each explictly in the DocBlock.
 *
 * @package Babble
 * @since Alpha 1.1
 */

/**
 * Hooks the WP admin_init action to redirect any requests accessing
 * content which is not in the current language.
 *
 * @return void
 **/
function bbl_admin_init() {
	global $pagenow;
	$taxonomy = @ $_GET[ 'taxonomy' ];

	// Deal with the special URL case of the listing screens for vanilla posts
	if ( ! ( $post_type = @ $_GET[ 'post_type' ] ) && 'edit.php' == $pagenow )
		$post_type = 'post';

	$cur_lang_code = bbl_get_current_lang_code();
	$new_taxonomy = bbl_get_taxonomy_in_lang( $taxonomy, $cur_lang_code );
	if ( $taxonomy != $new_taxonomy ) {
		$url = add_query_arg( array( 'taxonomy' => $new_taxonomy ) );
		wp_redirect( $url );
		exit;
	}
	$new_post_type = bbl_get_post_type_in_lang( $post_type, $cur_lang_code );
	if ( $post_type != $new_post_type ) {
		$url = add_query_arg( array( 'post_type' => $new_post_type ) );
		wp_redirect( $url );
		exit;
	}
}
add_action( 'admin_init', 'bbl_admin_init' );

?>