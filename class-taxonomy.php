<?php

/**
 * Manages the translations for taxonomies.
 *
 * @package Babble
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
	 * The shadow taxonomies created to handle the translated terms.
	 *
	 * @var array
	 **/
	protected $taxonomies;

	/**
	 * The languages represented by each of the shadow taxonomies.
	 *
	 * @var array
	 **/
	protected $lang_map;
	
	/**
	 * Setup any add_action or add_filter calls. Initiate properties.
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->setup( 'babble-taxonomy', 'plugin' );
		$this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'parse_request' );
		$this->add_action( 'registered_taxonomy', null, null, 3 );
		$this->add_filter( 'term_link', null, null, 3 );
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP init action early
	 *
	 * @return void
	 **/
	public function init_early() {
		// Ensure we catch any existing language shadow taxonomies already registered
		if ( is_array( $this->taxonomies ) )
			$taxonomies = array_merge( array( 'post_tag', 'category' ), array_keys( $this->taxonomies ) );
		else
			$taxonomies = array( 'post_tag', 'category' );
		// This translation will connect each term with it's translated equivalents
		register_taxonomy( 'term_translation', 'term', array(
			'rewrite' => false,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'label' => __( 'Term Translation ID', 'bbl' ),
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

		foreach ( $langs as $lang ) {
			$new_args = $args;
			$new_object_type = array();
			foreach( $object_type as $ot )
				$new_object_type[] = strtolower( "{$ot}_{$lang->code}" );

			$new_args[ 'query_var' ] = strtolower( $args[ 'query_var' ] . "_{$lang->code}" );

			// @FIXME: Note currently we are in danger of a taxonomy name being longer than 32 chars
			// Perhaps we need to create some kind of map like (taxonomy) + (lang) => (shadow translated taxonomy)
			$new_taxonomy = strtolower( "{$taxonomy}_{$lang->code}" );

			foreach ( $new_args[ 'labels' ] as & $label )
				$label = "$label ({$lang->code})";

			$this->taxonomies[ $new_taxonomy ] = $taxonomy;
			$this->lang_map[ $new_taxonomy ] = $lang->code;
			
			error_log( "New tax: $new_taxonomy, " . print_r( $new_object_type, true ) . ", " . print_r( $new_args, true ) . "" );

			register_taxonomy( $new_taxonomy, $new_object_type, $new_args );
		}

		$this->no_recursion = false;
	}

	/**
	 * Hooks the WordPress term_link filter to provide functions to provide
	 * appropriate links for the shadow taxonomies. 
	 *
	 * @param string $termlink The currently generated term URL
	 * @param object $term The WordPress term object we're generating a link for
	 * @param string $taxonomy The 
	 * @return string The term link
	 **/
	public function term_link( $termlink, $term, $taxonomy ) {
		$taxonomy = strtolower( $taxonomy );
		// No need to worry about the built in taxonomies
		if ( 'post_tag' == $taxonomy || 'category' == $taxonomy || ! isset( $this->taxonomies[ $taxonomy ] ) )
			return $termlink;
		error_log( "term link for $term->name in $taxonomy: $termlink" );

		// Deal with our shadow post types
		if ( ! ( $base_taxonomy = $this->taxonomies[ $taxonomy ] ) ) 
			return $post_link;

		// Let us commence copying, near verbatim, from WordPress core functions
		global $wp_rewrite;

		if ( !is_object($term) ) {
			if ( is_int($term) ) {
				$term = &get_term($term, $base_taxonomy);
			} else {
				$term = &get_term_by('slug', $term, $base_taxonomy);
			}
		}

		if ( !is_object($term) )
			$term = new WP_Error('invalid_term', __('Empty Term'));

		if ( is_wp_error( $term ) )
			return $term;

		$termlink = $wp_rewrite->get_extra_permastruct($base_taxonomy);

		$slug = $term->slug;
		$t = get_taxonomy($base_taxonomy);

		if ( empty($termlink) ) {
			if ( 'category' == $base_taxonomy )
				$termlink = '?cat=' . $term->term_id;
			elseif ( $t->query_var )
				$termlink = "?$t->query_var=$slug";
			else
				$termlink = "?taxonomy=$base_taxonomy&term=$slug";
			$termlink = home_url($termlink);
		} else {
			if ( $t->rewrite['hierarchical'] ) {
				$hierarchical_slugs = array();
				$ancestors = get_ancestors($term->term_id, $base_taxonomy);
				foreach ( (array)$ancestors as $ancestor ) {
					$ancestor_term = get_term($ancestor, $base_taxonomy);
					$hierarchical_slugs[] = $ancestor_term->slug;
				}
				$hierarchical_slugs = array_reverse($hierarchical_slugs);
				$hierarchical_slugs[] = $slug;
				$termlink = str_replace("%$base_taxonomy%", implode('/', $hierarchical_slugs), $termlink);
			} else {
				$termlink = str_replace("%$base_taxonomy%", $slug, $termlink);
			}
			$termlink = home_url( user_trailingslashit($termlink, 'category') );
		}

		return $termlink;
	}

	/**
	 * Hooks the WP parse_request action 
	 *
	 * FIXME: Should I be extending and replacing the WP class?
	 *
	 * @param object $wp WP object, passed by reference (so no need to return)
	 * @return void
	 **/
	public function parse_request( $wp ) {

		// Sequester the original query, in case we need it to get the default content later
		if ( ! isset( $wp->query_vars[ 'bbl_original_query' ] ) )
			$wp->query_vars[ 'bbl_original_query' ] = $wp->query_vars;
		
		if ( isset( $wp->query_vars[ 'tag' ] ) ) {
			$taxonomy = $this->translated_taxonomy( 'tag', $wp->query_vars[ 'lang' ] );
			$wp->query_vars[ $taxonomy ] = $wp->query_vars[ 'tag' ];
			unset( $wp->query_vars[ 'tag' ] );
		}

		error_log( "QVs: " . print_r( $wp->query_vars, true ) );
		// exit;
	}
	
	// CALLBACKS
	// =========
	
	// PUBLIC METHODS
	// ==============

	/**
	 * Provided with a taxonomy name, e.g. `post_tag`, and a language
	 * code, will return the shadow taxonomy in that language.
	 *
	 * @param string $taxonomy The origin taxonomy 
	 * @param string $lang_code The target language code
	 * @return string The taxonomy name in that language
	 **/
	public function translated_taxonomy( $origin_taxonomy, $lang_code ) {
		return strtolower( "{$origin_taxonomy}_{$lang_code}" );
	}
	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Return the translation group ID (a term ID) that the given term ID 
	 * belongs to.
	 *
	 * @param int $target_term_id The term ID to find the translation group for 
	 * @return int The transID the target term belongs to
	 **/
	protected function get_transid( $target_term_id ) {
		$transids = wp_get_object_terms( $target_term_id, 'term_translation', array( 'fields' => 'ids' ) );
		return (int) array_pop( $transids );
	}

	/**
	 * Set the translation group ID (a term ID) that the given term ID 
	 * belongs to.
	 *
	 * @param int $target_term_id The term ID to set the translation group for
	 * @param int $translation_group_id The ID of the translation group to add this 
	 * @return int The transID the target term belongs to
	 **/
	protected function set_transid( $target_term_id, $transid = null ) {
		if ( ! $transid ) {
			$transid_name = 'term_transid_' . uniqid();
			$result = wp_insert_term( $transid_name, 'term_translation', array() );
			if ( is_wp_error( $result ) )
				error_log( "Problem creating a new Term TransID: " . print_r( $result, true ) );
			else
				$transid = $result[ 'term_id' ];
		}
		return wp_set_object_terms( $target_term_id, $transid, 'term_translation' );
	}

}

$bbl_Taxonomies = new Babble_Taxonomies();

?>