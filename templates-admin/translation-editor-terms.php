<?php foreach ( $terms as $term ) { ?>

	<?php
	$original    = $term['original'];
	$translation = $term['translation'];
	?>

	<div class="bbl-translation-group">

		<div class="bbl-translation-section">

			<h4><?php _e( 'Name', 'babble' ); ?></h4>
			<div class="bbl-translation-property bbl-translation-property-term_name">
				<input type="text" class="regular-text" name="bbl_translation[terms][<?php echo $original->term_id; ?>][name]" value="<?php echo esc_attr( $translation->name ); ?>">
			</div>
			<div class="bbl-translation-original bbl-translation-original-term_name">
				<?php echo esc_html( $original->name ); ?>
			</div>

		</div>

		<?php if ( !empty( $original->slug ) or !empty( $translation->slug ) ) { ?>
			<div class="bbl-translation-section">

				<h4><?php _e( 'Slug (optional)', 'babble' ); ?></h4>
				<div class="bbl-translation-property bbl-translation-property-term_slug">
					<input type="text" class="regular-text" name="bbl_translation[terms][<?php echo $original->term_id; ?>][slug]" value="<?php echo esc_attr( $translation->slug ); ?>">
				</div>
				<div class="bbl-translation-original bbl-translation-original-term_slug">
					<?php echo esc_html( $original->slug ); ?>
				</div>

			</div>
		<?php } ?>

		<?php if ( !empty( $original->description ) or !empty( $translation->description ) ) { ?>
			<div class="bbl-translation-section">

				<h4><?php _e( 'Description', 'babble' ); ?></h4>
				<div class="bbl-translation-property bbl-translation-property-term_description">
					<textarea class="regular-text" name="bbl_translation[terms][<?php echo $original->term_id; ?>][description]"><?php echo esc_textarea( $translation->description ); ?></textarea>
				</div>
				<div class="bbl-translation-original bbl-translation-original-term_description">
					<textarea class="regular-text" readonly><?php echo esc_textarea( $original->description ); ?></textarea>
				</div>

			</div>
		<?php } ?>

	</div>

<?php }