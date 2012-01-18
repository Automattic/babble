jQuery( document ).ready( function( $ ) {
	if ( ! bbl_post_public.is_default_lang ) {
		// Fixup the side admin menu, which is confused by our
		// additional language post types.
		if ( bbl_post_public.menu_id ) {
			$( bbl_post_public.menu_id + ', ' + bbl_post_public.menu_id + '>a' )
				.addClass( 'wp-has-current-submenu' )
				.addClass( 'wp-menu-open' )
				.removeClass( 'wp-not-current-submenu' );
		}
		// Remove the add button next to the title for non-default languages
		$( 'h2 .add-new-h2' ).remove();
	}
} );
