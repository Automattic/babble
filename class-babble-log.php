<?php

/**
 *
 *
 * @package Babble
 * @since Alpha 1
 */
class Babble_Log {

	/**
	 * Whether to log or not.
	 *
	 * @var boolean
	 **/
	public $logging = false;

	/**
	 * A unique ID so we can identify different sessions in
	 * the error log.
	 *
	 * @var string
	 **/
	protected $session;

	/**
	 * Construction time!
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->session = uniqid();
	}

	/**
	 * Hooks the WP admin_init action
	 *
	 * @return void
	 **/
	public function log( $msg ) {
		if ( $this->logging && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[$this->session] BABBLE LOG: $msg" );
		}
	}

}

global $bbl_log;
$bbl_log = new Babble_Log();
