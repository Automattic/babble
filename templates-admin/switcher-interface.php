<tr>
	<th scope="row"><?php _e( 'Interface Language', 'babble' ); ?></th>
	<td><select name="interface_lang">
		<?php foreach ( $langs as $lang ) { ?>
			<option value="<?php echo esc_attr( $lang->code ); ?>" <?php selected( $lang->code, $current ); ?>><?php echo esc_html( $lang->display_name ); ?></option>
		<?php } ?>
	</select></td>
</tr>