<?php
/**
 * Class for handling jobs for the various language
 * translation teams.
 *
 * @package Babble
 * @since 1.3
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
		
		$this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'admin_init' );
		$this->add_action( 'edit_form_after_title' );
		$this->add_action( 'add_meta_boxes' );
		$this->add_action( 'bbl_translation_post_meta_boxes', null, 10, 3 );
		$this->add_action( 'bbl_translation_terms_meta_boxes', null, 10, 2 );
		$this->add_action( 'bbl_translation_submit_meta_boxes', null, 10, 2 );
		$this->add_action( 'save_post', null, null, 2 );
		$this->add_action( 'save_post', 'save_job', null, 2 );
		$this->add_action( 'manage_bbl_job_posts_custom_column', 'action_column', null, 2 );
		$this->add_action( 'add_meta_boxes_bbl_job', null, 999 );
		$this->add_action( 'load-post.php', 'load_post_edit' );

		$this->add_filter( 'manage_bbl_job_posts_columns', 'filter_columns' );
		$this->add_filter( 'bbl_translated_post_type', null, null, 2 );
		$this->add_filter( 'bbl_translated_taxonomy', null, null, 2 );
		$this->add_filter( 'post_updated_messages' );
		$this->add_filter( 'wp_insert_post_empty_content', null, null, 2 );
		$this->add_filter( 'admin_title', null, null, 2 );

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
		# @TODO use filemtime everywhere
		wp_enqueue_style( 'bbl-jobs-admin', $this->url( 'css/jobs-admin.css' ), array(), $this->version );
	}

	/**
	 * Hooks the WP action load-post.php to detect people
	 * trying to edit translation posts, and instead kick 
	 * redirect them to an existing translation job or
	 * create a translation job and direct them to that.
	 *
	 * @action load-post.php
	 * 
	 * @return void
	 **/
	public function load_post_edit() {
		$screen = get_current_screen();
		$post = get_post( absint( $_GET[ 'post' ] ) );
		if ( ! bbl_is_translated_post_type( $post->post_type ) )
			return;
		if ( bbl_get_default_lang_code() == bbl_get_post_lang_code( $post ) )
			return;
		// Check capabilities include editing a translation post
		// - If not, the button shouldn't be on the Admin Bar
		// - But we also need to not process at this point
		// @TODO Check for an existing translation job, not completed
		// and redirect to that if it exists.
		
		// Create a new translation job for the current language
		$lang_codes = (array) bbl_get_post_lang_code( $post );
		$jobs = $this->create_translation_jobs( $post, $lang_codes );
		// Redirect to the translation job
		$url = get_edit_post_link( $jobs[0], 'url' );
		wp_redirect( $url );
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
			$lang = $this->get_job_language( $job );
			$admin_title = sprintf( $pto->labels->edit_item_context, $lang->display_name );
			$GLOBALS[ 'title' ] = $admin_title;
		}
		return $admin_title;
	}

	public function edit_form_after_title() {

		if ( 'bbl_job' != get_current_screen()->post_type )
			return;

		$job     = get_post();
		$objects = $this->get_job_objects( $job );
		$items   = array();

		if ( isset( $objects['post'] ) ) {

			$post = $objects['post'];
			$post_translation = get_post_meta( $job->ID, "bbl_post_{$post->ID}", true );
			if ( empty( $post_translation ) )
				$post_translation = array();

			$items['post'] = array(
				'original'    => $post,
				'translation' => (object) $post_translation,
			);

		}

		if ( isset( $objects['terms'] ) ) {

			foreach ( $objects['terms'] as $taxo => $terms ) {

				foreach ( $terms as $term ) {

					$term_translation = get_post_meta( $job->ID, "bbl_term_{$term->term_id}", true );
					if ( empty( $term_translation ) )
						$term_translation = array();

					$items['terms'][$taxo][] = array(
						'original'    => $term,
						'translation' => (object) $term_translation,
					);

				}

			}

		}

		$vars = array(
			'job'   => $job,
			'items' => $items,
		);

		$this->render_admin( 'translation-editor.php', $vars );

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

	public function bbl_translation_submit_meta_boxes( $type, $job ) {

		add_meta_box( 'bbl_job_submit', __( 'Save Translation' , 'babble'), array( $this, 'metabox_translation_submit' ), $type, 'submit' );

	}

	public function metabox_translation_terms( array $items ) {

		$vars = $items;

		$this->render_admin( 'translation-editor-terms.php', $vars );

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

		# create / update post trans if necessary
		# create / update term trans if necessary

		$post_nonce  = isset( $_POST[ '_bbl_translation_editor_post' ] ) ? $_POST[ '_bbl_translation_editor_post' ] : false;
		$terms_nonce = isset( $_POST[ '_bbl_translation_editor_terms' ] ) ? $_POST[ '_bbl_translation_editor_terms' ] : false;
		$language    = get_the_terms( $job, 'bbl_job_language' );

		if ( empty( $language ) )
			return false;
		else
			$lang = reset( $language )->name;

		if ( $post_nonce and wp_verify_nonce( $post_nonce, "bbl_translation_editor_post_{$job->ID}") ) {

			$post_data = stripslashes_deep( $_POST['bbl_translation']['post'] );
			$post_info = get_post_meta( $job->ID, 'bbl_job_post', true );
			list( $post_type, $post_id ) = explode( '|', $post_info );
			$post = get_post( $post_id );

			update_post_meta( $job->ID, "bbl_post_{$post_id}", $post_data );

			if ( 'complete' == $job->post_status ) {

				if ( !$trans = $bbl_post_public->get_post_in_lang( $post, $lang, false ) )
					$trans = $bbl_post_public->initialise_translation( $post, $lang );

				$post_data['ID']          = $trans->ID;
				$post_data['post_status'] = $post->post_status;

				$this->no_recursion = true;
				wp_update_post( $post_data, true );
				$this->no_recursion = false;

			}

		}

		if ( $terms_nonce and wp_verify_nonce( $terms_nonce, "bbl_translation_editor_terms_{$job->ID}") ) {

			$terms_data = stripslashes_deep( $_POST['bbl_translation']['terms'] );
			$terms      = get_post_meta( $job->ID, 'bbl_job_term', false );

			foreach ( $terms as $term_info ) {

				list( $taxo, $term_id ) = explode( '|', $term_info );
				$term = get_term( $term_id, $taxo );

				update_post_meta( $job->ID, "bbl_term_{$term_id}", $terms_data[$term_id] );

				if ( 'complete' == $job->post_status ) {

					$trans = $bbl_taxonomies->get_term_in_lang( $term, $taxo, $lang, false );
					if ( !$trans )
						$trans = $bbl_taxonomies->initialise_translation( $term, $taxo, $lang );

					$terms_data[$term->term_id]['term_id'] = $trans->term_id;

					$args = array(
						'name' => $terms_data[$term->term_id]['name'],
						'slug' => '',
					);
					$update = wp_update_term( absint( $trans->term_id ), $trans->taxonomy, $args );

				}

			}

		}


	}

	public function save_post( $post_id, WP_Post $post ) {

		if ( $this->no_recursion )
			return;
		if ( !bbl_is_translated_post_type( $post->post_type ) )
			return;

		$nonce    = isset( $_POST[ '_bbl_ready_for_translation' ] ) ? $_POST[ '_bbl_ready_for_translation' ] : false;
		$ready_id = isset( $_POST[ 'babble_ready_for_translation' ] ) ? $_POST[ 'babble_ready_for_translation' ] : 0;

		if ( !$nonce )
			return;
		if ( $ready_id != $post->ID )
			return;

		# @TODO this should be wp_verify_nonce() with a return instead of check_admin_referer()
		check_admin_referer( "bbl_ready_for_translation-{$post->ID}", '_bbl_ready_for_translation' );

		$langs       = bbl_get_active_langs();
		$lang_codes  = wp_list_pluck( $langs, 'code' );
		$this->create_translation_jobs( $post->ID, $lang_codes );
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
			'edit_item'          => _x( 'Edit Translation Job', 'translation job', 'babble' ),
			'edit_item_context'  => _x( 'Edit %s Translation Job', 'translation job; e.g. "Edit French Translation Job"', 'babble' ),
			'new_item'           => _x( 'New Job', 'translation job', 'babble' ),
			'view_item'          => _x( 'View Job', 'translation job', 'babble' ),
			'search_items'       => _x( 'Search Jobs', 'translation job', 'babble' ),
			'not_found'          => _x( 'No translation jobs found.', 'translation job', 'babble' ),
			'not_found_in_trash' => _x( 'No translation jobs found in Trash.', 'translation job', 'babble' ),
			'all_items'          => _x( 'All Translation Jobs', 'translation job', 'babble' ),
		);
		$args = array(
			'description'        => __( 'Content, both posts and taxonomy terms, which need to be translated.', 'babble' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => false,
			'labels'             => $labels,
			'can_export'         => true,
			'supports'           => false,
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
			'show_ui' => false
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

		# @TODO cap checks

		$langs   = bbl_get_active_langs();
		$trans   = bbl_get_post_translations( $post );
		$jobs    = $this->get_post_jobs( $post );
		$default = bbl_get_default_lang_code();

		unset( $trans[$default] );

		#foreach ( $jobs as $job_lang => $job ) {
		#	if ( 'complete' != get_post_status( $job ) )
		#		unset( $trans[$job_lang] );
		#}

		if ( !empty( $trans ) ) {

			if ( !empty( $jobs ) ) {
				?><h4><?php _e( 'Complete:', 'babble' ); ?></h4><?php
			}

			foreach ( $trans as $lang_code => $translation ) {
				$lang = bbl_get_lang( $lang_code );
				?>
				<p><?php printf( '%s: <a href="%s">%s</a>', $lang->display_name, get_edit_post_link( $translation->ID ), __( 'View', 'babble' ) ); ?>
				<?php
			}

		}

		if ( !empty( $jobs ) ) {

			?><h4><?php _e( 'Pending:', 'babble' ); ?></h4><?php
			foreach ( $jobs as $job ) {
				$lang = $this->get_job_language( $job );
				$status = get_post_status_object( $job->post_status );
				?>
				<p><?php printf( '%s (%s)', $lang->display_name, $status->label ); ?>
				<?php
			}

			# @TODO meta_key|value won't work here of course because they're not public query args.
			# implement a bbl_job_post|term|etc query var that adds a meta query.
			$args = array(
				'post_type'  => 'bbl_job',
				'meta_key'   => 'bbl_job_post',
				'meta_value' => "{$post->post_type}|{$post->ID}",
			);
			?>
			<p><a href="<?php echo add_query_arg( $args, admin_url( 'edit.php' ) ); ?>"><?php _e( 'View pending translation jobs &raquo;', 'babble' ); ?></a></p>
			<?php

		} else {

			wp_nonce_field( "bbl_ready_for_translation-{$post->ID}", '_bbl_ready_for_translation' );

			?>
			<p><label><input type="checkbox" name="babble_ready_for_translation" value="<?php echo absint( $post->ID ); ?>" /> <?php _e( 'Ready for translation', 'babble' ); ?></label></p>
			<?php

		}

	}

	// PUBLIC METHODS
	// ==============

	/**
	 * Return the array of jobs for a Post, keyed
	 * by lang code.
	 *
	 * @param object $post A WP Post object
	 * @return array An array of WP Translation Job Post objects 
	 */
	public function get_post_jobs( WP_Post $post ) {
		return $this->get_object_jobs( $post->ID, 'post', $post->post_type );
	}

	/**
	 * Return the array of jobs for a Term, keyed
	 * by lang code.
	 *
	 * @param object $post A WP Term object
	 * @return array An array of WP Translation Job Post objects 
	 */
	public function get_term_jobs( $term ) {
		return $this->get_object_jobs( $term->term_id, 'term', $term->taxonomy );
	}

	/**
	 * Return the array of jobs for a Term or Post, keyed
	 * by lang code.
	 *
	 * @param object $post A WP Term object
	 * @param string $type Either 'term' or 'post'
	 * @return array An array of WP Translation Job Post objects 
	 */
	public function get_object_jobs( $id, $type, $name ) {

		bbl_stop_translating(); # Yuck yuck yuck
		$jobs = get_posts( array(
			'post_type'      => 'bbl_job',
			'post_status'    => array(
				'new', 'in-progress'
			),
			'meta_key'       => "bbl_job_{$type}",
			'meta_value'     => "{$type}|{$id}",
			'posts_per_page' => -1,
		) );
		bbl_start_translating(); # kcuy kcuy kcuY

		if ( empty( $jobs ) )
			return array();

		$return = array();

		foreach ( $jobs as $job ) {
			$lang = $this->get_job_language( $job );
			$return[$lang->code] = $job;
		}

		return $return;

	}

	public function get_job_language( WP_Post $job ) {
		$languages = get_the_terms( $job, 'bbl_job_language' );
		if ( empty( $languages ) )
			return false;
		return bbl_get_lang( reset( $languages )->name );
	}

	public function get_job_type( WP_Post $job ) {

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

	public function get_job_objects( WP_Post $job ) {

		$post  = get_post_meta( $job->ID, 'bbl_job_post', true );
		$terms = get_post_meta( $job->ID, 'bbl_job_term', false );

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

		return $return;

	}

	/**
	 * Create some translation jobs.
	 *
	 * @param int $post_id The ID of the post to create translation jobs for
	 * @param array $lang_codes The language codes to create translation jobs of this post for
	 * @return array An array of Translation Job posts
	 **/
	public function create_translation_jobs( $post_id, $lang_codes ) {
		$post        = get_post( $post_id );
		$taxos       = get_object_taxonomies( $post->post_type );
		$trans_terms = array();

		foreach ( $taxos as $key => $taxo ) {

			if ( !bbl_is_translated_taxonomy( $taxo ) )
				continue;

			$terms = get_the_terms( $post, $taxo );

			if ( empty( $terms ) )
				continue;

			foreach ( $terms as $term )
				$trans_terms[$taxo][$term->term_id] = bbl_get_term_translations( $term->term_id, $term->taxonomy );

		}

		# @TODO individual language selection when marking post as translation ready – NOW DONE?

		$jobs = array();
		foreach ( $lang_codes as $lang_code ) {

			if ( bbl_get_default_lang_code() == $lang_code )
				continue;

			# @TODO abstract this:
			$this->no_recursion = true;
			$job = wp_insert_post( array(
				'post_type'   => 'bbl_job',
				'post_status' => 'new',
				'post_author' => get_current_user_id(),
				'post_title'  => get_the_title( $post ),
				// This post_name construction may need to change when we have multiple translation jobs per canonical post
				// Do we even need to set a post_name?
				'post_name'   => "job-{$lang_code}-{$post->post_name}", 
			) );
			$jobs[] = $job;
			$this->no_recursion = false;

			add_post_meta( $job, 'bbl_job_post', "{$post->post_type}|{$post->ID}", true );
			wp_set_object_terms( $job, $lang_code, 'bbl_job_language' );

			#$this->initialise_post_translation( $post, $lang_code );

			if ( empty( $trans_terms ) )
				continue;

			$objects = array();
			foreach ( $trans_terms as $taxo => $terms ) {
				foreach ( $terms as $term_id => $trans ) {
					if ( !isset( $trans[$lang_code] ) )
						add_post_meta( $job, 'bbl_job_term', "{$taxo}|{$term_id}", false );
				}
			}
		}
		return $jobs;
	}

	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Called by admin_init, this method ensures we are all up to date and 
	 * so on.
	 *
	 * @return void
	 **/
	protected function upgrade() {
		
	}

}

global $bbl_jobs;
$bbl_jobs = new Babble_Jobs();
