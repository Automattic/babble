<?php

/**
 * Functions and (mainly) hooks which don't fit in the various
 * classes for whatever reason. Consider these various things
 * Private access, for this plugin only, please.
 *
 * Will try to keep the functions in here to an absolute minumum.
 *
 * @package Babble
 * @since Alpha 1.1
 */

if ( !is_admin() ) {
	foreach ( array( 'admin_bar_init', 'admin_bar_menu' ) as $hook ) {
		add_action( $hook, 'bbl_load_interface_textdomain', -9999 );
	}
	foreach ( array( 'add_admin_bar_menus', 'wp_after_admin_bar_render' ) as $hook ) {
		add_action( $hook, 'bbl_load_content_textdomain', 9999 );
	}
}

/**
 * Load the textdomain for Babble's interface language.
 *
 * This is used to attempt to ensure the interface language is used for the admin toolbar. Effective for core, but not themes or plugins which add items to the admin toolbar.
 */
function bbl_load_interface_textdomain() {
	load_default_textdomain( bbl_get_current_interface_lang_code() );
	$GLOBALS['wp_locale'] = new WP_Locale();
}

/**
 * Load the textdomain for Babble's content language.
 *
 */
function bbl_load_content_textdomain() {
	load_default_textdomain( bbl_get_current_content_lang_code() );
	$GLOBALS['wp_locale'] = new WP_Locale();
}

/**
 * Hooks the WP admin_init action to redirect any requests accessing
 * content which is not in the current language.
 *
 * @return void
 **/
function bbl_admin_init() {
	global $pagenow;

	$taxonomy = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : false;
	$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : false;

	// Deal with the special URL case of the listing screens for vanilla posts
	if ( ! $post_type && 'edit.php' == $pagenow )
		$post_type = 'post';

	$cur_lang_code = bbl_get_current_lang_code();

	if ( $taxonomy ) {
		$new_taxonomy = bbl_get_taxonomy_in_lang( $taxonomy, $cur_lang_code );
		if ( $taxonomy != $new_taxonomy ) {
			$url = add_query_arg( array( 'taxonomy' => $new_taxonomy ) );
			wp_safe_redirect( $url );
			exit;
		}
	}
	if ( $post_type ) {
		$new_post_type = bbl_get_post_type_in_lang( $post_type, $cur_lang_code );
		if ( $post_type != $new_post_type ) {
			$url = add_query_arg( array( 'post_type' => $new_post_type ) );
			wp_safe_redirect( $url );
			exit;
		}
	}
}
add_action( 'admin_init', 'bbl_admin_init' );


/**
 * Replicates the core comments_template function, but uses the API
 * to fetch the comments and includes more filters.
 *
 * Loads the comment template specified in $file.
 *
 * Will not display the comments template if not on single post or page, or if
 * the post does not have comments.
 *
 * Uses the WordPress database object to query for the comments. The comments
 * are passed through the 'comments_array' filter hook with the list of comments
 * and the post ID respectively.
 *
 * The $file path is passed through a filter hook called, 'comments_template'
 * which includes the TEMPLATEPATH and $file combined. Tries the $filtered path
 * first and if it fails it will require the default comment template from the
 * default theme. If either does not exist, then the WordPress process will be
 * halted. It is advised for that reason, that the default theme is not deleted.
 *
 * @since 1.5.0
 * @global array $comment List of comment objects for the current post
 * @uses $wpdb
 * @uses $post
 * @uses $withcomments Will not try to get the comments if the post has none.
 *
 * @see comments_template()
 *
 * @param string $file Optional, default '/comments.php'. The file to load
 * @param bool $separate_comments Optional, whether to separate the comments by comment type. Default is false.
 * @return null Returns null if no comments appear
 */
