<?php
/**
 * Class for handling post meta translations.
 *
 * @package Babble
 * @since 1.5
 */
abstract class Babble_Meta_Field {
	
	public function __construct( WP_Post $post, $meta_key, $meta_title ) {
		$this->post       = $post;
		$this->meta_key   = $meta_key;
		$this->meta_title = $meta_title;
		$this->meta_value = get_post_meta( $this->post->ID, $this->meta_key, true );
	}

	abstract public function get_input( $name, $value );

	public function get_output() {
		return esc_html( $this->get_value() );
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
		return nl2br( esc_html( $this->get_value() ) );
	}

}
