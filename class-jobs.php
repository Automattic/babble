<?php

/**
 * Class for handling jobs for the various language
 * translation teams.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Job extends Babble_Plugin {
	
	public function __construct() {
		$this->setup( 'babble-job', 'plugin' );
		
		$this->add_action( 'init', 'init_early', 0 );
	}

	/**
	 * Hooks the WP init action early to register the
	 * job post_type.
	 *
	 * @return void
	 **/
	public function init_early() {
		$labels = array(
			'name' => _x( 'Jobs', 'translation jobs general name', 'babble' ),
			'singular_name' => _x( 'Job', 'translation jobs singular name', 'babble' ),
			'add_new' => _x( 'Add New', 'translation job', 'babble' ),
			'add_new_item' => _x( 'Create New Job', 'translation job', 'babble' ),
			'edit_item' => _x( 'Edit Job', 'translation job', 'babble' ),
			'new_item' => _x( 'New Job', 'translation job', 'babble' ),
			'view_item' => _x( 'View Job', 'translation job', 'babble' ),
			'search_items' => _x( 'Search Jobs', 'translation job', 'babble' ),
			'not_found' => _x( 'No jobs found.', 'translation job', 'babble' ),
			'not_found_in_trash' => _x( 'No jobs found in Trash.', 'translation job', 'babble' ),
			'all_items' => _x( 'All Jobs', 'translation job', 'babble' ),
		);
		$args = array(
			'public' => true,
			'publicly_queryable' => false,
			'show_ui' => false,
			'query_var' => false,
			'labels' => $labels,
			'can_export' => true,
		);
		register_post_type( 'bbl_job', $args );
	}
	
	// PUBLIC METHODS
	// ==============

	
	
	// PRIVATE/PROTECTED METHODS
	// =========================

}

$bbl_job = new Babble_Job();

?>