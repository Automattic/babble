<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI_Handler_Files' ) ) :

/**
 * EUAPI handler for plugins and themes where the plugin or theme updates are fetched simply by reading
 * the plugin or theme files from a URL (as opposed to communicating with an update API).
 * 
 */
abstract class EUAPI_Handler_Files extends EUAPI_Handler {

	/**
	 * Class constructor
	 *
	 * @param array $config {
	 *     Configuration for the handler.
	 *
	 *     @type string $file The EUAPI_Item file name.
	 *     @type string $type The item type. Accepts 'plugin' or 'theme'.
	 *     @type array  $http Array of args to pass to any HTTP requests relating to this handler. Optional.
	 * }
	 */
	public function __construct( array $config ) {

		$defaults = array(
			'http' => array(
				'timeout' => 5,
			),
		);

		// Back-compat with earlier versions where we had these values in the root of the $config array.
		if ( isset( $config['sslverify'] ) ) {
			$config['http']['sslverify'] = $config['sslverify'];
		}
		if ( isset( $config['timeout'] ) ) {
			$config['http']['timeout'] = $config['timeout'];
		}

		parent::__construct( array_merge( $defaults, $config ) );

	}

	/**
	 * Returns the URL of the plugin or theme file.
	 *
	 * @author John Blackbourn
	 * @param  string $file Optional file name. Defaults to base plugin file or theme stylesheet.
	 * @return string URL of the plugin file.
	 */
	abstract public function get_file_url( $file = null );

	/**
	 * Fetch the latest version number. Does this by fetching the plugin
	 * file and then parsing the header to get the version number.
	 *
	 * @author John Blackbourn
	 * @return string|false Version number, or false on failure.
	 */
	final public function fetch_new_version() {

		$response = EUAPI::fetch( $this->get_file_url(), $this->config['http'] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = EUAPI::get_content_data( $response, array(
			'version' => 'Version'
		) );

		if ( empty( $data['version'] ) ) {
			return false;
		}

		return $data['version'];

	}

	/**
	 * Fetch info about the latest version of the item.
	 *
	 * @author John Blackbourn
	 * @return EUAPI_Info|WP_Error An EUAPI_Info object, or a WP_Error object on failure.
	 */
	final public function fetch_info() {

		$fields = array(
			'author'      => 'Author',
			'description' => 'Description'
		);

		switch ( $this->get_type() ) {

			case 'plugin':
				$file = $this->get_file_url();
				$fields['plugin_name'] = 'Plugin Name';
				break;

			case 'theme':
				$file = $this->get_file_url( 'style.css' );
				$fields['theme_name'] = 'Theme Name';
				break;

		}

		$response = EUAPI::fetch( $file, $this->config['http'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = EUAPI::get_content_data( $response, $fields );

		$info = array_merge( $data, array(

			'slug'          => $this->get_file(),
			'version'       => $this->get_new_version(),
			'homepage'      => $this->get_homepage_url(),
			'download_link' => $this->get_package_url(),
	#		'requires'      => '',
	#		'tested'        => '',
	#		'last_updated'  => '',
			'downloaded'    => 0,
			'sections'      => array(
				'description' => $data['description'],
			),

		) );

		return new EUAPI_Info( $info );

	}

}

endif; // endif class exists
