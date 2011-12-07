<?php

/**
 * Class for handling the public, content handling post types.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Post_Public extends Babble_Plugin {
	
	/**
	 * A simple flag to stop infinite recursion in various places.
	 *
	 * @var boolean
	 **/
	protected $no_recursion;

	/**
	 * The shadow (translated) post types created by this plugin.
	 *
	 * @var array
	 **/
	protected $post_types;

	/**
	 * A structure describing the languages served by various post types.
	 *
	 * @var array
	 **/
	protected $lang_map;

	/**
	 * Another structure describing the languages served by various post types.
	 *
	 * @var array
	 **/
	protected $lang_map2;

	// /**
	//  * Regex for detecting the language from a URL
	//  *
	//  * @var string
	//  **/
	// protected $lang_regex = '|^[^/]+|i';
	
	public function __construct() {
		$this->setup( 'babble-post-public', 'plugin' );

		$this->add_action( 'added_post_meta', null, null, 4 );
		$this->add_action( 'deleted_post_meta', null, null, 4 );
		$this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'parse_request' );
		$this->add_action( 'pre_get_posts' );
		$this->add_action( 'registered_post_type', null, null, 2 );
		$this->add_action( 'updated_post_meta', null, null, 4 );
		$this->add_action( 'wp_insert_post', null, null, 2 );
		$this->add_filter( 'add_menu_classes' );
		$this->add_filter( 'page_link', null, null, 2 );
		$this->add_filter( 'posts_request' );
		$this->add_filter( 'post_link', 'post_type_link', null, 3 );
		$this->add_filter( 'post_type_link', null, null, 3 );
		$this->add_filter( 'single_template' );
		
		$this->post_types = array();
		$this->lang_map = array();
	}

	/**
	 * Hooks the WP init action early
	 *
	 * @return void
	 **/
	public function init_early() {
		register_taxonomy( 'term_translation', 'term', array(
			'rewrite' => false,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'label' => __( 'Term Translation ID', 'sil' ),
		) );

		// Ensure we catch any existing language shadow post_types already registered
		if ( is_array( $this->post_types ) )
			$post_types = array_merge( array( 'post', 'page' ), array_keys( $this->post_types ) );
		else
			$post_types = array( 'post', 'page' );
		register_taxonomy( 'post_translation', $post_types, array(
			'rewrite' => false,
			'public' => false,
			'show_ui' => false,
			'show_in_nav_menus' => false,
			'label' => __( 'Post Translation ID', 'sil' ),
		) );
	}

	/**
	 * Hooks the WP registered_post_type action. 
	 * 
	 * N.B. THIS HOOK IS NOT IMPLEMENTED UNTIL WP 3.3
	 *
	 * @param string $post_type The post type which has just been registered. 
	 * @param array $args The arguments with which the post type was registered
	 * @return void
	 **/
	public function registered_post_type( $post_type, $args ) {
		// Don't bother with non-public post_types for now
		// @FIXME: This may need to change for menus?
		if ( ! $args->public )
			return;

		// Don't shadow shadow post types, it's going to get silly
		if ( in_array( $post_type, $this->post_types ) )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		// @FIXME: Not sure this is the best way to specify languages
		$langs = bbl_get_active_langs();

		// Lose the default language as any existing post types are in that language
		unset( $langs[ bbl_get_default_lang_url_prefix() ] );

		// $args is an object at this point, but register_post_type needs an array
		$args = get_object_vars( $args );
		// @FIXME: Is it reckless to convert ALL object instances in $args to an array?
		foreach ( $args as $key => & $arg ) {
			if ( is_object( $arg ) )
				$arg = get_object_vars( $arg );
			// Don't set any args reserved for built-in post_types
			if ( '_' == substr( $key, 0, 1 ) )
				unset( $args[ $key ] );
		}

		$args[ 'supports' ] = array(
			'title',
			'editor',
			'comments',
			'revisions',
			'trackbacks',
			'author',
			'excerpt',
			'page-attributes',
			'thumbnail',
			'custom-fields'
		);

		// I am a little concerned that this argument may make things
		// brittle, e.g. the UI might stop showing up in the shadow
		// post type edit screens, p'raps.
		$args[ 'show_ui' ] = true;
		$slug = ( $args[ 'rewrite' ][ 'slug' ] ) ? $args[ 'rewrite' ][ 'slug' ] : $post_type;

		foreach ( $langs as $lang ) {
			$new_args = $args;

			// @FIXME: We are in danger of a post_type name being longer than 20 chars
			// I would prefer to keep the post_type human readable, as human devs and sysadmins always 
			// end up needing to read this kind of thing.
			$new_post_type = strtolower( "{$post_type}_{$lang->code}" );

			foreach ( $new_args[ 'labels' ] as & $label )
				$label = "$label ({$lang->code})";
				
			if ( false !== $args[ 'rewrite' ] ) {
				if ( ! is_array( $new_args[ 'rewrite' ] ) )
					$new_args[ 'rewrite' ] = array();
				// Do I not need to add this query_var into the query_vars filter? It seems not.
				$new_args[ 'query_var' ] = $new_args[ 'rewrite' ][ 'slug' ] = $this->get_translated_slug( $slug, $lang->code );
			}

			$result = register_post_type( $new_post_type, $new_args );
			if ( is_wp_error( $result ) ) {
				// bbl_log( "Error creating shadow post_type for $new_post_type: " . print_r( $result, true ) );
			} else {
				$this->post_types[ $new_post_type ] = $post_type;
				$this->lang_map[ $new_post_type ] = $lang->code;

				// @TODO: Refactor the $this::lang_map array so we can use this new structure instead
				if ( ! isset( $this->lang_map2[ $lang->code ] ) || ! is_array( $this->lang_map2[ $lang->code ] ) )
					$this->lang_map2[ $lang->code ] = array();
				$this->lang_map2[ $lang->code ][ $post_type ] = $new_post_type;

				// This will not work until init has run at the early priority used
				// to register the post_translation taxonomy. However we catch all the
				// post_types registered before the hook runs, so we don't miss any 
				// (take a look at where we register post_translation for more info).
				register_taxonomy_for_object_type( 'post_translation', $new_post_type );
			}
		}
		do_action( 'bbl_registered_shadow_post_types', $post_type );
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP added_post_meta action to sync metadata across to the
	 * translations in shadow post types.
	 *
	 * @param int $meta_id The ID for this meta entry
	 * @param int $post_id The ID for the WordPress Post object this meta relates to
	 * @param string $meta_key The key for this meta entry
	 * @param mixed $meta_value The new value for this meta entry
	 * @return void
	 **/
	public function added_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Some metadata shouldn't be synced
		if ( in_array( $meta_key, apply_filters( 'bbl_unsynced_meta_keys', array() )  ) )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			add_post_meta( $translation->ID, $meta_key, $meta_value );
		}
		
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP updated_post_meta action to sync metadata across to the
	 * translations in shadow post types.
	 *
	 * @param int $meta_id The ID for this meta entry
	 * @param int $post_id The ID for the WordPress Post object this meta relates to
	 * @param string $meta_key The key for this meta entry
	 * @param mixed $meta_value The new value for this meta entry
	 * @return void
	 **/
	public function updated_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Some metadata shouldn't be synced
		if ( in_array( $meta_key, apply_filters( 'bbl_unsynced_meta_keys', array() )  ) )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			update_post_meta( $translation->ID, $meta_key, $meta_value );
		}
		
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP deleted_post_meta action to sync metadata across to the
	 * translations in shadow post types.
	 *
	 * @param int $meta_id The ID for this meta entry
	 * @param int $post_id The ID for the WordPress Post object this meta relates to
	 * @param string $meta_key The key for this meta entry
	 * @param mixed $meta_value The new value for this meta entry
	 * @return void
	 **/
	public function deleted_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Some metadata shouldn't be synced
		if ( in_array( $meta_key, apply_filters( 'bbl_unsynced_meta_keys', array() )  ) )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = true;

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			delete_post_meta( $translation->ID, $meta_key );
		}
		
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP pre_get_posts ref action in the WP_Query,
	 * for the main query it does nothing, for other queries
	 * if switches the post types to our shadow post types.
	 *
	 * @param object $wp_query A WP_Query object, passed by reference
	 * @return void (param passed by reference)
	 **/
	public function pre_get_posts( $query ) {
		if ( ! bbl_translating() ) {
			return;
		}
		if ( $query->is_main_query() ) {
			return;
		}
		
		$query->query_vars = $this->translate_query_vars( $query->query_vars );
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
		if ( ! bbl_translating() ) {
			return;
		}
		global $bbl_locale, $bbl_languages;

		if ( is_admin() ) {
			return;
		}

		$wp->query_vars = $this->translate_query_vars( $wp->query_vars, $wp->request );
	}

	/**
	 * Hooks posts_request.
	 *
	 * @param  
	 * @return void
	 **/
	public function posts_request( $query ) {
		// error_log( "Query: $query" );
		return $query;
	}

	/**
	 * Hooks the WP the_posts filter on WP_Query. 
	 * 
	 * Check the post_title, post_excerpt, post_content and substitute from
	 * the default language where appropriate.
	 *
	 * @param array $posts The posts retrieved by WP_Query, passed by reference 
	 * @param object $wp_query The WP_Query, passed by reference 
	 * @return array The posts
	 **/
	public function the_posts( $posts, $wp_query ) {
		if ( is_admin() )
			return $posts;
		
		// Get fallback content
		$subs_index = array();
		foreach ( $posts as & $post ) {
			if ( empty( $post->post_title ) || empty( $post->post_excerpt ) || empty( $post->post_content ) ) {
				if ( $default_post = bbl_get_default_lang_post( $post->ID ) )
					$subs_index[ $post->ID ] = $default_post->ID;
			}
			if ( ! $this->get_transid( $post ) && bbl_get_default_lang_code() == bbl_get_post_lang_code( $post ) )
				$this->set_transid( $post );
		}
		if ( ! $subs_index )
			return $posts;
		$subs_posts = get_posts( array( 'include' => array_values( $subs_index ), 'post_status' => 'publish' ) );
		// @FIXME: Check the above get_posts call results are cached somewhere… I think they are
		// @FIXME: Alternative approach: hook on save_post to save the current value to the translation, BUT content could get out of date – in post_content_filtered
		foreach ( $posts as & $post ) {
			// @FIXME: I'm assuming this get_post call is cached, which it seems to be
			$default_post = get_post( $subs_index[ $post->ID ] );
			if ( empty( $post->post_title ) )
				$post->post_title = 'Fallback: ' . $default_post->post_title;
			if ( empty( $post->post_excerpt ) )
				$post->post_excerpt = "Fallback excerpt\n\n" . $default_post->post_excerpt;
			if ( empty( $post->post_content ) )
				$post->post_content = "Fallback content\n\n" . $default_post->post_content;
		}
		return $posts;
	}

	/**
	 * Hooks the WP post_type_link filter 
	 *
	 * @param string $post_link The permalink 
	 * @param object $post The WP Post object being linked to
	 * @return string The permalink
	 **/
	public function post_type_link( $post_link, $post, $leavename ) {
		global $wp_rewrite;
	
		// Regular ol' post types, and other types added by other plugins, etc
		if ( 'post' == $post->post_type || 'page' == $post->post_type || ! isset( $this->post_types[ $post->post_type ] ) )
			return $post_link;
	
		// Deal with our shadow post types
		if ( ! ( $base_post_type = $this->post_types[ $post->post_type ] ) ) 
			return $post_link;
	
		// Deal with post_types shadowing the post post_type
		if ( 'post' == $base_post_type ) {
			// @FIXME: Probably move this into another function
			// @FIXME: Is there any way I can provide an appropriate permastruct so I can avoid having to copy all this code, with the associated maintenance headaches?
			// START copying from get_permalink function
			// N.B. The $permalink var is replaced with $post_link
			$rewritecode = array(
				'%year%',
				'%monthnum%',
				'%day%',
				'%hour%',
				'%minute%',
				'%second%',
				'%postname%',
				'%post_id%',
				'%category%',
				'%author%',
				'%pagename%',
			);
	
			$post_link = get_option('permalink_structure');
	
			// @FIXME: Should I somehow fake this, so plugin authors who hook it still get some consequence?
			// $post_link = apply_filters('pre_post_link', $post_link, $post, $leavename);
	
			if ( '' != $post_link && ! in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
				$unixtime = strtotime($post->post_date);
	
				$category = '';
				if ( strpos($post_link, '%category%') !== false ) {
					$cats = get_the_category($post->ID);
					if ( $cats ) {
						usort($cats, '_usort_terms_by_ID'); // order by ID
						$category = $cats[0]->slug;
						if ( $parent = $cats[0]->parent )
							$category = get_category_parents($parent, false, '/', true) . $category;
					}
					// show default category in permalinks, without
					// having to assign it explicitly
					if ( empty($category) ) {
						$default_category = get_category( get_option( 'default_category' ) );
						$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
					}
				}
	
				$author = '';
				if ( strpos($post_link, '%author%') !== false ) {
					$authordata = get_userdata($post->post_author);
					$author = $authordata->user_nicename;
				}
	
				$date = explode(" ",date('Y m d H i s', $unixtime));
				$rewritereplace =
				array(
					$date[0],
					$date[1],
					$date[2],
					$date[3],
					$date[4],
					$date[5],
					$post->post_name,
					$post->ID,
					$category,
					$author,
					$post->post_name,
				);
				$lang = bbl_get_post_lang_code( $post );
				bbl_switch_to_lang( $lang );
				$post_link = home_url( str_replace( $rewritecode, $rewritereplace, $post_link ) );
				bbl_restore_lang();
				$post_link = user_trailingslashit($post_link, 'single');
				// END copying from get_permalink function
				return $post_link;
			} else { // if they're not using the fancy permalink option the link won't work. Known bug. :)
				return $post_link;
			}
	
		} else if ( 'page' == $base_post_type ) {
			return get_page_link( $post->ID, $leavename );
		}
	
		return $post_link;
	}

	/**
	 * Hooks the WP page_link filter to ensure correct virtual language directory prefix, etc.
	 *
	 * @param string $link The permalink for the page
	 * @param int $id The ID for the post represented by this permalink 
	 * @return string
	 **/
	public function page_link( $link, $post_id ) {
		if ( $this->no_recursion )
			return $link;

		// Deal with the language front pages
		if ( 'page' == get_option('show_on_front') ) {
			$front_page_transid = $this->get_transid( get_option( 'page_on_front' ) );
			$this_transid = $this->get_transid( $post_id );
			if ( $front_page_transid == $this_transid ) {
				bbl_switch_to_lang( bbl_get_post_lang_code( $post_id ) );
				$link = home_url();
				bbl_restore_lang();
				return $link;
			}
		}

		$this->no_recursion = true;
		$lang = bbl_get_post_lang_code( $post_id );
		bbl_switch_to_lang( $lang );
		$link = get_page_link( $post_id );
		bbl_restore_lang();
		$this->no_recursion = false;
		return $link;
	}

	/**
	 * Hooks the WP wp_insert_post action to set a transid on 
	 *
	 * @param int $post_id The ID of the post which has just been inserted
	 * @param object $post The WP Post object which has just been inserted 
	 * @return void
	 **/
	public function wp_insert_post( $new_post_id, $new_post ) {
		if ( $this->no_recursion )
			return;

		if ( 'auto-draft' != $new_post->post_status )
			return;

		$this->no_recursion = true;

		// Get any approved term ID for the transid for any new translation
		$transid = (int) @ $_GET[ 'bbl_transid' ];
		$this->set_transid( $new_post, $transid );

		$origin_id = (int) @ $_GET[ 'bbl_origin_id' ];

		// Ensure the post is in the correct shadow post_type
		if ( bbl_get_default_lang_code() != bbl_get_current_lang_code() ) {
			$new_post_type = $this->get_post_type_in_lang( $new_post->post_type, bbl_get_current_lang_code() );
			wp_update_post( array( 'ID' => $new_post_id, 'post_type' => $new_post_type ) );
		}
		$metas = $this->get_all_post_meta( $origin_id );
		foreach ( $metas as $meta ) {
			// Some metadata shouldn't be synced
			if ( in_array( $meta->meta_key, apply_filters( 'bbl_unsynced_meta_keys', array() )  ) )
				continue;
			add_post_meta( $new_post_id, $meta->meta_key, $meta->meta_value );
		}
		$this->no_recursion = false;
		
		do_action( 'bbl_created_new_shadow_post', $new_post_id, $origin_id );
		
		// Now we have to do a redirect, to ensure the WP Nonce gets generated correctly
		wp_redirect( admin_url( "/post.php?post={$new_post_id}&action=edit&post_type={$new_post->post_type}" ) );
	}

	/**
	 * Hooks the WP add_menu_classes filter to try and remove the
	 * links to shadow post types.
	 *
	 * @param array $menu The WP admin menu 
	 * @return array The WP admin menu
	 **/
	public function add_menu_classes( $menu ) {
		foreach ( $menu as $key => $item ) {
			$vars = array();
			$url_info = parse_url( $item[ 2 ] );
			if ( ! isset( $url_info[ 'query' ] ) )
				continue;
			parse_str( $url_info[ 'query' ], $vars );
			if ( ! isset( $vars[ 'post_type' ] ) || ! isset( $this->post_types[ $vars[ 'post_type' ] ] ) )
				continue;
			unset( $menu[ $key ] );
		}
		return $menu;
	}

	/**
	 * Hooks the WP filter single_template to deal with the shadow post
	 * types for pages, ensuring they use the right template.
	 *
	 * @param string $template Path to a template file 
	 * @return Path to a template file
	 **/
	public function single_template( $template ) {
		// Deal with the language front pages and custom page templates
		if ( 'page' == get_option('show_on_front') ) {
			$front_page_transid = $this->get_transid( get_option( 'page_on_front' ) );
			$this_transid = $this->get_transid( get_the_ID() );
			if ( $front_page_transid == $this_transid ) {
				$post = get_post( get_the_ID() );
				// global $wp_query, $wp;
				if ( 'page' == $this->get_base_post_type( $post->post_type ) ) {
					if ( $custom_page_template = get_post_meta( get_option( 'page_on_front' ), '_wp_page_template', true ) )
						$templates = array( $custom_page_template );
					else
						$templates = array( 'page.php' );
					if ( $_template = locate_template( $templates ) ) {
						return $_template;
					}
				}
			}
		}
		return $template;
	}
	
	// PUBLIC METHODS
	// ==============

	/**
	 * Takes a set of query vars and amends them to show the content
	 * in the current language.
	 *
	 * @param array $query_vars A set of WordPress query vars (sometimes called query arguments)
	 * @param string|boolean $request If this is called on the parse_request hook, $request contains the root relative URL
	 * @return array $query_vars A set of WordPress query vars
	 **/
	protected function translate_query_vars( $query_vars, $request = false ) {

		// Sequester the original query, in case we need it to get the default content later
		$query_vars[ 'sil_original_query' ] = $query_vars;

		// We were here (avoid doing this again)
		if ( isset( $query_vars[ 'sil_done_translation' ] ) && $query_vars[ 'sil_done_translation' ] )
			return $query_vars;
		$query_vars[ 'sil_done_translation' ] = true;

		$lang_url_prefix = isset( $query_vars[ 'lang_url_prefix' ] ) ? $query_vars[ 'lang_url_prefix' ] : get_query_var( 'lang_url_prefix' );
		$lang = isset( $query_vars[ 'lang' ] ) ? $query_vars[ 'lang' ] : get_query_var( 'lang' );

		// Detect language specific homepages
		if ( $request == $lang_url_prefix ) {
			unset( $query_vars[ 'error' ] );

			// @FIXME: Cater for front pages which don't list the posts
			if ( 'page' == get_option('show_on_front') && get_option('page_on_front') ) {
				// @TODO: Get translated page ID
				$query_vars[ 'p' ] = $this->get_post_in_lang( get_option('page_on_front'), bbl_get_current_lang_code() )->ID;
				$query_vars[ 'post_type' ] = $this->get_post_type_in_lang( 'page', bbl_get_current_lang_code() );
				return $query_vars;
			}

			// Trigger the archive listing for the relevant shadow post type
			// for this language.
			if ( bbl_get_default_lang_code() != $lang ) {
				$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( 'post', bbl_get_current_lang_code() );
			}
			return $query_vars;
		}

		// If we're asking for the default content, it's fine
		if ( bbl_get_default_lang_code() == $lang ) {
			return $query_vars;
		}

		// Now swap the query vars so we get the content in the right language post_type

		// @FIXME: Do I need to change $wp->matched query? I think $wp->matched_rule is fine?
		// @FIXME: Danger of post type slugs clashing??
		if ( isset( $query_vars[ 'pagename' ] ) && $query_vars[ 'pagename' ] ) {
			// Substitute post_type for 
			$query_vars[ 'name' ] = $wp->query_vars[ 'pagename' ];
			$query_vars[ bbl_get_post_type_in_lang( 'page', $query_vars[ 'lang' ] ) ] = $query_vars[ 'pagename' ];
			$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( 'page', bbl_get_current_lang_code() );
			unset( $query_vars[ 'page' ] );
			unset( $query_vars[ 'pagename' ] );
		} elseif ( isset( $query_vars[ 'year' ] ) ) { 
			// @FIXME: This is not a reliable way to detect queries for the 'post' post_type.
			$query_vars[ 'post_type' ] =  bbl_get_post_type_in_lang( 'post', bbl_get_current_lang_code() );
		} elseif ( isset( $query_vars[ 'post_type' ] ) ) { 
			$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( $query_vars[ 'post_type' ], bbl_get_current_lang_code() );
		}

		return $query_vars;
	}

	/**
	 * Discover whether a post is set as the front page
	 * for the site in a particular language.
	 *
	 * @param int $post_id The ID of a post 
	 * @return boolean True if this post is used as the front page of the site for a language
	 **/
	public function is_language_front_page( $post_id = null, $lang_code = null ) {
		if ( 'page' != get_option('show_on_front') )
		 	return false;

		$post = get_post( $post_id );
		// If we have a lang code, and it doesn't match the requested post lang then this 
		// is not the right front page
		if ( ! is_null( $lang_code ) && $lang_code != $this->get_post_lang_code( $post->ID ) )
			return false;
		
		$front_page_transid = $this->get_transid( get_option( 'page_on_front' ) );
		$this_transid = $this->get_transid( get_the_ID() );
		if ( $front_page_transid != $this_transid )
			return false;

		return true;
	}

	/**
	 * Return the language code for the language a given post is written for/in.
	 *
	 * @param int|object $post Either a WP Post object, or a post ID 
	 * @return string|object Either a language code, or a WP_Error object
	 * @access public
	 **/
	public function get_post_lang_code( $post ) {
		$post = get_post( $post );
		if ( ! $post )
			return new WP_Error( 'invalid_post', __( 'Invalid Post' ) );
		if ( isset( $this->lang_map[ $post->post_type ] ) )
			return $this->lang_map[ $post->post_type ];
		return bbl_get_default_lang_code();
	}

	/**
	 * Return the admin URL to create a new translation for a post in a
	 * particular language.
	 *
	 * @param int|object $default_post The post in the default language to create a new translation for, either WP Post object or post ID
	 * @param string $lang The language code 
	 * @return string The admin URL to create the new translation
	 **/
	public function get_new_post_translation_url( $default_post, $lang_code ) {
		$default_post = get_post( $default_post );
		bbl_switch_to_lang( $lang_code );
		$transid = $this->get_transid( $default_post );
		$url = admin_url( '/post-new.php' );
		$args = array( 
			'bbl_transid' => $transid, 
			'bbl_origin_id' => $default_post->ID, 
			'lang' => $lang_code, 
			'post_type' => $this->get_post_type_in_lang( $default_post->post_type, $lang_code ),
		);
		$url = add_query_arg( $args, $url );
		bbl_restore_lang();
		return $url;
	}

	/**
	 * Returns the post ID for the post in the default language from which 
	 * this post was translated.
	 *
	 * @param int|object $post Either a WP Post object, or a post ID 
	 * @return int The ID of the default language equivalent post
	 **/
	public function get_default_lang_post( $post ) {
		$post = get_post( $post );
		$translations = bbl_get_post_translations( $post->ID );
		if ( isset( $translations[ bbl_get_default_lang_code() ] ) )
			return $translations[ bbl_get_default_lang_code() ];
		return false;
	}

	/**
	 * Get the posts which are the translations for the provided 
	 * post ID. N.B. The returned array of post objects (and false 
	 * values) will include the post for the post ID passed.
	 * 
	 * @FIXME: Should I filter out the post ID passed?
	 *
	 * @param int|object $post Either a WP Post object, or a post ID 
	 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
	 **/
	public function get_post_translations( $post ) {
		$post = get_post( $post );
		// @FIXME: Is it worth caching here, or can we just rely on the caching in get_objects_in_term and get_posts?
		$transid = $this->get_transid( $post );
		if ( is_wp_error( $transid ) )
			error_log( "Error getting transid: " . print_r( $transid, true ) );
		$post_ids = get_objects_in_term( $transid, 'post_translation' );
		// Get all the translations in one cached DB query
		$args = array(
			'include' => $post_ids,
			'post_type' => 'any',
			'post_status' => array( 'publish', 'pending', 'draft', 'future' ),
		);
		// We want a clean listing, without any particular language
		bbl_stop_translating();
		$posts = get_posts( $args );
		bbl_start_translating();
		$translations = array();
		foreach ( $posts as & $post ) {
			if ( isset( $this->lang_map[ $post->post_type ] ) )
				$translations[ $this->lang_map[ $post->post_type ] ] = $post;
			else
				$translations[ bbl_get_default_lang_code() ] = $post;
		}
		return $translations;
	}

	/**
	 * Return the base post type (in the default language) for a 
	 * provided post type.
	 *
	 * @param string $post_type The name of a post type 
	 * @return string The name of the base post type
	 **/
	public function get_base_post_type( $post_type ) {
		if ( ! isset( $this->post_types[ $post_type ] ) )
			return $post_type;
		return $this->post_types[ $post_type ];
	}

	/**
	 * Returns the equivalent post_type in the specified language.
	 *
	 * @param string $taxonomy A post_type to return in a given language
	 * @param string $lang_code The language code for the required language 
	 * @return boolean|string The equivalent post_type name, or false if it doesn't exist
	 **/
	public function get_post_type_in_lang( $post_type, $lang_code ) {
		$base_post_type = $this->get_base_post_type( $post_type );
		if ( bbl_get_default_lang_code() == $lang_code )
			return $base_post_type;
		if ( ! isset( $this->lang_map2[ $lang_code ][ $base_post_type ] ) )
			return false;
		return $this->lang_map2[ $lang_code ][ $base_post_type ];
	}

	/**
	 * Returns an array of all the shadow post types associated with
	 * this post type.
	 *
	 * @param string $base_post_type The post type to look up shadow post types for 
	 * @return array The names of all the related shadow post types
	 **/
	public function get_shadow_post_types( $base_post_type ) {
		$post_types = array();
		$langs = bbl_get_active_langs();
		foreach ( $langs as $lang ) {
			if ( isset( $this->lang_map2[ $lang->code ][ $base_post_type ] ) )
				$post_types[] = $this->lang_map2[ $lang->code ][ $base_post_type ];
		}
		return $post_types;
	}

	/**
	 * Returns the post in a particular language, or 
	 *
	 * @param int|object $post Either a WP Post object, or a post ID 
	 * @param string $lang_code The language code for the required language 
	 * @param boolean $fallback If true: if a post is not available, fallback to the default language content (defaults to true)
	 * @return object|boolean The WP Post object, or if $fallback was false and no post then returns false
	 **/
	public function get_post_in_lang( $post, $lang_code, $fallback = true ) {
		$translations = $this->get_post_translations( $post );
		if ( isset( $translations[ $lang_code ] ) ) {
			return $translations[ $lang_code ];
		}
		if ( ! $fallback ) {
			return false;
		}
		return $translations[ bbl_get_default_lang_code() ];
	}

	/**
	 * Returns a slug translated into a particular language.
	 *
	 * @param string $slug The slug to translate
	 * @param string $lang_code The language code for the required language (optional, defaults to current)
	 * @return void
	 **/
	public function get_translated_slug( $slug, $lang_code = null ) {
		if ( is_null( $lang_code ) )
			$lang_code = bbl_get_current_lang_code();
		$_slug = strtolower( apply_filters( 'bbl_translate_post_type_slug', $slug ) );
		if ( $_slug &&  $_slug != $slug )
			return $_slug;
		// Do we need to check that the slug is unique at this point?
		return strtolower( "{$_slug}_{$lang_code}" );
	}
	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Gets all the post meta for a post in an array.
	 * 
	 * Hello VIP Code reviewer. I imagine you've just noticed the
	 * direct database access at this point, and are wondering just
	 * what the heck I think I'm doing? The issue is that I need to
	 * clone a post, including postmeta and there is no built-in
	 * meta API function to get all the postmeta entries for
	 * a given post. Hope we can agree that this is OK, unless
	 * I'm missing something?
	 *
	 * @param int $post A WordPress post ID
	 * @return array An array of postmeta values
	 **/
	protected function get_all_post_meta( $post_id ) {
		global $wpdb;
		$sql = " SELECT * FROM $wpdb->postmeta WHERE post_id = %d ";
		return $wpdb->get_results( $wpdb->prepare( $sql, $post_id ) );
	}

	/**
	 * Get the transID for this post, this is an identifier linking all the translations 
	 * for a single piece of content together.
	 *
	 * Marked private as we may change how translations are linked. Please use API, or 
	 * raise an issue.
	 *
	 * @param int|object $post The WP Post object, or the ID of a post
	 * @return string The transid
	 * @access private
	 **/
	function get_transid( $post ) {
		$post = get_post( $post );
		$transids = (array) wp_get_object_terms( $post->ID, 'post_translation', array( 'fields' => 'ids' ) );
		// "There can be only one" (so we'll just drop the others)
		if ( isset( $transids[ 0 ] ) )
			return $transids[ 0 ];
		
		return $this->set_transid( $post );
	}

	/**
	 * Create and assign a new TransID to a post.
	 *
	 * @param int|object $post Either a Post ID or a WP Post object 
	 * @param string $transid (optional) A transid to associate with the post
	 * @return string The transid which has just been set
	 * @access private
	 **/
	function set_transid( $post, $transid = false ) {
		$post = get_post( $post );
		// @FIXME: Abstract the code for generating and associating a new TransID
		if ( ! $transid ) {
			$transid_name = 'post_transid_' . uniqid();
			$result = wp_insert_term( $transid_name, 'post_translation', array() );
			if ( is_wp_error( $result ) )
				error_log( "Problem creating a new Post TransID: " . print_r( $result, true ) );
			else
				$transid = $result[ 'term_id' ];
		}
		$result = wp_set_object_terms( $post->ID, $transid, 'post_translation' );
		if ( is_wp_error( $result ) )
			error_log( "Problem associating TransID with new posts: " . print_r( $result, true ) );
		
		return $transid;
	}

}

$bbl_post_public = new Babble_Post_Public();

?>