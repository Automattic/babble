<?php

/**
 * Class for providing the switch/create links for the current 
 * content items. Works in the admin or public areas of the site.
 * 
 * Used by the admin bar class, for example.
 *
 * @package Babble
 * @since 0.2
 */
class Babble_Switcher_Menu {
	
	/**
	 * The translations for the current content item.
	 *
	 * @var array
	 **/
	protected $translations;

	/**
	 * A multi-dimensional array of the links for the current 
	 * translation structure.
	 *
	 * @var array
	 **/
	protected $links;
	
	/**
	 * The WP Screen object
	 *
	 * @var object
	 **/
	protected $screen;
	
	// PUBLIC METHODS
	// ==============

	/**
	 * Returns an array of links to the other objects
	 * in this translation group. Each element in the array
	 * looks like:
	 * array (
	 * 		
	 * )
	 *
	 * @param string $id_prefix A prefix to the ID for each item
	 * @return array An array of link info for the objects in this current translation group.
	 **/
	public function get_switcher_links( $id_prefix ) {
		$this->populate_links();
		return $this->links;
	}

	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	protected function populate_links() {
		if ( is_array( $this->links ) && ! empty( $this->links ) )
			return; // Already done
		
		$this->links = array();

		// @FIXME: Not sure this is the best way to specify languages
		$alt_langs = bbl_get_active_langs();

		$this->screen = is_admin() ? get_current_screen() : false;

		// Create a handy flag for whether we're editing a post or listing posts
		$editing_post = false;
		$listing_posts = false;
		if ( is_admin() ) {
			$editing_post = ( is_admin() && 'post' == $this->screen->base && isset( $_GET[ 'post' ] ) );
			$listing_posts = ( is_admin() && 'edit' == $this->screen->base && ! isset( $_GET[ 'post' ] ) );
		}

		// Create a handy flag for whether we're editing a term or listing terms
		$editing_term = false;
		$listing_terms = false;
		if ( is_admin() ) {
			$editing_term = ( is_admin() && 'edit-tags' == $this->screen->base && isset( $_GET[ 'tag_ID' ] ) );
			$listing_terms = ( is_admin() && 'edit-tags' == $this->screen->base && ! isset( $_GET[ 'tag_ID' ] ) );
		}

		if ( is_singular() || is_single() || $editing_post ) {
			$this->translations = bbl_get_post_translations( get_the_ID() );
		} else if ( 'page' == get_option( 'show_on_front' ) && is_home() ) {
			$this->translations = bbl_get_post_translations( get_option( 'page_for_posts' ) );
		} else if ( is_tax() || is_category() || $editing_term ) {
			if ( isset( $_REQUEST[ 'tag_ID' ] ) )
				$term = get_term( (int) @ $_REQUEST[ 'tag_ID' ], $this->screen->taxonomy );
			else
				$term = get_queried_object();
			$this->translations = bbl_get_term_translations( $term->term_id, $term->taxonomy );
		}

		foreach ( $alt_langs as $i => & $alt_lang ) {
			// @TODO: Convert to a switch statement, convert all the vars to a single property on the class
			if ( is_admin() ) {
				if ( $editing_post ) {			// Admin: Editing post link
					$this->add_admin_post_link( $alt_lang );
				} else if ( $editing_term ) {	// Admin: Editing term link
					$this->add_admin_term_link( $alt_lang );
				} else if ( $listing_posts ) {	// Admin: Listing posts link
					$this->add_admin_list_posts_link( $alt_lang );
				} else if ( $listing_terms ) {	// Admin: Listing terms link
					$this->add_admin_list_terms_link( $alt_lang );
				} else {						// Admin: Generic link link
					$this->add_admin_generic_link( $alt_lang );
				}
				continue;
			}
			
			if ( 
				is_singular() || is_single() ||
				( 'page' == get_option( 'show_on_front' ) && is_home() )
			) {	// Single posts, pages, blog homepage
				$this->add_post_link( $alt_lang );
				continue;
			}
			
			// Don't add a switcher link if the language is not public and
			// the user cannot edit any posts (as a rough guide to whether 
			// they are more than just a subscriber).
			if ( ! bbl_is_public_lang( $alt_lang->code ) && ! current_user_can( 'edit_post' ) )
				continue;
			
			if ( is_front_page() ) { 				// Language homepage
				// is_front_page works for language homepages, phew
				$this->add_front_page_link( $alt_lang );
			} else if ( is_post_type_archive() ) {			// Post type archives
				$this->add_post_type_archive_link( $alt_lang );
			} else if ( is_tax() || is_category() ) { 		// Category or taxonomy archive
				$this->add_taxonomy_archive_link( $alt_lang );
			} else { // 404's, amongst other things
				$this->add_arbitrary_link( $alt_lang );
			}
		}
		
		// Make up the class attribute on all links
		foreach ( $this->links as & $link )
			$link[ 'class' ] = implode( ' ', $link[ 'classes' ] );
	}

