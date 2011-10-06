<?php

/**
 * Manages the translations for taxonomies.
 *
 * @package WordPress
 * @subpackage Babble
 * @since Alpha 1.2
 */
class Babble_Taxonomies extends Babble_Plugin {
	
	/**
	 * The current version for purposes of rewrite rules, any 
	 * DB updates, cache busting, etc
	 *
	 * @var int
	 **/
	protected $version = 1;
	
	/**
	 * Setup any add_action or add_filter calls. Initiate properties.
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->setup( 'babble-taxonomy', 'plugin' );
		$this->add_action( 'init', 'init_early', 0 );
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP init action early
	 *
	 * @return void
	 **/
	public function init_early() {
	}
	
	// CALLBACKS
	// =========
	
	// PUBLIC METHODS
	// ==============
	
	// PRIVATE/PROTECTED METHODS
	// =========================

}

$bbl_Taxonomies = new Babble_Taxonomies();

?>