<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI_Item_Theme' ) ) :

/**
 * EUAPI theme item. A simple container for theme information, usually fetched priorly via
 * file headers or an external source.
 */
class EUAPI_Item_Theme extends EUAPI_Item {

	public $type = 'theme';

	public function __construct( $theme, array $data ) {

		$this->file    = $theme;
		$this->url     = $data['ThemeURI'];
		$this->version = $data['Version'];
		$this->data    = $data;

	}

}

endif;
