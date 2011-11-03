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
// class Babble_Switcher_Menu extends Babble_Plugin {
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
	 * @return array An array of link info for the objects in this current translation group.
	 **/
	public function get_switcher_links( $id_prefix ) {
		$this->populate_links();
		
		$links = $this->links;
		$links[ 0 ][ 'id' ] = $id_prefix . '-' . $links[ 0 ][ 'id' ];
		foreach ( $links[ 0 ][ 'children' ] as & $child )
			$child[ 'id' ] = $id_prefix . '-' . $child[ 'id' ];

		return $links;
	}

	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	protected function populate_links() {
		if ( is_array( $this->links ) && ! empty( $this->links ) )
			return; // Already done
		
		$this->links = array();

		// @FIXME: Not sure this is the best way to specify languages
		$alt_langs = bbl_get_active_langs();

		// Remove the current language
		foreach ( $alt_langs as $i => & $alt_lang )
			if ( $alt_lang->code == bbl_get_current_lang_code() )
				unset( $alt_langs[ $i ] );

		$current_lang = bbl_get_current_lang();
		$this->links[ 0 ] = array(
			'children' => array(),
			'href' => '#',
			'id' => $current_lang->url_prefix,
			'meta' => array( 'class' => "bbl_lang_$current_lang->code bbl_lang" ),
			'title' => $current_lang->names,
		);

		$screen = is_admin() ? get_current_screen() : false;

		// Create a handy flag for whether we're editing a post
		$editing_post = false;
		if ( is_admin() )
			$editing_post = ( is_admin() && 'post' == $screen->base );

		// Create a handy flag for whether we're editing a term
		$editing_term = false;
		if ( is_admin() )
			$editing_term = ( is_admin() && 'edit-tags' == $screen->base );

		if ( is_singular() || is_single() || $editing_post ) {
			$this->translations = bbl_get_post_translations( get_the_ID() );
		} else if ( $editing_term ) {
			$term = get_term( (int) @ $_REQUEST[ 'tag_ID' ], $screen->taxonomy );
			$this->translations = bbl_get_term_translations( $term->term_id, $screen->taxonomy );
		}

		foreach ( $alt_langs as $i => & $alt_lang ) {
			if ( is_admin() ) {
				if ( $editing_post ) {
					$this->add_admin_post_link( $this->links[ 0 ], $alt_lang );
				} else if ( $editing_term ) {
					$this->add_admin_term_link( $this->links[ 0 ], $alt_lang );
				} else {
					$this->add_admin_generic_link( $this->links[ 0 ], $alt_lang );
				}
			} else if ( is_singular() || is_single() ) {
				$this->add_post_link( $this->links[ 0 ], $alt_lang );
			} else if ( is_front_page() ) { // is_front_page works for language homepages
				$this->add_front_page_link( $this->links[ 0 ], $alt_lang );
			}
			
		}
	}

	/**
	 * Add an admin link to the same page, but with the language switch GET
	 * parameter set.
	 *
	 * @param array $parent A reference to the parent link
	 * @param object $lang A Babble language object for this link
	 **/
	protected function add_admin_generic_link( & $parent, $lang ) {
		$href = add_query_arg( array( 'lang' => $alt_lang->code ) );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-generic';
		$classes[] = 'bbl-lang';
		$parent[ 'children' ][] = array(
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin term link to the parent link provided (by reference).
	 *
	 * @param array $parent A reference to the parent link
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_term_link( & $parent, $lang ) {
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
			$default_term = $this->translations[ bbl_get_default_lang_code() ];
			$href = bbl_get_new_term_translation_url( $default_term, $lang->code, $screen->taxonomy );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-term';
		}
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-term';
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-post';
		$parent[ 'children' ][] = array(
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add an admin post link to the parent link provided (by reference)
	 *
	 * @param array $parent A reference to the parent link
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_admin_post_link( & $parent, $lang ) {
		$classes = array();
		if ( isset( $this->translations[ $lang->code ]->ID ) ) { // Translation exists
			$href = add_query_arg( array( 'lang' => $lang->code, 'post' => $this->translations[ $alt_lang->code ]->ID ) );
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing-edit';
			$classes[] = 'bbl-existing-edit-post';
		} else { // Translation does not exist
			$default_post = $this->translations[ bbl_get_default_lang_code() ];
			$href = bbl_get_new_post_translation_url( $default_post, $lang->code );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-post';
		}
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-admin';
		$classes[] = 'bbl-admin-post';
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-post';
		$parent[ 'children' ][] = array(
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a post link to the parent link provided (by reference)
	 *
	 * @param array $parent A reference to the parent link
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_post_link( & $parent, $lang ) {
		if ( isset( $this->translations[ $lang->code ]->ID ) ) { // Translation exists
			bbl_switch_to_lang( $lang->code );
			$href = get_permalink( $this->translations[ $lang->code ]->ID );
			bbl_restore_lang();
			$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-existing';
			$classes[] = 'bbl-existing-post';
		} else { // Translation does not exist
			// Generate a URL to create the translation
			$default_post = $this->translations[ bbl_get_default_lang_code() ];
			$href = bbl_get_new_post_translation_url( $default_post, $lang->code );
			$title = sprintf( __( 'Create for %s', 'bbl' ), $lang->names );
			$classes[] = 'bbl-add';
			$classes[] = 'bbl-add-post';
		}
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-lang';
		$classes[] = 'bbl-post';
		$parent[ 'children' ][] = array(
			'href' => $href,
			'id' => $lang->url_prefix,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

	/**
	 * Add a link to a language specific front page.
	 *
	 * @param array $parent A reference to the parent link
	 * @param object $lang A Babble language object for this link
	 * @return void
	 **/
	protected function add_front_page_link( & $parent, $lang ) {
		global $bbl_locale;
		remove_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
		$href = home_url( "$lang->url_prefix/" );
		add_filter( 'home_url', array( $bbl_locale, 'home_url'), null, 2 );
		$title = sprintf( __( 'Switch to %s', 'bbl' ), $lang->names );
		$classes[] = "bbl-lang-$lang->code bbl-lang-$lang->url_prefix";
		$classes[] = 'bbl-existing';
		$classes[] = 'bbl-front-page';
		$classes[] = 'bbl-lang';
		$parent[ 'children' ][] = array(
			'id' => $lang->url_prefix,
			'href' => $href,
			'meta' => array( 'class' => strtolower( join( ' ', array_unique( $classes ) ) ) ),
			'title' => $title,
		);
	}

}

$bbl_switcher_menu = new Babble_Switcher_Menu();

?>