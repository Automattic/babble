<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Translation Groups</h2>

	<script type="text/javascript" charset="utf-8">
		jQuery( document ).ready( function( $ ) {
			// Check for duplicate post IDs in translation groups
			var dupes = new Object();
			$( '#translation-groups .post-id' ).each( function () {
				var post_id = $( this ).text();
				if ( $( '#translation-groups .post-id-' + post_id ).length > 1 ) {
					$( '#translation-groups .post-id-' + post_id ).css( 'color', 'red' ). css( 'font-weight', 'bold' );
					dupes[ 'post-id-' + post_id ] = post_id;
				}
			} );
			if ( ! $.isEmptyObject( dupes ) ) {
				var msg = '';
				var counter = 0;
				var dupe_ids = new Array();
				for ( i in dupes ) {
					counter++;
					dupe_ids.push( dupes[ i ] );
				}
				msg += 'Got ' + counter + ' duplicate post IDs, look for the red: ' + dupe_ids.join( ', ' );
				alert( msg );
			}
		} );
	</script>

	<div>
		<form action="" method="get">
			<input type="hidden" name="page" value="btgt" />
			<p><?php esc_html_e( 'Show only the following statuses:', 'babble' ); ?></p>
			<p><?php
				$stati = get_post_stati( null, 'objects' );
				$selected_stati = ( isset( $_GET[ 'bbl_stati' ] ) ) ? $_GET[ 'bbl_stati' ] : array( 'publish', 'private', 'draft', 'private', 'future', 'pending' );
				foreach ( $stati as $status => $status_obj ) : ?>
				<label for="status-<?php echo esc_attr( $status ); ?>"><input type="checkbox" name="bbl_stati[]" value="<?php echo esc_attr( $status ); ?>" id="status-<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, $selected_stati ) ); ?> /> <?php echo esc_html( $status_obj->label ); ?> (<?php echo esc_html( $status_obj->public ) ? esc_html__( 'public', 'babble' ) : esc_html__( 'hidden', 'babble' ); ?>)</label><br />
			<?php endforeach; ?></p>
			<?php submit_button( __( 'Filter', 'babble' ) ); ?>
		</form>
	</div>

	<?php

		$terms = get_terms( 'post_translation' );

		if ( $terms ) : ?>
			<table class="wp-list-table widefat fixed translation-groups" cellspacing="0" id="translation-groups">
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
					<th colspan="4"><h3>Translation Group: <?php echo esc_html( $term->term_id ); ?></h3></th>
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
						<?php if ( ! $post ) : ?>
							<tr>
								<th colspan="4">
									<span class="error"><strong>WARNING:</strong> Post <?php echo absint( $post_id ); ?> does not exist</span> –
									<a href="<?php echo esc_url( $this->get_action_link( $post_id, 'delete_from_groups' ) ); ?>">remove from all groups</a>
								</th>
							</tr>
						<?php else : ?>
							<tr class="post-id-<?php echo esc_attr( $post->ID ); ?>">
								<th scope="row" class="manage-column column-id">
									<span class="post-id"><?php echo absint( $post->ID ); ?></span><br />
									<a href="<?php echo esc_url( add_query_arg( array( 'lang' => bbl_get_post_lang_code( $post->ID ) ), get_edit_post_link( $post->ID ) ) ); ?>">edit</a> |
									<a href="<?php echo esc_url( $this->get_action_link( $post->ID, 'delete_post', "tg-$term->term_id" ) ); ?>">delete</a> |
									<a href="<?php echo esc_url( $this->get_action_link( $post->ID, 'trash_post', "tg-$term->term_id" ) ); ?>">trash</a> |
									<?php if ( bbl_get_default_lang_code() == bbl_get_post_lang_code( $post->ID ) ) : ?>
										<a href="<?php echo esc_url( $this->get_action_link( $post->ID, 'delete_from_groups', "tg-$term->term_id" ) ); ?>">remove from group</a>
									<?php endif; ?>
								</th>
								<td class="manage-column column-type"><?php echo esc_html( $post->post_type ); ?></td>
								<td class="manage-column column-status"><?php echo esc_html( $post->post_status ); ?></td>
								<td class="manage-column column-lang"><?php echo esc_html( bbl_get_post_lang_code( $post->ID ) ) ?></td>
							</tr>
					<?php endif; ?>

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