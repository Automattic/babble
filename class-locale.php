<?php

/**
 * Manages the locale currently set for the site.
 *
 * @package Babble
 * @since Alpha 1
 */
class Babble_Locale {
	
	/**
	 * A regex to get the language code prefix from
	 * a URL.
	 *
	 * @var string
	 **/
	protected $lang_regex = '|^[^/]+|i';

	/**
	 * The language for the content of the current request.
	 *
	 * @var string
	 **/
	public $content_lang;

	/**
	 * The interface language for the current request.
	 *
	 * @var string
	 **/
	public $interface_lang;

	/**
	 * The locale for the current request.
	 *
	 * @var string
	 **/
	protected $locale;

	/**
	 * The URL prefix for the current request
	 *
	 * @var string
	 **/
	protected $url_prefix;
	
	/**
	 * A simple flag to stop infinite recursion in various places.
	 *
	 * @var boolean
	 **/
	protected $no_recursion;
	
	/**
	 * The languages that we've switched to, in order.
	 *
	 * @var array
	 **/
	protected $lang_stack;
	
	/**
	 * The current version for purposes of rewrite rules, any 
	 * DB updates, cache busting, etc
	 *
	 * @var int
	 **/
	protected $version = 2;
	
	/**
	 * Setup any add_action or add_filter calls. Initiate properties.
	 *
	 * @return void
	 **/
	function __construct() {
		add_action( 'plugins_loaded',                  array( $this, 'plugins_loaded' ), 0 );
		add_action( 'admin_init',                      array( $this, 'admin_init' ) );
		add_action( 'admin_notices',                   array( $this, 'admin_notices' ) );
		add_action( 'parse_request',                   array( $this, 'parse_request_early' ), 0 );
		add_action( 'pre_comment_on_post',             array( $this, 'pre_comment_on_post' ) );

		add_filter( 'body_class',                      array( $this, 'body_class' ) );
		add_filter( 'locale',                          array( $this, 'set_locale' ) );
		add_filter( 'mod_rewrite_rules',               array( $this, 'mod_rewrite_rules' ) );
		add_filter( 'post_class',                      array( $this, 'post_class' ), null, 3 );
		add_filter( 'pre_update_option_rewrite_rules', array( $this, 'internal_rewrite_rules_filter' ) );
		add_filter( 'query_vars',                      array( $this, 'query_vars' ) );
	}

	public function plugins_loaded() {
		global $wpdb;

		# @TODO this exposes the $wpdb prefix. We should set the cookie path to the site path instead
		# (example.com/site or site.example.com) so the cookie is only set for the current site on a multisite install
		# @TODO actually, both of these should be user preferences, not cookies.
		$this->content_lang_cookie   = $wpdb->prefix . '_bbl_content_lang_' . COOKIEHASH;
		$this->interface_lang_cookie = $wpdb->prefix . '_bbl_interface_lang_' . COOKIEHASH;
	}

	/**
	 * Hooks the WP admin_init action 
	 *
	 * @return void
	 **/
	public function admin_init() {
		add_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
		$this->maybe_update();
		$this->maybe_set_cookie_content_lang();
		$this->maybe_set_cookie_interface_lang();
	}

	/**
	 * Hooks the WP admin_notices action to warn the admin
	 * if the permalinks aren't pretty enough.
	 *
	 * @return void
	 **/
	public function admin_notices() {
		if ( ! get_option( 'permalink_structure' ) ) {
			printf( '<div class="error"><p>%s</p></div>', sprintf( __( '<strong>Babble problem:</strong> Fancy permalinks are disabled. <a href="%s">Please enable them</a> in order to have language prefixed URLs work correctly.', 'babble' ), admin_url( '/options-permalink.php' ) ) );
		}
	}

	/**
	 * Ensure we keep the standard WP rewrite rules.
	 *
	 * @param string $rules The mod_rewrite rules block generated by WP 
	 * @return string A mod_rewrite rules block
	 **/
	public function mod_rewrite_rules( $rules ) {
		global $wp_rewrite;
		if ( $this->no_recursion )
			return $rules;
		$this->no_recursion = true;
		// We need the WP_Rewrite mod_rewrite_rules method to run
		// home_url without a lang query var set, or it generates 
		// an inaccurate RewriteBase and last RewriteRule.
		remove_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
		$rules = $wp_rewrite->mod_rewrite_rules();
		add_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
		$this->no_recursion = false;
		return $rules;
	}
	
