<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI_Handler_GitHub' ) ) :

/**
 * EUAPI handler for plugins and themes hosted on GitHub.com.
 * 
 * Supports public and private repos.
 * 
 * If a repo is private then a valid OAuth access token must be passed in the 'access_token' argument.
 * See http://developer.github.com/v3/oauth/ for details.
 */
class EUAPI_Handler_GitHub extends EUAPI_Handler_Files {

	/**
	 * Class constructor
	 *
	 * @param array $config {
	 *     Configuration for the handler.
	 *
	 *     @type string $github_url   The URL of the repo homepage.
	 *     @type string $file         The EUAPI_Item file name.
	 *     @type string $type The item type. Accepts 'plugin' or 'theme'.
	 *     @type string $access_token A GitHub API access token if this is a handler for a private repo. Optional.
	 *     @type array  $http         Array of args to pass to any HTTP requests relating to this handler. Optional.
	 * }
	 */
	public function __construct( array $config ) {

		if ( !isset( $config['github_url'] ) or !isset( $config['file'] ) ) {
			return;
		}

		$defaults = array(
			'access_token' => null,
		);

		$path = trim( parse_url( $config['github_url'], PHP_URL_PATH ), '/' );
		list( $username, $repo ) = explode( '/', $path, 2 );

		$defaults['base_url'] = sprintf( 'https://raw.githubusercontent.com/%1$s/%2$s/master',
			$username,
			$repo
		);
		$defaults['package_url'] = sprintf( 'https://api.github.com/repos/%1$s/%2$s/zipball',
			$username,
			$repo
		);

		parent::__construct( array_merge( $defaults, $config ) );

	}

	/**
	 * Returns the URL of the plugin or theme's homepage.
	 *
	 * @author John Blackbourn
	 * @return string URL of the plugin or theme's homepage.
	 */
	public function get_homepage_url() {

		return $this->config['github_url'];

	}

	/**
	 * Returns the URL of the plugin or theme file on GitHub, with access token appended if relevant.
	 *
	 * @author John Blackbourn
	 * @param  string $file Optional file name. Defaults to base plugin file or theme stylesheet.
	 * @return string URL of the plugin file.
	 */
	public function get_file_url( $file = null ) {

		if ( empty( $file ) ) {
			$file = $this->config['file_name'];
		}

		$url = trailingslashit( $this->config['base_url'] ) . $file;

		if ( !empty( $this->config['access_token'] ) ) {
			$url = add_query_arg( array(
				'access_token' => $this->config['access_token']
			), $url );
		}

		return $url;
	}

	/**
	 * Returns the URL of the plugin or theme's ZIP package on GitHub, with access token appended if relevant.
	 *
	 * @author John Blackbourn
	 * @return string URL of the plugin or theme's ZIP package.
	 */
	public function get_package_url() {

		$url = $this->config['package_url'];

		if ( !empty( $this->config['access_token'] ) ) {
			$url = add_query_arg( array(
				'access_token' => $this->config['access_token']
			), $url );
		}

		return $url;

	}

}

endif; // endif class exists
