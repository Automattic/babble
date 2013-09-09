<?php foreach ( $terms as $term ) { ?>

	<?php
	$original    = $term['original'];
	$translation = $term['translation'];
	?>

	<div class="bbl-translation-section">

		<h4>Name</h4>
		<div class="bbl-translation-property bbl-translation-property-term_name">
			<input type="text" class="regular-text" name="bbl_translation[terms][<?php echo $original->term_id; ?>][name]" value="<?php echo esc_attr( $translation->name ); ?>">
		</div>
		<div class="bbl-translation-original bbl-translation-original-term_name">
			<?php echo esc_html( $original->name ); ?>
		</div>

	</div>

	<?php if ( !empty( $original->description ) or !empty( $translation->description ) ) { ?>
		<div class="bbl-translation-section">

			<h4>Description</h4>
			<div class="bbl-translation-property bbl-translation-property-term_description">
				<textarea class="regular-text" name="bbl_translation[terms][<?php echo $original->term_id; ?>][description]"><?php echo esc_textarea( $translation->description ); ?></textarea>
			</div>
			<div class="bbl-translation-original bbl-translation-original-term_description">
				<textarea class="regular-text" readonly><?php echo esc_textarea( $original->description ); ?></textarea>
			</div>

		</div>
	<?php } ?>

<?php } ?>