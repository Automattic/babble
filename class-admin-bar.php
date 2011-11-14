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
		global $bbl_switcher_menu;

		$links = $bbl_switcher_menu->get_switcher_links( 'bbl-admin-bar' );
	
		foreach ( $links as $link ) {
			$link[ 'parent' ] = false;
			$args = $link;
			unset( $args[ 'children' ] );
			$wp_admin_bar->add_menu( $args );
			foreach ( $link[ 'children' ] as $child ) {
				$child[ 'parent' ] = $link[ 'id' ];
				$args = $child;
				unset( $args[ 'children' ] );
				$wp_admin_bar->add_menu( $args );
			}
		}
	}
	
}

$bbl_admin_bar = new Babble_Admin_bar();

?>