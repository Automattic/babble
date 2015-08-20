<?php
/**
 * Class for handling post meta translations.
 *
 * @package Babble
 * @since 1.5
 */
abstract class Babble_Meta_Field {

	public function __construct( WP_Post $post, $meta_key, $meta_title, array $args = array() ) {
		$this->post       = $post;
		$this->meta_key   = $meta_key;
		$this->meta_title = $meta_title;
		$this->meta_value = get_post_meta( $this->post->ID, $this->meta_key, true );
		$this->args       = $args;
	}

	abstract public function get_input( $name, $value );

	public function get_output() {
		return $this->get_value();
	}

	public function get_title() {
		return $this->meta_title;
	}

	public function get_value() {
		return $this->meta_value;
	}

	public function get_key() {
		return $this->meta_key;
	}

	public function update( $value, WP_Post $job ) {
		return $value;
	}

}

class Babble_Meta_Field_Text extends Babble_Meta_Field {

	public function get_input( $name, $value ) {
		return sprintf( '<input type="text" name="%s" value="%s">',
			esc_attr( $name ),
			esc_attr( $value )
		);
	}

}

class Babble_Meta_Field_Textarea extends Babble_Meta_Field {

	public function get_input( $name, $value ) {
		return sprintf( '<textarea name="%s" rows="10">%s</textarea>',
			esc_attr( $name ),
			esc_textarea( $value )
		);
	}

	public function get_output() {
		return $this->get_value();
	}

}

class Babble_Meta_Field_Editor extends Babble_Meta_Field {

	public function get_input( $name, $value ) {
		$args = array(
			'textarea_name' => $name,
		);

		# see _WP_Editors()::parse_settings() for available editor settings
		if ( !empty( $this->args['editor_settings'] ) ) {
			$args = array_merge( $args, $this->args['editor_settings'] );
		}

		ob_start();
		wp_editor( $value, sprintf( 'meta-input-%s', $this->get_key() ), $args );
		return ob_get_clean();
	}

	public function get_output() {
		$args = array(
			'textarea_name' => 'doesnotmatter',
			'media_buttons' => false,
			'tinymce'       => array(
				'readonly' => 1,
			),
		);

		ob_start();
		wp_editor( $this->get_value(), sprintf( 'meta-output-%s', $this->get_key() ), $args );
		return ob_get_clean();
	}

}
