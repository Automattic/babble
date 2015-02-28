<?php
/*
Plugin Name: Babble Translation Fields
Plugin URI:  http://babbleplugin.com/
Description: Support for translating meta fields in various popular plugins.
Version:     1.0
Author:      Automattic
Author URI:  https://automattic.com/
Text Domain: babble
Domain Path: /languages/
License:     GPL v2 or later

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

function bbl_wpseo_meta_fields( array $fields, WP_Post $post ) {
	if ( class_exists( 'WPSEO_Meta' ) ) {

		$title    = WPSEO_Meta::$meta_prefix . 'title';
		$metadesc = WPSEO_Meta::$meta_prefix . 'metadesc';

		$fields[ $title ]    = new Babble_Meta_Field_Text( $post, $title, _x( 'SEO Title', 'WordPress SEO plugin meta field', 'babble' ) );
		$fields[ $metadesc ] = new Babble_Meta_Field_Textarea( $post, $metadesc, _x( 'Meta Description', 'WordPress SEO plugin meta field', 'babble' ) );

		foreach ( array(
			'opengraph'   => __( 'Facebook', 'wordpress-seo' ),
			'twitter'     => __( 'Twitter', 'wordpress-seo' ),
			'google-plus' => __( 'Google+', 'wordpress-seo' ),
		) as $network => $label ) {

			$title = WPSEO_Meta::$meta_prefix . $network . '-title';
			$desc  = WPSEO_Meta::$meta_prefix . $network . '-description';

			$fields[ $title ] = new Babble_Meta_Field_Text( $post, $title, sprintf( __( '%s Title', 'wordpress-seo' ), $label ) );
			$fields[ $desc ]  = new Babble_Meta_Field_Text( $post, $desc, sprintf( __( '%s Description', 'wordpress-seo' ), $label ) );

		}

	}
	return $fields;
}

add_filter( 'bbl_translated_meta_fields', 'bbl_wpseo_meta_fields', 10, 2 );
