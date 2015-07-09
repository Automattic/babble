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
		add_action( 'bbl_created_new_shadow_post',      array( $this, 'created_new_shadow_post' ), 10, 2 );
		add_action( 'bbl_registered_shadow_post_types', array( $this, 'registered_shadow_post_types' ) );
		add_action( 'init',                             array( $this, 'init_early' ), 0 );
		add_action( 'parse_request',                    array( $this, 'parse_request' ) );
		add_action( 'registered_taxonomy',              array( $this, 'registered_taxonomy' ), 10, 3 );
		add_action( 'save_post',                        array( $this, 'save_post' ), 10, 2 );
		add_action( 'set_object_terms',                 array( $this, 'set_object_terms' ), 10, 5 );
		add_filter( 'get_terms',                        array( $this, 'get_terms' ) );
		add_filter( 'term_link',                        array( $this, 'term_link' ), 10, 3 );
		add_filter( 'bbl_translated_taxonomy',          array( $this, 'bbl_translated_taxonomy'), 10, 2 );
		add_filter( 'body_class',                       array( $this, 'body_class'), 10, 2 );
		add_filter( 'taxonomy_template',                array( $this, 'taxonomy_template' ) );
		add_filter( 'admin_body_class',                 array( $this, 'admin_body_class' ) );

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
		register_taxonomy( 'term_translation', array(), array(
			'rewrite' => false,
			'public' => false,
			'show_ui' => false,
			'show_in_nav_menus' => false,
			'label' => __( 'Term Translation ID', 'babble' ),
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
		if ( in_array( $taxonomy, $this->ignored_taxonomies() )  ) {
			return;
		}

		if ( $this->no_recursion ) {
			return;
		}

		$this->no_recursion = true;

		if ( ! is_array( $object_type ) ) {
			$object_type = array_unique( (array) $object_type );
		}

		// Use the Babble term counting function, unless the taxonomy registrant
		// has defined their own – in which case we'll just have to hope against 
		// hope that it's Babble aware :S
		// FIXME: Setting this in the following fashion seems hacky… I feel uncomfortable.
		if ( empty( $GLOBALS[ 'wp_taxonomies' ][ $taxonomy ]->update_count_callback ) ) {
			$GLOBALS[ 'wp_taxonomies' ][ $taxonomy ]->update_count_callback = array( $this, 'update_post_term_count' );
		}

		// Untranslated taxonomies do not have shadow equivalents in each language,
		// but do apply to the bast post_type and all it's shadow post_types.
		if ( ! $this->is_taxonomy_translated( $taxonomy ) ) {
			// Apply this taxonomy to all the shadow post types
			// of all of the base post_types it applies to.
			foreach ( $object_type as $ot ) {
				if ( ! ( $base_post_type = bbl_get_base_post_type( $ot ) ) ) {
					continue;
				}
				$shadow_post_types = bbl_get_shadow_post_types( $base_post_type );
				foreach ( $shadow_post_types as $shadow_post_type ) {
					register_taxonomy_for_object_type( $taxonomy, $shadow_post_type );
				}
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

		#$args[ 'rewrite' ] = false;
		unset( $args[ 'name' ] );
		unset( $args[ 'object_type' ] );

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
				$new_args[ 'query_var' ] = $new_args[ 'rewrite' ][ 'slug' ] = $this->get_slug_in_lang( $slug, $lang->code );
			}

			// @FIXME: Note currently we are in danger of a taxonomy name being longer than 32 chars
			// Perhaps we need to create some kind of map like (taxonomy) + (lang) => (shadow translated taxonomy)
			$new_taxonomy = strtolower( "{$taxonomy}_{$lang->code}" );

			$this->taxonomies[ $new_taxonomy ] = $taxonomy;
			if ( ! isset( $this->lang_map[ $lang->code ] ) || ! is_array( $this->lang_map[ $lang->code ] ) )
				$this->lang_map[ $lang->code ] = array();
			$this->lang_map[ $lang->code ][ $taxonomy ] = $new_taxonomy;
			
			register_taxonomy( $new_taxonomy, $new_object_type, $new_args );
			
		}
		// bbl_stop_logging();

		$this->no_recursion = false;
	}

	public function ignored_taxonomies() {
		return array( 'post_translation', 'term_translation' );
	}

	public function is_taxonomy_translated( $taxonomy ) {
		if( in_array( $taxonomy, $this->ignored_taxonomies() ) ) {
			return false;
		}

		// @FIXME: Remove this when menu's are translatable
		if( 'nav_menu' == $taxonomy ) {
			return false;
		}

		return apply_filters( 'bbl_translated_taxonomy', true, $taxonomy );
	}

	/**
	 * Hooks the WP bbl_registered_shadow_post_types action to check that we've applied
	 * all untranslated taxonomies to the shadow post types created for this base
	 * post type. 
	 * 
	 * @param string $post_type The post type for which the shadow post types have been registered. 
	 * @return void
	 **/
	public function registered_shadow_post_types( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );

		$object_type = (array) $post_type;
		
		foreach ( $taxonomies as $taxonomy ) {
			// Untranslated taxonomies do not have shadow equivalents in each language,
			// but do apply to the bast post_type and all it's shadow post_types.
			if ( ! $this->is_taxonomy_translated( $taxonomy ) ) {
				// Apply this taxonomy to all the shadow post types
				// of all of the base post_types it applies to.
				foreach ( $object_type as $ot ) {
					if ( ! ( $base_post_type = bbl_get_base_post_type( $ot ) ) ) {
						continue;
					}
					$shadow_post_types = bbl_get_shadow_post_types( $base_post_type );
					foreach ( $shadow_post_types as $shadow_post_type ) {
						register_taxonomy_for_object_type( $taxonomy, $shadow_post_type );
					}
				}
			}
		}
	}

	/**
	 * Hooks the Babble action bbl_created_new_shadow_post, which is fired
	 * when a new translation post is created, to sync any existing untranslated
	 * taxonomy terms.
	 *
	 * @param int $new_post_id The ID of the new post (to sync to)
	 * @param int $origin_post_id The ID of the originating post (to sync from)
	 * @return void
	 **/
	public function created_new_shadow_post( $new_post_id, $origin_post_id ) {
		$new_post = get_post( $new_post_id );
		if ( ! ( $origin_post = get_post( $origin_post_id ) ) ) {
			return;
		}
		
		if ( $this->no_recursion ) {
			return;
		}
		$this->no_recursion = true;
		
		$taxonomies = get_object_taxonomies( $origin_post->post_type );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $this->is_taxonomy_translated( $taxonomy ) ) {
				$term_ids = wp_get_object_terms( $origin_post->ID, $taxonomy, array( 'fields' => 'ids' ) );
				$term_ids = array_map( 'absint', $term_ids );
				wp_set_object_terms( $new_post->ID, $term_ids, $taxonomy );
			}
		}

		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP save_post action to resync data
	 * when requested.
	 *
	 * @param int $post_id The ID of the WP post
	 * @param object $post The WP Post object 
	 * @return void
	 **/
	public function save_post( $post_id, $post ) {
		$this->maybe_resync_terms( $post_id, $post );
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
		if ( 'post_tag' == $taxonomy || 'category' == $taxonomy || ! isset( $this->taxonomies[ $taxonomy ] ) ) {
			return $termlink;
		}
	
		// Deal with our shadow taxonomies
		if ( ! ( $base_taxonomy = $this->get_base_taxonomy( $taxonomy ) ) ) {
			return $termlink;
		}
	
		// START copying from get_term_link, replacing $taxonomy with $base_taxonomy
		global $wp_rewrite;
	
		if ( !is_object($term) ) {
			if ( is_int($term) ) {
				$term = &get_term($term, $base_taxonomy);
			} else {
				$term = &get_term_by('slug', $term, $base_taxonomy);
			}
		}
	
		if ( !is_object($term) ) {
			$term = new WP_Error( 'invalid_term', esc_html__( 'Empty Term', 'babble' ) );
		}
	
		if ( is_wp_error( $term ) ) {
			return $term;
		}
	
		$termlink = $wp_rewrite->get_extra_permastruct($base_taxonomy);
	
		$slug = $term->slug;
		$t = get_taxonomy($base_taxonomy);
	
		$lang = $this->get_taxonomy_lang_code( $taxonomy );
		bbl_switch_to_lang( $lang );

		if ( empty($termlink) ) {
			if ( 'category' == $base_taxonomy ) {
				$termlink = '?cat=' . $term->term_id;
			} elseif ( $t->query_var ) {
				$termlink = "?$t->query_var=$slug";
			} else {
				$termlink = "?taxonomy=$base_taxonomy&term=$slug";
			}
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

		bbl_restore_lang();

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
			if ( empty( $term ) ) {
				continue;
			}
			if ( isset( $this->taxonomies ) ) {
				continue;
			}
			if ( isset( $this->taxonomies[ $term->taxonomy ] ) ) {
				if ( ! $this->get_transid( $term->term_id ) ) {
					throw new exception( "ERROR: Translated term ID $term->term_id does not have a transid" );
				} else {
					continue;
				}
			}
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

		if ( is_admin() ) {
			return;
		}

		// Sequester the original query, in case we need it to get the default content later
		if ( ! isset( $wp->query_vars[ 'bbl_tax_original_query' ] ) ) {
			$wp->query_vars[ 'bbl_tax_original_query' ] = $wp->query_vars;
		}

		$taxonomy 	= false;
		$terms 		= false;
		$lang_code  = bbl_get_current_lang_code();
		$taxonomies = get_taxonomies( null, 'objects' );

		if ( isset( $wp->query_vars[ 'tag' ] ) ) {
			$taxonomy = $this->get_taxonomy_in_lang( 'post_tag', $wp->query_vars[ 'lang' ] );
			$terms = $wp->query_vars[ 'tag' ];
			unset( $wp->query_vars[ 'tag' ] );
		} else if ( isset( $wp->query_vars[ 'category_name' ] ) ) {
			$taxonomy = $this->get_taxonomy_in_lang( 'category', $wp->query_vars[ 'lang' ] );
			$terms = $wp->query_vars[ 'category_name' ];
			unset( $wp->query_vars[ 'category_name' ] );
		} else {
			$taxonomies = array();
			$lang_taxonomies = array();

			foreach ( $taxonomies as $taxonomy => $tax_obj ) {
				$tax = $this->get_taxonomy_in_lang( $taxonomy, $lang_code );
				$lang_taxonomies[ $tax_obj->rewrite[ 'slug' ] ] = $tax;
			}

			foreach ( $lang_taxonomies as $slug => $tax ) {
				if ( isset( $wp->query_vars[ $slug ] ) ) {
					$taxonomies[] = $tax;
					break;
				}
			}
			
			if ( $taxonomies ) {
				$post_types = array();
				foreach ( $taxonomies as $taxonomy ) {
					$taxonomy = get_taxonomy( $taxonomy );
					$post_types = array_merge( $post_types, $taxonomy->object_type );
					// Filter out the post_types not in this language
					foreach ( $post_types as & $post_type ) {
						$post_type = bbl_get_post_type_in_lang( $post_type );
					}
					$post_types = array_unique( $post_types );
				}
				$wp->query_vars[ 'post_type' ] = $post_types;
			}
		}

		if ( $taxonomy && $terms ) {

			if ( ! isset( $wp->query_vars[ 'tax_query' ] ) || ! is_array( $wp->query_vars[ 'tax_query' ] ) ) {
				$wp->query_vars[ 'tax_query' ] = array();
			}
		
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
	 * @param int $object_id The object to relate to
	 * @param array $terms The slugs or ids of the terms
	 * @param array $tt_ids The term_taxonomy_ids
	 * @param string $taxonomy The name of the taxonomy for which terms are being set
	 * @param bool $append If false will delete difference of terms
	 * @return void
	 **/
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append ) {
		if ( $this->no_recursion ) {
			return;
		}
		$this->no_recursion = true;

		// DO NOT SYNC THE TRANSID TAXONOMIES!!
		if ( in_array( $taxonomy, $this->ignored_taxonomies() ) ) {
			$this->no_recursion = false;
			return;
		}

		if ( $this->is_taxonomy_translated( $taxonomy ) ) {
			
			// Here we assume that this taxonomy is on a post type
			$translations = bbl_get_post_translations( $object_id );

			foreach ( $translations as $lang_code => & $translation ) {

				if ( bbl_get_post_lang_code( $object_id ) == $lang_code ) {
					continue;
				}

				$translated_taxonomy = bbl_get_taxonomy_in_lang( $taxonomy, $lang_code );
				$translated_terms = array();

				foreach ( $terms as $term ) {

					if ( is_int( $term ) ) {
						$_term = get_term( $term, $taxonomy );
					} else {
						$_term = get_term_by( 'name', $term, $taxonomy );
					}
					if ( is_wp_error( $_term ) or empty( $_term ) ) {
						continue;
					}

					if ( $translated_term = $this->get_term_in_lang( $_term->term_id, $taxonomy, $lang_code, false ) ) {
						$translated_terms[] = (int) $translated_term->term_id;
					}

				}

				if ( $translated_terms ) {
					$result = wp_set_object_terms( $translation->ID, $translated_terms, $translated_taxonomy, $append );
				}
			}
			
		} else {

			// Here we assume that this taxonomy is on a post type
			$translations = bbl_get_post_translations( $object_id );
			foreach ( $translations as $lang_code => & $translation ) {
				if ( bbl_get_post_lang_code( $object_id ) == $lang_code ) {
					continue;
				}
				wp_set_object_terms( $translation->ID, $terms, $taxonomy, $append );
			}

		}

		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP body_class filter to add classes to the
	 * body element.
	 *
	 * @param array $classes An array of class strings, poss with some indexes containing more than one space separated class 
	 * @param string|array $class One or more classes which have been added to the class list.
	 * @return array An array of class strings, poss with some indexes containing more than one space separated class 
	 **/
	public function body_class( array $classes, $class ) {
		if ( is_tax() ) {
			$taxonomy      = get_queried_object();
			$base_taxonomy = bbl_get_term_in_lang( get_queried_object(), $taxonomy->taxonomy, bbl_get_default_lang_code() );

			if ( 'category' == $base_taxonomy->taxonomy ) {
				$classes[] = 'category';

				if ( isset( $base_taxonomy->term_id ) ) {
					$classes[] = 'category-' . sanitize_html_class( $base_taxonomy->slug, $base_taxonomy->term_id );
					$classes[] = 'category-' . $base_taxonomy->term_id;
				}
			} elseif ( 'post_tag' == $base_taxonomy->taxonomy ) {
				$classes[] = 'tag';

				if ( isset( $base_taxonomy->term_id ) ) {
					$classes[] = 'tag-' . sanitize_html_class( $base_taxonomy->slug, $base_taxonomy->term_id );
					$classes[] = 'tag-' . $base_taxonomy->term_id;
				}
			} else {
				if ( isset( $base_taxonomy->term_id ) ) {
					$classes[] = 'tax-' . sanitize_html_class( $base_taxonomy->taxonomy );
					$classes[] = 'term-' . sanitize_html_class( $base_taxonomy->slug, $base_taxonomy->term_id );
					$classes[] = 'term-' . $base_taxonomy->term_id;
				}
			}
		}
		return $classes;
	}

	/**
	 * Hooks the WP filter taxonomy_template to deal with the shadow terms,
	 * ensuring they use the right template.
	 *
	 * @param string $template Path to a template file 
	 * @return Path to a template file
	 **/
	public function taxonomy_template( $template ) {
		if( bbl_is_default_lang() ) {
			return $template;
		}

		$term          = get_queried_object();
		$base_taxonomy = $this->get_base_taxonomy( $term->taxonomy );

		if ( 'category' == $base_taxonomy ) {
			if ( ! empty( $term->slug ) ) {
				$templates[] = "category-{$term->slug}.php";
				$templates[] = "category-{$term->term_id}.php";
			}

			$templates[] = 'category.php';
		}
		else if ( 'post_tag' == $base_taxonomy ) {
			if ( ! empty( $term->slug ) ) {
				$templates[] = "tag-{$term->slug}.php";
				$templates[] = "tag-{$term->term_id}.php";
			}
			$templates[] = 'tag.php';
		}
		else {
			if ( ! empty( $term->slug ) ) {
				$taxonomy    = $term->taxonomy;
				$templates[] = "taxonomy-$taxonomy-{$term->slug}.php";
				$templates[] = "taxonomy-$taxonomy.php";
			}
			$templates[] = 'taxonomy.php';
		}

		$template = get_query_template( 'bbl-taxonomy', $templates );

		return $template;
	}
	
	// CALLBACKS
	// =========
	
	// PUBLIC METHODS
	// ==============

	public function admin_body_class( $class ) {

		$taxonomy = get_current_screen() ? get_current_screen()->taxonomy : null;
		if ( $taxonomy ) {
			$class .= ' bbl-taxonomy-' . $taxonomy;
		}

		return $class;

	}

	public function bbl_translated_taxonomy( $translated, $taxonomy ) {
		if ( 'term_translation' == $taxonomy ) {
			return false;
		}
		if ( 'nav_menu' == $taxonomy ) {
			return false;
		}
		if ( 'link_category' == $taxonomy ) {
			return false;
		}
		if ( 'post_format' == $taxonomy ) {
			return false;
		}
		return $translated;
	}

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
	 * term ID. N.B. The returned array of term objects (and false 
	 * values) will include the term for the term ID passed.
	 * 
	 * @FIXME: We should cache the translation groups, as we do for posts
	 *
	 * @param int|object $term Either a WP Term object, or a term_id 
	 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Term object
	 **/
	public function get_term_translations( $term, $taxonomy ) {
		$term = get_term( $term, $taxonomy );

		$langs = bbl_get_active_langs();
		$translations = array();
		foreach ( $langs as $lang ) {
			$translations[ $lang->code ] = false;
		}

		$transid = $this->get_transid( $term->term_id );
		// I thought the fracking bug where the get_objects_in_term function returned integers
		// as strings was fixed. Seems not. See #17646 for details. Argh.
		$term_ids = array_map( 'absint', get_objects_in_term( $transid, 'term_translation' ) );

		// We're dealing with terms across multiple taxonomies
		$base_taxonomy = isset( $this->taxonomies[ $taxonomy ] ) ? $this->taxonomies[ $taxonomy ] : $taxonomy ;
		$taxonomies = array();
		$taxonomies[] = $base_taxonomy;
		foreach ( $this->lang_map as $lang_taxes ) {
			if ( $lang_taxes[ $base_taxonomy ] ) {
				$taxonomies[] = $lang_taxes[ $base_taxonomy ];
			}
		}

		// Get all the translations in one cached DB query
		$existing_terms = get_terms( $taxonomies, array( 'include' => $term_ids, 'hide_empty' => false ) );

		// Finally, we're ready to return the terms in this 
		// translation group.
		$terms = array();
		foreach ( $existing_terms as $t ) {
			$terms[ $this->get_taxonomy_lang_code( $t->taxonomy ) ] = $t;
		}
		return $terms;
	}

	/**
	 * Returns the term in a particular language, or the fallback content
	 * if there's no term available.
	 *
	 * @param int|object $term Either a WP Term object, or a term_id 
	 * @param string $lang_code The language code for the required language 
	 * @param boolean $fallback If true: if a term is not available, fallback to the default language content (defaults to true)
	 * @return object|boolean The WP Term object, or if $fallback was false and no post then returns false
	 **/
	public function get_term_in_lang( $term, $taxonomy, $lang_code, $fallback = true  ) {
		$translations = $this->get_term_translations( $term, $taxonomy );
		if ( isset( $translations[ $lang_code ] ) ) {
			return $translations[ $lang_code ];
		}
		if ( ! $fallback ) {
			return false;
		}
		return $translations[ bbl_get_default_lang_code() ];
	}

	/**
	 * Return the admin URL to create a new translation for a term in a
	 * particular language.
	 *
	 * @param int|object $default_term The term in the default language to create a new translation for, either WP Post object or post ID
	 * @param string $lang_code The language code 
	 * @param string $taxonomy The taxonomy name
	 * @return string|WP_Error The admin URL to create the new translation, a `WP_Error` object on failure
	 * @access public
	 **/
	public function get_new_term_translation_url( $default_term, $lang_code, $taxonomy ) {
		$default_term = get_term( $default_term, $taxonomy );
		if ( is_wp_error( $default_term ) ) {
			return $default_term;
		}
		$url = admin_url( 'post-new.php' );
		$args = array( 
			'bbl_origin_term' => $default_term->term_id,
			'bbl_origin_taxonomy' => $default_term->taxonomy,
			'lang'            => $lang_code,
			'post_type'       => 'bbl_job',
		);
		$url = add_query_arg( $args, $url );
		return $url;
	}

	/**
	 * Returns the language code associated with a particular taxonomy.
	 *
	 * @param string $taxonomy The taxonomy to get the language for 
	 * @return string The lang code
	 **/
	public function get_taxonomy_lang_code( $taxonomy ) {
		if ( ! isset( $this->taxonomies[ $taxonomy ] ) ) {
			return bbl_get_default_lang_code();
		}
		foreach ( $this->lang_map as $lang => $data ) {
			foreach ( $data as $trans_tax ) {
				if ( $taxonomy == $trans_tax ) {
					return $lang;
				}
			}
		}
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
		if ( ! isset( $this->taxonomies[ $taxonomy ] ) ) {
			return $taxonomy;
		}
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
		// Some taxonomies are untranslated…
		if ( ! $this->is_taxonomy_translated( $taxonomy ) ) {
			return $taxonomy;
		}
			
		if ( ! $taxonomy ) {
			return false; // @FIXME: Should I actually be throwing an error here?
		}

		if ( is_null( $lang_code ) ) {
			$lang_code = bbl_get_current_lang_code();
		}

		$base_taxonomy = $this->get_base_taxonomy( $taxonomy );

		if ( bbl_get_default_lang_code() == $lang_code ) {
			return $base_taxonomy;
		}

		return $this->lang_map[ $lang_code ][ $base_taxonomy ];
	}

	/**
	 * Returns a slug translated into a particular language.
	 *
	 * @TODO: This is more or less the same method as Babble_Post_Public::get_taxonomy_lang_code, do I need to DRY that up?
	 *
	 * @param string $slug The slug to translate
	 * @param string $lang_code The language code for the required language (optional, defaults to current)
	 * @return string A translated slug
	 **/
	public function get_slug_in_lang( $slug, $lang_code = null ) {
		if ( is_null( $lang_code ) ) {
			$lang_code = bbl_get_current_lang_code();
		}
		$_slug = mb_strtolower( apply_filters( 'bbl_translate_taxonomy_slug', $slug, $lang_code ) );
		// @FIXME: For some languages the translation might be the same as the original
		if ( $_slug &&  $_slug != $slug ) {
			return $_slug;
		}
		// Do we need to check that the slug is unique at this point?
		return mb_strtolower( "{$_slug}_{$lang_code}" );
	}
	

	public function initialise_translation( $origin_term, $taxonomy, $lang_code ) {

		$new_taxonomy = $this->get_slug_in_lang( $taxonomy, $lang_code );

		$transid = $this->get_transid( $origin_term->term_id );

		// Insert translation:
		$this->no_recursion = true;
		$new_term_id = wp_insert_term( $origin_term->name . ' - ' . $lang_code, $new_taxonomy );
		$this->no_recursion = false;

		$new_term = get_term( $new_term_id['term_id'], $new_taxonomy );

		// Assign transid to translation:
		$this->set_transid( $new_term_id['term_id'], $transid );

		return $new_term;

	}

	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Will update term count based on object types of the current 
	 * taxonomy. Will only count the post(s) in the default language.
	 *
	 * Private function for the default callback for post_tag and category
	 * taxonomies.
	 *
	 * @param array $terms List of Term taxonomy IDs
	 * @param object $taxonomy Current taxonomy object of terms
	 */
	function update_post_term_count( $terms, $taxonomy ) {
		global $wpdb;

		$object_types = (array) $taxonomy->object_type;

		foreach ( $object_types as &$object_type ) {
			list( $object_type ) = explode( ':', $object_type );
			// Babble specific code, to only count in primary language
			$object_type = bbl_get_post_type_in_lang( $object_type, bbl_get_default_lang_code() );
		}

		$object_types = array_unique( $object_types );

		if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) ) {
			unset( $object_types[ $check_attachments ] );
			$check_attachments = true;
		}

		if ( $object_types ) {
			$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
		}
		foreach ( (array) $terms as $term ) {
			$count = 0;

			// Attachments can be 'inherit' status, we need to base count off the parent's status if so
			if ( $check_attachments ) {
				$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id AND ( post_status = 'publish' OR ( post_status = 'inherit' AND post_parent > 0 AND ( SELECT post_status FROM $wpdb->posts WHERE ID = p1.post_parent ) = 'publish' ) ) AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );
			}

			if ( $object_types ) {
				$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ('" . implode("', '", $object_types ) . "') AND term_taxonomy_id = %d", $term ) );
			}

			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}

	/**
	 * Return the translation group ID (a term ID) that the given term ID 
	 * belongs to.
	 *
	 * @param int $target_term_id The term ID to find the translation group for 
	 * @return int The transID the target term belongs to
	 **/
	public function get_transid( $target_term_id ) {
		if ( $transid = wp_cache_get( $target_term_id, 'bbl_term_transids' ) ) {
			return $transid;
		}

		if ( ! $target_term_id ) {
			throw new exception( "Please specify a target term_id" );
		}

		$transids = wp_get_object_terms( $target_term_id, 'term_translation', array( 'fields' => 'ids' ) );
		// "There can be only one" (so we'll just drop the others)
		if ( isset( $transids[ 0 ] ) ) {
			$transid = $transids[ 0 ];
		} else {
			$transid = $this->set_transid( $target_term_id );
		}

		wp_cache_set( $target_term_id, $transid, 'bbl_term_transids' );

		return $transid;
	}

	/**
	 * Set the translation group ID (a term ID) that the given term ID 
	 * belongs to.
	 *
	 * @param int $target_term_id The term ID to set the translation group for
	 * @param int $translation_group_id The ID of the translation group to add this 
	 * @return int The transID the target term belongs to
	 **/
	public function set_transid( $target_term_id, $transid = null ) {
		if ( ! $target_term_id ) {
			throw new exception( "Please specify a target term_id" );
		}

		if ( ! $transid ) {
			$transid_name = 'term_transid_' . uniqid();
			$result = wp_insert_term( $transid_name, 'term_translation', array() );
			if ( is_wp_error( $result ) ) {
				bbl_log( "Problem creating a new Term TransID: " . print_r( $result, true ), true );
			} else {
				$transid = $result[ 'term_id' ];
			}
		}

		$result = wp_set_object_terms( $target_term_id, absint( $transid ), 'term_translation' );
		if ( is_wp_error( $result ) ) {
			bbl_log( "Problem associating TransID with new posts: " . print_r( $result, true ), true );
		}

		wp_cache_delete( $target_term_id, 'bbl_term_transids' );
		
		return $transid;
	}

	/**
	 * Checks for the relevant POSTed field, then 
	 * resyncs the terms.
	 *
	 * @param int $post_id The ID of the WP post
	 * @param object $post The WP Post object 
	 * @return void
	 **/
	protected function maybe_resync_terms( $post_id, $post ) {
		// Check that the fields were included on the screen, we
		// can do this by checking for the presence of the nonce.
		$nonce = isset( $_POST[ '_bbl_metabox_resync' ] ) ? $_POST[ '_bbl_metabox_resync' ] : false;
		
		
		if ( ! in_array( $post->post_status, array( 'draft', 'publish' ) ) ) {
			return;
		}
		
		if ( ! $nonce ) {
			return;
		}
			
		$posted_id = isset( $_POST[ 'post_ID' ] ) ? $_POST[ 'post_ID' ] : 0;
		if ( $posted_id != $post_id ) {
			return;
		}
		// While we're at it, let's check the nonce
		check_admin_referer( "bbl_resync_translation-$post_id", '_bbl_metabox_resync' );
		
		if ( $this->no_recursion ) {
			return;
		}
		$this->no_recursion = true;

		$taxonomies = get_object_taxonomies( $post->post_type );
		$origin_post = bbl_get_post_in_lang( $post_id, bbl_get_default_lang_code() );

		// First dissociate all the terms from synced taxonomies from this post
		wp_delete_object_term_relationships( $post_id, $taxonomies );

		// Now associate terms from synced taxonomies in from the origin post
		foreach ( $taxonomies as $taxonomy ) {
			$origin_taxonomy = $taxonomy;
			if ( $this->is_taxonomy_translated( $taxonomy ) ) {
				$origin_taxonomy = bbl_get_taxonomy_in_lang( $taxonomy, bbl_get_default_lang_code() );
			}
			$term_ids = wp_get_object_terms( $origin_post->ID, $origin_taxonomy, array( 'fields' => 'ids' ) );
			$term_ids = array_map( 'absint', $term_ids );
			$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			if ( is_wp_error( $result, true ) ) {
				throw new exception( "Problem syncing terms: " . print_r( $terms, true ), " Error: " . print_r( $result, true ) );
			}
		}
	}

}

global $bbl_taxonomies;
$bbl_taxonomies = new Babble_Taxonomies();
