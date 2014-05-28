<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI_Info' ) ) :

/**
 * EUAPI Info class.
 */
class EUAPI_Info {

	public $external = true;

	public function __construct( array $args ) {

		foreach ( $args as $k => $v ) {
			$this->$k = $v;
		}

	}

}

endif;
