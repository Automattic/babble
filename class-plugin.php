<?php

// ======================================================================================
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
// ======================================================================================
// @author     Simon Wheatley (http://simonwheatley.co.uk)
// @version    1.0
// @copyright  Copyright &copy; 2010 Simon Wheatley, All Rights Reserved
// @copyright  Some parts Copyright &copy; 2007 John Godley, All Rights Reserved
// ======================================================================================
// 1.0     - Initial release
// 1.01    - Added add_shortcode
// 1.10    - Added code to allow the base class to be used in a theme
// 1.2     - Truncate helper method, admin notices/errors, throw error if not provided
//           with name in setup method call, default $pluginfile to __FILE__, bugfix around
//           option key in delete_option method.
// 1.3     - Locale stuff
//         - Fix for get_option
// 1.31    - Attempt to cope with Win32 directory separators
// 1.32    - Add a remove_filter method
// 1.33    - Add `sil_plugins_dir` and `sil_plugins_url` filters, to allow placement
//           outside the `wp-content/plugins/` folder, for example using `require_once`
//           to include from the theme `functions.php`.
// ======================================================================================


/**
 * Wraps up several useful functions for WordPress plugins and provides a method to separate
 * display HTML from PHP code.
 *
 * <h4>Display Rendering</h4>
 *
 * The class uses a similar technique to Ruby On Rails views, whereby the display HTML is kept
 * in a separate directory and file from the main code.  A display is 'rendered' (sent to the browser)
 * or 'captured' (returned to the calling function).
 *
 * Template files are separated into two areas: admin and user.  Admin templates are only for display in
 * the WordPress admin interface, while user templates are typically for display on the site (although neither
 * of these are enforced).  All templates are PHP code, but are referred to without .php extension.
 *
 * The reason for this separation is that one golden rule of plugin creation is that someone will
 * always want to change the formatting and style of your output.  Rather than forcing them to
 * modify the plugin (bad), or modify files within the plugin (equally bad), the class allows
 * user templates to be overridden with files contained within the theme.
 *
 * An additional benefit is that it leads to code re-use, especially with regards to Ajax (i.e.
 * your display code can be called from many locations)
 *
 * @package Babble
 * @author Simon Wheatley
 * @copyright Copyright (C) Simon Wheatley (except where noted)
 **/
class Babble_Plugin {

	/**
	 * The name of this plugin
	 *
	 * @var string
	 **/
	protected $name;

	/**
	 * The filepath to the directory containing this plugin
	 *
	 * @var string
	 **/
	protected $dir;

	/**
	 * The URL for the directory containing this plugin
	 *
	 * @var string
	 **/
	protected $url;

	/**
	 * Useful for switching between debug and compressed scripts.
	 *
	 * @var string
	 **/
	protected $suffix;

	/**
	 * Records the type of this class, either 'plugin' or 'theme'.
	 *
	 * @var string
	 **/
	protected $type;

