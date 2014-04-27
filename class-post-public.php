<?php

/**
 * Class for handling the public, content handling post types.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Post_Public extends Babble_Plugin {
	
	/**
	 * A simple flag to stop infinite recursion when syncing 
	 * post meta places.
	 *
	 * @var boolean
	 **/
	protected $no_meta_recursion;
	
	/**
	 * A simple flag to stop infinite recursion in various 
	 * places (except for post meta).
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

	/**
	 * A version number to use for cache busting, database updates, etc
	 *
	 * @var int
	 **/
	protected $version;
	
	/**
	 * An array of query_vars and slugs for our shadow post types,
	 * we use changes to this to determine if rewrite rules 
	 * need flushing.
	 *
	 * @var array
	 **/
	protected $slugs_and_vars;

	
	/**
	 * An array of Post IDs for posts that are in the process of
	 * being deleted.
	 *
	 * @var array
	 **/
	protected $deleting_post_ids;

	/**
	 * An array of meta_keys, indexed by meta_key, containing
	 * meta_keys we KNOW to be added as unique.
	 *
	 * @var array
	 **/
	protected $unique_meta_keys;
	
	public function __construct() {
		$this->setup( 'babble-post-public', 'plugin' );

		$this->add_action( 'added_post_meta', null, null, 4 );
		$this->add_action( 'admin_init' );
		$this->add_action( 'clean_post_cache' );
		$this->add_action( 'body_class', null, null, 2 );
		$this->add_action( 'before_delete_post', 'clean_post_cache' );
		$this->add_action( 'deleted_post', 'clean_post_cache' );
		$this->add_action( 'deleted_post_meta', null, null, 4 );
		$this->add_action( 'init', 'init_late', 9999 );
		$this->add_action( 'load-post-new.php', 'load_post_new' );
		$this->add_action( 'manage_pages_custom_column', 'manage_posts_custom_column', null, 2 );
		$this->add_action( 'manage_posts_custom_column', 'manage_posts_custom_column', null, 2 );
		$this->add_action( 'parse_request' );
		$this->add_action( 'post_updated' );
		$this->add_action( 'pre_get_posts', null, 11 );
		$this->add_action( 'registered_post_type', null, null, 2 );
		$this->add_action( 'transition_post_status', null, null, 3 );
		$this->add_action( 'updated_post_meta', null, null, 4 );
		$this->add_action( 'wp_before_admin_bar_render' );
		$this->add_filter( 'add_menu_classes' );
		$this->add_filter( 'add_post_metadata', null, null, 5 );
		$this->add_filter( 'bbl_sync_meta_key', 'sync_meta_key', null, 2 );
		$this->add_filter( 'manage_posts_columns', 'manage_posts_columns', null, 2 );
		$this->add_filter( 'page_link', null, null, 2 );
		$this->add_filter( 'post_link', 'post_type_link', null, 3 );
		$this->add_filter( 'post_type_archive_link', null, null, 2 );
		$this->add_filter( 'post_type_link', null, null, 3 );
		$this->add_filter( 'get_sample_permalink', null, null, 5 );
		$this->add_filter( 'single_template' );
		$this->add_filter( 'the_posts', null, null, 2 );
		$this->add_filter( 'bbl_translated_taxonomy', null, null, 2 );
		$this->add_filter( 'admin_body_class' );

		$this->initiate();
	}
	/**
	 * Initiates 
	 *
	 * @return void
	 **/
	public function initiate() {
		$this->lang_map = array();
		$this->post_types = array();
		$this->slugs_and_vars = array();
		$this->no_meta_recursion = false;
		$this->deleting_post_ids = array();

		$this->version = 9;

		// Ensure we catch any existing language shadow post_types already registered
		$core_post_types = array( 'post', 'page', 'attachment' );
		if ( is_array( $this->post_types ) )
			$post_types = array_merge( $core_post_types, array_keys( $this->post_types ) );
		else
			$post_types = $core_post_types;

		register_taxonomy( 'post_translation', $post_types, array(
			'rewrite' => false,
			'public' => false,
			'show_ui' => false,
			'show_in_nav_menus' => false,
		) );
	}

	/**
	 * Hooks the WP admin_init action to 
	 *
	 * @return void
	 **/
	public function admin_init() {
		$this->maybe_upgrade();
		$post_type = false;
		if ( isset( $_GET[ 'post_type' ] ) ) {
			$post_type = $_GET[ 'post_type' ];
		} else if ( isset( $_GET[ 'post' ] ) ) {
			$post = (int) $_GET[ 'post' ];
			$post = get_post( $post );
			$post_type = $post->post_type;
		}
		$menu_id = false;
		if ( isset( $this->post_types[ $post_type ] ) )
			$menu_id = '#menu-posts-' . $this->post_types[ $post_type ];

		$data = array(
			'menu_id' => $menu_id,
			'is_default_lang' => (bool) ( bbl_get_current_lang_code() == bbl_get_default_lang_code() ),
			'is_bbl_post_type' => (bool) ( 0 === strpos( $post_type, 'bbl_' ) ),
		);
		wp_enqueue_script( 'post-public-admin', $this->url( '/js/post-public-admin.js' ), array( 'jquery' ), $this->version );
		wp_localize_script( 'post-public-admin', 'bbl_post_public', $data );
	}

	/**
	 * Initialise a translation for the given post.
	 *
	 * @param  WP_Post|int $origin_post The origin post object or post ID
	 * @param  string      $lang_code   The language code for the new translation
	 * @return WP_Post The translation post
	 */
	public function initialise_translation( $origin_post, $lang_code ) {

		$origin_post   = get_post( $origin_post );
		$new_post_type = $this->get_post_type_in_lang( $origin_post->post_type, $lang_code );
		$transid       = $this->get_transid( $origin_post->ID );

		// Insert translation:
		$this->no_recursion = true;
		$new_post_id = wp_insert_post( array(
			'post_type'   => $new_post_type,
			'post_status' => 'draft',
		), true );
		$this->no_recursion = false;

		$new_post = get_post( $new_post_id );

		// Assign transid to translation:
		$this->set_transid( $new_post, $transid );

		// Copy all the metadata across
		$this->sync_post_meta( $new_post->ID );

		// Copy the various core post properties across
		$this->sync_properties( $origin_post->ID, $new_post->ID );

		do_action( 'bbl_created_new_shadow_post', $new_post->ID, $origin_post->ID );

		return $new_post;

	}

	/**
	 * Hooks the WP init action really really late.
	 *
	 * @TODO we should performance profile this. Two calls to serialise two potentially large objects might be slow.
	 *
	 * @return void
	 **/
	public function init_late() {
		$old_serialised = serialize( get_option( 'bbl_rewrites', 'NOTHING' ) );
		$new_serialised = serialize( $this->slugs_and_vars );
		if ( $old_serialised != $new_serialised ) {
			flush_rewrite_rules();
			update_option( 'bbl_rewrites', unserialize( $new_serialised ) );
		}
	}

	/**
	 * Hooks the WP load-post-new.php action to stop translators
	 * creating new posts in languages other than the default. 
	 *
	 * @return void
	 **/
	public function load_post_new() {
		$screen = get_current_screen();
		if ( 'post' != $screen->base || 'add' != $screen->action )
			return;
		if ( bbl_get_current_lang_code() == bbl_get_default_lang_code() )
			return;
		if ( !bbl_is_translated_post_type( $screen->post_type ) )
			return;

		wp_die( __( 'You can only create content in your site\'s default language. Please consult your editorial team.', 'babble' ), '', array( 'back_link' => true ) );
	}

	/**
	 * Hooks the WP wp_before_admin_bar_render action
	 * to prune out unneeded post type add controls from
	 * the add menu.
	 *
	 * @return void
	 **/
	public function wp_before_admin_bar_render() {
		global $wp_admin_bar;
		if ( ! bbl_is_default_lang() )
			$wp_admin_bar->remove_node( 'new-content' );
	}

	/**
	 * Hooks the WP registered_post_type action. 
	 *
	 * @param string $post_type The post type which has just been registered. 
	 * @param object $args The arguments with which the post type was registered
	 * @return void
	 **/
	public function registered_post_type( $post_type, $args ) {
		// Don't bother with non-public post_types for now
		// @FIXME: This may need to change for menus?
		if ( false === $args->public )
			return;

		// Don't shadow shadow post types, it's going to get silly
		if ( in_array( $post_type, $this->post_types ) )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = 'registered_post_type';

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

		$features = $this->get_features_supported_by_post_type( $post_type );
		$args[ 'supports' ] = array();
		foreach ( $features as $feature => $true )
			$args[ 'supports' ][] = $feature;

		// I am a little concerned that this argument may make things
		// brittle, e.g. the UI might stop showing up in the shadow
		// post type edit screens, p'raps.
		$args[ 'show_ui' ] = true;

		$slug = ( $args[ 'rewrite' ][ 'slug' ] ) ? $args[ 'rewrite' ][ 'slug' ] : $post_type;
		$archive_slug = false;
		if ( $archive_slug = $args[ 'has_archive' ] )
			if ( ! is_string( $args[ 'has_archive' ] ) )
				$archive_slug = $slug;

		foreach ( $langs as $lang ) {
			$new_args = $args;
				

			// @FIXME: We are in danger of a post_type name being longer than 20 chars
			// I would prefer to keep the post_type human readable, as human devs and sysadmins always 
			// end up needing to read this kind of thing.
			// @FIXME: Should I be sanitising these values?
			$new_post_type = strtolower( "{$post_type}_{$lang->code}" );

			if ( false !== $args[ 'rewrite' ] ) {
				if ( ! is_array( $new_args[ 'rewrite' ] ) )
					$new_args[ 'rewrite' ] = array();
				$new_args[ 'query_var' ] = $new_args[ 'rewrite' ][ 'slug' ] = $this->get_slug_in_lang( $slug, $lang, $args );
				$new_args[ 'has_archive' ] = $this->get_slug_in_lang( $archive_slug, $lang );
			}
			$this->slugs_and_vars[ $lang->code . '_' . $post_type ] = array( 
				'query_var' => $new_args[ 'query_var' ],
				'has_archive' => $new_args[ 'has_archive' ],
			);

			$new_args['show_in_admin_bar'] = false;

			$result = register_post_type( $new_post_type, $new_args );
			if ( is_wp_error( $result ) ) {
				error_log( "Error creating shadow post_type for $new_post_type: " . print_r( $result, true ) );
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
	 * Store whether a particular meta_key is unique or not. Pretty hacky.
	 *
	 * @param null $null Follows a pattern for actions/filters relating to meta, but meta ID not set yet so null
	 * @param int $post_id The ID for the WordPress Post object this meta relates to
	 * @param string $meta_key The key for this meta entry
	 * @param mixed $meta_value The new value for this meta entry
	 * @param bool $unique Whether the meta_key should be unique
	 * @return null Always return null, or we are bypassing the meta save to DB
	 **/
	public function add_post_metadata( $null, $post_id, $meta_key, $meta_value, $unique ) {
		if ( $unique ) {
			$this->unique_meta_keys[ $meta_key ] = $meta_key;
		}
		return null;
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
		if ( ! apply_filters( 'bbl_sync_meta_key', true, $meta_key ) )
			return;

		if ( $this->no_meta_recursion )
			return;
		$this->no_meta_recursion = 'added_post_meta';

		$unique = isset( $this->unique_meta_keys[ $meta_key ] );

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			add_post_meta( $translation->ID, $meta_key, $meta_value, $unique );
		}
		
		$this->no_meta_recursion = false;
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
		if ( ! apply_filters( 'bbl_sync_meta_key', true, $meta_key ) )
			return;

		if ( $this->no_meta_recursion )
			return;
		$this->no_meta_recursion = 'updated_post_meta';

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			update_post_meta( $translation->ID, $meta_key, $meta_value );
		}
		
		$this->updated_post_meta = false;
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
		
		// When we are deleting posts, we don't want to sync
		// the metadata deletion across the other posts in 
		// the same translation group
		if ( in_array( $post_id, $this->deleting_post_ids ) )
			return;

		// Some metadata shouldn't be synced
		if ( ! apply_filters( 'bbl_sync_meta_key', true, $meta_key ) )
			return;

		if ( $this->no_meta_recursion )
			return;
		$this->no_meta_recursion = 'deleted_post_meta';

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $this->get_post_lang_code( $post_id ) == $lang_code )
				continue;
			delete_post_meta( $translation->ID, $meta_key );
		}
		
		$this->no_meta_recursion = false;
	}

	/**
	 * Hooks the WP pre_get_posts ref action in the WP_Query,
	 * for the main query it does nothing, for other queries
	 * it switches the post types to our shadow post types.
	 *
	 * @param WP_Query $wp_query A WP_Query object, passed by reference
	 * @return void (param passed by reference)
	 **/
	public function pre_get_posts( WP_Query & $query ) {
		if ( false === $query->get( 'bbl_translate' ) ) {
			return;
		}
		if ( $query->is_main_query() ) {
			return;
		}
		# @TODO we should scrap this and more intelligently filter the QVs rather than basing it on whether we're on a media tab
		if ( $this->is_media_upload_tab( 'gallery' ) ) {
			return;
		}
		if ( $this->is_media_manager() ) {
			return;
		}
		
		$query->query_vars = $this->translate_query_vars( $query->query_vars );
	}

	/**
	 * Hooks the WP parse_request action 
	 *
	 * FIXME: Should I be extending and replacing the WP class?
	 *
	 * @param WP $wp WP object, passed by reference (so no need to return)
	 * @return void
	 **/
	public function parse_request( WP & $wp ) {

		if ( isset( $wp->query_vars['bbl_translate'] ) and ( false === $wp->query_vars['bbl_translate'] ) ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		$wp->query_vars = $this->translate_query_vars( $wp->query_vars, $wp->request );
	}

	/**
	 * Hooks the WP the_posts filter on WP_Query. 
	 * 
	 * Check the post_title, post_excerpt, post_content and substitute from
	 * the default language where appropriate.
	 *
	 * @param array $posts The posts retrieved by WP_Query, passed by reference 
	 * @param WP_Query $wp_query The WP_Query, passed by reference 
	 * @return array The posts
	 **/
	public function the_posts( array $posts, WP_Query & $wp_query ) {
		if ( is_admin() )
			return $posts;
		
		// Get fallback content in the default language
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
			// @TODO why does this only override the title/excerpt/content? Why not override the post object entirely?
			// @FIXME: I'm assuming this get_post call is cached, which it seems to be
			if( isset( $subs_index[ $post->ID ] ) ) {
				$default_post = get_post( $subs_index[ $post->ID ] );
				if ( empty( $post->post_title ) )
					$post->post_title = $default_post->post_title;
				if ( empty( $post->post_excerpt ) )
					$post->post_excerpt = $default_post->post_excerpt;
				if ( empty( $post->post_content ) )
					$post->post_content = $default_post->post_content;
			}
		}
		return $posts;
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
		// Shadow post_type archives also get the post_type class for
		// the default language
		if ( is_post_type_archive() && ! bbl_is_default_lang() )
			$classes[] = 'post-type-archive-' . bbl_get_post_type_in_lang( get_query_var( 'post_type' ), bbl_get_default_lang_code() );
		if ( is_single() )
			$classes[] = 'single-' . bbl_get_base_post_type( get_post_type() );
		return $classes;
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
			return user_trailingslashit( $post_link );
	
		// Deal with our shadow post types
		if ( ! ( $base_post_type = $this->get_base_post_type( $post->post_type ) ) ) 
			return user_trailingslashit( $post_link );
	
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
	
		return user_trailingslashit( $post_link );
	}

	/**
	 * Hooks the get_sample_permalink filter to provide a correct sample permalink
	 * in situations where the post_name has been hacked for a particular context.
	 * 
	 * @filter get_sample_permalink (not yet in existence, see http://core.trac.wordpress.org/attachment/ticket/22338)
	 * 
	 * @param array $permalink The array, like array( $permalink, $post_name )
	 * @param string $title A desired title (could be null)
	 * @param string $name A desired post name (could be null)
	 * @param int $id The Post ID 
	 * @param object $post A (hacked) Post object 
	 * @return array The array, like array( $permalink, $post_name )
	 */
	public function get_sample_permalink( $permalink, $title, $name, $id, $post ) {
		$permalink[ 0 ] = $this->post_type_link( $permalink[ 0 ], $post, $leavename );
		return $permalink;
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
		if ( 'page' == get_option('show_on_front') && $page_on_front = get_option( 'page_on_front' ) ) {
			$front_page_transid = $this->get_transid( $page_on_front );
			$this_transid = $this->get_transid( $post_id );
			if ( $front_page_transid == $this_transid ) {
				bbl_switch_to_lang( bbl_get_post_lang_code( $post_id ) );
				$link = home_url();
				bbl_restore_lang();
				return $link;
			}
		}

		$this->no_recursion = 'page_link';
		$lang = bbl_get_post_lang_code( $post_id );
		bbl_switch_to_lang( $lang );
		$link = get_page_link( $post_id );
		bbl_restore_lang();
		$this->no_recursion = false;
		return $link;
	}

	/**
	 * Hooks the WP post_type_archive_link filter to return the correct
	 * post type archive link for the current language.
	 *
	 * @param string $link The link to the post type archive (probably wrong for this language) 
	 * @param string $post_type The post_type we need an archive for (though we'll probably need to use a translated (shadow) post_type)
	 * @return string A URL for the translated (shadow) post_type archive
	 **/
	public function post_type_archive_link( $link, $post_type ) {
		if ( $this->no_recursion )
			return $link;
		$this->no_recursion = 'post_type_archive_link';

		$lang_post_type = $this->get_post_type_in_lang( $post_type, bbl_get_current_lang_code() );

		bbl_switch_to_lang( bbl_get_current_lang_code() );
		$link = get_post_type_archive_link( $lang_post_type );
		bbl_restore_lang();
		
		$this->no_recursion = false;
		return $link;
	}

	/**
	 * Hooks the WP clean_post_cache action to clear the Babble
	 * post translation and transid caches.
	 *
	 * Occasionally called directly by within this class.
	 *
	 * @param int $post_id The ID of the post to clear the caches for 
	 * @return void
	 **/
	function clean_post_cache( $post_id ) {
		wp_cache_delete( $post_id, 'bbl_post_transids' );
		// clean_post_cache gets called in some situations where
		// the post is already deleted, in which case do not
		// force the creation of a transid.
		if ( ! $transid = $this->get_transid( $post_id, false ) ) {
			return;
		}
		wp_cache_delete( $transid, 'bbl_post_translations' );
	}

	/**
	 * Hooks the WP post_updated action to ensure that the 
	 * required properties are copied to the other posts in 
	 * this translation group.
	 *
	 * @param int $post_id The ID of the post being updated
	 * @return void
	 **/
	public function post_updated( $post_id ) {
		if ( $this->no_recursion )
			return;
		$this->no_recursion = 'post_updated';

		$transid = $this->get_transid( $post_id );

		$this->clean_post_cache( $post_id );

		$translations = $this->get_post_translations( $post_id );
		foreach ( $translations as $lang_code => & $translation ) {
			if ( $translation->ID == $post_id )
				continue;
			// Copy the various core post properties across
			$this->sync_properties( $post_id, $translation->ID );
		}

		// Revert comment status, which often gets turned off by
		// auto drafts.
		$post_lang_code = bbl_get_post_lang_code( $post_id );
		if ( bbl_get_default_lang_code() != $post_lang_code ) {
			$source_post = bbl_get_post_in_lang( $post_id, bbl_get_default_lang_code() );
			$target_post = get_post( $post_id );
			$post_data = array(
				'ID' => $post_id,
				'comment_status' => $source_post->comment_status,
				'post_modified' => $target_post->post_modified,
				'post_modified_gmt' => $target_post->post_modified_gmt,
			);
			wp_update_post( $post_data );
		}
		
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP transition_post_status action which fires whenever
	 * a post status changes through use of wp_transition_post_status.
	 *
	 * @param string $new_status The new status 
	 * @param string $old_status The old status 
	 * @param object $post The post object
	 * @return void
	 **/
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( $new_status == $old_status )
			return;

		if ( $this->no_recursion ) {
			return;
		}
		$this->no_recursion = 'transition_post_status';

		if ( 'publish' == $new_status && $new_status != $old_status ) {
			// Ensure the date of publication of a translation gets
			// sync'd immediately with the original language post.
			if ( bbl_get_default_lang_code() != bbl_get_post_lang_code( $post->ID ) ) {
				$source_post = bbl_get_post_in_lang( $post->ID, bbl_get_default_lang_code() );
				$postdata = array(
					'ID' => $post->ID,
					'post_date' =>$source_post->post_date,
				);
				wp_update_post( $postdata );
			}
		}
			
		$this->no_recursion = false;
	}

	/**
	 * Hooks the WP add_menu_classes filter to fixup the side
	 * admin menu.
	 *
	 * @param array $menu The WP admin menu 
	 * @return array The WP admin menu
	 **/
	public function add_menu_classes( $menu ) {
		global $submenu;
		// Remove "new post" links from submenu(s) for non-default languages
		foreach ( $submenu as $parent => $items ) {
			foreach ( $items as $key => $item ) {
				if ( bbl_get_current_lang_code() != bbl_get_default_lang_code() ) {
					if ( 'post-new.php' == substr( $item[ 2 ], 0, 12 ) ) {
						unset( $submenu[ $parent ][ $key ] );
					}
				}
			}
		}
		// Remove links to shadow post types
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
	 * types for pages and singular templates, ensuring they use the 
	 * right template.
	 *
	 * @param string $template Path to a template file 
	 * @return Path to a template file
	 **/
	public function single_template( $template ) {
		if( bbl_is_default_lang() )
			return $template;

		// Deal with the language front pages and custom page templates
		$post = get_post( get_the_ID() );
		if ( 'page' == get_option('show_on_front') ) {
			$front_page_transid = $this->get_transid( get_option( 'page_on_front' ) );
			$this_transid = $this->get_transid( get_the_ID() );
			// Check if this is a translation of the page on the front of the site
			if ( $front_page_transid == $this_transid ) {
				// global $wp_query, $wp;
				if ( 'page' == $this->get_base_post_type( $post->post_type ) ) {
					if ( $custom_page_template = get_post_meta( get_option( 'page_on_front' ), '_wp_page_template', true ) )
						$templates = (array) $custom_page_template;
					else
						$templates = (array) 'page.php';
					if ( $_template = locate_template( $templates ) ) {
						return $_template;
					}
				}
			}
		}
		// Check if we're dealing with a page or a translation of a page
		if ( 'page' == $this->get_base_post_type( $post->post_type ) ) {
			$custom_page_template = get_post_meta( get_the_ID(), '_wp_page_template', true );
			if ( false !== $custom_page_template && 'default' != $custom_page_template )
				$templates = (array) $custom_page_template;
			else
				$templates = array( 'page.php' );
			if ( $_template = locate_template( $templates ) ) {
				return $_template;
			}
		}

		$templates[] = "single-{$this->get_base_post_type($post->post_type)}.php";
		$templates[] = "single.php";
		$template = get_query_template( 'single-posts', $templates );

		return $template;
	}

	/**
	 * Hooks the bbl_sync_meta_key filter from this class which checks 
	 * if a meta_key should be synced. If we return false, it won't be.
	 *
	 * @TODO correct inline docs
	 **/
	function sync_meta_key( $sync, $meta_key ) {
		$sync_not = array(
			'_edit_last', // Related to edit lock, should be individual to translations
			'_edit_lock', // The edit lock, should be individual to translations
			'_bbl_default_text_direction', // The text direction, should be individual to translations
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
		);
		if ( in_array( $meta_key, $sync_not ) )
			$sync = false;
		return $sync;
	}

	/**
	 * Hooks the WP manage_posts_columns filter to add our “link” column.
	 *
	 * @param array $cols The columns for this post type lists table
	 * @param string $post_type The post type for this lists table 
	 * @return array The columns
	 **/
	public function manage_posts_columns( array $columns, $post_type ) {
		// Insert our cols just before comments, or date.
		if ( $post_type == bbl_get_post_type_in_lang( $post_type, bbl_get_default_lang_code() ) )
			return $columns;
		# @TODO is this phrase localisable? Might need changing.
		$columns[ 'bbl_link' ] = __( 'Translation of', 'babble' );
		return $columns;
	}

	/**
	 * Hooks the WP manage_posts_custom_column action to add our “link” content.
	 *
	 * @param string $column_name The name of this column
	 * @param int $post_id The ID for the post for the row which parents this column
	 * @return void
	 **/
	public function manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'bbl_link' != $column_name )
			return;
		$default_post = bbl_get_post_in_lang( $post_id, bbl_get_default_lang_code() );
		if ( ! $default_post ) {
			echo '<em style="color: #bc0b0b">' . __( 'No link', 'babble' ) . '</em>';
			return;
		}
		$edit_link = get_edit_post_link( $default_post->ID );
		$edit_link = add_query_arg( array( 'lang' => bbl_get_default_lang_code() ), $edit_link );
		bbl_switch_to_lang( bbl_get_default_lang_code() );
		$view_link = get_permalink( $default_post->ID );
		bbl_restore_lang();
		$edit_title = esc_attr( sprintf( __( 'Edit the originating post: “%s”', 'babble' ), get_the_title( $default_post->ID ) ) );
		$view_title = esc_attr( sprintf( __( 'View the originating post: “%s”', 'babble' ), get_the_title( $default_post->ID ) ) );
		echo "<a href='$view_link' title='$view_title'>" . __( 'View', 'babble' ) . "</a> | <a href='$edit_link' title='$edit_title'>" . __( 'Edit', 'babble' ) . "</a>";
	}

	// PUBLIC METHODS
	// ==============

	public function bbl_translated_taxonomy( $translated, $taxonomy ) {
		if ( 'post_translation' == $taxonomy )
			return false;
		return $translated;
	}

	public function admin_body_class( $class ) {

		$post_type = get_current_screen() ? get_current_screen()->post_type : null;
		if ( $post_type )
			$class .= ' bbl-post-type-' . $post_type;

		return $class;

	}

	/**
	 * Takes a set of query vars and amends them to show the content
	 * in the current language.
	 *
	 * @param array $query_vars A set of WordPress query vars (sometimes called query arguments)
	 * @param string|boolean $request If this is called on the parse_request hook, $request contains the root relative URL
	 * @return array $query_vars A set of WordPress query vars
	 **/
	protected function translate_query_vars( array $query_vars, $request = false ) {

		// Sequester the original query, in case we need it to get the default content later
		$query_vars[ 'bbl_original_query' ] = $query_vars;

		// We've done this already (avoid re-translating the vars)
		if ( isset( $query_vars[ 'bbl_done_translation' ] ) && $query_vars[ 'bbl_done_translation' ] )
			return $query_vars;
		$query_vars[ 'bbl_done_translation' ] = true;

		$lang_url_prefix = isset( $query_vars[ 'lang_url_prefix' ] ) ? $query_vars[ 'lang_url_prefix' ] : get_query_var( 'lang_url_prefix' );
		$lang = isset( $query_vars[ 'lang' ] ) ? $query_vars[ 'lang' ] : get_query_var( 'lang' );

		// Detect language specific homepages
		if ( $request == $lang_url_prefix ) {
			unset( $query_vars[ 'error' ] );

			// @FIXME: Cater for front pages which don't list the posts
			if ( 'page' == get_option('show_on_front') && $page_on_front = get_option('page_on_front') ) {
				// @TODO: Get translated page ID
				$query_vars[ 'p' ] = $this->get_post_in_lang( $page_on_front, bbl_get_current_lang_code() )->ID;
				$query_vars[ 'post_type' ] = $this->get_post_type_in_lang( 'page', bbl_get_current_lang_code() );
				return $query_vars;
			}

			// Trigger the archive listing for the relevant shadow post type
			// of 'post' for this language.
			if ( bbl_get_default_lang_code() != $lang ) {
				$post_type = isset( $query_vars[ 'post_type' ] ) ? $query_vars[ 'post_type' ] : 'post';

				$query_vars[ 'post_type' ] = $this->get_post_type_in_lang( $post_type, bbl_get_current_lang_code() );

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
			$query_vars[ 'name' ] = $query_vars[ 'pagename' ];
			$query_vars[ bbl_get_post_type_in_lang( 'page', $query_vars[ 'lang' ] ) ] = $query_vars[ 'pagename' ];
			$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( 'page', bbl_get_current_lang_code() );
			// Trigger a listing of translated posts if this is meant to
			// be the blog page.
			if ( 'page' == get_option( 'show_on_front' ) ) {
				// Test if the current page is in the same translation group as
				// the 'page_for_posts.
				$current_post = get_page_by_path( $query_vars[ 'pagename' ], null, $query_vars[ 'post_type' ] );
				if ( $this->get_transid( get_option( 'page_for_posts' ) ) == $this->get_transid( $current_post ) ) {
					$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( 'post', bbl_get_current_lang_code() );
					unset( $query_vars[ 'name' ] );
					unset( $query_vars[ bbl_get_post_type_in_lang( 'page', $query_vars[ 'lang' ] ) ] );
				}
			}
			unset( $query_vars[ 'page' ] );
			unset( $query_vars[ 'pagename' ] );
		} elseif ( isset( $query_vars[ 'year' ] ) && $query_vars[ 'year' ] ) { 
			// @FIXME: This is not a reliable way to detect queries for the 'post' post_type.
			$query_vars[ 'post_type' ] =  bbl_get_post_type_in_lang( 'post', bbl_get_current_lang_code() );
		} elseif ( isset( $query_vars[ 'post_type' ] ) ) {
			if ( is_array( $query_vars[ 'post_type' ] ) ) {
				$new_post_types = array();
				foreach ( $query_vars[ 'post_type' ] as $post_type ) {
					$new_post_types[] = bbl_get_post_type_in_lang( $post_type, bbl_get_current_lang_code() );
				}
				$query_vars[ 'post_type' ] = $new_post_types;
			} else {
				$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( $query_vars[ 'post_type' ], bbl_get_current_lang_code() );
			}
		} else {
			$query_vars[ 'post_type' ] = bbl_get_post_type_in_lang( 'post', bbl_get_current_lang_code() );
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
			return new WP_Error( 'bbl_invalid_post', __( 'Invalid Post passed to get_post_lang_code', 'babble' ) );
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
		$url = admin_url( 'post-new.php' );
		$args = array( 
			'bbl_origin_post' => $default_post->ID, 
			'lang'            => $lang_code, 
			'post_type'       => 'bbl_job',
		);
		$url = add_query_arg( $args, $url );
		return $url;
	}

	/**
	 * Returns the post ID for the post in the default language from which 
	 * this post was translated.
	 *
	 * @param int|WP_Post $post Either a WP Post object, or a post ID 
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
	 * @param int|WP_Post $post Either a WP Post object, or a post ID 
	 * @return array Either an array keyed by the site languages, each key containing false (if no translation) or a WP Post object
	 **/
	public function get_post_translations( $post ) {
		$post = get_post( $post );
		// @FIXME: Is it worth caching here, or can we just rely on the caching in get_objects_in_term and get_posts?
		$transid = $this->get_transid( $post );

		if ( $translations = wp_cache_get( $transid, 'bbl_post_translations' ) ) {
			return $translations;
		}

		# @TODO A transid should never be a wp_error. Check and fix.
		if ( is_wp_error( $transid ) )
			error_log( "Error getting transid: " . print_r( $transid, true ) );
		$post_ids = get_objects_in_term( $transid, 'post_translation' );

		// Work out all the translated equivalent post types
		$post_types = array();
		$langs = bbl_get_active_langs();
		foreach ( $langs as $lang )
			$post_types[] = bbl_get_post_type_in_lang( $post->post_type, $lang->code );

		// Get all the translations in one cached DB query
		$args = array(
			// We want a clean listing, without any particular language
			'bbl_translate' => false,
			'include' => $post_ids,
			'post_type' => $post_types,
			'post_status' => array( 'publish', 'pending', 'draft', 'future' ),
		);
		$posts = get_posts( $args );
		$translations = array();
		foreach ( $posts as $post )
			$translations[ $this->get_post_lang_code( $post ) ] = $post;

		wp_cache_add( $transid, $translations, 'bbl_post_translations' );

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
 	 * Return all the base post types (in the default language).
 	 *
 	 * @return array An array of post_type objects
	 **/
	public function get_base_post_types() {
		$post_types = array();
		foreach ( $this->post_types as $post_type )
			$post_types[ $post_type ] = get_post_type_object( $post_type );
		return $post_types;
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
		// Some post types are untranslated…
		if ( ! apply_filters( 'bbl_translated_post_type', true, $post_type ) )
			return $post_type;
		if ( ! isset( $this->lang_map2[ $lang_code ][ $base_post_type ] ) ) {
			return false;
		}
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
	 * Returns the post in a particular language, or the fallback content
	 * if there's no post available.
	 *
	 * @param int|WP_Post $post Either a WP Post object, or a post ID 
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
	 * @param string $lang A Babble language object
	 * @param array $post_type_args The args for the post type associated with this post type
	 * @return void
	 **/
	public function get_slug_in_lang( $slug, $lang ) {
		$_slug = mb_strtolower( apply_filters( 'bbl_translate_post_type_slug', $slug, $lang->code ) );
		// @FIXME: For some languages the translation might be the same as the original
		if ( $_slug &&  $_slug != $slug )
			return $_slug;
		// FIXME: Do we need to check that the slug is unique at this point?
		return mb_strtolower( "{$_slug}_{$lang->code}" );
	}
	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Save the checkbox indicating text directionality.
	 *
	 * @TODO this needs to move into the translation jobs class
	 *
	 * @param int $post_id The ID of the post
	 * @param object $post The post object itself
	 * @return void
	 **/
	function save_text_directionality( $post_id, $post ) {
		if ( ! isset( $_POST[ '_bbl_default_text_direction' ] ) )
			return;

		if ( 'revision' == $post->post_type )
			return;

		if ( $this->no_recursion )
			return;
		$this->no_recursion = 'save_text_directionality';

		check_admin_referer( "bbl_default_text_direction-{$post->ID}", '_bbl_default_text_direction' );
		if ( isset( $_POST[ 'bbl_default_text_direction' ] ) && (bool) $_POST[ 'bbl_default_text_direction' ] )
			update_post_meta( $post_id, '_bbl_default_text_direction', true );
		else
			delete_post_meta( $post_id, '_bbl_default_text_direction' );

		$this->no_recursion = false;
	}

	/**
	 * Copy various properties from one post to another.
	 *
	 * @param int $source_id The source post, to copy FROM 
	 * @param int $target_id The target post, to copy TO 
	 * @return void
	 **/
	public function sync_properties( $source_id, $target_id ) {
		if ( ! ( $source_post = get_post( $source_id ) ) )
			return;

		$source_lang_code = bbl_get_post_lang_code( $source_id );

		$target_lang_code = bbl_get_post_lang_code( $target_id );

		$target_parent_post = false;
		if ( $source_post->post_parent ) {
			$source_parent_post = $this->get_post_in_lang( $source_post->post_parent, $source_lang_code );
			$target_parent_post = $this->get_post_in_lang( $source_parent_post, $target_lang_code );
		}

		$target_post = get_post( $target_id );

		$postdata = array(
			'ID' => $target_id,
			'post_author' => $source_post->post_author,
			'post_modified' => $target_post->post_modified,
			'post_modified_gmt' => $target_post->post_modified_gmt,
			'ping_status' => $source_post->ping_status,
			'post_password' => $source_post->post_password,
			'menu_order' => $source_post->menu_order,
			'post_mime_type' => $source_post->post_mime_type,
		);
		
		if ( $target_parent_post )
			$postdata[ 'post_parent' ] = $target_parent_post->ID;
		else
			$postdata[ 'post_parent' ] = 0;

		if ( bbl_get_default_lang_code() == $source_lang_code ) {
			$postdata[ 'post_date' ] = $source_post->post_date;
			$postdata[ 'post_date_gmt' ] = $source_post->post_date_gmt;
		}

		// Comment status only synced when going from the default lang code
		if ( bbl_get_default_lang_code() == $source_lang_code )
			$postdata[ 'comment_status' ] = $source_post->comment_status;

		$postdata = apply_filters( 'bbl_pre_sync_properties', $postdata, $source_id );

		wp_update_post( $postdata );
	}

	/**
	 * Resync all (synced) post meta data from the post in
	 * the default language to this post.
	 *
	 * @param $int The post ID to sync TO
	 * @return void
	 **/
	function sync_post_meta( $post_id ) {
		if ( $this->no_meta_recursion )
			return;
		$this->no_meta_recursion = 'updated_post_meta';

		// First delete all the synced meta from this post
		$current_metas = (array) get_post_meta( $post_id );
		foreach ( $current_metas as $current_meta_key => & $current_meta_values ) {
			// Some metadata shouldn't be synced, this filter allows a dev to return
			// false if the particular meta_key is one which shouldn't be synced.
			// If you find a core meta_key which is currently synced and should NOT be, 
			// please submit a patch to the sync_meta_key method on this class. Thanks.
			if ( apply_filters( 'bbl_sync_meta_key', true, $current_meta_key ) )
				delete_post_meta( $post_id, $current_meta_key );
		}

		// Now add meta in again from the origin post
		$origin_post = bbl_get_post_in_lang( $post_id, bbl_get_default_lang_code() );
		
		$metas = get_post_meta( $origin_post->ID );
		if ( ! $metas )
			return;

		foreach ( $metas as $meta_key => & $meta_value ) {
			// Some metadata shouldn't be synced
			if ( ! apply_filters( 'bbl_sync_meta_key', true, $meta_key ) )
				continue;
			// The meta could be an array stored in a single postmeta row or an
			// array of values from multiple rows; work out which we have.
			$val_multi = get_post_meta( $origin_post->ID, $meta_key );
			foreach ( $val_multi as & $val_single ) {
				add_post_meta( $post_id, $meta_key, $val_single );
			}
		}
		$this->no_meta_recursion = false;
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
	function get_transid( $post, $create = true ) {
		$post = get_post( $post );

		if ( ! $post->ID )
			return false;

		if ( $transid = wp_cache_get( $post->ID, 'bbl_post_transids' ) ) {
			return $transid;
		}

		$transids = (array) wp_get_object_terms( $post->ID, 'post_translation', array( 'fields' => 'ids' ) );
		// "There can be only one" (so we'll just drop the others)
		$transid = false;
		if ( isset( $transids[ 0 ] ) ) {
			$transid = $transids[ 0 ];
		} else {
			if ( $create ) {
				$transid = $this->set_transid( $post );
			}
		}
		
		if ( ! $transid ) {
			return false;
		}

		wp_cache_add( $post->ID, $transid, 'bbl_post_transids' );

		return $transid;
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
		if ( ! isset( $post->ID ) )
			return false;
		// @FIXME: Abstract the code for generating and associating a new TransID
		if ( ! $transid ) {
			$transid_name = 'post_transid_' . uniqid();
			$result = wp_insert_term( $transid_name, 'post_translation', array() );
			if ( is_wp_error( $result ) )
				error_log( "Problem creating a new Post TransID: " . print_r( $result, true ) );
			else
				$transid = $result[ 'term_id' ];
			// Delete anything in there currently
			wp_cache_delete( $transid, 'bbl_post_translations' );
		}
		$result = wp_set_object_terms( $post->ID, $transid, 'post_translation' );
		if ( is_wp_error( $result ) )
			error_log( "Problem associating TransID with new posts: " . print_r( $result, true ) );

		$this->clean_post_cache( $post->ID );
		
		return $transid;
	}

	/**
	 * Return a list of features supported by a post_type.
	 *
	 * Hello there, code investigator. I imagine you're wondering
	 * why I'm accessing a global prefixed by an underscore? I realise
	 * these are nominally private variables, prone to change, but
	 * I need to access a list of all features supported by a post
	 * type, in order to shadow it for the various translations,
	 * and there's no core function to allow me to do this.
	 * 
	 * @TODO: Raise a Trac ticket for adding this functionality to the post type API
	 *
	 * @param string $post_type The name of the post type for which to get the features supported
	 * @return array An array of features supported by this post type
	 **/
	protected function get_features_supported_by_post_type( $post_type ) {
		global $_wp_post_type_features;
		return (array) $_wp_post_type_features[$post_type];
	}

	/**
	 * Are we on the media upload gallery tab?
	 *
	 * @param string $tab A specific tab to detect
	 * @return boolean True if we are on media upload generally, and the specific tab if specified
	 **/
	protected function is_media_upload_tab( $tab = null ) {
		if ( ! is_admin() )
			return false;
		if ( 'media-upload.php' != basename( $_SERVER[ 'SCRIPT_NAME' ] ) ) {
			return false;
		}
		if ( is_null( $tab ) ) {
			return true;
		}
		if ( isset( $_GET[ 'tab' ] ) || $tab == $_GET[ 'tab' ] ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Are we viewing the (3.5+) media manager?
	 *
	 * @return boolean True if we are viewing the media manager
	 **/
	protected function is_media_manager() {
		if ( ! is_admin() )
			return false;
		if ( !isset( $_POST['action'] ) ) {
			return false;
		}
		if ( 'query-attachments' == $_POST['action'] ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Remove over-synced post metas.
	 *
	 * @return void
	 **/
	protected function prune_post_meta() {
		global $wpdb;
		$meta_keys = array( 
			'_thumbnail_id', 
			'_wp_old_slug' ,
			'_wp_page_template', 
			'_wp_trash_meta_status',
			'_wp_trash_meta_time', 
		);
		foreach ( $meta_keys as $meta_key ) {
			$prepared_sql = $wpdb->prepare( "SELECT COUNT(*) AS count, post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key = %s GROUP BY post_id, meta_key, meta_value HAVING count > 1", $meta_key );
			$metas = $wpdb->get_results( $prepared_sql );
			foreach ( $metas as $meta ) {
				if ( $meta->count < 2 ) {
					continue;
				}
				$prepared_sql = $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s AND meta_value = %s LIMIT %d", $meta->post_id, $meta->meta_key, $meta->meta_value, (int) $meta->count - 1 );
				$wpdb->query( $prepared_sql );
			}
		}
	}
	
	/**
	 * Checks the DB structure is up to date, rewrite rules, 
	 * theme image size options are set, etc.
	 *
	 * @return void
	 **/
	protected function maybe_upgrade() {
		global $wpdb;
		$option_name = 'bbl_post_public_version';
		$version = get_option( $option_name, 0 );

		if ( $version == $this->version )
			return;

		if ( $start_time = get_option( "{$option_name}_running", false ) ) {
			$time_diff = time() - $start_time;
			// Check the lock is less than 30 mins old, and if it is, bail
			if ( $time_diff < ( 60 * 30 ) ) {
				error_log( "Babble Post Public: Existing update routine has been running for less than 30 minutes" );
				return;
			}
			error_log( "Babble Post Public: Update routine is running, but older than 30 minutes; going ahead regardless" );
		} else {
			add_option( "{$option_name}_running", time(), null, 'no' );
		}

		if ( $version < 9 ) {
			error_log( "Babble Post Public: Start pruning metadata" );
			$this->prune_post_meta();
			error_log( "Babble Post Public: Remove excess post meta" );
		}

		// N.B. Remember to increment $this->version above when you add a new IF

		update_option( $option_name, $this->version );
		delete_option( "{$option_name}_running", true, null, 'no' );
		error_log( "Babble Post Public: Done upgrade, now at version " . $this->version );
	}

	/**
	 * Checks for duplicated metadata in some key meta_keys.
	 *
	 * @return boolean
	 * @author Simon Wheatley
	 */
	function have_duplicate_metadata() {
		global $wpdb;
		$sql = "
			SELECT COUNT(*) AS count, post_id, meta_key, meta_value
			FROM $wpdb->postmeta
			WHERE meta_key IN (
				'_extmedia-youtube', '_extmedia-duration', '_thumbnail_id', '_wp_trash_meta_time', '_wp_page_template', '_wp_trash_meta_status'
			)
			GROUP BY post_id, meta_key, meta_value
			HAVING count > 1
			ORDER BY count, post_id, meta_key
		";
		return (bool) count( $wpdb->get_results( $sql ) );
	}
	

}

global $bbl_post_public;
$bbl_post_public = new Babble_Post_Public();
