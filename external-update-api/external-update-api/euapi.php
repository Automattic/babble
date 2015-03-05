<?php

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'EUAPI' ) ) :

/**
 * The EUAPI plugin class.
 */
class EUAPI {

	protected $handlers = array();

	/**
	 * Class constructor. Sets up some actions and filters.
	 *
	 * @author John Blackbourn
	 */
	private function __construct() {

		add_filter( 'http_request_args',                     array( $this, 'filter_http_request_args' ), 20, 2 );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_plugins' ) );
		add_filter( 'pre_set_site_transient_update_themes',  array( $this, 'filter_update_themes' ) );

		add_filter( 'plugins_api',                           array( $this, 'filter_plugins_api' ), 10, 3 );
		add_filter( 'themes_api',                            array( $this, 'filter_themes_api' ), 10, 3 );

		add_filter( 'upgrader_pre_install',                  array( $this, 'filter_upgrader_pre_install' ), 10, 2 );
		add_filter( 'upgrader_post_install',                 array( $this, 'filter_upgrader_post_install' ), 10, 3 );

		add_filter( 'euapi_plugin_handler',                  array( $this, 'filter_euapi_plugin_handler' ), 10, 2 );

	}

	/**
	 * Filter the arguments for HTTP requests. If the request is to a URL that's part of
	 * something we're handling then filter the arguments accordingly.
	 *
	 * @author John Blackbourn
	 * @param  array  $args HTTP request arguments.
	 * @param  string $url  HTTP request URL.
	 * @return array        Updated array of arguments.
	 */
	public function filter_http_request_args( array $args, $url ) {

		if ( preg_match( '#://api\.wordpress\.org/(?P<type>plugins|themes)/update-check/(?P<version>[0-9\.]+)/#', $url, $matches ) ) {

			switch ( $matches['type'] ) {

				case 'plugins':
					return $this->plugin_request( $args, floatval( $matches['version'] ) );
					break;

				case 'themes':
					return $this->theme_request( $args, floatval( $matches['version'] ) );
					break;

			}

		}

		$query = parse_url( $url, PHP_URL_QUERY );

		if ( empty( $query ) ) {
			return $args;
		}

		parse_str( $query, $query );

		if ( !isset( $query['_euapi_type'] ) or !isset( $query['_euapi_file'] ) ) {
			return $args;
		}

		if ( !( $handler = $this->get_handler( $query['_euapi_type'], $query['_euapi_file'] ) ) ) {
			return $args;
		}

		$args = array_merge( $args, $handler->config['http'] );

		return $args;

	}

	/**
	 * Filters the arguments for HTTP requests to the plugin update check API.
	 *
	 * Here we loop over each plugin in the update check request and remove ones for which we're
	 * handling or excluding updates.
	 *
	 * @author John Blackbourn
	 * @param  array $args    HTTP request arguments.
	 * @param  float $version The API request version number.
	 * @return array          Updated array of arguments.
	 */
	protected function plugin_request( array $args, $version ) {

		switch ( $version ) {

			case 1.0:
				_doing_it_wrong( __METHOD__, sprintf( esc_html__( 'External Update API is not compatible with version %s of the WordPress Plugin API. Please update to the latest version of WordPress.', 'euapi' ), $version ), 0.4 );
				return $args;
				break;

			case 1.1:
			default:
				$plugins = json_decode( $args['body']['plugins'] );
				break;

		}

		if ( ! is_object( $plugins ) or empty( $plugins->plugins ) ) {
			return $args;
		}

		foreach ( $plugins->plugins as $plugin => $data ) {

			if ( !is_object( $data ) ) {
				continue;
			}

			$data    = get_object_vars( $data );
			$item    = new EUAPI_Item_Plugin( $plugin, $data );
			$handler = $this->get_handler( 'plugin', $plugin, $item );

			if ( null === $handler ) {
				continue;
			}

			if ( is_a( $handler, 'EUAPI_Handler' ) ) {
				$handler->item = $item;
			}

			unset( $plugins->plugins->{$plugin} );

		}

		$args['body']['plugins'] = json_encode( $plugins );

		return $args;

	}

