<?php

/**
 * Class for providing the user interface switching option on the user profile screen
 *
 * @package Babble
 * @since 1.3
 */
class Babble_Switcher_Interface {
	
	// PUBLIC METHODS
	// ==============
	
	public function __construct() {
		add_action( 'personal_options', array( $this, 'action_personal_options' ), 1 );
	}

	public function action_personal_options ( WP_User $user ) {
		$langs   = bbl_get_active_langs();
		$current = bbl_get_current_interface_lang_code();

		if ( empty( $langs ) )
			return;
		?>
		<tr>
			<th scope="row"><?php _e( 'Interface Language', 'babble' ); ?></th>
			<td><select name="interface_lang">
				<?php foreach ( $langs as $lang ) { ?>
					<option value="<?php echo esc_attr( $lang->code ); ?>" <?php selected( $lang->code, $current ); ?>><?php echo esc_html( $lang->display_name ); ?></option>
				<?php } ?>
			</select></td>
		</tr>
		<?php
	}

}

global $bbl_switcher_interface;
$bbl_switcher_interface = new Babble_Switcher_Interface;