function bbl_comments_template( $file = '/comments.php', $separate_comments = false ) {
	global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_ID, $user_identity, $overridden_cpage;

	if ( !(is_single() || is_page() || $withcomments) || empty($post) )
		return;

	if ( empty($file) )
		$file = '/comments.php';

	$req = get_option('require_name_email');

	/**
	 * Comment author information fetched from the comment cookies.
	 *
	 * @uses wp_get_current_commenter()
	 */
	$commenter = wp_get_current_commenter();

	/**
	 * The name of the current comment author escaped for use in attributes.
	 */
	$comment_author = $commenter['comment_author']; // Escaped by sanitize_comment_cookies()

	/**
	 * The email address of the current comment author escaped for use in attributes.
	 */
	$comment_author_email = $commenter['comment_author_email'];  // Escaped by sanitize_comment_cookies()

	/**
	 * The url of the current comment author escaped for use in attributes.
	 */
	$comment_author_url = esc_url($commenter['comment_author_url']);

	$query = new Bbl_Comment_Query;
	$args = array(
		'order' => 'ASC',
		'post_id' => $post->ID,
		'status' => 'approve',
		'status' => 'approve',
	);
	if ( $user_ID) {
		$args[ 'unapproved_user_id' ] = $user_ID;
	} else if ( ! empty($comment_author) ) {
		$args[ 'unapproved_author' ] = wp_specialchars_decode($comment_author,ENT_QUOTES);
		$args[ 'unapproved_author_email' ] = $comment_author_email;
	}
	$args = apply_filters( 'comments_template_args', $args );
	$comments = $query->query( $args );

	// keep $comments for legacy's sake
	$wp_query->comments = apply_filters( 'comments_array', $comments, $post->ID );
	$comments = &$wp_query->comments;
	$wp_query->comment_count = count($wp_query->comments);
	update_comment_cache($wp_query->comments);

	if ( $separate_comments ) {
		$wp_query->comments_by_type = &separate_comments($comments);
		$comments_by_type = &$wp_query->comments_by_type;
	}

	$overridden_cpage = FALSE;
	if ( '' == get_query_var('cpage') && get_option('page_comments') ) {
		set_query_var( 'cpage', 'newest' == get_option('default_comments_page') ? get_comment_pages_count() : 1 );
		$overridden_cpage = TRUE;
	}

	if ( !defined('COMMENTS_TEMPLATE') || !COMMENTS_TEMPLATE)
		define('COMMENTS_TEMPLATE', true);

	$include = apply_filters('comments_template', STYLESHEETPATH . $file );
	if ( file_exists( $include ) )
		require $include;
	elseif ( file_exists( TEMPLATEPATH . $file ) )
		require TEMPLATEPATH .  $file;
	else // Backward compat code will be removed in a future release
		require ABSPATH . WPINC . '/theme-compat/comments.php';
}


/**
 * WordPress Comment Query class.
 *
 * See Trac: http://core.trac.wordpress.org/ticket/19623
 *
 * @since 3.1.0
 */
class Bbl_Comment_Query {

	/**
	 * Execute the query
	 *
	 * @since 3.1.0
	 *
	 * @param string|array $query_vars
	 * @return int|array
	 */
	function query( $query_vars ) {
		global $wpdb;

		$defaults = array(
			'author_email' => '',
			'ID' => '',
			'karma' => '',
			'number' => '',
			'offset' => '',
			'orderby' => '',
			'order' => 'DESC',
			'parent' => '',
			'post_ID' => '',
			'post_id' => '',
			'post__in' => '',
			'post_author' => '',
			'post_name' => '',
			'post_parent' => '',
			'post_status' => '',
			'post_type' => '',
			'status' => '',
			'type' => '',
			'unapproved_author' => '',
			'unapproved_author_email' => '',
			'unapproved_user_id' => '',
			'user_id' => '',
			'search' => '',
			'count' => false,
		);

		$this->query_vars = wp_parse_args( $query_vars, $defaults );
		do_action_ref_array( 'pre_get_comments', array( $this ) );
		extract( $this->query_vars, EXTR_SKIP );

		// $args can be whatever, only use the args defined in defaults to compute the key
		$key = md5( serialize( compact(array_keys($defaults)) )  );
		$last_changed = wp_cache_get('last_changed', 'comment');
		if ( !$last_changed ) {
			$last_changed = time();
			wp_cache_set('last_changed', $last_changed, 'comment');
		}
		$cache_key = "get_comments:$key:$last_changed";

		if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
			return $cache;
		}

		if ( empty( $post_id ) && empty( $post__in ) )
			$post_id = 0;

		$post_id = absint($post_id);

		$where = '';

		$show_unapproved = ( '' != $unapproved_user_id || '' !== $unapproved_author || '' != $unapproved_author_email );

		if ( $show_unapproved ) {
			$where .= ' ( ';
		}

