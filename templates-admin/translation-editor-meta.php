<?php
$original    = $meta['original'];
$translation = $meta['translation'];
$key         = $original->get_key();
?>

<div class="bbl-translation-group">

	<div class="bbl-translation-section">

		<div class="bbl-translation-property bbl-translation-property-meta_key">
			<?php echo $original->get_input( "bbl_translation[meta][{$key}]", $translation ); ?>
		</div>
		<div class="bbl-translation-original bbl-translation-original-meta_key">
			<?php echo wp_kses_post( $original->get_output() ); ?>
		</div>

	</div>

</div>
