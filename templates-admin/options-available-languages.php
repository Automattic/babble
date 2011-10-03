<div class="wrap">
<div id="icon-tools" class="icon32"><br></div><h2><?php _e( 'Available Languages', 'babble' ); ?></h2>

<form action="" method="post">

<?php wp_nonce_field( 'babble_available_languages', '_babble_nonce' ); ?>

<p><?php _e( 'Please select the languages you wish to translate this site into, and select "Save Changes" below the languages table.' ); ?></p>

<table class="wp-list-table widefat fixed babble_languages" cellspacing="0">
	<thead>
	<tr>
		<th scope="col" id="cb" class="manage-column column-cb check-column">
			<label class="screen-reader-text" for="header_check_all">
				<?php _e( 'Active', 'babble' ); ?>
			</label>
			<input type="checkbox" id="header_check_all">
		</th>
		<th scope="col" id="language" class="manage-column column-language-code"><?php _e( 'Code', 'babble' ); ?></th>
		<th scope="col" id="language" class="manage-column column-language"><?php _e( 'Name(s)', 'babble' ); ?></th>
		<th scope="col" id="display_name" class="manage-column column-display_name"><?php _e( 'Display Name', 'babble' ); ?></th>
		<th scope="col" id="url_prefix" class="manage-column column-url_prefix"><?php _e( 'URL Prefix', 'babble' ); ?></th>
		<th scope="col" id="text_direction" class="manage-column column-text_direction"><?php _e( 'Text Direction', 'babble' ); ?></th>
	</thead>

	<tfoot>
	<tr>
		<th scope="col" class="manage-column column-cb check-column">
			<label class="screen-reader-text" for="footer_check_all">
				<?php _e( 'Active', 'babble' ); ?>
			</label>
			<input type="checkbox" id="header_check_all">
		</th>
		<th scope="col" class="manage-column column-language-code"><?php _e( 'Code', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-language"><?php _e( 'Name(s)', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-display_name"><?php _e( 'Display Name', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-url_prefix"><?php _e( 'URL Prefix', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-text_direction"><?php _e( 'Text Direction', 'babble' ); ?></th>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:babble_languages">
		<?php foreach ( $langs as $lang ) : ?>
		<tr id="language-<?php echo esc_attr( $lang[ 'code' ] ); ?>">
			<th scope="row" class="manage-column column-cb check-column"><input type="checkbox" name="enable_langs[]" id="enable_<?php echo esc_attr( $lang[ 'code' ] ); ?>" <?php checked( false ); ?>></th>
			<td scope="col" class="manage-column column-language-code">
				<label for="enable_<?php echo esc_attr( $lang[ 'code' ] ); ?>" title="<?php echo esc_attr( sprintf( __( 'Enable "%s" on this site', 'babble' ), $lang[ 'names' ] ) ); ?>">
					<?php echo esc_html( $lang[ 'code' ] ); ?>
				</label>
			</td>
			<td scope="col" class="manage-column column-language">
				<label for="enable_<?php echo esc_attr( $lang[ 'code' ] ); ?>" title="<?php echo esc_attr( sprintf( __( 'Enable "%s" on this site', 'babble' ), $lang[ 'names' ] ) ); ?>">
					<?php echo esc_html( $lang[ 'names' ] ); ?>
				</label>
			</td>
			<td scope="col" class="manage-column column-display_name">
				<label class="screen-reader-text" for="display_name_<?php echo esc_attr( $lang[ 'code' ] ); ?>"><?php echo esc_html( sprintf( __( 'Display name for "%s"', 'babble' ), $lang[ 'names' ] ) ); ?></label>
				<input type="text" name="display_name_<?php echo esc_attr( $lang[ 'code' ] ); ?>" value="<?php echo esc_attr( $lang[ 'display_name' ] ); ?>" id="display_name_<?php echo esc_attr( $lang[ 'code' ] ); ?>" class="<?php echo esc_attr( $lang[ 'input_lang_class' ] ); ?>">
			</td>
			<td scope="col" class="manage-column column-url_prefix">
				<label class="screen-reader-text" for="url_prefix_<?php echo esc_attr( $lang[ 'code' ] ); ?>"><?php echo esc_html( sprintf( __( 'URL prefix for "%s"', 'babble' ), $lang[ 'names' ] ) ); ?></label>
				<input type="text" name="url_prefix_<?php echo esc_attr( $lang[ 'code' ] ); ?>" value="<?php echo esc_attr( $lang[ 'url_prefix' ] ); ?>" id="url_prefix_<?php echo esc_attr( $lang[ 'code' ] ); ?>" class="small-text">
			</td>
			<td scope="col" class="manage-column column-text_direction">
				<label class="screen-reader-text" for="text_direction_<?php echo esc_attr( $lang[ 'code' ] ); ?>"><?php echo esc_html( sprintf( __( 'Text direction for %s', 'babble' ), $lang[ 'code' ] ) ); ?></label>
				<select name="text_direction_<?php echo esc_attr( $lang[ 'code' ] ); ?>" id="text_direction_<?php echo esc_attr( $lang[ 'code' ] ); ?>">
					<option value="ltr" <?php selected( $lang[ 'text_direction' ], 'ltr' ); ?>><?php _e( 'Left to right', 'babble' ); ?></option>
					<option value="rtl" <?php selected( $lang[ 'text_direction' ], 'rtl' ); ?>><?php _e( 'Right to left', 'babble' ); ?></option>
				</select>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php submit_button(); ?>

</form>

</div>