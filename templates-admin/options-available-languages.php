<?php 
	/**
	 * HTML template for the Available Languages screen in the admin area.
	 *
	 * @package Babble
	 * @subpackage Templates
	 * @since Alpha 1.1
	 */
 ?>
<div class="wrap">
<div id="icon-tools" class="icon32"><br></div><h2><?php _e( 'Available Languages', 'babble' ); ?></h2>

<form action="" method="post">

<?php 
	// @FIXME: This contains no element, like a post ID in a publish/update post nonce, which is unique to this request
	wp_nonce_field( 'babble_lang_prefs', '_babble_nonce' ); 
?>

<p><?php _e( 'Please select the languages you wish to translate this site into, you should select at least two, and select "Save Changes" below the languages table.' ); ?></p>

<p>
	<label for="default_lang"><?php _e( 'Default language:', 'babble' ); ?></label> 
	<select name="default_lang" id="default_lang">
		<?php foreach( $active_langs as $lang ) : ?>
			<option value="<?php echo esc_attr( $lang->code ); ?>" <?php selected( $lang->code, $default_lang ); ?>><?php echo esc_html( $lang->names ); ?></option>
		<?php endforeach; ?>
	</select>
</p>

<table class="wp-list-table widefat fixed babble_languages" cellspacing="0">
	<thead>
	<tr>
		<th scope="col" id="cb" class="manage-column column-cb check-column">
			<label class="screen-reader-text" for="header_check_all">
				<?php _e( 'Active', 'babble' ); ?>
			</label>
			<input type="checkbox" id="header_check_all">
		</th>
		<th scope="col" id="public" class="manage-column column-public"><?php _e( 'Public', 'babble' ); ?></th>
		<th scope="col" id="lang_code" class="manage-column column-language-code"><?php _e( 'Code', 'babble' ); ?></th>
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
		<th scope="col" class="manage-column column-public"><?php _e( 'Public', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-language-code"><?php _e( 'Code', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-language"><?php _e( 'Name(s)', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-display_name"><?php _e( 'Display Name', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-url_prefix"><?php _e( 'URL Prefix', 'babble' ); ?></th>
		<th scope="col" class="manage-column column-text_direction"><?php _e( 'Text Direction', 'babble' ); ?></th>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:babble_languages">
		<?php foreach ( $langs as $lang ) : ?>
		<tr id="language-<?php echo esc_attr( $lang->code ); ?>">
			<th scope="row" class="manage-column column-cb check-column"><input type="checkbox" name="active_langs[]" value="<?php echo esc_attr( $lang->code ); ?>" id="enable_<?php echo esc_attr( $lang->code ); ?>" <?php checked( $lang->active ); ?>></th>
			<td scope="col" class="manage-column column-public">
				<label for="public_<?php echo esc_attr( $lang->code ); ?>" title="<?php echo esc_attr( sprintf( __( 'Show "%s" on this site', 'babble' ), $lang->names ) ); ?>">
					<input type="checkbox" name="public_langs[]" value="<?php echo esc_attr( $lang->code ); ?>" id="public_<?php echo esc_attr( $lang->code ); ?>" <?php checked( in_array( $lang->code, $this->public_langs ) ); ?>>
				</label>
			</td>
			<td scope="col" class="manage-column column-language-code">
				<label for="enable_<?php echo esc_attr( $lang->code ); ?>" title="<?php echo esc_attr( sprintf( __( 'Enable "%s" on this site', 'babble' ), $lang->names ) ); ?>">
					<?php echo esc_html( $lang->code ); ?>
				</label>
			</td>
			<td scope="col" class="manage-column column-language">
				<label for="enable_<?php echo esc_attr( $lang->code ); ?>" title="<?php echo esc_attr( sprintf( __( 'Enable "%s" on this site', 'babble' ), $lang->names ) ); ?>">
					<?php echo esc_html( $lang->names ); ?>
				</label>
			</td>
			<td scope="col" class="manage-column column-display_name">
				<label class="screen-reader-text" for="display_name_<?php echo esc_attr( $lang->code ); ?>">
					<?php echo esc_html( sprintf( __( 'Display name for "%s"', 'babble' ), $lang->names ) ); ?>
				</label>
				<input type="text" name="display_name_<?php echo esc_attr( $lang->code ); ?>" value="<?php echo esc_attr( $lang->display_name ); ?>" id="display_name_<?php echo esc_attr( $lang->code ); ?>" class="<?php echo esc_attr( $lang->input_lang_class ); ?>">
			</td>
			<td scope="col" class="manage-column column-url_prefix">
				<label class="screen-reader-text" for="url_prefix_<?php echo esc_attr( $lang->code ); ?>">
					<?php echo esc_html( sprintf( __( 'URL prefix for "%s"', 'babble' ), $lang->names ) ); ?>
				</label>
				<input type="text" name="url_prefix_<?php echo esc_attr( $lang->code ); ?>" value="<?php echo esc_attr( $lang->url_prefix ); ?>" id="url_prefix_<?php echo esc_attr( $lang->code ); ?>" class="small-text <?php echo esc_attr( $lang->url_prefix_error ); ?>">
			</td>
			<td scope="col" class="manage-column column-text_direction">
				<?php if ( 'ltr' == $lang->text_direction ) : ?>
					<?php _e( '<strong>Left</strong> to right', 'babble' ); ?>
				<?php else : ?>
					<?php _e( '<strong>Right</strong> to left', 'babble' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php submit_button(); ?>

</form>

</div>