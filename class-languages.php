<?php

/**
 * Manages the languages available for the site.
 *
 * @package WordPress
 * @subpackage Babble
 * @since 0.1
 */
class Babble_Languages {
	
	/**
	 * The languages available within the system.
	 *
	 * @var array
	 **/
	protected $available_langs;
	
	/**
	 * The languages selected for this site.
	 *
	 * @var array
	 **/
	protected $langs;
	
	/**
	 * Setup any add_action or add_filter calls. Initiate properties.
	 *
	 * @return void
	 **/
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}
	
	/**
	 * Hooks the WP admin_menu action 
	 *
	 * @param  
	 * @return void
	 **/
	public function admin_menu() {
		add_options_page( __( 'Available Languages', 'babble' ), __( 'Available Languages' ), 'manage_options', 'babble_languages', array( $this, 'options_available_languages' ) );
	}
	
	/**
	 * Callback function to provide the HTML for the "Available Languages"
	 * options page.
	 *
	 * @return void
	 **/
	public function options_available_languages() {
		$this->parse_available_languages();
		include( 'templates-admin/options-available-languages.php' );
	}
	
	/**
	 * Parse the files in wp-content/languages and work out what 
	 * languages we've got available.
	 *
	 * @return array An array of languages
	 **/
	protected function parse_available_languages() {
		unset( $this->available_langs );
		$this->available_langs = array();
		foreach ( glob( WP_LANG_DIR . '/*.mo' ) as $mo_file ) {
			preg_match( '/(([a-z]+)(_[a-z]+)?)\.mo$/i', $mo_file, $matches );
			// var_dump( $matches );
			$this->available_langs[ $matches[ 1 ] ] = array(
				'lang_code' => $matches[ 1 ],
				'lang_code_short' => $matches[ 2 ],
				'rtl' => $this->is_rtl( $matches[ 1 ] ),
			);
		}
		var_dump( $this->available_langs );
	}
	
	/**
	 * Parse (DON'T require or include) the [lang_code].php locale file in the languages 
	 * directory to work if the specified language is right to left. (We can't include or 
	 * require because it may contain function names which clash with other locale files.)
	 *
	 * @param string $lang The language code to retrieve RTL info for
	 * @return bool True if the language is RTL
	 **/
	protected function is_rtl( $lang ) {
		$locale_file = WP_LANG_DIR . "/$lang.php";
		if ( ( 0 === validate_file( $lang ) ) && is_readable( $locale_file ) ) {
			$locale_file_code = file_get_contents( $locale_file );
			// Regex to find something looking like $text_direction = 'rtl';
			return (bool) preg_match( '/\$text_direction\s?=\s?[\'|"]rtl[\'|"]\s?;/i', $locale_file_code );
		}
		return false;
	}
}

$babble_languages = new Babble_Languages();

?>