jQuery( function ( $ ) {
	if ( ! bbl_post_public.is_default_lang ) {
		// Fixup the side admin menu, which is confused by our additional language post types.
		if ( bbl_post_public.menu_id ) {
			$( bbl_post_public.menu_id + ', ' + bbl_post_public.menu_id + '>a' )
				.addClass( 'wp-has-current-submenu wp-menu-open' )
				.removeClass( 'wp-not-current-submenu' );
		}
	}

	if ( bbl_post_public.is_bbl_post_type || ! bbl_post_public.is_default_lang ) {
		// Remove the add button next to the title for non-default languages
		$( 'h2 .add-new-h2' ).remove();
		// Remove Bulk Edit and Quick Edit options
		$( '#posts-filter option[value="edit"], #posts-filter td.column-title span.inline' ).remove();
	}

	$( '#original_post_content' ).prop( 'readOnly', true );

} );