	/**
	 * Initiate!
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function setup( $name, $type = null ) {
		$this->name = $name;

		// Attempt to handle a Windows
		$ds = ( defined( 'DIRECTORY_SEPARATOR' ) ) ? DIRECTORY_SEPARATOR : '\\';
		$file = str_replace( $ds, '/', __FILE__ );
		$plugins_dir = str_replace( $ds, '/', dirname( __FILE__ ) );
		// Setup the dir and url for this plugin
		if ( stripos( $file, $plugins_dir ) !== false || 'plugin' == $type ) {
			// This is a plugin
			$this->type = 'plugin';

			// Allow someone to override the assumptions we're making here about where
			// the plugin is held. For example, if this plugin is included as part of
			// the files for a theme, in wp-content/themes/[your theme]/plugins/ then
			// you could hook `sil_plugins_dir` and `sil_plugins_url` to correct
			// our assumptions.
			// N.B. Because this code is running when the file is required, other plugins
			// may not be loaded and able to hook these filters!
			$plugins_dir = apply_filters( 'sil_plugins_dir', $plugins_dir, $this->name );
			$plugins_url = apply_filters( 'sil_plugins_url', plugins_url( '', __FILE__ ), $this->name );
			$this->dir = trailingslashit( $plugins_dir );
			$this->url = trailingslashit( $plugins_url );
		} else {
			// WTF?
			bbl_log( 'PLUGIN/THEME ERROR: Cannot find ' . $plugins_dir . ' or "themes" in ' . $file, true );
		}

		// Suffix for enqueuing
		$this->suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		if ( is_admin() ) {
			// Admin notices
			add_action( 'admin_notices', array( $this, '_admin_notices' ) );
		}

	}

	/**
	 * Renders an admin template from this plugin's /templates-admin/ directory.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin( $template_file, $vars = null ) {
		// Plus our specific template vars
		if ( is_array( $vars ) )
			extract( $vars );

		// Try to render
		if ( file_exists( $this->dir( "templates-admin/$template_file" ) ) ) {
			require( $this->dir( "templates-admin/$template_file" ) );
		} else {
			$msg = sprintf( __( "This plugin admin template could not be found: %s" ), $this->dir( "templates-admin/$template_file" ) );
			bbl_log( "Plugin template error: $msg", true );
			echo "<p style='background-color: #ffa; border: 1px solid red; color: #300; padding: 10px;'>" . esc_html( $msg ) . "</p>";
		}
	}

	/**
	 * Hooks the WP admin_notices action to render any notices
	 * that have been set with the set_admin_notice method.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function _admin_notices() {
		$notices = $this->get_option( 'admin_notices' );
		$errors = $this->get_option( 'admin_errors' );
		if ( $errors ) {
			foreach ( $errors as $error ) {
				$this->render_admin_error( $error );
				$this->delete_option( 'admin_errors' );
			}
		}
		if ( $notices ) {
			foreach ( $notices as $notice ) {
				$this->render_admin_notice( $notice );
				$this->delete_option( 'admin_notices' );
			}
		}
	}

	/**
	 * Echoes some HTML for an admin notice.
	 *
	 * @param string $notice The notice
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin_notice( $notice ) {
		echo "<div class='updated'><p>" . esc_html( $notice ) . "</p></div>";
	}

	/**
	 * Echoes some HTML for an admin error.
	 *
	 * @param string $error The error
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin_error( $error ) {
		echo "<div class='error'><p>" . esc_html( $error ) . "</p></div>";
	}

	/**
	 * Sets a string as an admin notice.
	 *
	 * @param string $msg A *localised* admin notice message
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function set_admin_notice( $msg ) {
		$notices = (array) $this->get_option( 'admin_notices' );
		$notices[] = $msg;
		$this->update_option( 'admin_notices', $notices );
	}

	/**
	 * Sets a string as an admin error.
	 *
	 * @param string $msg A *localised* admin error message
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function set_admin_error( $msg ) {
		$errors = (array) $this->get_option( 'admin_errors' );
		$errors[] = $msg;
		$this->update_option( 'admin_errors', $errors );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param $path string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Filesystem path
	 * @author Simon Wheatley
	 **/
	protected function dir( $path ) {
		return trailingslashit( $this->dir ) . trim( $path, '/' );
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param $path string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string URL
	 * @author Simon Wheatley
	 **/
	protected function url( $path ) {
		return esc_url( trailingslashit( $this->url ) . trim( $path, '/' ) );
	}

	/**
	 * Gets the value from an array index on an option named as per this plugin.
	 *
	 * @param string $key A string
	 * @return mixed Whatever
	 * @author Simon Wheatley
	 **/
	public function get_option( $key, $value = null ) {
		$option = get_option( $this->name );
		if ( ! is_array( $option ) || ! isset( $option[ $key ] ) )
			return $value;
		return $option[ $key ];
	}

	/**
	 * Sets the value on an array index on an option named as per this plugin.
	 *
	 * @param string $key A string
	 * @param mixed $value Whatever
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	protected function update_option( $key, $value ) {
		$option = get_option( $this->name );
		$option[ $key ] = $value;
		return update_option( $this->name, $option );
	}

	/**
	 * Deletes the array index on an option named as per this plugin.
	 *
	 * @param string $key A string
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	protected function delete_option( $key ) {
		$option = get_option( $this->name );
		if ( isset( $option[ $key ] ) )
			unset( $option[ $key ] );
		return update_option( $this->name, $option );
	}

} // END Babble_Plugin class
