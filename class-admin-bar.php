<?php

/**
 * Class to handle adding our links to the admin bar.
 *
 * @package Babble
 * @since 0.2
 */
class Babble_Admin_bar extends Babble_Plugin {
	
	function __construct() {
		$this->setup( 'babble-switcher-menu', 'plugin' );
		$this->add_action( 'admin_bar_menu', null, 100 );
	}

	/**
	 * Hooks the WP admin_bar_menu action 
	 *
	 * @param object $wp_admin_bar The WP Admin Bar, passed by reference
	 * @return void
	 **/
	public function admin_bar_menu( $wp_admin_bar ) {
		$links = bbl_get_switcher_links( 'bbl-admin-bar' );
		
		$current_lang = bbl_get_current_lang();

		// Remove the current language
		unset( $links[ $current_lang->code ] );
		
		$parent_id = "bbl-admin-bar-{$current_lang->url_prefix}";
		$wp_admin_bar->add_menu( array(
			'children' => array(),
			'href' => '#',
			'id' => $parent_id,
			'meta' => array( 'class' => "bbl_lang_{$current_lang->code} bbl_lang" ),
			'title' => $current_lang->names,
			'parent' => false,
		) );
		foreach ( $links as & $link ) {
			$link[  'parent' ] = $parent_id;
			$wp_admin_bar->add_menu( $link );
		}
	}
	
}

global $bbl_admin_bar;
$bbl_admin_bar = new Babble_Admin_bar();

?>