	/**
	 * Hooks the WP pre_update_option_rewrite_rules filter to add 
	 * a prefix to the URL to pick up the virtual sub-dir specifying
	 * the language. The redirect portion can and should remain perfectly
	 * ignorant of it though, as we change it in parse_request.
	 * 
	 * @param array $langs The language codes
	 * @return array An array of language codes utilised for this site. 
	 **/
	public function internal_rewrite_rules_filter( $rules ){
		global $wp_rewrite;

		// Some rules need to be at the root of the site, without a
		// language prefix, e.g. http://www.example.com/humans.txt. 
		// The following filter allows plugin and theme devs to add 
		// to this list of site root level URLs which are untranslated.
		$non_translated_rewrite_rules = apply_filters( 'bbl_non_translated_queries', array(
			'humans\.txt$',
			'robots\.txt$',
		) );

	    foreach( (array) $rules as $regex => $query ) {
			if ( in_array( $regex, $non_translated_rewrite_rules ) ) {
				$new_rules[ $regex ] = $query;
				continue;
			}

			if ( substr( $regex, 0, 1 ) == '^' ) {
				$new_rules[ '^[a-zA-Z_]+/' . substr( $regex, 1 ) ] = $query;
			}
			else {
				$new_rules[ '[a-zA-Z_]+/' . $regex ] = $query;
			}
		}

		// The WP robots.txt rewrite rule will not have worked, as the
		// code objects to the language prefix. Here we add it in again.
		$hooked = false;

		if ( has_filter( 'home_url' ) ) {
			remove_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
			$hooked = true;
		}

		$home_path = parse_url( home_url() );

		if ( $hooked ) {
			add_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
		}

		if ( empty( $home_path['path'] ) || '/' == $home_path['path'] ) {
			$new_rules[ 'robots\.txt$' ] = $wp_rewrite->index . '?robots=1';
		}

	    return $new_rules;
	}

	/**
	 * Hooks the WP locale filter to switch locales whenever we gosh darned want.
	 *
	 * @param string $locale The locale 
	 * @return string The locale
	 **/
	public function set_locale( $locale ) {
		// Deal with the special case of wp-comments-post.php
		if ( false !== stristr( $_SERVER[ 'REQUEST_URI' ], 'wp-comments-post.php' ) ) {
			// @TODO we should be able to hook into an action here (pre_comment_post) rather than looking at the URL.
			if ( $comment_post_ID = ( isset( $_POST[ 'comment_post_ID' ] ) ) ? (int) $_POST[ 'comment_post_ID' ] : false ) {
				if ( ! isset( $this->content_lang ) ) {
					$this->set_content_lang( bbl_get_post_lang_code( $comment_post_ID ) );
				}
				return $this->content_lang;
			}
		}

		if ( is_admin() ) {
			if ( isset( $this->interface_lang ) ) {
				return $this->interface_lang;
			}
		} else {
			if ( isset( $this->content_lang ) ) {
				return $this->content_lang;
			}
		}

		// $current_user = wp_get_current_user();
		if ( $lang = $this->get_cookie_interface_lang() ) {
			$this->set_interface_lang( $lang );
		}

		// $current_user = wp_get_current_user();
		if ( $lang = $this->get_cookie_content_lang() ) {
			$this->set_content_lang( $lang );
		}

		if ( is_admin() ) {
			// @FIXME: At this point a mischievous XSS "attack" could set a user's admin area language for them
			if ( isset( $_POST[ 'interface_lang' ] ) ) {
				$this->set_interface_lang( $_POST[ 'interface_lang' ] );
			}
			// @FIXME: At this point a mischievous XSS "attack" could set a user's content language for them
			if ( isset( $_GET[ 'lang' ] ) ) {
				$this->set_content_lang( $_GET[ 'lang' ] );
			}
		} else { // Front end
			// @FIXME: Should probably check the available languages here
			if ( preg_match( $this->lang_regex, $this->get_request_string(), $matches ) )
				$this->set_content_lang_from_prefix( $matches[ 0 ] );
		}

		if ( ! isset( $this->content_lang ) || ! $this->content_lang )
			$this->set_content_lang( bbl_get_default_lang_code() );
		if ( ! isset( $this->interface_lang ) || ! $this->interface_lang )
			$this->set_interface_lang( bbl_get_default_lang_code() );

		if ( is_admin() )
			return $this->interface_lang;
		else
			return $this->content_lang;
	}

