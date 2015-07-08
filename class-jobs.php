<?php
/**
 * Class for handling jobs for the various language
 * translation teams.
 *
 * @package Babble
 * @since 1.4
 */
class Babble_Jobs extends Babble_Plugin {

	/**
	 * A version number used for cachebusting, rewrite rule
	 * flushing, etc.
	 *
	 * @var int
	 **/
	protected $version;

	/**
	 * A simple flag to stop infinite recursion in various places.
	 *
	 * @var boolean
	 **/
	protected $no_recursion;

	public function __construct() {
		$this->setup( 'babble-job', 'plugin' );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'add_meta_boxes_bbl_job', array( $this, 'add_meta_boxes_bbl_job' ), 999 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'babble_create_empty_translation', array( $this, 'create_empty_translation' ) );
		add_action( 'bbl_translation_post_meta_boxes', array( $this, 'bbl_translation_post_meta_boxes' ), 10, 3 );
		add_action( 'bbl_translation_submit_meta_boxes', array( $this, 'bbl_translation_submit_meta_boxes' ), 10, 2 );
		add_action( 'bbl_translation_terms_meta_boxes', array( $this, 'bbl_translation_terms_meta_boxes' ), 10, 2 );
		add_action( 'bbl_translation_meta_meta_boxes', array( $this, 'bbl_translation_meta_meta_boxes' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'edit_form_after_title' ) );
		add_action( 'init', array( $this, 'init_early' ), 0 );
		add_action( 'load-post.php', array( $this, 'load_post_edit' ) );
		add_action( 'manage_bbl_job_posts_custom_column', array( $this, 'action_column' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'save_post', array( $this, 'save_job' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_before_admin_bar_render', array( $this, 'wp_before_admin_bar_render' ) );

		add_filter( 'admin_title', array( $this, 'admin_title' ), 10, 2 );
		add_filter( 'bbl_translated_post_type', array( $this, 'bbl_translated_post_type' ), 10, 2 );
		add_filter( 'bbl_translated_taxonomy', array( $this, 'bbl_translated_taxonomy' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'get_edit_post_link' ), 10, 3 );
		add_filter( 'manage_bbl_job_posts_columns', array( $this, 'filter_columns' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 10, 3 );
		add_filter( 'wp_insert_post_empty_content', array( $this, 'wp_insert_post_empty_content' ), 10, 2 );

		$this->version = 1.1;
	}

	public function add_meta_boxes_bbl_job( WP_Post $post ) {

		# Unapologetically remove all meta boxes from the translation screen:

		global $wp_meta_boxes;
		unset( $wp_meta_boxes['bbl_job'] );

	}

	public function wp_insert_post_empty_content( $maybe_empty, $postarr ) {
		// Allow translations to have empty content
		if ( bbl_get_base_post_type( $postarr['post_type'] ) != $postarr['post_type'] )
			return false;
		return $maybe_empty;
	}

	public function bbl_translated_post_type( $translated, $post_type ) {
		if ( 'bbl_job' == $post_type )
			return false;
		return $translated;
	}

	public function bbl_translated_taxonomy( $translated, $taxonomy ) {
		if ( 'bbl_job_language' == $taxonomy )
			return false;
		return $translated;
	}

	/**
	 * Add our post type updated messages.
	 *
	 * The messages are as follows:
	 *
	 *   1 => "Post updated. {View Post}"
	 *   2 => "Custom field updated."
	 *   3 => "Custom field deleted."
	 *   4 => "Post updated."
	 *   5 => "Post restored to revision from [date]."
	 *   6 => "Post published. {View post}"
	 *   7 => "Post saved."
	 *   8 => "Post submitted. {Preview post}"
	 *   9 => "Post scheduled for: [date]. {Preview post}"
	 *  10 => "Post draft updated. {Preview post}"
	 *
	 * @param array $messages An associative array of post updated messages with post type as keys.
	 * @return array Updated array of post updated messages.
	 */
	public function post_updated_messages( array $messages ) {

		$messages['bbl_job'] = array(
			1  => __( 'Translation job updated.', 'babble' ),
			4  => __( 'Translation job updated.', 'babble' ),
			8  => __( 'Translation job submitted.', 'babble' ),
			10 => __( 'Translation job draft updated.', 'babble' ),
		);

		return $messages;

	}

	/**
	 * Hooks the WP admin_init action to enqueue some stuff.
	 *
	 * @return void
	 **/
	public function admin_init() {
		wp_enqueue_style( 'bbl-jobs-admin', $this->url( 'css/jobs-admin.css' ), array(), filemtime( $this->dir( 'css/jobs-admin.css' ) ) );
	}

	/**
	 * Hooks the WP action load-post.php to detect people
	 * trying to edit translated posts, and instead kick
	 * redirect them to an existing translation job or
	 * create a translation job and direct them to that.
	 *
	 * @TODO this should be in the post-public class
	 *
	 * @action load-post.php
	 *
	 * @return void
	 **/
	public function load_post_edit() {
		$post_id = isset( $_GET[ 'post' ] ) ? absint( $_GET[ 'post' ] ) : false;
		if ( ! $post_id )
			$post_id = isset( $_POST[ 'post_ID' ] ) ? absint( $_POST[ 'post_ID' ] ) : false;
		$translated_post = get_post( $post_id );
		if ( ! $translated_post )
			return;
		if ( ! bbl_is_translated_post_type( $translated_post->post_type ) )
			return;
		$canonical_post = bbl_get_default_lang_post( $translated_post );
		$lang_code = bbl_get_post_lang_code( $translated_post );
		if ( bbl_get_default_lang_code() == $lang_code )
			return;
		// @TODO Check capabilities include editing a translation post
		// - If not, the button shouldn't be on the Admin Bar
		// - But we also need to not process at this point
		$existing_jobs = $this->get_incomplete_post_jobs( $canonical_post );
		if ( isset( $existing_jobs[ $lang_code ] ) ) {
			$url = get_edit_post_link( $existing_jobs[ $lang_code ], 'url' );
			wp_safe_redirect( $url );
			exit;
		}
		// Create a new translation job for the current language
		$lang_codes = array( $lang_code );
		$jobs = $this->create_post_jobs( $canonical_post, $lang_codes );
		// Redirect to the translation job
		$url = get_edit_post_link( $jobs[0], 'url' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Hooks the WP admin_title filter to give some context to the
	 * page titles.
	 *
	 * @filter admin_title
	 *
	 * @param string $admin_title The admin title (for the TITLE element)
	 * @param string $title The title used in the H2 element above the edit form
	 * @return string The admin title
	 **/
	public function admin_title( $admin_title, $title ) {
		$screen = get_current_screen();
		if ( 'post' == $screen->base && 'bbl_job' == $screen->post_type ) {
			$pto = get_post_type_object( 'bbl_job' );
			$job = get_post();
			if ( 'add' == $screen->action ) {
				if ( isset( $_GET['lang'] ) ) {
					$lang = bbl_get_lang( $_GET['lang'] );
					$admin_title = sprintf( $pto->labels->add_item_context, $lang->display_name );
				}
			} else {
				$lang = $this->get_job_language( $job );
				$admin_title = sprintf( $pto->labels->edit_item_context, $lang->display_name );
			}
			$GLOBALS[ 'title' ] = $admin_title;
		}
		return $admin_title;
	}

	/**
	 * Filters the public query vars and adds some of our own
	 *
	 * @filter query_vars
	 * @param  array $vars Public query vars
	 * @return array Updated public query vars
	 */
	public function query_vars( array $vars ) {
		if ( is_admin() ) {
			$vars[] = 'bbl_job_post';
			$vars[] = 'bbl_job_term';
			$vars[] = 'bbl_job_meta';
		}
		return $vars;
	}

	/**
	 * Filter the user's capabilities so they can be added/removed on the fly.
	 *
	 * @TODO description of what this does
	 *
	 * @filter user_has_cap
	 * @param array $user_caps     User's capabilities
	 * @param array $required_caps Actual required capabilities for the requested capability
	 * @param array $args          Arguments that accompany the requested capability check:
	 *                             [0] => Requested capability from current_user_can()
	 *                             [1] => Current user ID
	 *                             [2] => Optional second parameter from current_user_can()
	 * @return array User's capabilities
	 */
	public function user_has_cap( array $user_caps, array $required_caps, array $args ) {

		$user = new WP_User( $args[1] );

		switch ( $args[0] ) {

			case 'edit_post':
			case 'edit_bbl_job':
			case 'delete_post':
			case 'delete_bbl_job':
			case 'publish_post':
			case 'publish_bbl_job':

				$job = get_post( $args[2] );

				if ( ! $job or ( 'bbl_job' != $job->post_type ) ) {
					break;
				}

				$objects = $this->get_job_objects( $job );
				$pto     = get_post_type_object( $job->post_type );
				$cap     = str_replace( 'bbl_job', 'post', $args[0] );

				if ( isset( $objects['post'] ) && $objects['post']->post_type != 'bbl_job' ) {

					# This directly maps the ability to edit/delete/publish the job with the ability to do the same to the job's post:

					$can = user_can( $user, $cap, $objects['post']->ID );
					foreach ( $required_caps as $required ) {
						if ( ! isset( $user_caps[$required] ) ) {
							$user_caps[$required] = $can;
						}
					}

				} else { # else if isset object terms

				}

				break;

			case 'edit_bbl_jobs':

				# Special case for displaying the admin menu:

				# By default, Translators will have this cap:
				if ( isset( $user_caps[$args[0]] ) )
					break;

				# Cycle through post types with show_ui true, give edit_bbl_jobs cap to the user if they can edit any of the post types

				foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pto ) {
					// Don't check the capability we already checked.
					if ( $args[0] == $pto->cap->edit_posts ) {
						continue;
					}

					if ( user_can( $user, $pto->cap->edit_posts ) ) {
						$user_caps[$args[0]] = true;
						break;
					}
				}

				break;

		}

		return $user_caps;

	}

	/**
	 * Hooks the WP pre_get_posts ref action in the WP_Query. Sets the meta query
	 * that's necessary for filtering jobs by their objects.
	 *
	 * @param WP_Query $wp_query A WP_Query object, passed by reference
	 * @return void (param passed by reference)
	 **/
	public function pre_get_posts( WP_Query & $query ) {

		if ( $job_post = $query->get( 'bbl_job_post' ) ) {
			$query->set( 'meta_key', 'bbl_job_post' );
			$query->set( 'meta_value', $job_post );
		} else if ( $job_term = $query->get( 'bbl_job_term' ) ) {
			$query->set( 'meta_key', 'bbl_job_term' );
			$query->set( 'meta_value', $job_term );
		} else if ( $job_meta = $query->get( 'bbl_job_meta' ) ) {
			$query->set( 'meta_key', 'bbl_job_meta' );
			$query->set( 'meta_value', $job_meta );
		}

	}

	/**
	 * Hooks the WP filter get_edit_post_link
	 *
	 * @filter get_edit_post_link
	 * @param string $url The edit post link URL
	 * @param int $post_ID The ID of the post to edit
	 * @param string $context The link context.
	 *
	 * @return string The edit post link URL
	 * @author Simon Wheatley
	 **/
	public function get_edit_post_link( $url, $post_ID, $context ) {
		if ( $this->no_recursion ) {
			return $url;
		}
		if ( bbl_get_default_lang_code() == bbl_get_post_lang_code( $post_ID ) ) {
			return $url;
		}

		$completed_jobs = $this->get_completed_post_jobs( bbl_get_default_lang_post( $post_ID ) );
		if ( ! isset( $completed_jobs[ bbl_get_current_lang_code() ] ) ) {
			return $url;
		}
		$job = $completed_jobs[ bbl_get_current_lang_code() ];

		if ( ! current_user_can( 'publish_post', $job->ID ) ) {
			return $url;
		}

		$this->no_recursion = true;
		$url = get_edit_post_link( $completed_jobs[ bbl_get_current_lang_code() ]->ID );
		$this->no_recursion = false;
		return $url;
	}

	public function edit_form_after_title() {

		$screen = get_current_screen();

		if ( 'bbl_job' != $screen->post_type ) {
			return;
		}

		$job   = get_post();
		$items = $objects = $vars = array();

		if ( ( 'add' == $screen->action ) and isset( $_GET['lang'] ) ) {

			$vars['lang_code'] = stripslashes( $_GET['lang'] );

			if ( isset( $_GET['bbl_origin_post'] ) ) {

				$post  = get_post( absint( $_GET['bbl_origin_post' ] ) );
				$terms = $this->get_post_terms_to_translate( $post, $_GET['lang'] );
				$meta  = $this->get_post_meta_to_translate( $post, $_GET['lang'] );
				$objects['post'] = $post;

				if ( !empty( $terms ) ) {
					$objects['terms'] = $terms;
				}

				if ( !empty( $meta ) ) {
					$objects['meta'] = $meta;
				}

				$vars['origin_post'] = $post->ID;

			} else if ( isset( $_GET['bbl_origin_term'] ) and isset( $_GET['bbl_origin_taxonomy'] ) ) {

				$term = get_term( $_GET['bbl_origin_term'], $_GET['bbl_origin_taxonomy'] );
				$objects['terms'][$term->taxonomy][$term->term_id] = $term;
				$vars['origin_term']     = $term->term_id;
				$vars['origin_taxonomy'] = $term->taxonomy;

			}

		} else {

			$objects = $this->get_job_objects( $job );

		}

		if ( isset( $objects['post'] ) ) {

			$post = $objects['post'];
			$post_translation = get_post_meta( $job->ID, "bbl_post_{$post->ID}", true );

			if ( empty( $post_translation ) ) {
				$post_translation = get_default_post_to_edit( $post->post_type );
			}

			$items['post'] = array(
				'original'    => $post,
				'translation' => (object) $post_translation,
			);

		}

		if ( isset( $objects['meta'] ) ) {

			foreach ( $objects['meta'] as $meta_key => $meta_field ) {

				$meta_translation = get_post_meta( $job->ID, "bbl_meta_{$meta_key}", true );

				if ( empty( $meta_translation ) ) {
					$meta_translation = '';
				}

				$items['meta'][$meta_key] = array(
					'original'    => $meta_field,
					'translation' => $meta_translation,
				);

			}

		}

		if ( isset( $objects['terms'] ) ) {

			foreach ( $objects['terms'] as $taxo => $terms ) {

				foreach ( $terms as $term ) {

					$term_translation = get_post_meta( $job->ID, "bbl_term_{$term->term_id}", true );

					if ( empty( $term_translation ) ) {
						$term_translation = array( 'name' => '', 'slug' => '' );
					}

					$items['terms'][$taxo][] = array(
						'original'    => $term,
						'translation' => (object) $term_translation,
					);

				}

			}

		}

		$statuses = array(
			'in-progress' => get_post_status_object( 'in-progress' )->label,
		);

		if ( ( 'pending' == $job->post_status ) or !current_user_can( 'publish_post', $job->ID ) ) {
			$statuses['pending'] = get_post_status_object( 'pending' )->label;
		}

		if ( current_user_can( 'publish_post', $job->ID ) ) {
			$statuses['complete'] = get_post_status_object( 'complete' )->label;
		}

		$statuses = apply_filters( 'bbl_job_statuses', $statuses, $job, $objects );

		$vars['job']      = $job;
		$vars['items']    = $items;
		$vars['statuses'] = $statuses;

		$this->render_admin( 'translation-editor.php', $vars );

	}

	public function admin_menu() {
		# Remove the 'Add New' submenu for Translations.
		remove_submenu_page( 'edit.php?post_type=bbl_job', 'post-new.php?post_type=bbl_job' );
	}

	public function wp_before_admin_bar_render() {
		global $wp_admin_bar;
		# Remove the '+New -> Translation Job' admin bar menu.
		$wp_admin_bar->remove_node( 'new-bbl_job' );
	}

	/**
	 * undocumented function
	 *
	 * @param
	 * @return void
	 **/
	public function add_meta_boxes( $post_type ) {

		 if ( bbl_is_translated_post_type( $post_type ) ) {
			add_meta_box( 'bbl_translations', _x( 'Translations', 'Translations meta box title', 'babble' ), array( $this, 'metabox_post_translations' ), $post_type, 'side', 'high' );
		}

	}

	public function bbl_translation_post_meta_boxes( $type, $original, $translation ) {

		if ( !empty( $original->post_excerpt ) or !empty( $translation->post_excerpt ) ) {
			add_meta_box( 'postexcerpt', __( 'Excerpt', 'babble' ), array( $this, 'metabox_translation_post_excerpt' ), $type, 'post' );
		}

	}

	public function bbl_translation_terms_meta_boxes( $type, $items ) {

		foreach ( $items as $taxo => $terms ) {
			$tax = get_taxonomy( $taxo );
			add_meta_box( "{$taxo}_terms", $tax->labels->name, array( $this, 'metabox_translation_terms' ), $type, $taxo );
		}

	}

	public function bbl_translation_meta_meta_boxes( $type, $items ) {

		$i = 0;

		foreach ( $items as $meta_key => $meta_field ) {
			add_meta_box( "meta_{$i}", esc_html( $meta_field['original']->get_title() ), array( $this, 'metabox_translation_meta' ), $type, $meta_key );
			$i++;
		}

	}

	public function bbl_translation_submit_meta_boxes( $type, $job ) {

		add_meta_box( 'bbl_job_submit', __( 'Save Translation' , 'babble'), array( $this, 'metabox_translation_submit' ), $type, 'submit' );

	}

	public function metabox_translation_terms( array $items ) {

		$vars = $items;

		$this->render_admin( 'translation-editor-terms.php', $vars );

	}

	public function metabox_translation_meta( array $items ) {

		$vars = $items;

		$this->render_admin( 'translation-editor-meta.php', $vars );

	}

	public function metabox_translation_post_excerpt( array $items ) {

		$vars = $items;

		$this->render_admin( 'translation-editor-post-excerpt.php', $vars );

	}

	public function metabox_translation_submit( array $items ) {

		$vars = $items;

		$this->render_admin( 'translation-editor-submit.php', $vars );

	}

	public function save_job( $job_id, WP_Post $job ) {

		global $bbl_post_public, $bbl_taxonomies;

		if ( $this->no_recursion )
			return;
		if ( 'bbl_job' != $job->post_type )
			return;

		$edit_post_nonce   = isset( $_POST[ '_bbl_translation_edit_post' ] ) ? $_POST[ '_bbl_translation_edit_post' ] : false;
		$edit_terms_nonce  = isset( $_POST[ '_bbl_translation_edit_terms' ] ) ? $_POST[ '_bbl_translation_edit_terms' ] : false;
		$edit_meta_nonce   = isset( $_POST[ '_bbl_translation_edit_meta' ] ) ? $_POST[ '_bbl_translation_edit_meta' ] : false;
		$origin_post_nonce = isset( $_POST[ '_bbl_translation_origin_post' ] ) ? $_POST[ '_bbl_translation_origin_post' ] : false;
		$origin_term_nonce = isset( $_POST[ '_bbl_translation_origin_term' ] ) ? $_POST[ '_bbl_translation_origin_term' ] : false;
		$lang_code_nonce   = isset( $_POST[ '_bbl_translation_lang_code' ] ) ? $_POST[ '_bbl_translation_lang_code' ] : false;

		if ( $lang_code_nonce and wp_verify_nonce( $lang_code_nonce, "bbl_translation_lang_code_{$job->ID}" ) ) {
			wp_set_object_terms( $job->ID, stripslashes( $_POST['bbl_lang_code'] ), 'bbl_job_language', false );
		}

		$language = get_the_terms( $job, 'bbl_job_language' );

		if ( empty( $language ) )
			return false;
		else
			$lang_code = reset( $language )->name;

		if ( $origin_post_nonce and wp_verify_nonce( $origin_post_nonce, "bbl_translation_origin_post_{$job->ID}") ) {
			if ( $origin_post = get_post( absint( $_POST['bbl_origin_post'] ) ) ) {
				add_post_meta( $job->ID, 'bbl_job_post', "{$origin_post->post_type}|{$origin_post->ID}", true );

				foreach ( $this->get_post_terms_to_translate( $origin_post, $lang_code ) as $taxo => $terms ) {
					foreach ( $terms as $term_id => $term )
						add_post_meta( $job->ID, 'bbl_job_term', "{$taxo}|{$term_id}", false );
				}

				foreach ( $this->get_post_meta_to_translate( $origin_post, $lang_code ) as $key => $field ) {
					add_post_meta( $job->ID, 'bbl_job_meta', $key, false );
				}

			}
			# @TODO else wp_die()?
		}

		# @TODO not implemented:
		if ( $origin_term_nonce and wp_verify_nonce( $origin_term_nonce, "bbl_translation_origin_term_{$job->ID}") ) {
			if ( $origin_term = get_term( absint( $_POST['bbl_origin_term'] ), $_POST['bbl_origin_taxonomy'] ) )
				add_post_meta( $job->ID, 'bbl_job_term', "{$origin_term->taxonomy}|{$origin_term->term_id}", false );
			# @TODO else wp_die()?
		}

		if ( $edit_post_nonce and wp_verify_nonce( $edit_post_nonce, "bbl_translation_edit_post_{$job->ID}" ) ) {

			$post_data = stripslashes_deep( $_POST['bbl_translation']['post'] );
			if ( $post_data['post_name'] )
				$post_data['post_name'] = sanitize_title( $post_data['post_name'] );
			$post_info = get_post_meta( $job->ID, 'bbl_job_post', true );
			list( $post_type, $post_id ) = explode( '|', $post_info );
			$post = get_post( $post_id );

			update_post_meta( $job->ID, "bbl_post_{$post_id}", $post_data );

			if ( 'pending' == $job->post_status ) {

				# Nothing.

			}

			if ( 'complete' == $job->post_status ) {

				# The ability to complete a translation of a post directly
				# maps to the ability to publish the origin post.

				if ( current_user_can( 'publish_post', $job->ID ) ) {

					if ( !$trans = $bbl_post_public->get_post_in_lang( $post, $lang_code, false ) )
						$trans = $bbl_post_public->initialise_translation( $post, $lang_code );

					$post_data['ID']          = $trans->ID;
					$post_data['post_status'] = $post->post_status;

					$this->no_recursion = true;
					wp_update_post( $post_data, true );
					$this->no_recursion = false;

				} else {

					# Just in case. Switch the job back to in-progress status.
					# It would be nice to be able to use the 'publish' status because then we get the built-in
					# publish_post cap checks, but we can't control the post status label on a per-post-type basis yet.

					$this->no_recursion = true;
					wp_update_post( array(
						'ID'          => $job->ID,
						'post_status' => 'in-progress',
					), true );
					$this->no_recursion = false;

				}

			}

			if ( $edit_meta_nonce and wp_verify_nonce( $edit_meta_nonce, "bbl_translation_edit_meta_{$job->ID}" ) ) {

				$meta_data = stripslashes_deep( $_POST['bbl_translation']['meta'] );

				foreach ( $meta_data as $meta_key => $meta_value ) {

					update_post_meta( $job->ID, "bbl_meta_{$meta_key}", $meta_value );

					if ( 'complete' == $job->post_status ) {
						if ( current_user_can( 'publish_post', $job->ID ) ) {
							update_post_meta( $trans->ID, $meta_key, $meta_value );
						}
					}

				}

			}

		}

		if ( $edit_terms_nonce and wp_verify_nonce( $edit_terms_nonce, "bbl_translation_edit_terms_{$job->ID}") ) {

			$terms_data = stripslashes_deep( $_POST['bbl_translation']['terms'] );
			$terms      = get_post_meta( $job->ID, 'bbl_job_term', false );

			foreach ( $terms as $term_info ) {

				list( $taxo, $term_id ) = explode( '|', $term_info );
				$term = get_term( $term_id, $taxo );
				$terms_data[$term_id]['slug'] = sanitize_title( $terms_data[$term_id]['slug'] );

				update_post_meta( $job->ID, "bbl_term_{$term_id}", $terms_data[$term_id] );

				if ( 'complete' == $job->post_status ) {

					# @TODO if current user can edit term

					$trans = $bbl_taxonomies->get_term_in_lang( $term, $taxo, $lang_code, false );
					if ( !$trans )
						$trans = $bbl_taxonomies->initialise_translation( $term, $taxo, $lang_code );

					$terms_data[$term->term_id]['term_id'] = $trans->term_id;

					$args = array(
						'name' => $terms_data[$term->term_id]['name'],
						'slug' => '',
					);
					wp_update_term( absint( $trans->term_id ), $trans->taxonomy, $args );

				}

			}

		}

	}

	public function save_post( $post_id, WP_Post $post ) {

		if ( $this->no_recursion )
			return;
		if ( !bbl_is_translated_post_type( $post->post_type ) )
			return;

		$nonce = isset( $_POST[ '_bbl_ready_for_translation' ] ) ? $_POST[ '_bbl_ready_for_translation' ] : false;

		if ( !$nonce )
			return;
		if ( !wp_verify_nonce( $nonce, "bbl_ready_for_translation-{$post->ID}" ) )
			return;
		if ( !isset( $_POST['babble_ready_for_translation'] ) )
			return;

		# @TODO individual language selection when marking post as translation ready
		$langs       = bbl_get_active_langs();
		$lang_codes  = wp_list_pluck( $langs, 'code' );
		$this->create_post_jobs( $post->ID, $lang_codes );
	}

	/**
	* Hooks the WP init action early to register the
	* job post type.
	*
	* @return void
	**/
	public function init_early() {
		$labels = array(
			'name'               => _x( 'Translation Jobs', 'translation jobs general name', 'babble' ),
			'singular_name'      => _x( 'Translation Job', 'translation jobs singular name', 'babble' ),
			'menu_name'          => _x( 'Translations', 'translation jobs menu name', 'babble' ),
			'add_new'            => _x( 'Add New', 'translation job', 'babble' ),
			'add_new_item'       => _x( 'Create New Job', 'translation job', 'babble' ),
			'add_item_context'   => _x( 'Add Translation Job (%s)', 'translation job; e.g. "Add Translation Job (French)"', 'babble' ),
			'edit_item'          => _x( 'Edit Translation', 'translation job', 'babble' ),
			'edit_item_context'  => _x( 'Edit Translation (%s)', 'translation job; e.g. "Edit Translation (French)"', 'babble' ),
			'new_item'           => _x( 'New Job', 'translation job', 'babble' ),
			'view_item'          => _x( 'View Job', 'translation job', 'babble' ),
			'search_items'       => _x( 'Search Jobs', 'translation job', 'babble' ),
			'not_found'          => _x( 'No translation jobs found.', 'translation job', 'babble' ),
			'not_found_in_trash' => _x( 'No translation jobs found in Trash.', 'translation job', 'babble' ),
			'all_items'          => _x( 'All Translation Jobs', 'translation job', 'babble' ),
		);
		$args = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-clipboard',
			'query_var'          => false,
			'labels'             => $labels,
			'can_export'         => true,
			'supports'           => false,
			'capability_type'    => 'bbl_job',
			'map_meta_cap'       => true,
		);
		register_post_type( 'bbl_job', $args );
		register_post_status( 'new', array(
			'label'                  => __( 'New', 'babble' ),
			'public'                 => false,
			'exclude_from_search'    => false,
			'show_in_admin_all_list' => true,
			'label_count'            => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'babble' ),
			'protected'              => true,
			) );
		register_post_status( 'in-progress', array(
			'label'                  => __( 'In Progress', 'babble' ),
			'public'                 => false,
			'exclude_from_search'    => false,
			'show_in_admin_all_list' => true,
			'label_count'            => _n_noop( 'In Progress <span class="count">(%s)</span>', 'In Progress <span class="count">(%s)</span>', 'babble' ),
			'protected'              => true,
		) );
		register_post_status( 'complete', array(
			'label'                  => __( 'Complete', 'babble' ),
			'public'                 => false,
			'exclude_from_search'    => false,
			'show_in_admin_all_list' => true,
			'label_count'            => _n_noop( 'Complete <span class="count">(%s)</span>', 'Complete <span class="count">(%s)</span>', 'babble' ),
			'protected'              => true,
		) );
		$args = array(
			'public'  => false,
			'show_ui' => false,
		);
		register_taxonomy( 'bbl_job_language', array( 'bbl_job' ), $args );
	}

	// CALLBACKS
	// =========

	public function filter_columns( $cols ) {
		$new_cols = array();
		foreach ( $cols as $col_id => $col ) {
			if ( 'date' != $col_id ) {
				$new_cols[$col_id] = $col;
			} else {
				$new_cols['bbl_language'] = __( 'Language', 'babble' );
				$new_cols['bbl_type']     = __( 'Items', 'babble' );
				$new_cols['bbl_status']   = __( 'Status', 'babble' );
				$new_cols['date'] = $col;
			}
		}
		return $new_cols;
	}

	public function action_column( $col, $post_id ) {

		$post = get_post( $post_id );
		$status = get_post_status_object( $post->post_status );

		switch ( $col ) {

			case 'bbl_language':
				echo $this->get_job_language( $post )->display_name;
				break;

			case 'bbl_type':
				echo implode( ', ', $this->get_job_type( $post ) );
				break;

			case 'bbl_status':
				echo $status->label;
				break;

		}

	}

	public function metabox_post_translations( WP_Post $post, array $metabox ) {

		$trans   = bbl_get_post_translations( $post );
		$incomplete_jobs    = $this->get_incomplete_post_jobs( $post );
		$completed_jobs    = $this->get_completed_post_jobs( $post );
		$default = bbl_get_default_lang_code();

		# The ability to create a translation of a post directly
		# maps to the ability to publish the canonical post.
		$capable = current_user_can( 'publish_post', $post->ID );

		unset( $trans[$default] );

		if ( !empty( $trans ) ) {

			if ( !empty( $completed_jobs ) and $capable ) {
				?><h4><?php esc_html_e( 'Complete:', 'babble' ); ?></h4><?php
			}

			foreach ( $completed_jobs as $lang_code => $job ) {
				$lang = bbl_get_lang( $lang_code );
				?>
				<p><?php printf( '%s: <a href="%s">%s</a>', esc_html( $lang->display_name ), esc_url( get_edit_post_link( $job->ID ) ), esc_html__( 'View', 'babble' ) ); ?>
				<?php
			}

		}

		if ( !empty( $incomplete_jobs ) and $capable ) {

			?><h4><?php esc_html_e( 'Pending:', 'babble' ); ?></h4><?php
			foreach ( $incomplete_jobs as $job ) {
				$lang = $this->get_job_language( $job );
				$status = get_post_status_object( $job->post_status );
				?>
				<p><?php printf( '%s (%s)', esc_html( $lang->display_name ), esc_html( $status->label ) ); ?>
				<?php
			}

			$args = array(
				'post_type'    => 'bbl_job',
				'bbl_job_post' => "{$post->post_type}|{$post->ID}",
			);
			?>
			<p><a href="<?php echo esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'View pending translation jobs &raquo;', 'babble' ); ?></a></p>
			<?php

		} else if ( $capable ) {

			wp_nonce_field( "bbl_ready_for_translation-{$post->ID}", '_bbl_ready_for_translation' );

			?>
			<p><label><input type="checkbox" name="babble_ready_for_translation" value="<?php echo absint( $post->ID ); ?>" /> <?php esc_html_e( 'Ready for translation', 'babble' ); ?></label></p>
			<?php

		} else {

			?>
			<p><?php esc_html_e( _x( 'None', 'No translations', 'babble' ) ); ?></p>
			<?php

		}

	}

	// PUBLIC METHODS
	// ==============

	/**
	 * Return the array of incomplete jobs for a Post, keyed
	 * by lang code.
	 *
	 * @param WP_Post|int $post A WP Post object or a post ID
	 * @return array An array of WP Translation Job Post objects
	 */
	public function get_incomplete_post_jobs( $post ) {
		$post = get_post( $post );
		return $this->get_object_jobs( $post->ID, 'post', $post->post_type, array( 'new', 'in-progress' ) );
	}

	/**
	 * Return the array of completed jobs for a Post, keyed
	 * by lang code.
	 *
	 * @param WP_Post|int $post A WP Post object or a post ID
	 * @return array An array of WP Translation Job Post objects
	 */
	public function get_completed_post_jobs( $post ) {
		$post = get_post( $post );
		return $this->get_object_jobs( $post->ID, 'post', $post->post_type, array( 'complete' ) );
	}

	/**
	 * Return the array of jobs for a Term, keyed
	 * by lang code.
	 *
	 * @param object $term A WP Term object or a term ID
	 * @return array An array of WP Translation Job Post objects
	 */
	public function get_term_jobs( $term, $taxonomy ) {
		$term = get_term( $term, $taxonomy );
		return $this->get_object_jobs( $term->term_id, 'term', $term->taxonomy );
	}

	/**
	 * Return the array of jobs for a term or post, keyed
	 * by lang code.
	 *
	 * @param int The ID of the object (eg. post ID or term ID)
	 * @param string $type Either 'term' or 'post'
	 * @param string $name The post type name or the term's taxonomy name
	 * @return array An array of translation job WP_Post objects
	 */
	public function get_object_jobs( $id, $type, $name, $statuses = array( 'new', 'in-progress', 'complete' ) ) {

		$jobs = get_posts( array(
			'bbl_translate'  => false,
			'post_type'      => 'bbl_job',
			'post_status'    => $statuses,
			'meta_key'       => "bbl_job_{$type}",
			'meta_value'     => "{$name}|{$id}",
			'posts_per_page' => -1,
		) );

		if ( empty( $jobs ) )
			return array();

		$return = array();

		foreach ( $jobs as $job ) {
			if ( $lang = $this->get_job_language( $job ) )
				$return[$lang->code] = $job;
		}

		return $return;

	}

	public function get_job_language( $job ) {
		$job       = get_post( $job );
		$languages = get_the_terms( $job, 'bbl_job_language' );
		if ( empty( $languages ) )
			return false;
		return bbl_get_lang( reset( $languages )->name );
	}

	public function get_job_type( $job ) {

		$job   = get_post( $job );
		$post  = get_post_meta( $job->ID, 'bbl_job_post', true );
		$terms = get_post_meta( $job->ID, 'bbl_job_term', false );

		$return = array();

		if ( !empty( $post ) ) {
			list( $post_type, $post_id ) = explode( '|', $post );
			$return[] = get_post_type_object( $post_type )->labels->singular_name;
		}

		if ( !empty( $terms ) ) {

			foreach ( $terms as $term ) {
				list( $taxonomy, $term_id ) = explode( '|', $term );
				$return[] = get_taxonomy( $taxonomy )->labels->name;
			}

		}

		return array_unique( $return );

	}

	public function get_job_objects( $job ) {

		$job   = get_post( $job );
		$post  = get_post_meta( $job->ID, 'bbl_job_post', true );
		$terms = get_post_meta( $job->ID, 'bbl_job_term', false );
		$meta  = get_post_meta( $job->ID, 'bbl_job_meta', false );
		$lang  = $this->get_job_language( $job );

		$return = array();

		if ( !empty( $post ) ) {
			list( $post_type, $post_id ) = explode( '|', $post );
			# @TODO in theory a translation job could actually include more than one post.
			# we should implement this earlier rather than later to save potential headaches down the road.
			$return['post'] = get_post( $post_id );
		}

		if ( !empty( $terms ) ) {

			foreach ( $terms as $term ) {
				list( $taxonomy, $term_id ) = explode( '|', $term );
				$return['terms'][$taxonomy][] = get_term( $term_id, $taxonomy );
			}

		}

		if ( !empty( $meta ) ) {

			$post = get_post_meta( $job->ID, 'bbl_job_post', true );
			list( $post_type, $post_id ) = explode( '|', $post );
			$post = get_post( $post_id );

			foreach ( $this->get_post_meta_to_translate( $post, $lang->code ) as $meta_key => $meta_field ) {
				$return['meta'][$meta_key] = $meta_field;
			}

		}

		return $return;

	}

	/**
	 * Create empty translations of a post for all languages. Called via WP-Cron on the `babble_create_empty_translation` hook.
	 *
	 * @param  array  $args Args array containing a `post_id` element.
	 */
	public function create_empty_translation( array $args ) {
		global $bbl_post_public;

		if ( !$post = get_post( $args['post_id'] ) ) {
			return;
		}

		foreach ( bbl_get_active_langs() as $lang ) {

			if ( !$trans = $bbl_post_public->get_post_in_lang( $post, $lang->code, false ) ) {
				$trans = $bbl_post_public->initialise_translation( $post, $lang->code );
			}

			$post_data = array(
				'ID'          => $trans->ID,
				'post_status' => $post->post_status,
				'post_name'   => $post->post_name,
			);

			$this->no_recursion = true;
			wp_update_post( $post_data, true );
			$this->no_recursion = false;

		}

	}

	/**
	 * Create some translation jobs.
	 *
	 * @param int $post_id The ID of the post to create translation jobs for
	 * @param array $lang_codes The language codes to create translation jobs of this post for
	 * @return array An array of Translation Job post IDs
	 **/
	public function create_post_jobs( $post_id, array $lang_codes ) {
		$post = get_post( $post_id );

		// @TODO Validate that the $post is in the default language, otherwise fail

		$jobs = array();
		foreach ( $lang_codes as $lang_code ) {

			if ( bbl_get_default_lang_code() == $lang_code )
				continue;

			if ( apply_filters( 'bbl_create_empty_translation', false, $post ) ) {

				wp_schedule_single_event( time(), 'babble_create_empty_translation', array(
					array(
						'post_id' => $post->ID,
					)
				) );

			}

			$this->no_recursion = true;
			$job = wp_insert_post( array(
				'post_type'   => 'bbl_job',
				'post_status' => 'new',
				'post_author' => get_current_user_id(),
				'post_title'  => get_the_title( $post ),
			) );
			$this->no_recursion = false;
			// @TODO If a translation already exists, populate the translation job with the translation
			$jobs[] = $job;

			add_post_meta( $job, 'bbl_job_post', "{$post->post_type}|{$post->ID}", true );
			wp_set_object_terms( $job, $lang_code, 'bbl_job_language' );

			foreach ( $this->get_post_terms_to_translate( $post, $lang_code ) as $taxo => $terms ) {
				foreach ( $terms as $term_id => $term )
					add_post_meta( $job, 'bbl_job_term', "{$taxo}|{$term_id}", false );
			}

			foreach ( $this->get_post_meta_to_translate( $post, $lang_code ) as $key => $field ) {
				add_post_meta( $job, 'bbl_job_meta', $key, false );
			}

		}

		return $jobs;
	}

	public function get_post_terms_to_translate( $post_id, $lang_code ) {

		$post        = get_post( $post_id );
		$taxos       = get_object_taxonomies( $post->post_type );
		$trans_terms = array();

		foreach ( $taxos as $key => $taxo ) {

			if ( !bbl_is_translated_taxonomy( $taxo ) )
				continue;

			$terms = get_the_terms( $post, $taxo );

			if ( empty( $terms ) )
				continue;

			foreach ( $terms as $term ) {

				$trans = bbl_get_term_translations( $term->term_id, $term->taxonomy );

				if ( !isset( $trans[$lang_code] ) )
					$trans_terms[$taxo][$term->term_id] = $term;

			}

		}

		return $trans_terms;

	}

	/**
	 * Return an array of a post's meta fields which are to be translated. The array keys are the post meta keys and the
	 * array values are the meta value for that key.
	 *
	 * @param  WP_Post $post_id  A post object.
	 * @param  string $lang_code The language code for the translation job for this post.
	 * @return array             An array of post meta keys which should be translated for this post.
	 */
	public function get_post_meta_to_translate( WP_Post $post, $lang_code ) {
		$meta = get_post_meta( $post->ID );

		if ( empty( $meta ) ) {
			return array();
		}

		$fields = $this->get_translated_meta_fields( $post );

		return array_intersect_key( $fields, $meta );
	}

	/**
	 * Return an array of meta field keys which should be translated. Array contains `Babble_Meta_Field` objects with
	 * the meta keys as the array keys.
	 *
	 * @param  WP_Post             A post object.
	 * @return Babble_Meta_Field[] An array of Babble meta field handlers.
	 */
	public function get_translated_meta_fields( WP_Post $post ) {
		$fields = (array) apply_filters( 'bbl_translated_meta_fields', array(), $post );

		foreach ( $fields as $key => $field ) {
			if ( ! ( $field instanceof Babble_Meta_Field ) ) {
				unset( $fields[ $key ] );
			}
		}

		return $fields;
	}

}

global $bbl_jobs;
$bbl_jobs = new Babble_Jobs();
