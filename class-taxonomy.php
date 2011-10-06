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
	 * A simple flag to stop infinite recursion in various places.
	 *
	 * @var boolean
	 **/
	protected $no_recursion;
	
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
		$this->add_action( 'registered_taxonomy', null, null, 3 );
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP init action early
	 *
	 * @return void
	 **/
	public function init_early() {
		// This translation will connect each term with it's translated equivalents
		register_taxonomy( 'term_translation', 'term', array(
			'rewrite' => false,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'label' => __( 'Term Translation ID', 'sil' ),
		) );
		
	}

	/**
	 * Hooks the WP registered_taxonomy action 
	 *
	 * @param string $taxonomy The name of the newly registered taxonomy 
	 * @param string|array $args The object_type(s)
	 * @param array $args The args passed to register the taxonomy
	 * @return void
	 **/
	public function registered_taxonomy( $taxonomy, $object_type, $args ) {
		global $sil_post_types, $sil_lang_map;

		// Don't bother with non-public taxonomies for now
		// If we remove this, we need to avoid dealing with post_translation and term_translation
		if ( ! $args[ 'public' ] || 'post_translation' == $taxonomy || 'term_translation' == $taxonomy )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		if ( ! is_array( $object_type ) )
			$object_type = array_unique( (array) $object_type );

		// @FIXME: Not sure this is the best way to specify languages
		$langs = bbl_get_active_langs();

		// Lose the default language as any existing taxonomies are in that language
		unset( $langs[ bbl_get_default_lang_url_prefix() ] );

		// @FIXME: Is it reckless to convert ALL object instances in $args to an array?
		foreach ( $args as $key => & $arg ) {
			if ( is_object( $arg ) )
				$arg = get_object_vars( $arg );
			// Don't set any args reserved for built-in post_types
			if ( '_' == substr( $key, 0, 1 ) )
				unset( $args[ $key ] );
		}

		$args[ 'rewrite' ] = false;
		unset( $args[ 'name' ] );
		unset( $args[ 'query_var' ] );

		foreach ( $langs as $lang ) {
			$new_args = $args;
			$new_object_type = array();
			foreach( $object_type as $ot )
				$new_object_type[] = strtolower( "{$ot}_{$lang->code}" );
			// var_dump( $new_object_type );
			// var_dump( $sil_post_types );
			// var_dump( $sil_lang_map );
			// exit;

			// @FIXME: Note currently we are in danger of a taxonomy name being longer than 32 chars
			// Perhaps we need to create some kind of map like (taxonomy) + (lang) => (shadow translated taxonomy)
			$new_taxonomy = "{$taxonomy}_{$lang->code}";

			foreach ( $new_args[ 'labels' ] as & $label )
				$label = "$label ({$lang->code})";

			register_taxonomy( $new_taxonomy, $new_object_type, $new_args );
		}

		$this->no_recursion = false;
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