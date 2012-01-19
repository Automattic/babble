<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Translation Groups</h2>

	<div>
		<form action="" method="get">
			<input type="hidden" name="page" value="btgt" />
			<p><?php _e( 'Show only the following statuses:', 'bbl' ); ?></p>
			<p><?php 
				$stati = get_post_stati( null, 'objects' ); 
				$selected_stati = ( isset( $_GET[ 'bbl_stati' ] ) ) ? $_GET[ 'bbl_stati' ] : array( 'publish', 'private', 'draft', 'private', 'future', 'pending' );
				foreach ( $stati as $status => $status_obj ) : ?>
				<label for="status-<?php echo esc_attr( $status ); ?>"><input type="checkbox" name="bbl_stati[]" value="<?php echo esc_attr( $status ); ?>" id="status-<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, $selected_stati ) ); ?> /> <?php echo esc_html( $status_obj->label ); ?> (<?php echo $status_obj->public ? __( 'public', 'bbl' ) : __( 'hidden', 'bbl' ); ?>)</label><br />
			<?php endforeach; ?></p>
			<?php submit_button( __( 'Filter', 'bbl' ) ); ?>
		</form>
	</div>

	<?php
	
		$terms = get_terms( 'post_translation' );
	
		if ( $terms ) : ?>
			<table class="wp-list-table widefat fixed translation-groups" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" id="id" class="manage-column column-id" style=""><span>ID</span></th>
						<th scope="col" id="type" class="manage-column column-type" style=""><span>Type</span></th>
						<th scope="col" id="status" class="manage-column column-status" style=""><span>Status</span></th>
						<th scope="col" id="lang" class="manage-column column-lang" style=""><span>Lang</span></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="manage-column column-id" style=""><span>ID</span></th>
						<th scope="col" class="manage-column column-type" style=""><span>Type</span></th>
						<th scope="col" class="manage-column column-status" style=""><span>Status</span></th>
						<th scope="col" class="manage-column column-lang" style=""><span>Lang</span></th>
					</tr>
				</tfoot>
			<?php endif; foreach ( $terms as $term ) : ?>
				<tbody id="tg-<?php echo esc_attr( $term->term_id ); ?>">
				<tr>
					<th colspan="4"><h3>Translation Group: <?php echo $term->term_id; ?></h3></th>
				</tr>
					<?php 
						$post_ids = get_objects_in_term( $term->term_id, 'post_translation' );
						$posts = array();
						foreach ( $post_ids as $post_id )
							$posts[] = get_post( $post_id );
						usort( $posts, array( 'SortPosts', 'post_type_descending' ) );
						if ( $posts ) : 
					?>
					<?php foreach ( $posts as $post ) : if ( ! in_array( $post->post_status, $selected_stati ) ) continue; ?>
				<tr>
					<?php if ( ! $post ) : ?>
						<th colspan="4">
							<span class="error"><strong>WARNING:</strong> Post <?php echo $post_id ?> does not exist</span> –
							<a href="<?php echo $this->get_action_link( $post_id, 'delete_from_groups' ); ?>">remove from all groups</a>
						</th>
					<?php else : ?>
						<th scope="row" class="manage-column column-id">
							<?php echo $post->ID ?><br /> 
							<a href="<?php echo add_query_arg( array( 'lang' => bbl_get_post_lang_code( $post->ID ) ), get_edit_post_link( $post->ID ) ); ?>">edit</a> |
							<a href="<?php echo $this->get_action_link( $post->ID, 'delete_post', "tg-$term->term_id" ); ?>">delete</a> |
							<a href="<?php echo $this->get_action_link( $post->ID, 'trash_post', "tg-$term->term_id" ); ?>">trash</a>
						</th>
						<td class="manage-column column-type"><?php echo $post->post_type ?></td>
						<td class="manage-column column-status"><?php echo $post->post_status ?></td>
						<td class="manage-column column-lang"><?php echo bbl_get_post_lang_code( $post->ID ) ?></td>
					<?php endif; ?>
				</tr>
							
						<?php endforeach; ?>
						<?php else : ?>
							
				<tr><td colspan="4"><em>no posts found for this translation group</em></td></tr>
							
						<?php endif; // if $post_ids ?>
		</tbody><?php endforeach; // foreach $terms ?>
		<?php if ( $terms ) : ?>
			</table>
		<?php else : ?>
			<p><em>No translation groups found.</em></p>
		<?php endif;
	?>

</div>