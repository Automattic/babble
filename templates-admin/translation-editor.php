<div id="bbl-translation-editor">

	<?php
		if ( isset( $lang_code ) ) {
			wp_nonce_field( "bbl_translation_lang_code_{$job->ID}", '_bbl_translation_lang_code' );
			echo '<input type="hidden" name="bbl_lang_code" value="' . esc_attr( $lang_code ) . '">';
		}
	?>

	<?php if ( isset( $items['post'] ) ) { ?>

		<?php
	
			if ( isset( $origin_post ) ) {
				wp_nonce_field( "bbl_translation_origin_post_{$job->ID}", '_bbl_translation_origin_post' );
				echo '<input type="hidden" name="bbl_origin_post" value="' . absint( $origin_post ) . '">';
			}

			wp_nonce_field( "bbl_translation_edit_post_{$job->ID}", '_bbl_translation_edit_post' );

			$original    = $items['post']['original'];
			$translation = $items['post']['translation'];

			do_action( 'bbl_translation_post_meta_boxes', 'bbl_translation_editor_post', $original, $translation );

		?>

		<div class="bbl-translation-item bbl-translation-item-post">

			<?php if ( !empty( $original->post_title ) or !empty( $translation->post_title ) ) { ?>

				<div class="bbl-translation-section bbl-translation-section-post_title">
					<div class="bbl-translation-property bbl-translation-property-post_title">
						<input type="text" class="regular-text" name="bbl_translation[post][post_title]" value="<?php echo esc_attr( $translation->post_title ); ?>" placeholder="<?php echo esc_attr( apply_filters( 'enter_title_here', __( 'Enter title here', 'babble' ), $original ) ); ?>">
					</div>
					<div class="bbl-translation-original bbl-translation-original-post_title">
						<?php echo esc_html( $original->post_title ); ?>
					</div>
				</div>

			<?php } ?>

			<?php if ( !empty( $original->post_name ) or !empty( $translation->post_name ) ) { ?>

				<div class="bbl-translation-section bbl-translation-section-post_name">
					<div class="bbl-translation-property bbl-translation-property-post_name">
						<input type="text" class="regular-text" name="bbl_translation[post][post_name]" value="<?php echo esc_attr( $translation->post_name ); ?>" placeholder="<?php echo esc_attr( apply_filters( 'enter_name_here', sprintf( __( 'Enter the %s slug here', 'babble' ), $original_cpto->labels->singular_name ), $original ) ); ?>">
					</div>
					<div class="bbl-translation-original bbl-translation-original-post_name">
						<?php echo esc_html( $original->post_name ); ?>
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

		if ( isset( $origin_term ) ) {
			wp_nonce_field( "bbl_translation_origin_term_{$job->ID}", '_bbl_translation_origin_term' );
			echo '<input type="hidden" name="bbl_origin_term" value="' . absint( $origin_term ) . '">';
			echo '<input type="hidden" name="bbl_origin_taxonomy" value="' . absint( $origin_taxonomy ) . '">';
		}

		wp_nonce_field( "bbl_translation_edit_terms_{$job->ID}", '_bbl_translation_edit_terms' );

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
			<?php foreach ( $statuses as $status => $label ) { ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $job->post_status, $status ); ?>><?php echo esc_html( $label ); ?></option>
			<?php } ?>
		</select>
		<?php submit_button( __( 'Update', 'babble' ), 'primary large', 'submit', false ); ?>

	</div>

</div>