		if ( 'hold' == $status )
			$where .= "comment_approved = '0'";
		elseif ( 'approve' == $status )
			$where .= "comment_approved = '1'";
		elseif ( 'spam' == $status )
			$where .= "comment_approved = 'spam'";
		elseif ( 'trash' == $status )
			$where .= "comment_approved = 'trash'";
		else
			$where .= "( comment_approved = '0' OR comment_approved = '1' )";

		if ( $show_unapproved ) {
			$where .= ' OR ( comment_approved = 0 ';
			if ( '' !== $unapproved_author )
				$where .= $wpdb->prepare( ' AND comment_author = %s', $unapproved_author );
			if ( '' !== $unapproved_author_email )
				$where .= $wpdb->prepare( ' AND comment_author_email = %s', $unapproved_author_email );
			if ( '' !== $unapproved_user_id )
				$where .= $wpdb->prepare( ' AND user_id = %d', $unapproved_user_id );
			$where .= ' ) ) ';
		}

		$order = ( 'ASC' == strtoupper($order) ) ? 'ASC' : 'DESC';

		if ( ! empty( $orderby ) ) {
			$ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby);
			$ordersby = array_intersect(
				$ordersby,
				array(
					'comment_agent',
					'comment_approved',
					'comment_author',
					'comment_author_email',
					'comment_author_IP',
					'comment_author_url',
					'comment_content',
					'comment_date',
					'comment_date_gmt',
					'comment_ID',
					'comment_karma',
					'comment_parent',
					'comment_post_ID',
					'comment_type',
					'user_id',
				)
			);
			$orderby = empty( $ordersby ) ? 'comment_date_gmt' : implode(', ', $ordersby);
		} else {
			$orderby = 'comment_date_gmt';
		}

		$number = absint($number);
		$offset = absint($offset);

		if ( !empty($number) ) {
			if ( $offset )
				$limits = 'LIMIT ' . $offset . ',' . $number;
			else
				$limits = 'LIMIT ' . $number;
		} else {
			$limits = '';
		}

		if ( $count )
			$fields = 'COUNT(*)';
		else
			$fields = '*';

		$join = '';

		if ( ! empty($post_id) ) {
			$where .= $wpdb->prepare( ' AND comment_post_ID = %d', $post_id );
		} else if ( '' != $post__in ) {
			$_post__in = implode(',', array_map( 'absint', $post__in ));
			$where .= " AND comment_post_ID IN ($_post__in)";
		}
		if ( '' !== $author_email )
			$where .= $wpdb->prepare( ' AND comment_author_email = %s', $author_email );
		if ( '' !== $karma )
			$where .= $wpdb->prepare( ' AND comment_karma = %d', $karma );
		if ( 'comment' == $type ) {
			$where .= " AND comment_type = ''";
		} elseif( 'pings' == $type ) {
			$where .= ' AND comment_type IN ("pingback", "trackback")';
		} elseif ( ! empty( $type ) ) {
			$where .= $wpdb->prepare( ' AND comment_type = %s', $type );
		}
		if ( '' !== $parent )
			$where .= $wpdb->prepare( ' AND comment_parent = %d', $parent );
		if ( '' !== $user_id )
			$where .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		if ( '' !== $search )
			$where .= $this->get_search_sql( $search, array( 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content' ) );

		$post_fields = array_filter( compact( array( 'post_author', 'post_name', 'post_parent', 'post_status', 'post_type', ) ) );
		if ( ! empty( $post_fields ) ) {
			$join = "JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";
			foreach( $post_fields as $field_name => $field_value )
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field_name} = %s", $field_value );
		}

		$pieces = array( 'fields', 'join', 'where', 'orderby', 'order', 'limits' );
		$clauses = apply_filters_ref_array( 'comments_clauses', array( compact( $pieces ), $this ) );
		foreach ( $pieces as $piece )
			$$piece = isset( $clauses[ $piece ] ) ? $clauses[ $piece ] : '';

		$query = "SELECT $fields FROM $wpdb->comments $join WHERE $where ORDER BY $orderby $order $limits";

		if ( $count )
			return $wpdb->get_var( $query );

		$comments = $wpdb->get_results( $query );
		$comments = apply_filters_ref_array( 'the_comments', array( $comments, $this ) );

		wp_cache_set( $cache_key, $comments, 'comment' );

		return $comments;
	}
}
