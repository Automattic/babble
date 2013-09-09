<div id="bbl-translation-editor">

	<?php if ( isset( $items['post'] ) ) { ?>

		<?php
	
			wp_nonce_field( "bbl_translation_editor_post_{$job->ID}", '_bbl_translation_editor_post' );

			$original    = $items['post']['original'];
			$translation = $items['post']['translation'];

			do_action( 'bbl_translation_post_meta_boxes', 'bbl_translation_editor_post', $original, $translation );

		?>

		<div class="bbl-translation-item bbl-translation-item-post">

			<?php if ( !empty( $original->post_title ) or !empty( $translation->post_title ) ) { ?>

				<div class="bbl-translation-section bbl-translation-section-post_title">
					<div class="bbl-translation-property bbl-translation-property-post_title">
						<input type="text" class="regular-text" name="bbl_translation[post][post_title]" value="<?php echo esc_attr( $translation->post_title ); ?>" placeholder="<?php echo esc_attr( apply_filters( 'enter_title_here', __( 'Enter title here' ), $original ) ); ?>">
					</div>
					<div class="bbl-translation-original bbl-translation-original-post_title">
						<?php echo esc_html( $original->post_title ); ?>
					</div>
				</div>

			<?php } ?>
			<?php if ( !empty( $original->post_content ) or !empty( $translation->post_content ) ) { ?>

				<?php
					# We have two bugs here that require Trac tickets:
					# 1. The 'readonly' setting for TinyMCE affects subsequent editors, when it shouldn't. This makes the order of these calls to wp_editor() important.
					# 2. The 'buttons' => true argument in the quicktags settings is a hack to hide the Quicktags buttons but retain the Visual/Text tabs.
				?>

				<div class="bbl-translation-section bbl-translation-section-post_content">
					<div class="bbl-translation-property bbl-translation-property-post_content">
						<?php wp_editor( $translation->post_content, 'translation_post_content', array(
							'textarea_name' => 'bbl_translation[post][post_content]',
						) ); ?>
					</div>
					<div class="bbl-translation-original bbl-translation-original-post_content">
						<?php wp_editor( $original->post_content, 'original_post_content', array(
							'textarea_name' => 'bbl_original[post][post_content]',
							'media_buttons' => false,
							'quicktags'     => array(
								'buttons' => true,
							),
							'tinymce'       => array(
								'readonly' => 1,
							),
						) ); ?>
					</div>
				</div>

			<?php } ?>

			<?php do_meta_boxes( 'bbl_translation_editor_post', 'post', compact( 'original', 'translation' ) ); ?>

		</div>

	<?php } ?>

	<?php if ( isset( $items['terms'] ) ) { ?>

		<?php

		wp_nonce_field( "bbl_translation_editor_terms_{$job->ID}", '_bbl_translation_editor_terms' );

		do_action( 'bbl_translation_terms_meta_boxes', 'bbl_translation_editor_terms', $items['terms'] );

		?>
		<div class="bbl-translation-item bbl-translation-item-terms">
			<?php

			foreach ( $items['terms'] as $taxo => $terms )
				do_meta_boxes( 'bbl_translation_editor_terms', $taxo, compact( 'taxo', 'terms' ) );

			?>
		</div>

	<?php } ?>

	<div class="bbl-translation-submit">

		<select name="post_status">
			<option value="in-progress"><?php esc_html_e( 'In Progress', 'babble' ); ?></option>
			<option value="complete" <?php selected( $job->post_status, 'complete' ); ?>><?php esc_html_e( 'Complete', 'babble' ); ?></option>
		</select>
		<?php submit_button( __( 'Update', 'babble' ), 'primary large', 'submit', false ); ?>

	</div>

</div>