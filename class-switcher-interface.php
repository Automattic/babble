<?php

/**
 * Class for providing the user interface switching option on the user profile screen
 *
 * @package Babble
 * @since 1.4
 */
class Babble_Switcher_Interface extends Babble_Plugin {

	// PUBLIC METHODS
	// ==============

	public function __construct() {
		$this->setup( 'babble-switcher-interface', 'plugin' );

		add_action( 'personal_options', array( $this, 'personal_options' ) );
	}

	public function personal_options( WP_User $user ) {
		$langs   = bbl_get_active_langs();
		$current = bbl_get_current_interface_lang_code();

		if ( empty( $langs ) )
			return;

		$vars = compact( 'langs', 'current' );
		$this->render_admin( 'switcher-interface.php', $vars );

	}

}

global $bbl_switcher_interface;
$bbl_switcher_interface = new Babble_Switcher_Interface;