	/**
	 * Add an admin link to the same page, but with the language switch GET
	 * parameter set.
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_generic_link( $lang ) {
		$classes = array();
		$href = add_query_arg( array( 'lang' => $lang->code ) );
		$href = apply_filters( 'bbl_switch_admin_generic_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-generic';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';

		// Preventing errors on initial plugin load - before settings saved the first time
		if ( empty( $lang->display_name ) ) {
			$lang->display_name = '';
		}

		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin term link to the parent link provided (by reference).
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_term_link( $lang ) {
		$classes = array();
		if ( isset( $this->translations[ $lang->code ]->term_id ) ) { // Translation exists
			$args = array( 
				'lang' => $lang->code, 
				'taxonomy' => $this->translations[ $lang->code ]->taxonomy, 
				'tag_ID' => $this->translations[ $lang->code ]->term_id 
			);
			$href = add_query_arg( $args );
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing-edit';
			$classes[] = 'bbl-existing-edit-term';
		} else { // Translation does not exist
			$default_term = (int) $_GET[ 'tag_ID' ];
			$href = bbl_get_new_term_translation_url( $default_term, $lang->code, $this->screen->taxonomy );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-term';
		}
		$href = apply_filters( 'bbl_switch_admin_term_link', $href, $lang, $this->translations );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-taxonomy';
		$classes[] = 'bbl-admin-edit-term';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin list terms screen link to the parent link provided (by reference).
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_list_terms_link( $lang ) {
		$classes = array();
		$args = array( 
			'lang' => $lang->code, 
			'taxonomy' => bbl_get_taxonomy_in_lang( $this->screen->taxonomy, $lang->code ),
		);
		$href = add_query_arg( $args );
		$href = apply_filters( 'bbl_switch_admin_list_terms_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-taxonomy';
		$classes[] = 'bbl-admin-list-terms';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';

		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin post link to the parent link provided (by reference)
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_post_link( $lang ) {
		$classes = array();
		if ( isset( $this->translations[ $lang->code ]->ID ) ) { // Translation exists
			$href = add_query_arg( array( 'lang' => $lang->code, 'post' => $this->translations[ $lang->code ]->ID ) );
			$href = remove_query_arg( 'message', $href );
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing-edit';
			$classes[] = 'bbl-existing-edit-post';
		} else { // Translation does not exist
			if ( isset( $this->translations[ bbl_get_default_lang_code() ] ) ) {
				$default_post = $this->translations[ bbl_get_default_lang_code() ];
				$href = bbl_get_new_post_translation_url( $default_post, $lang->code );
				$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
				$classes[] = 'bbl-add';
				$classes[] = 'bbl-add-post';
			} else {
				return; // Don't create the switcher menu items yet
			}
		}
		$href = apply_filters( 'bbl_switch_admin_post_link', $href, $lang, $this->translations );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-edit-post';
		$classes[] = 'bbl-admin-post-type';
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-post';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin list posts screen link to the parent link provided (by reference).
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_list_posts_link( $lang ) {
		$classes = array();
		$args = array( 
			'lang' => $lang->code, 
			'post_type' => bbl_get_post_type_in_lang( $this->screen->post_type, $lang->code ),
		);
		$href = add_query_arg( $args );
		$href = apply_filters( 'bbl_switch_admin_list_posts_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-edit-post';
		$classes[] = 'bbl-admin-post-type';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';

		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a post link to the parent link provided (by reference)
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_post_link( $lang ) {
		$classes = array();
		if ( isset( $this->translations[ $lang->code ] ) ) { // Translation exists
			// Don't add this link if the user cannot edit THIS post and
			// the language is not public.
			if ( 
				! bbl_is_public_lang( $lang->code ) && 
				! current_user_can( 'edit_post', $this->translations[ $lang->code ]->ID ) 
			) {
				return;
			}

			bbl_switch_to_lang( $lang->code );
			$href = get_permalink( $this->translations[ $lang->code ]->ID );
			bbl_restore_lang();
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing';
			$classes[] = 'bbl-existing-post';
		} else if ( current_user_can( 'edit_post', $this->translations[ bbl_get_default_lang_code() ]->ID ) ) {
			// Generate a URL to create the translation
			$default_post = $this->translations[ bbl_get_default_lang_code() ];
			$href = bbl_get_new_post_translation_url( $default_post, $lang->code );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-post';
		} else {
			// Don't show links to non-public languages
			if ( ! bbl_is_public_lang( $lang->code ) )
				return;

			// Show a blank link for unavailable translations
			$href = false;
			$title = sprintf( __( 'This content is unavailable in %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-unavailable';
		}
		$href = apply_filters( 'bbl_switch_post_link', $href, $lang, $this->translations );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-post';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a link to a language specific front page.
	 *
	 * @TODO: Is this any different from a regular post link?
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_front_page_link( $lang ) {
		global $bbl_locale;
		$classes = array();
		remove_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
		$href = home_url( "$lang->url_prefix/" );
		add_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
		$href = apply_filters( 'bbl_switch_front_page_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-existing';
		$classes[] = 'bbl-front-page';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'id' => $lang->url_prefix,
			'href' => $href,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a link to a post_type archive.
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_post_type_archive_link( $lang ) {
		global $bbl_locale;
		$classes = array();
		bbl_switch_to_lang( $lang->code );
		$href = get_post_type_archive_link( get_query_var( 'post_type' ) );
		bbl_restore_lang();
		$href = apply_filters( 'bbl_switch_post_type_archive_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-existing';
		$classes[] = 'bbl-post-type-archive';
		$classes[] = 'bbl-lang';
		if ( $lang->code == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'id' => $lang->url_prefix,
			'href' => $href,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a link to a taxonomy archive.
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_taxonomy_archive_link( $lang ) {
		$classes = array();
		$queried_object = get_queried_object();
		if ( ! bbl_is_translated_taxonomy( $queried_object->taxonomy ) ) {
			$this->add_arbitrary_link( $lang );
			return;
		} elseif ( isset( $this->translations[ $lang->code ]->term_id ) ) { // Translation exists
			bbl_switch_to_lang( $lang->code );
			$href = get_term_link( $this->translations[ $lang->code ], bbl_get_base_taxonomy( $queried_object->taxonomy ) );
			bbl_restore_lang();
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing';
			$classes[] = 'bbl-existing-term';
		} else { // Translation does not exist
			// Generate a URL to create the translation
			$default_term = $this->translations[ bbl_get_default_lang_code() ];
			$href = bbl_get_new_term_translation_url( $default_term->term_id, $lang->code, $default_term->taxonomy );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-term';
		}
		$href = apply_filters( 'bbl_switch_taxonomy_archive_link', $href, $lang, $this->translations );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-term';
		if ( $lang == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a link to an arbitrary link, e.g. 404, within the site.
	 *
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_arbitrary_link( $lang ) {
		$classes = array();
		if ( ! preg_match( '|^/[^/]+/(.*)?|', $_SERVER[ 'REQUEST_URI' ], $matches ) )
			return;
		bbl_switch_to_lang( $lang->code );
		$href = home_url( $matches[ 1 ] );
		bbl_restore_lang();
		$href = apply_filters( 'bbl_switch_arbitrary_link', $href, $lang );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = 'bbl-existing';
		$classes[] = 'bbl-existing-term';
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-term';
		if ( $lang == bbl_get_current_lang_code() )
			$classes[] = 'bbl-active';
		$this->links[ $lang->code ] = array(
			'classes' => $classes,
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}
	
}

global $bbl_switcher_menu;
$bbl_switcher_menu = new Babble_Switcher_Menu();

?>