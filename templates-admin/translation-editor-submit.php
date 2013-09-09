<select name="post_status">
	<option value="in-progress"><?php esc_html_e( 'In Progress', 'babble' ); ?></option>
	<option value="complete" <?php selected( $job->post_status, 'complete' ); ?>><?php esc_html_e( 'Complete', 'babble' ); ?></option>
</select>
<?php submit_button( __( 'Update', 'babble' ), 'primary large', 'submit', false ); ?>