	/**
	 * Hooks the WP parse_request action 
	 *
	 * FIXME: Should I be extending and replacing the WP class?
	 *
	 * @param WP $wp The WP object, passed by reference (so no need to return)
	 * @return void
	 **/
	public function parse_request_early( WP $wp ) {
		// If this is the site root, redirect to default language homepage 
		if ( ! $wp->request ) {
			remove_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
			wp_redirect( home_url( bbl_get_default_lang_url_prefix() ) );
			exit;
		}
		// Otherwise, simply set the lang for this request
		$wp->query_vars[ 'lang' ] = $this->content_lang;
		$wp->query_vars[ 'lang_url_prefix' ] = $this->url_prefix;
	}

	/**
	 * Hooks the WP query_vars filter to add the home_url filter.
	 *
	 * @param array $query_vars An array of the public query vars 
	 * @return array An array of the public query vars
	 **/
	public function query_vars( array $query_vars ) {
		# @TODO why is this here?
		add_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
		return array_merge( $query_vars, array( 'lang', 'lang_url_prefix' ) );
	}

	/**
	 * Hooks the WP pre_comment_on_post action to add the 
	 * home_url filter.
	 *
	 * @return void
	 **/
	public function pre_comment_on_post() {
		# @TODO why is this here?
		add_filter( 'home_url', array( $this, 'home_url' ), null, 2 );
	}

	/**
	 * Hooks the WP home_url action 
	 * 
	 * Hackity hack: this function is attached with add_filter within
	 * the query_vars filter and the pre_comment_on_post action.
	 * @TODO: Can't remember why this is attached like this… investigate.
	 *
	 * @param string $url The URL 
	 * @param string $path The path 
	 * @param string $orig_scheme The original scheme 
	 * @param int $blog_id The ID of the blog 
	 * @return string The URL
	 **/
	public function home_url( $url, $path ) {
		$base_url = get_option( 'home' );
		$url      = trailingslashit( $base_url ) . $this->url_prefix;

		if ( $path && is_string( $path ) )
			$url .= '/' . ltrim( $path, '/' );

		return $url;
	}

	/**
	 * Hooks the WP body_class filter to add some language specific classes.
	 *
	 * @param array $classes The body classes 
	 * @return array The body classes 
	 **/
	public function body_class( array $classes ) {
		$lang = bbl_get_current_lang();
		$classes[] = 'bbl-' . $lang->text_direction;
		# @TODO I don't think this class should be included:
		$classes[] = 'bbl-' . sanitize_title( $lang->name );
		$classes[] = 'bbl-' . sanitize_title( $lang->url_prefix );
		$classes[] = 'bbl-' . sanitize_title( $lang->code );
		# @TODO I don't think this class should be included:
		$classes[] = 'bbl-' . sanitize_title( $lang->display_name );
		return $classes;
	}

	/**
	 * Hooks the WP post_class filter to add some language specific classes.
	 *
	 * @param array $classes The post classes 
	 * @param array $class One or more classes which have been added to the class list.
	 * @param int $post_id The ID of the post we're providing classes for 
	 * @return array The body classes 
	 **/
	public function post_class( array $classes, $class, $post_id ) {
		$post = get_post( $post_id );
		$post_lang_code = bbl_get_post_lang_code( $post );
		$lang = bbl_get_lang( $post_lang_code );
		if ( self::use_default_text_direction( $post ) ) {
			$default_lang = bbl_get_default_lang();
			$classes[] = 'bbl-post-' . $default_lang->text_direction;
		} else {
			$classes[] = 'bbl-post-' . $lang->text_direction;
		}
		# @TODO I don't think this class should be included:
		$classes[] = 'bbl-post-' . sanitize_title( $lang->name );
		$classes[] = 'bbl-post-' . sanitize_title( $lang->url_prefix );
		$classes[] = 'bbl-post-' . sanitize_title( $lang->code );
		# @TODO I don't think this class should be included:
		$classes[] = 'bbl-post-' . sanitize_title( $lang->display_name );
		return $classes;
	}