	/**
	 * Filters the arguments for HTTP requests to the theme update check API.
	 *
	 * Here we loop over each theme in the update check request and remove ones for which we're
	 * handling or excluding updates.
	 *
	 * @author John Blackbourn
	 * @param  array $args    HTTP request arguments.
	 * @param  float $version The API request version number.
	 * @return array          Updated array of arguments.
	 */
	protected function theme_request( array $args, $version ) {

		switch ( $version ) {

			case 1.0:
				_doing_it_wrong( __METHOD__, sprintf( esc_html__( 'External Update API is not compatible with version %s of the WordPress Theme API. Please update to the latest version of WordPress.', 'euapi' ), $version ), 0.4 );
				return $args;
				break;

			case 1.1:
			default:
				$themes = json_decode( $args['body']['themes'] );
				break;

		}

		if ( ! is_object( $themes ) or empty( $themes->themes ) ) {
			return $args;
		}

		foreach ( $themes->themes as $theme => $data ) {

			if ( !is_object( $data ) ) {
				continue;
			}

			$data = get_object_vars( $data );

			if ( !isset( $data['ThemeURI'] ) ) {
				# ThemeURI is missing from $data by default for some reason
				$data['ThemeURI'] = wp_get_theme( $data['Template'] )->get( 'ThemeURI' );
			}

			$item    = new EUAPI_Item_Theme( $theme, $data );
			$handler = $this->get_handler( 'theme', $theme, $item );

			if ( null === $handler ) {
				continue;
			}

			if ( is_a( $handler, 'EUAPI_Handler' ) ) {
				$handler->item = $item;
			}

			unset( $themes->themes->{$theme} );

		}

		$args['body']['themes'] = json_encode( $themes );

		return $args;

	}

	/**
	 * Called immediately before the plugin update check results are saved in a transient.
	 *
	 * We use this to fire off update checks to each of the plugins we're handling updates
	 * for, and populate the results in the update check object.
	 *
	 * @author John Blackbourn
	 * @param  object $update The plugin update check object.
	 * @return object         The updated update check object.
	 */
	public function filter_update_plugins( $update ) {
		if ( !isset( $this->handlers['plugin'] ) ) {
			return $update;
		}
		return self::check( $update, $this->handlers['plugin'] );
	}

	/**
	 * Called immediately before the theme update check results are saved in a transient.
	 *
	 * We use this to fire off update checks to each of the themes we're handling updates
	 * for, and populate the results in the update check object.
	 *
	 * @author John Blackbourn
	 * @param  object $update Theme update check object.
	 * @return object         Updated update check object.
	 */
	public function filter_update_themes( $update ) {
		if ( !isset( $this->handlers['theme'] ) ) {
			return $update;
		}
		return self::check( $update, $this->handlers['theme'] );
	}

	/**
	 * Fire off update checks for each of the handlers specified and populate the results in
	 * the update check object.
	 *
	 * @author John Blackbourn
	 * @param  object $update   Update check object.
	 * @param  array  $handlers Handlers that we're interested in.
	 * @return object           Updated update check object.
	 */
	public static function check( $update, array $handlers ) {

		if ( empty( $update->checked ) ) {
			return $update;
		}

		foreach ( array_filter( $handlers ) as $handler ) {

			$handler_update = $handler->get_update();

			if ( $handler_update->get_new_version() and 1 === version_compare( $handler_update->get_new_version(), $handler->get_current_version() ) ) {
				if ( 'plugin' == $handler->get_type() ) {
					$update->response[ $handler->get_file() ] = (object) $handler_update->get_data_to_store();
				} else {
					$update->response[ $handler->get_file() ] = $handler_update->get_data_to_store();
				}
			}

		}

		return $update;

	}

	/**
	 * Get the update handler for the given item, if one is present.
	 *
	 * @author John Blackbourn
	 * @param  string             $type Handler type (either 'plugin' or 'theme').
	 * @param  string             $file Item base file name.
	 * @param  EUAPI_Item|null    $item Item object for the plugin/theme. Optional.
	 * @return EUAPI_Handler|null       Update handler object, or null if no update handler is present.
	 */
	public function get_handler( $type, $file, $item = null ) {

		if ( isset( $this->handlers[$type] ) and array_key_exists( $file, $this->handlers[$type] ) ) {
			return $this->handlers[$type][$file];
		}

		if ( !$item ) {
			$item = self::populate_item( $type, $file );
		}

		if ( ! is_a( $item, 'EUAPI_Item' ) ) {
			$handler = null;
		} else {
			$handler = apply_filters( "euapi_{$type}_handler", null, $item );
		}

		$this->handlers[$type][$file] = $handler;

		return $handler;

	}

