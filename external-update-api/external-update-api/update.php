<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI_Update' ) ) :

/**
 * EUAPI update item. Contains information about an available update.
 */
class EUAPI_Update {

	public function __construct( array $args ) {

		$this->slug           = $args['slug'];
		$this->new_version    = $args['new_version'];
		$this->upgrade_notice = $args['upgrade_notice'];
		$this->url            = $args['url'];
		$this->package        = $args['package'];

	}

	public function get_data_to_store() {
		return get_object_vars( $this );
	}

	public function get_new_version() {
		return $this->new_version;
	}

}

endif;