	// Public Methods
	// --------------

	/**
	 * Return whether the post should use the default language's text direction or not.
	 *
	 * @param  WP_Post $post The post object.
	 * @return bool          True if the post should use the default language text direction. False if not.
	 */
	public static function use_default_text_direction( WP_Post $post ) {
		if ( get_post_meta( $post->ID, '_bbl_default_text_direction', true ) ) {
			return true;
		} else if ( empty( $post->post_content ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the current (content) lang for this class, which is also the
	 * current lang in the Query Vars.
	 *
	 * @TODO deprecate
	 *
	 * @return string
	 **/
	public function get_lang() {
		return $this->get_content_lang();
	}

	/**
	 * Get the current content lang for this class, which is also the
	 * current lang in the Query Vars.
	 *
	 * @return string
	 **/
	public function get_content_lang() {
		return $this->content_lang;
	}

	/**
	 * Get the current interface lang for this class.
	 *
	 * @return string
	 **/
	public function get_interface_lang() {
		return $this->interface_lang;
	}

	/**
	 * Set the current (content) lang for this class, and in Query Vars.
	 *
	 * @param string $lang The language code to switch to 
	 * @return bool Whether the switch was successful
	 **/
	public function switch_to_lang( $lang ) {
		// @FIXME: Need to validate language here

		$stacked = $this->content_lang;
		$set     = $this->set_content_lang( $lang );

		if ( ! $set ) {
			return false;
		}

		if ( ! is_array( $this->lang_stack ) ) {
			$this->lang_stack = array();
		}
		$this->lang_stack[] = $stacked;
		
		set_query_var( 'lang', $this->content_lang );
		return true;
	}
	
	/**
	 * Restore the previous lang from the switched stack.
	 *
	 * @return void
	 **/
	public function restore_lang() {
		$this->set_content_lang( array_pop( $this->lang_stack ) );
		set_query_var( 'lang', $this->content_lang );
	}

	// Non-public Methods
	// ------------------

	/**
	 * Set the content language code and URL prefix for any 
	 * subsequent requests.
	 *
	 * @param string $code A language code
	 * @return void
	 **/
	protected function set_content_lang( $code ) {
		global $bbl_languages;
		// Set the content language in the application
		$url_prefix = $bbl_languages->get_url_prefix_from_code( $code );
		if ( ! $url_prefix ) {
			return false;
		}
		$this->content_lang = $code;
		$this->url_prefix   = $url_prefix;
		return true;
	}

	/**
	 * Set the interace language code.
	 *
	 * @FIXME: Currently we don't check that the language is valid
	 *
	 * @param string $code A language code
	 * @return void
	 **/
	protected function set_interface_lang( $code ) {
		// Set the interface language in the application
		$this->interface_lang = $code;
	}

	/**
	 * Set the content language for the URL prefix provided.
	 *
	 * @param string $url_prefix A URL prefix, e.g. "de" 
	 * @return void
	 **/
	protected function set_content_lang_from_prefix( $url_prefix ) {
		global $bbl_languages;
		$this->set_content_lang( bbl_get_lang_from_prefix( $url_prefix ) );
	}

	/**
	 * Get the request string for the request, using code copied 
	 * straight from WP->parse_request.
	 *
	 * @return string The request
	 **/
	protected function get_request_string() {
		global $wp_rewrite;
		// @FIXME: Copying a huge hunk of code from WP->parse_request here, feels ugly.
		// START: Huge hunk of WP->parse_request
		if ( isset($_SERVER['PATH_INFO']) )
			$pathinfo = $_SERVER['PATH_INFO'];
		else
			$pathinfo = '';
		$pathinfo_array = explode('?', $pathinfo);
		$pathinfo = str_replace("%", "%25", $pathinfo_array[0]);
		$req_uri = $_SERVER['REQUEST_URI'];
		$req_uri_array = explode('?', $req_uri);
		$req_uri = $req_uri_array[0];
		$self = $_SERVER['PHP_SELF'];
		$home_path = parse_url(home_url());
		if ( isset($home_path['path']) )
			$home_path = $home_path['path'];
		else
			$home_path = '';
		$home_path = trim($home_path, '/');

		// Trim path info from the end and the leading home path from the
		// front.  For path info requests, this leaves us with the requesting
		// filename, if any.  For 404 requests, this leaves us with the
		// requested permalink.
		$req_uri = str_replace($pathinfo, '', $req_uri);
		$req_uri = trim($req_uri, '/');
		$req_uri = preg_replace("|^$home_path|", '', $req_uri);
		$req_uri = trim($req_uri, '/');
		$pathinfo = trim($pathinfo, '/');
		$pathinfo = preg_replace("|^$home_path|", '', $pathinfo);
		$pathinfo = trim($pathinfo, '/');
		$self = trim($self, '/');
		$self = preg_replace("|^$home_path|", '', $self);
		$self = trim($self, '/');

		// The requested permalink is in $pathinfo for path info requests and
		//  $req_uri for other requests.
		if ( ! empty($pathinfo) && !preg_match('|^.*' . $wp_rewrite->index . '$|', $pathinfo) ) {
			$request = $pathinfo;
		} else {
			// If the request uri is the index, blank it out so that we don't try to match it against a rule.
			if ( is_object( $wp_rewrite ) && $req_uri == $wp_rewrite->index )
				$req_uri = '';
			$request = $req_uri;
		}
		// END: Huge hunk of WP->parse_request
		return $request;
	}

	/**
	 * Sets the content language cookie where necessary. We are using cookies
	 * as we cannot get userdata at the set_locale action, which is where 
	 * we need to read the user's language.
	 *
	 * @return void
	 **/
	protected function maybe_set_cookie_content_lang() {
		// @FIXME: At this point a mischievous XSS "attack" could set a user's content language for them
		if ( $requested_lang = ( isset( $_GET[ 'lang' ] ) ) ? $_GET[ 'lang' ] : false )
			setcookie( $this->content_lang_cookie, $requested_lang, time() + 31536000, COOKIEPATH, COOKIE_DOMAIN);
	}

	/**
	 * Sets the admin language cookie where necessary. We are using cookies
	 * as we cannot get userdata at the set_locale action, which is where 
	 * we need to read the user's language.
	 *
	 * @return void
	 **/
	protected function maybe_set_cookie_interface_lang() {
		// @FIXME: At this point a mischievous XSS "attack" could set a user's admin area language for them
		if ( $requested_lang = ( isset( $_POST[ 'interface_lang' ] ) ) ? $_POST[ 'interface_lang' ] : false )
			setcookie( $this->interface_lang_cookie, $requested_lang, time() + 31536000, COOKIEPATH, COOKIE_DOMAIN);
	}

	/**
	 * Gets the language code from the content language cookie.
	 *
	 * @TODO: This should use a cookie that's keyed to the current user when present
	 *
	 * @return string A language code
	 **/
	protected function get_cookie_content_lang() {
		return ( isset( $_COOKIE[ $this->content_lang_cookie ] ) ) ? $_COOKIE[ $this->content_lang_cookie ] : '';
	}

	/**
	 * Gets the language code from the interface language cookie.
	 *
	 * @TODO: This should use a cookie that's keyed to the current user when present
	 *
	 * @return string A language code
	 **/
	protected function get_cookie_interface_lang() {
		return ( isset( $_COOKIE[ $this->interface_lang_cookie] ) ) ? $_COOKIE[ $this->interface_lang_cookie ] : '';
	}

	/**
	 * Checks the DB structure is up to date.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function maybe_update() {
		global $wpdb;
		$option_name = 'bbl-locale-version';
		$version = get_option( $option_name, 0 );

		if ( $this->version == $version )
			return;

		if ( $version < 1 ) {
			bbl_log( "Babble Locale: Flushing rewrite rules", true );
			flush_rewrite_rules();
		}

		bbl_log( "Babble Locale: Done updates", true );
		update_option( $option_name, $this->version );
	}

}

global $bbl_locale;
$bbl_locale = new Babble_Locale();
