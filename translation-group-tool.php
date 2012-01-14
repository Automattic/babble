<?php
/*
Plugin Name: Babble: Translation Group Tool
Plugin URI: http://simonwheatley.co.uk/wordpress/btgt
Description: This provides a page in Admin > Tools which allows you to see and edit Babble translation associations.
Version: 0.1
Author: Simon Wheatley
Author URI: http://simonwheatley.co.uk/wordpress/
*/
 
/*  Copyright 2012 Simon Wheatley

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

require_once( 'class-plugin.php' );

/**
 * Handles the display and functionality of the translation group tool.
 * 
 * @package BabbleTranslationGroupTool
 * @author Simon Wheatley
 **/
class BabbleTranslationGroupTool extends Babble_Plugin {
	

	/**
	 * Initiate!
	 *
	 * @return void
	 * @access public
	 **/
	function __construct() {
		$this->setup( 'babble-tgt', 'plugin' );
		$this->add_action( 'admin_menu' );
		$this->add_action( 'load-tools_page_btgt', 'load_tools_page' );
	}
	
	// HOOKS AND ALL THAT
	// ==================
	
	/**
	 * Hooks the WP admin_menu action to add a menu to
	 * the Tools section.
	 *
	 * @return void
	 **/
	public function admin_menu() {
		add_management_page( __( 'Translation Groups', 'babble-tgt' ), __( 'Translation Groups', 'babble-tgt' ), 'manage_options', 'btgt', array( $this, 'tools_page' ) );
	}

	/**
	 * Hooks the dynamic load-* action called when
	 * this tools page loads.
	 *
	 * @return void
	 **/
	public function load_tools_page() {
		if ( ! $action = ( isset( $_GET[ 'btgt_action' ] ) ) ? $_GET[ 'btgt_action' ] : false )
			return;
		
		$obj_id = ( isset( $_GET[ 'obj_id' ] ) ) ? $_GET[ 'obj_id' ] : false;
		$wp_nonce = ( isset( $_GET[ '_wpnonce' ] ) ) ? $_GET[ '_wpnonce' ] : false;
		error_log( "SW: Action: $action" );
		switch ( $action ) {
			case 'delete_from_groups':
				if ( ! wp_verify_nonce( $wp_nonce, "btgt_delete_from_groups_$obj_id" ) ) {
					$this->set_admin_error( 'Sorry, went wrong. Please try again.' );
					return;
				}
				wp_delete_object_term_relationships( $obj_id, 'post_translation' );
				$this->set_admin_notice( "Deleted term relationships for $obj_id" );
			case 'delete_post':
				wp_delete_object_term_relationships( $obj_id, 'post_translation' );
			case 'trash_post':
				wp_delete_object_term_relationships( $obj_id, 'post_translation' );
		}
	}

	// CALLBACKS
	// =========

	/**
	 * Callback function for the HTML for the tools page.
	 *
	 * @return void
	 **/
	public function tools_page() {
		$vars = array();
		$this->render_admin( 'translation-groups.php', $vars );
	}

	// UTILITIES
	// =========

	/**
	 * Get a link to trash a particular post.
	 *
	 * @param int $post_id The ID of the post to trash
	 * @param string $action The action for this link
	 * @return string A Nonced action URL
	 **/
	protected function get_action_link( $obj_id, $action ) {
		$args = array( 
			'btgt_action' => $action,
			'obj_id' => $obj_id,
			'lang' => bbl_get_default_lang_code(), 
		);
		return wp_nonce_url( add_query_arg( $args ), "btgt_{$action}_$obj_id" );
	}



} // END BabbleTranslationGroupTool class 

$bbl_translation_group_tool = new BabbleTranslationGroupTool();

?>