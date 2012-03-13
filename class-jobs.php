<?php

/**
 * Class for handling jobs for the various language
 * translation teams.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Job extends Babble_Plugin {
    
	/**
	 * A version number used for cachebusting, rewrite rule
	 * flushing, etc.
	 *
	 * @var int
	 **/
	protected $version;

    public function __construct() {
        $this->setup( 'babble-job', 'plugin' );
        
        $this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'admin_init' );
		$this->add_action( 'add_meta_boxes' );
		
		$this->version = 1;
    }

	/**
	 * Hooks the WP admin_init action to enqueue some stuff.
	 *
	 * @return void
	 **/
	public function admin_init() {
		wp_enqueue_style( 'bbl-jobs-admin', $this->url( 'css/jobs-admin.css' ), array(), $this->version );
	}

	/**
	 * undocumented function
	 *
	 * @param  
	 * @return void
	 **/
	public function add_meta_boxes( $post_type ) {
		if ( 'bbl_job' != $post_type )
			return;
		remove_meta_box( 'submitdiv', 'bbl_job', 'side' );
		// add_meta_box( 'bbl_job_save', __( 'Save', 'babble' ), array( & $this, 'metabox_save' ), 'bbl_job', 'side' );
		add_meta_box( 'bbl_job_status', __( 'Status', 'babble' ), array( & $this, 'metabox_status' ), 'bbl_job', 'side' );
		add_meta_box( 'bbl_job_detail', __( 'Details', 'babble' ), array( & $this, 'metabox_detail' ), 'bbl_job', 'normal' );
		// add_meta_box( 'bbl_job_comments', __( 'Details', 'babble' ), array( & $this, 'metabox_status' ), 'bbl_job', 'side' );
	}

    /**
     * Hooks the WP init action early to register the
     * job post_type.
     *
     * @return void
     **/
    public function init_early() {
        $labels = array(
            'name' => _x( 'Jobs', 'translation jobs general name', 'babble' ),
            'singular_name' => _x( 'Job', 'translation jobs singular name', 'babble' ),
            'add_new' => _x( 'Add New', 'translation job', 'babble' ),
            'add_new_item' => _x( 'Create New Job', 'translation job', 'babble' ),
            'edit_item' => _x( 'Edit Job', 'translation job', 'babble' ),
            'new_item' => _x( 'New Job', 'translation job', 'babble' ),
            'view_item' => _x( 'View Job', 'translation job', 'babble' ),
            'search_items' => _x( 'Search Jobs', 'translation job', 'babble' ),
            'not_found' => _x( 'No jobs found.', 'translation job', 'babble' ),
            'not_found_in_trash' => _x( 'No jobs found in Trash.', 'translation job', 'babble' ),
            'all_items' => _x( 'All Jobs', 'translation job', 'babble' ),
        );
        $args = array(
			'description' => __( 'Content, both posts and taxonomy terms, which need to be translated.', 'babble' ),
            'public' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
			'show_in_menu' => true,
            'query_var' => false,
            'labels' => $labels,
            'can_export' => true,
			'supports' => array( 'title' ),
        );
		register_post_type( 'bbl_job', $args );
		$labels = array(
			'name' => _x( 'Statuses', 'statuses of translation jobs, general name', 'babble' ),
			'singular_name' => _x( 'Status', 'statuses of translation jobs, singular name', 'babble' ),
			'search_items' => _x( 'Status', 'status of translation jobs', 'babble' ),
			'popular_items' => _x( 'Popular Statuses', 'status of translation jobs', 'babble' ),
			'all_items' => _x( 'All Statuses', 'status of translation jobs', 'babble' ),
			'edit_item' => _x( 'Edit Status', 'status of translation jobs', 'babble' ),
			'view_item' => _x( 'View Status', 'status of translation jobs', 'babble' ),
			'update_item' => _x( 'Update Status', 'status of translation jobs', 'babble' ),
			'add_new_item' => _x( 'Add New Status', 'status of translation jobs', 'babble' ),
			'new_item_name' => _x( 'New Status Name', 'status of translation jobs', 'babble' ),
			'separate_items_with_commas' => _x( 'Separate statuses with commas', 'status of translation jobs', 'babble' ),
			'add_or_remove_items' => _x( 'Add or remove statuses', 'status of translation jobs', 'babble' ),
			'choose_from_most_used' => _x( 'Choose from the most used statuses', 'status of translation jobs', 'babble' ),
		);
		$args = array(
			'query_var' => false,
			'show_ui' => false,
			'labels' => $labels,
			'hierarchical' => true,
		);
		register_taxonomy( 'bbl_jobs_status', array( 'bbl_job' ), $args );
		$labels = array(
			'name' => _x( 'Languages', 'language for translation jobs, general name', 'babble' ),
			'singular_name' => _x( 'Language', 'language for translation jobs, general name', 'babble' ),
			'search_items' => _x( 'Language', 'language for translation jobs, general name', 'babble' ),
			'popular_items' => _x( 'Popular Languages', 'language for translation jobs, general name', 'babble' ),
			'all_items' => _x( 'All Languages', 'language for translation jobs, general name', 'babble' ),
			'edit_item' => _x( 'Edit Language', 'language for translation jobs, general name', 'babble' ),
			'view_item' => _x( 'View Language', 'language for translation jobs, general name', 'babble' ),
			'update_item' => _x( 'Update Language', 'language for translation jobs, general name', 'babble' ),
			'add_new_item' => _x( 'Add New Language', 'language for translation jobs, general name', 'babble' ),
			'new_item_name' => _x( 'New Language Name', 'language for translation jobs, general name', 'babble' ),
			'separate_items_with_commas' => _x( 'Separate languages with commas', 'language for translation jobs, general name', 'babble' ),
			'add_or_remove_items' => _x( 'Add or remove languages', 'language for translation jobs, general name', 'babble' ),
			'choose_from_most_used' => _x( 'Choose from the most used languages', 'language for translation jobs, general name', 'babble' ),
		);
		$args = array(
			'query_var' => false,
			'show_ui' => false,
			'labels' => $labels,
			'hierarchical' => true,
		);
		register_taxonomy( 'bbl_jobs_language', array( 'bbl_job' ), $args );
    }

	// CALLBACKS
	// =========

	/**
	 * Callback function to provide the HTML for the metabox.
	 *
	 * @param object $post The Post object
	 * @param array $metabox The metabox arguments
	 * @return void
	 **/
	public function metabox_save() {
		?><div !id="publishing-action"><?php
		submit_button( __( 'Save' ), 'primary', 'publish', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) );
		?></div><?php
	}

	/**
	 * Callback function to provide the HTML for the metabox.
	 *
	 * @param object $post The Post object
	 * @param array $metabox The metabox arguments
	 * @return void
	 **/
	public function metabox_status() {
		$langs = bbl_get_active_langs();
		
		// var_dump( $langs );
		// return;
		// $lang_terms = wp_get_object_terms( get_the_ID(), 'bbl_jobs_language' );
		// var_dump( $lang_terms );
		// return;
		?>
		<p>
			<label for="tax_input_bbl_jobs_language">Language: 
			<select name="tax_input[bbl_jobs_language][]" id="tax_input_bbl_jobs_language">
		<?php
		foreach ( $langs as $lang ) {
			$slug = 'job-lang-' . sanitize_title( strtolower( $lang->code ) );
			if ( ! term_exists( $slug, 'bbl_jobs_language' ) ) {
				$result = wp_insert_term( $lang->names, 'bbl_jobs_language', array( 'slug' => $slug ) );
				if ( is_wp_error( $result ) )
					throw new exception( print_r( $result, true ) );
				$term = get_term( $result[ 'term_id' ], 'bbl_jobs_language' );
			} else {
				$term = get_term_by( 'slug', $slug, 'bbl_jobs_language' );
			}
			?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( has_term( $term->term_id, 'bbl_jobs_language' ) ); ?>><?php echo $term->name; echo " " . (int) selected( has_term( $term->term_id, 'bbl_jobs_language' ) );
				 ?> </option>
			<?php
		}
		?>	</select></label>
		</p>
		<?php
		
		$statuses = array(
			'open' => 'Open',
			'closed' => 'Closed',
		);
		?>
		<ul id="bbl_jobs_statuschecklist" class="list:bbl_jobs_status categorychecklist form-no-clear">
		<?php 
		foreach ( $statuses as $slug=> $name ) {
			if ( ! term_exists( $slug, 'bbl_jobs_status' ) ) {
				$result = wp_insert_term( $name, 'bbl_jobs_status', array( 'slug' => $slug ) );
				if ( is_wp_error( $result ) )
					throw new exception( print_r( $result, true ) );
				$term = get_term( $result[ 'term_id' ], 'bbl_jobs_status' );
			} else {
				$term = get_term_by( 'slug', $slug, 'bbl_jobs_status' );
			}
		?>
			<li id="bbl_jobs_status-<?php echo esc_attr( $term->term_id ); ?>"><label class="selectit"><input value="<?php echo esc_attr( $term->term_id ); ?>" type="radio" name="tax_input[bbl_jobs_status][]" id="in-bbl_jobs_status-<?php echo esc_attr( $term->term_id ); ?>" <?php checked( has_term( $term->term_id, 'bbl_jobs_status' ) ); ?> /> <?php echo $term->name ?></label></li>
		<?php
		}
		?>
		
		</ul>
		<div id="major-publishing-actions">
		<?php do_action('post_submitbox_start'); ?>

		<div id="publishing-action">
			<input name="save" type="submit" class="button-primary" id="save" tabindex="5" accesskey="p" value="<?php esc_attr_e('Save') ?>" />
		</div>
		<div class="clear"></div>
		</div>
<?php
	}

	/**
	 * Callback function to provide the HTML for the metabox.
	 *
	 * @param object $post The Post object
	 * @param array $metabox The metabox arguments
	 * @return void
	 **/
	public function metabox_detail() {
		
	}
    
    // PUBLIC METHODS
    // ==============

    
    
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

global $bbl_job;
$bbl_job = new Babble_Job();

?>