	/**
	 * Returns the item data for a given item, typically by reading the item file header
	 * and populating its data.
	 *
	 * @author John Blackbourn
	 * @param  string          $type Handler type (either 'plugin' or 'theme').
	 * @param  string          $file Item base file name.
	 * @return EUAPI_Item|null       Item object or null on failure.
	 */
	protected static function populate_item( $type, $file ) {

		switch ( $type ) {

			case 'plugin':
				if ( $data = self::get_plugin_data( $file ) ) {
					return new EUAPI_Item_Plugin( $file, $data );
				}
				break;

			case 'theme':
				if ( $data = self::get_theme_data( $file ) ) {
					return new EUAPI_Item_Theme( $file, $data );
				}
				break;

		}

		return null;

	}

	/**
	 * Get data for a plugin by reading its file header.
	 *
	 * @param  string      $file Plugin base file name.
	 * @return array|false       Array of plugin data, or false on failure.
	 */
	public static function get_plugin_data( $file ) {

		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		if ( file_exists( $plugin =  WP_PLUGIN_DIR . '/' . $file ) ) {
			return get_plugin_data( $plugin );
		}

		return false;

	}

	/**
	 * Get data for a theme by reading its file header.
	 *
	 * @param  string      $file Theme directory name.
	 * @return array|false       Array of theme data, or false on failure.
	 */
	public static function get_theme_data( $file ) {

		$theme = wp_get_theme( $file );

		if ( !$theme->exists() ) {
			return false;
		}

		$data = array(
			'Name'        => '',
			'ThemeURI'    => '',
			'Description' => '',
			'Author'      => '',
			'AuthorURI'   => '',
			'Version'     => '',
			'Template'    => '',
			'Status'      => '',
			'Tags'        => '',
			'TextDomain'  => '',
			'DomainPath'  => '',
		);

		foreach ( $data as $k => $v ) {
			$data[$k] = $theme->get( $k );
		}

		return $data;

	}

	/**
	 * Before the Plugin API performs an action, this short-circuit callback is fired, allowing us to override the
	 * API method for a given action.
	 *
	 * Here, we override the action which fetches plugin information from the wp.org API
	 * and return our own plugin information if necessary.
	 *
	 * @param  bool|object              $default Default return value for this request. Usually boolean false.
	 * @param  string                   $action  API function being performed.
	 * @param  object                   $plugin  Plugin Info API object.
	 * @return bool|WP_Error|EUAPI_Info          EUAPI Info object, WP_Error object on failure, $default if we're not interfering.
	 */
	public function filter_plugins_api( $default, $action, $plugin ) {

		if ( 'plugin_information' != $action ) {
			return $default;
		}
		if ( false === strpos( $plugin->slug, '/' ) ) {
			return $default;
		}

		$handler = $this->get_handler( 'plugin', $plugin->slug );

		if ( ! is_a( $handler, 'EUAPI_Handler' ) ) {
			return $default;
		}

		return $handler->get_info();

	}

	/**
	 * Before the Theme API performs an action, this short-circuit callback is fired, allowing us to override the
	 * API method for a given action.
	 *
	 * Here, we override the action which fetches theme information from the wp.org API
	 * and return our own theme information if necessary.
	 *
	 * @param  bool|object              $default Default return value for this request. Usually boolean false.
	 * @param  string                   $action  API function being performed.
	 * @param  object                   $theme   Theme Info API object.
	 * @return bool|WP_Error|EUAPI_Info          EUAPI Info object, WP_Error object on failure, $default if we're not interfering.
	 */
	public function filter_themes_api( $default, $action, $theme ) {

		if ( 'theme_information' != $action ) {
			return $default;
		}

		$handler = $this->get_handler( 'theme', $theme->slug );

		if ( ! is_a( $handler, 'EUAPI_Handler' ) ) {
			return $default;
		}

		return $handler->get_info();

	}

