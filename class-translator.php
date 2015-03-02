<?php
/**
 * Class for managing the translator role and associated caps
 *
 * @package Babble
 * @since 1.4
 */
class Babble_Translator extends Babble_Plugin {

	/**
	 * A version number used for cachebusting, rewrite rule
	 * flushing, etc.
	 *
	 * @var float
	 **/
	protected $version;

    public function __construct() {
        $this->setup( 'babble-translator', 'plugin' );

        add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		$this->version = 1;
    }

	/**
	 * Called by admin_init, this method ensures we are all up to date and
	 * so on.
	 *
	 * @return void
	 **/
	public function maybe_upgrade() {

		# @TODO should we amalgamate each class' version numbers into one?
		$option    = 'bbl-translator-version';
		$role_name = _x( 'Translator', 'Translator role', 'babble' );

		switch ( get_option( $option, 0 ) ) {

			case 0:

				if ( !$role = get_role( 'translator' ) )
					$role = add_role( 'translator', $role_name );

				$role->add_cap( 'read' );
				$role->add_cap( 'edit_bbl_jobs' );
				$role->add_cap( 'edit_others_bbl_jobs' );
				$role->add_cap( 'edit_published_bbl_jobs' );
				$role->add_cap( 'edit_private_bbl_jobs' );
				$role->add_cap( 'publish_bbl_jobs' );
				$role->add_cap( 'delete_bbl_jobs' );
				$role->add_cap( 'delete_others_bbl_jobs' );
				$role->add_cap( 'delete_published_bbl_jobs' );
				$role->add_cap( 'delete_private_bbl_jobs' );

				update_option( $option, $this->version );
				break;

		}

	}

}

global $bbl_translator;
$bbl_translator = new Babble_translator();
