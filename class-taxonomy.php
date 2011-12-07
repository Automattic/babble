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
		if ( is_admin() ) {
			$this->add_action( 'load-edit-tags.php', 'load_edit_term' );
		}
		$this->add_action( 'created_term', null, null, 3 );
		$this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'parse_request' );
		$this->add_action( 'registered_taxonomy', null, null, 3 );
		$this->add_action( 'set_object_terms', null, null, 5 );
		$this->add_filter( 'get_terms' );
		$this->add_filter( 'posts_request' );
		// $this->add_filter( 'term_link', null, null, 3 );
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP init action early
	 *
	 * @return void
	 **/
	public function init_early() {
		// // Ensure we catch any existing language shadow taxonomies already registered
		// if ( is_array( $this->taxonomies ) )
		// 	$taxonomies = array_merge( array( 'post_tag', 'category' ), array_keys( $this->taxonomies ) );
		// else
		// 	$taxonomies = array( 'post_tag', 'category' );

		// This translation will connect each term with it's translated equivalents
		register_taxonomy( 'term_translation', 'term', array(
			'rewrite' => false,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'label' => __( 'Term Translation ID', 'bbl' ),
		) );

		// // Catch any taxonomy which were registered before this class came along
		// // and hooked the registered_post_type action.
		// $existing_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		// // var_dump( $existing_taxonomies );
		// // exit;
		// foreach ( $existing_taxonomies as $taxonomy_object )
		// 	$this->registered_taxonomy( $taxonomy_object->name, $taxonomy_object->object_type, get_object_vars( $taxonomy_object ) );
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
		bbl_log( "Taxonomy $taxonomy" );
		// Don't bother with non-public taxonomies for now
		// If we remove this, we need to avoid dealing with post_translation and term_translation
		if ( ! $args[ 'public' ] || 'post_translation' == $taxonomy || 'term_translation' == $taxonomy ) {
			bbl_log( "Returning for $taxonomy" );
			return;
		}

		if ( $this->no_recursion ) {
			bbl_log( "No recursion on $taxonomy" );
			return;
		}

		$this->no_recursion = true;

		if ( ! is_array( $object_type ) )
			$object_type = array_unique( (array) $object_type );

		// Untranslated taxonomies do not have shadow equivalents in each language,
		// but do apply to the bast post_type and all it's shadow post_types.
		bbl_log( "Check tax $taxonomy is translated: $taxonomy" );
		if ( ! apply_filters( 'bbl_translated_taxonomy', true, $taxonomy ) ) {
			bbl_log( "Tax $taxonomy IS translated: $taxonomy" );
			// Apply this taxonomy to all the shadow post types
			// of all of the base post_types it applies to.
			foreach ( $object_type as $ot ) {
				if ( ! ( $base_post_type = bbl_get_base_post_type( $ot ) ) ) {
					bbl_log( "Base post type: $base_post_type" );
					continue;
				}
				$shadow_post_types = bbl_get_shadow_post_types( $base_post_type );
				bbl_log( "Shadow post types for $taxonomy: " . implode( ', ', $shadow_post_types ) );
				foreach ( $shadow_post_types as $shadow_post_type )
					register_taxonomy_for_object_type( $taxonomy, $shadow_post_type );
			}
			$this->no_recursion = false;
			return;
		}

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
		unset( $args[ 'object_type' ] );

		$this->add_taxonomy_hooks( $taxonomy );

		$slug = ( $args[ 'rewrite' ][ 'slug' ] ) ? $args[ 'rewrite' ][ 'slug' ] : $taxonomy;

		foreach ( $langs as $lang ) {
			$new_args = $args;
			$new_object_type = array();
			// N.B. Here we assume that the taxonomy is on a post type
			foreach( $object_type as $ot )
				$new_object_type[] = bbl_get_post_type_in_lang( $ot, $lang->code );

			if ( false !== $args[ 'rewrite' ] ) {
				if ( ! is_array( $new_args[ 'rewrite' ] ) )
					$new_args[ 'rewrite' ] = array();
				// Do I not need to add this query_var into the query_vars filter? It seems not.
				$new_args[ 'query_var' ] = $new_args[ 'rewrite' ][ 'slug' ] = $this->get_translated_slug( $slug, $lang->code );
			}

			// @FIXME: Note currently we are in danger of a taxonomy name being longer than 32 chars
			// Perhaps we need to create some kind of map like (taxonomy) + (lang) => (shadow translated taxonomy)
			$new_taxonomy = strtolower( "{$taxonomy}_{$lang->code}" );

			foreach ( $new_args[ 'labels' ] as & $label )
				$label = "$label ({$lang->code})";

			$this->taxonomies[ $new_taxonomy ] = $taxonomy;
			if ( ! isset( $this->lang_map[ $lang->code ] ) || ! is_array( $this->lang_map[ $lang->code ] ) )
				$this->lang_map[ $lang->code ] = array();
			$this->lang_map[ $lang->code ][ $taxonomy ] = $new_taxonomy;
			
			
			register_taxonomy( $new_taxonomy, $new_object_type, $new_args );
			
			$this->add_taxonomy_hooks( $new_taxonomy );
		}
		// bbl_stop_logging();

		$this->no_recursion = false;
	}

	/**
	 * Hooks the dynamic WP load-edit-tags.php action which is fired when the 
	 * term edit page is loaded.
	 *
	 * @return void
	 **/
	public function load_edit_term() {
		if ( ! @ $_POST[ 'bbl_term_translation' ] )
			return;
		$term_id = @ (int) $_POST[ 'tag_ID' ];
		check_admin_referer( 'bbl_edit_' . $term_id, '_bbl_nonce' );
		if ( ! ( $transid = @ $_POST[ 'bbl_term_translation' ] ) ) {
			$result = wp_insert_term( $transid_name, 'term_translation', array() );
			if ( is_wp_error( $result ) )
				throw new exception( "Problem creating a new Term TransID: " . print_r( $result, true ) );
			else
				$transid = (int) $result[ 'term_id' ];
		}
		$result = wp_set_object_terms( $term_id, $transid, 'term_translation' );
	}

	/**
	 * Hook the WP created_term action to add in a transid if available.
	 *
	 * @param int $term_id The term ID for the term created
	 * @param int $tt_id The term taxonomy ID for the term created
	 * @param string $taxonomy The taxonomy for the term created 
	 * @return void
	 **/
	public function created_term( $term_id, $tt_id, $taxonomy ) {
		if ( 'term_translation' == $taxonomy )
			return;
		$nonce = @ $_POST[ '_bbl_nonce' ];
		if ( ! $nonce )
			return;
		if ( wp_verify_nonce( $nonce, "bbl_edit_$term_id" ) )
			throw new exception( "Failed nonce check" );
		$transid = @ $_POST[ 'bbl_transid' ];
		$this->set_transid( $term_id, $transid );
	}

	/**
	 * Add some debug fields to all term edit screens.
	 *
	 * @param object $term A term object 
	 * @return void
	 **/
	public function edit_term_form_fields( $term ) {
		$screen = get_current_screen();
		$taxonomy = $screen->taxonomy;
		$transid = $this->get_transid( $term->term_id );
		?>
			<tr>
				<th>
					<label for="bbl_term_translation">Translation ID</label>
				</th>
				<td>
					<?php wp_nonce_field( 'bbl_edit_' . $term->term_id, '_bbl_nonce' ); ?>
					<input type="text" name="bbl_term_translation" value="<?php echo esc_attr( $transid ); ?>" id="bbl_term_translation">
					<?php var_dump( $transid ); ?>
				</td>
			</tr>
		<?php
	}

	/**
	 * Hooks the WP $taxonomy . '_add_form_fields' action to add fields
	 * to the add term request.
	 *
	 * @param string $taxonomy The taxonomy to add a term to 
	 * @return void
	 **/
	public function add_term_form_fields( $taxonomy ) {
		$transid = (int) $_REQUEST[ 'bbl_transid' ];
		wp_nonce_field( 'bbl_add_tag_' . $transid, '_bbl_nonce' );
		?>
			<input type="text" name="bbl_transid" value="<?php echo esc_attr( $transid ); ?>" id="bbl_transid" />
		<?php
	}

	/**
	 * Hooks the WordPress term_link filter to provide functions to provide
	 * appropriate links for the shadow taxonomies. 
	 *
	 * @see get_term_link from whence much of this was copied
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
	
		// Deal with our shadow taxonomies
		if ( ! ( $base_taxonomy = $this->get_base_taxonomy( $taxonomy ) ) ) 
			return $post_link;
	
		// START copying from get_term_link, replacing $taxonomy with $base_taxonomy
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
		// STOP copying from get_term_link
	
		return $termlink;
	}

	/**
	 * Hooks the WP get_terms filter to ensure the terms all have transids.
	 *
	 * @param array $terms The terms which have been got 
	 * @return array The terms which were got
	 **/
	public function get_terms( $terms ) {
		foreach ( $terms as $term ) {
			if ( isset( $this->taxonomies ) )
				continue;
			if ( isset( $this->taxonomies[ $term->taxonomy ] ) )
				if ( ! $this->get_transid( $term->term_id ) )
					throw new exception( "ERROR: Translated term ID $term->term_id does not have a transid" );
				else
					continue;
			if ( ! $this->get_transid( $term->term_id ) ) {
				$this->set_transid( $term->term_id );
			}
		}
		return $terms;
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

		// If the current language is the default language, then we don't need
		// to do anything at all
		if ( bbl_is_default_lang() ) {
			return;
		}

		// Sequester the original query, in case we need it to get the default content later
		if ( ! isset( $wp->query_vars[ 'bbl_original_query' ] ) )
			$wp->query_vars[ 'bbl_original_query' ] = $wp->query_vars;

		$taxonomy 	= false;
		$terms 		= false;

		if ( isset( $wp->query_vars[ 'tag' ] ) ) {
			$taxonomy = $this->get_taxonomy_in_lang( 'post_tag', $wp->query_vars[ 'lang' ] );
			$terms = $wp->query_vars[ 'tag' ];
			unset( $wp->query_vars[ 'tag' ] );
		} else if ( isset( $wp->query_vars[ 'category_name' ] ) ) {
			$taxonomy = $this->get_taxonomy_in_lang( 'category', $wp->query_vars[ 'lang' ] );
			$terms = $wp->query_vars[ 'category_name' ];
			unset( $wp->query_vars[ 'category_name' ] );
		}

		if ( $taxonomy && $terms ) {

			if ( ! is_array( $wp->query_vars[ 'tax_query' ] ) )
				$wp->query_vars[ 'tax_query' ] = array();
		
			$wp->query_vars[ 'tax_query' ][] = array(
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $terms,
			);
		
		}
	}

	/**
	 * Hooks the WP set_object_terms action to sync any untranslated
	 * taxonomies across to the translations.
	 *
	 * @param int $object_id The object to relate to.
	 * @param array $terms The slugs or ids of the terms
	 * @param array $tt_ids The term_taxonomy_ids.
	 * @param array|string $taxonomy The context in which to relate the term to the object.
	 * @param bool $append If false will delete difference of terms.
	 * @return void
	 **/
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append ) {
		if ( apply_filters( 'bbl_translated_taxonomy', true, $taxonomy ) ) {
			return;
		}

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		// Here we assume that this taxonomy is on a post type
		$translations = bbl_get_post_translations( $object_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( bbl_get_post_lang_code( $object_id ) == $lang_code )
				continue;
			bbl_stop_translating();
			wp_set_object_terms( $translation->ID, $terms, $taxonomy, $append );
			bbl_start_translating();
		}

		$this->no_recursion = false;
	}

	/**
	 * Hooks posts_request.
	 *
	 * @param  
	 * @return void
	 **/
	public function posts_request( $query ) {
		// bbl_log( "Query: $query" );
		return $query;
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

	/**
	 * Get the terms which are the translations for the provided 
	 * post ID. N.B. The returned array of term objects (and false 
	 * values) will include the post for the post ID passed.
	 * 
	 * @FIXME: Should I filter out the term ID passed?
	 *
	 * @param int|object $term Either a WP Term object, or a term_id 
	 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Term object
	 **/
	public function get_term_translations( $term, $taxonomy = null ) {
		$term = get_term( $term, $taxonomy );

		$langs = bbl_get_active_langs();
		$translations = array();
		foreach ( $langs as $lang )
			$translations[ $lang->code ] = false;

		$transid = $this->get_transid( $term->term_id );
		// I thought the fracking bug where the get_objects_in_term function returned integers
		// as strings was fixed. Seems not. See #17646 for details. Argh.
		$term_ids = array_map( 'absint', get_objects_in_term( $transid, 'term_translation' ) );

		// We're dealing with terms across multiple taxonomies
		$base_taxonomy = isset( $this->taxonomies[ $taxonomy ] ) ? $this->taxonomies[ $taxonomy ] : $taxonomy ;
		$taxonomies = array();
		$taxonomies[] = $base_taxonomy;
		foreach ( $this->lang_map as $lang_taxes )
			$taxonomies[] = $lang_taxes[ $base_taxonomy ];

		// Get all the translations in one cached DB query
		$existing_terms = get_terms( $taxonomies, array( 'include' => $term_ids, 'hide_empty' => false ) );

		// Finally, we're ready to return the terms in this 
		// translation group.
		$terms = array();
		$terms[ bbl_get_current_lang()->code ] = $term;
		// var_dump( $translations );
		foreach ( $existing_terms as $t )
			$terms[ $this->get_taxonomy_lang_code( $t->taxonomy ) ] = $t;
		return $terms;
	}

	/**
	 * Return the admin URL to create a new translation for a term in a
	 * particular language.
	 *
	 * @param int|object $default_term The term in the default language to create a new translation for, either WP Post object or post ID
	 * @param string $lang The language code 
	 * @return string The admin URL to create the new translation
	 * @access public
	 **/
	public function get_new_term_translation_url( $default_term, $lang_code, $taxonomy = null ) {
		if ( ! is_int( $default_term ) && is_null( $taxonomy ) )
			throw new exception( 'get_new_term_translation_url: Cannot get term from term_id without taxonomy' );
		else if ( is_int( $default_term ) )
			$default_term = get_term( $default_term, $taxonomy );
		if ( is_wp_error( $default_term ) )
			throw new exception( 'get_new_term_translation_url: Error getting term from term_id and taxonomy: ' . print_r( $default_term, true ) );
		
		// var_dump( $default_term );
		// $default_term = 
		bbl_switch_to_lang( $lang_code );
		// var_dump( $default_term );
		$transid = $this->get_transid( $default_term->term_id );
		$url = admin_url( "/edit-tags.php?taxonomy=$taxonomy" );
		$url = add_query_arg( array( 'taxonomy' => $this->lang_map[ $lang_code ][ $taxonomy ], 'bbl_transid' => $transid, 'lang' => $lang_code ), $url );
		bbl_restore_lang();
		return $url;
	}

	/**
	 * Returns the language code associated with a particular taxonomy.
	 *
	 * @param string $taxonomy The taxonomy to get the language for 
	 * @return string The lang code
	 **/
	public function get_taxonomy_lang_code( $taxonomy ) {
		if ( ! isset( $this->taxonomies[ $taxonomy ] ) )
			return bbl_get_default_lang_code();
		// var_dump( $this->lang_map );
		foreach ( $this->lang_map as $lang => $data )
			foreach ( $data as $trans_tax )
				if ( $taxonomy == $trans_tax )
					return $lang;
		// error_log( "Found nothing." );
		return false;
	}

	/**
	 * Return the base taxonomy (in the default language) for a 
	 * provided taxonomy.
	 *
	 * @param string $taxonomy The name of a taxonomy 
	 * @return string The name of the base taxonomy
	 **/
	public function get_base_taxonomy( $taxonomy ) {
		if ( ! isset( $this->taxonomies[ $taxonomy ] ) )
			return $taxonomy;
		return $this->taxonomies[ $taxonomy ];
	}

	/**
	 * Returns the equivalent taxonomy in the specified language.
	 *
	 * @param string $taxonomy A taxonomy to return in a given language
	 * @param string $lang_code The language code for the required language (optional, defaults to current)
	 * @return boolean|string The taxonomy name, or false if no taxonomy was specified
	 **/
	public function get_taxonomy_in_lang( $taxonomy, $lang_code = null ) {
		if ( ! $taxonomy )
			return false; // @FIXME: Should I actually be throwing an error here?
		if ( is_null( $lang_code ) )
			$lang_code = bbl_get_current_lang_code();
		$base_taxonomy = $this->get_base_taxonomy( $taxonomy );
		if ( bbl_get_default_lang_code() == $lang_code )
			return $base_taxonomy;
		return $this->lang_map[ $lang_code ][ $base_taxonomy ];
	}

	/**
	 * Returns a slug translated into a particular language.
	 *
	 * @TODO: This is more or less the same method as Babble_Post_Public::get_taxonomy_lang_code, do I need to DRY that up?
	 *
	 * @param string $slug The slug to translate
	 * @param string $lang_code The language code for the required language (optional, defaults to current)
	 * @return void
	 **/
	public function get_translated_slug( $slug, $lang_code = null ) {
		if ( is_null( $lang_code ) )
			$lang_code = bbl_get_current_lang_code();
		$_slug = strtolower( apply_filters( 'bbl_translate_taxonomy_slug', $slug ) );
		if ( $_slug &&  $_slug != $slug )
			return $_slug;
		// Do we need to check that the slug is unique at this point?
		return strtolower( "{$_slug}_{$lang_code}" );
	}
	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Hooks up a taxonomy with all the actions/filters it needs.
	 *
	 * @param string $taxonomy The taxonomy in question 
	 * @return void
	 **/
	protected function add_taxonomy_hooks( $taxonomy ) {
		$this->add_action( $taxonomy . '_edit_form_fields', 'edit_term_form_fields' );
		$this->add_action( $taxonomy . '_add_form_fields', 'add_term_form_fields' );
	}

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
		return wp_set_object_terms( $target_term_id, absint( $transid ), 'term_translation' );
	}

}

$bbl_taxonomies = new Babble_Taxonomies();

?>