	/**
	 * Fetch the contents of a URL.
	 *
	 * @author John Blackbourn
	 * @param  string   $url   URL to fetch.
	 * @param  array    $args  Array of arguments passed to wp_remote_get().
	 * @return WP_Error|string WP_Error object on failure, string contents of URL body on success.
	 */
	public static function fetch( $url, array $args = array() ) {

		$args = array_merge( array(
			'timeout' => 5
		), $args );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );

		if ( 200 != $code ) {
			return new WP_Error( 'fetch_failed', esc_html( $code . ' ' . $message ) );
		}

		return wp_remote_retrieve_body( $response );

	}

	/**
	 * Parse a plugin or theme file to fetch its header values.
	 *
	 * Based on WordPress' `get_file_data()` function.
	 *
	 * @param  string $content     The file content.
	 * @param  array  $all_headers The headers to return.
	 * @return array               The header values.
	 */
	public static function get_content_data( $content, array $all_headers ) {

		// Pull only the first 8kiB of the file in.
		if ( function_exists( 'mb_substr' ) ) {
			$file_data = mb_substr( $content, 0, 8192 );
		} else {
			$file_data = substr( $content, 0, 8192 );
		}

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		return $all_headers;
	}

	/**
	 * Pre-load our handlers so the plugin/theme update filters can function.
	 *
	 * @param  bool|WP_Error $default    Default return value for the update. Usually boolean true.
	 * @param  array         $hook_extra Extra arguments passed to hooked filters.
	 * @return bool|WP_Error             Boolean true or a WP_Error object.
	 */
	public function filter_upgrader_pre_install( $default, array $hook_extra ) {

		if ( isset( $hook_extra['plugin'] ) ) {
			$this->get_handler( 'plugin', $hook_extra['plugin'] );
		} else if ( isset( $hook_extra['theme'] ) ) {
			$this->get_handler( 'theme', $hook_extra['theme'] );
		}

		return $default;

	}

	/**
	 * If we have a handler for this update, do some post-processing after the update.
	 *
	 * @param  bool|WP_Error $default    Default return value for the update. Usually boolean true.
	 * @param  array         $hook_extra Extra arguments passed to hooked filters.
	 * @param  array         $result     Installation result data.
	 * @return bool|WP_Error             Boolean true or a WP_Error object.
	 */
	public function filter_upgrader_post_install( $default, array $hook_extra, array $result ) {

		global $wp_filesystem;

		if ( isset( $hook_extra['plugin'] ) ) {
			$handler = $this->get_handler( 'plugin', $hook_extra['plugin'] );
		} else if ( isset( $hook_extra['theme'] ) ) {
			$handler = $this->get_handler( 'theme', $hook_extra['theme'] );
		} else {
			return $default;
		}

		if ( ! is_a( $handler, 'EUAPI_Handler' ) ) {
			return $default;
		}

		switch ( $handler->get_type() ) {

			case 'plugin':
				$proper_destination = WP_PLUGIN_DIR . '/' . $handler->config['folder_name'];
				break;
			case 'theme':
				$proper_destination = get_theme_root() . '/' . $handler->config['folder_name'];
				break;

		}

		// Move
		$wp_filesystem->move( $result['destination'], $proper_destination );

		return $default;

	}

	/**
	 * Singleton instantiator.
	 *
	 * @return EUAPI Our instance of the EUAPI class.
	 */
	public static function init() {

		static $instance = null;

		if ( !$instance )
			$instance = new EUAPI;

		return $instance;

	}

	/**
	 * Eat our own dog food. Handle updates to EUAPI through GitHub.
	 *
	 * @param  EUAPI_Handler|null $handler The handler object for this item, or null if a handler isn't set.
	 * @param  EUAPI_Item         $item    The item in question.
	 * @return EUAPI_Handler|null The handler for this item, or null.
	 */
	public function filter_euapi_plugin_handler( EUAPI_Handler $handler = null, EUAPI_Item $item ) {

		if ( 'https://github.com/cftp/external-update-api' == $item->url ) {

			$handler = new EUAPI_Handler_GitHub( array(
				'type'       => $item->type,
				'file'       => $item->file,
				'github_url' => $item->url,
				'http'       => array(
					'sslverify' => false,
				),
			) );

		}

		return $handler;

	}

}

endif; // endif class exists
