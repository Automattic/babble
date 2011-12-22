jQuery( document ).ready( function( $ ) {
	$( bbl_post_public.menu_id + ', ' + bbl_post_public.menu_id + '>a' )
		.addClass( 'wp-has-current-submenu' )
		.addClass( 'wp-menu-open' )
		.removeClass( 'wp-not-current-submenu' );
} );