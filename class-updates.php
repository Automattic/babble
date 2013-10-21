<?php

/**
 * Class for providing updates to Babble via GitHub rather than wordpress.org
 *
 * @package Babble
 * @since 1.4.1
 */
class Babble_Updates extends Babble_Plugin {
	
	public function __construct() {
		$this->setup( 'babble-updates', 'plugin' );

		$this->add_action( 'plugins_loaded' );
		$this->add_filter( 'euapi_plugin_handler', null, 10, 2 );
	}

	/**
	 * Include the EUAPI if it's not already present.
	 */
	public function plugins_loaded() {
		if ( !class_exists( 'EUAPI' ) )
			include_once dirname( __FILE__ ) . '/external-update-api/external-update-api.php';
	}

	/**
	 * Hooks into the EUAPI update mechanism and tells it to fetch Babble updates from GitHub.
	 *
	 * @param  EUAPI_Handler|null $handler Usually null. Can be an EUAPI_Handler object if one has been set.
	 * @param  EUAPI_Item         $item    An EUAPI_Item for the current plugin.
	 * @return EUAPI_Handler|null          An EUAPI_Handler if we're overriding updates for this plugin, null if not.
	 */
	public function euapi_plugin_handler( EUAPI_Handler $handler = null, EUAPI_Item $item ) {
		if ( 'http://babbleplugin.com/' == $item->url ) {

			$handler = new EUAPI_Handler_GitHub( array(
				'type'       => $item->type,
				'file'       => $item->file,
				'github_url' => 'https://github.com/cftp/babble',
				'sslverify'  => false,
			) );

		}

		return $handler;
	}

}

global $bbl_updates;
$bbl_updates = new Babble_Updates;
