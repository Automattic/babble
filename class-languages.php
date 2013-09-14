<?php

/**
 * Manages the languages available for the site.
 *
 * @package Babble
 * @since Alpha 1.1
 */
class Babble_Languages extends Babble_Plugin {
	
	/**
	 * The languages available within the system.
	 *
	 * @var array
	 **/
	protected $available_langs;
	
	/**
	 * The language preferences set for this site, 
	 * i.e. url prefixes and display names.
	 *
	 * @var array
	 **/
	protected $lang_prefs;
	
	/**
	 * An array of language codes, keyed by language prefix
	 * for all the languages selected as ACTIVE for this site.
	 *
	 * Active languages are available to admins to add content.
	 *
	 * @var array
	 **/
	protected $active_langs;
	
	/**
	 * An array of language codes, keyed by language prefix
	 * for all the languages selected as PUBLIC for this site.
	 *
	 * Public languages are available to readers of the site
	 * to read.
	 *
	 * @var array
	 **/
	protected $public_langs;

	/**
	 * The language code for the default language.
	 *
	 * @var string
	 **/
	protected $default_lang;
	
	/**
	 * The current version for purposes of rewrite rules, any 
	 * DB updates, cache busting, etc
	 *
	 * @var int
	 **/
	protected $version = 1;
	
	/**
	 * Any fields to show errors on, currently only used by URL Prefix fields.
	 *
	 * @var array
	 **/
	protected $errors;
	
	/**
	 * Setup any add_action or add_filter calls. Initiate properties.
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->setup( 'babble-languages', 'plugin' );
		$this->add_action( 'admin_menu', 'admin_menu' );
		$this->add_action( 'admin_notices', 'admin_notices' );
		$this->add_action( 'load-settings_page_babble_languages', 'load_options' );


		$this->initiate();
	}

	/**
	 * (Re)initiates the properties of this object.
	 *
	 * @return void
	 **/
	public function initiate() {
		if ( ! ( $this->available_langs = $this->get_option( 'available_langs', false ) ) ) {
			$this->parse_available_languages();
		}
		$this->active_langs = $this->get_option( 'active_langs', array() );
		$this->langs = $this->get_option( 'langs', array() );
		$this->lang_prefs = $this->get_option( 'lang_prefs', array() );
		$this->langs = $this->merge_lang_sets( $this->langs, $this->lang_prefs );
		$this->default_lang = $this->get_option( 'default_lang', 'en_US' );
		$this->public_langs = $this->get_option( 'public_langs', array( $this->default_lang ) );
		// @FIXME: Add something in so the user gets setup with the single language they are currently using
		if ( ! $this->get_option( 'active_langs', false ) || ! $this->get_option( 'default_lang', false ) )
			$this->set_defaults();
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP admin_notices action to warn the admin
	 * if the available languages need to be set up.
	 *
	 * @return void
	 **/
	public function admin_notices() {
		if ( get_current_screen()->id == 'settings_page_babble_languages' )
			return;
		if ( ! $this->get_option( 'active_langs', false ) || ! $this->get_option( 'default_lang', false ) ) {
			printf( '<div class="error"><p>%s</p></div>', sprintf( __( '<strong>Babble setup:</strong> Please visit the <a href="%s">Available Languages settings</a> and setup your available languages and the default language.', 'babble' ), admin_url( 'options-general.php?page=babble_languages' ) ) );
		}
	}

	/**
	 * Hooks the WP admin_menu action 
	 *
	 * @return void
	 **/
	public function admin_menu() {
		add_options_page( __( 'Available Languages', 'babble' ), __( 'Available Languages' , 'babble'), 'manage_options', 'babble_languages', array( $this, 'options' ) );
	}
	
	/**
	 * Hooks the load action for the options page.
	 *
	 * @return void
	 **/
	public function load_options() {
		wp_enqueue_style( 'babble_languages_options', $this->url( '/css/languages-options.css' ), null, $this->version );
		$this->maybe_process_languages();
	}
	
	// CALLBACKS
	// =========

	/**
	 * Callback function to provide the HTML for the "Available Languages"
	 * options page.
	 *
	 * @return void
	 **/
	public function options() {
		// Refresh the current languages
		$this->parse_available_languages();
		// Merge in our previously set language settings
		$langs = $this->merge_lang_sets( $this->available_langs, $this->lang_prefs );
		// Merge in any POSTed field values
		foreach ( $langs as $code => & $lang ) {
			$lang->url_prefix = ( @ isset( $_POST[ 'url_prefix_' . $code ] ) ) ? $_POST[ "url_prefix_$code" ] : @ $lang->url_prefix;
			if ( ! $lang->url_prefix )
				$lang->url_prefix = $lang->url_prefix;
			$lang->text_direction = $lang->text_direction;
			// This line must come after the text direction value is set
			$lang->input_lang_class = ( 'rtl' == $lang->text_direction ) ? 'lang-rtl' : 'lang-ltr' ;
			$lang->display_name = ( @ isset( $_POST[ "display_name_$code" ] ) ) ? $_POST[ "display_name_$code" ] : @ $lang->display_name;
			if ( ! $lang->display_name )
				$lang->display_name = $lang->name;
			// Note any url_prefix errors
			$lang->url_prefix_error = ( @ $this->errors[ "url_prefix_$code" ] ) ? 'babble-error' : '0' ;
			// Flag the active languages
			$lang->active = false;
			if ( in_array( $code, $this->active_langs ) )
				$lang->active = true;
			
		}
		$vars = array();
		$vars[ 'langs' ] = $langs;
		$vars[ 'default_lang' ] = $this->default_lang;
		$vars[ 'active_langs' ] = $this->get_active_langs();
		$this->render_admin( 'options-available-languages.php', $vars );
	}
	
	// PUBLIC METHODS
	// ==============

	/**
	 * Set the active language objects for the current site, keyed
	 * by URL prefix.
	 * 
	 * @return array An array of Babble language objects
	 **/
	public function set_active_langs( $lang_codes ) {
		$this->parse_available_languages();
		error_log( "SW: WP_LANG_DIR: " . WP_LANG_DIR );
		$this->active_langs = $lang_codes;
	}

 	/**
	 * Return the active language objects for the current site, keyed
	 * by URL prefix. A language object looks like:
	 * 'ar' => 
	 * 		object(stdClass)
	 * 			public 'name' => string 'Arabic'
	 * 			public 'code' => string 'ar'
	 * 			public 'url_prefix' => string 'ar'
	 * 			public 'text_direction' => string 'rtl'
	 * 			public 'display_name' => string 'Arabic'
	 * 
	 * @return array An array of Babble language objects
	 **/
	public function get_active_langs() {
		$langs = array();
		foreach ( $this->active_langs as $url_prefix => $code )
			$langs[ $url_prefix ] = $this->langs[ $code ];
		return $langs;
	}

	/**
	 * Given a lang object or lang code, this checks whether the
	 * language is public or not.
	 * 
	 * @param string $lang_code A language code
	 * @return boolean True if public
	 **/
	public function is_public_lang( $lang_code ) {
		if ( ! is_string( $lang_code ) )
			throw new exception( 'Please provide a lang_code for the is_public_lang method.' );
		return in_array( $lang_code, $this->public_langs );
	}

	/**
 	 * Returns the requested language object.
	 *
	 * @param string $code A language code, e.g. "fr_BE" 
	 * @return object|boolean A Babble language object
	 **/
	public function get_lang( $lang_code ) {
		if ( ! isset( $this->langs[ $lang_code ] ) )
			return false;
		return $this->langs[ $lang_code ];
	}

	/**
	 * Returns the current language object, respecting any
	 * language switches; i.e. if your request was for
	 * Arabic, but the language is currently switched to
	 * French, this will return French.
	 *
	 * @return object|boolean A Babble language object
	 **/
	public function get_current_lang() {
		global $bbl_locale;
		return $this->get_lang( $bbl_locale->get_lang() );
	}

	/**
	 * Returns the default language code for this site.
	 *
	 * @return string A language code, e.g. "he_IL"
	 **/
	public function get_default_lang_code() {
		return $this->default_lang;
	}

	/**
	 * Returns the default language for this site.
	 *
	 * @return object The language object for the default language
	 **/
	public function get_default_lang() {
		return bbl_get_lang( $this->default_lang );
	}
	
	/**
	 * Given a language code, return the URL prefix.
	 *
	 * @param string $code A language code, e.g. "fr_BE" 
	 * @return bool|string A URL prefix, as set by the admin when editing the lang prefs, or false if no language
	 **/
	public function get_url_prefix_from_code( $code ) {
		if ( ! isset( $this->langs[ $code ]->url_prefix ) )
			return false;
		return $this->langs[ $code ]->url_prefix;
	}
	
	/**
	 * Given a URL prefix, return the language code.
	 *
	 * @param string $code A URL prefix, e.g. "de", as set by the admin
	 * @return bool|string A language code, e.g. "de_DE", or false if no language
	 **/
	public function get_code_from_url_prefix( $url_prefix ) {
		if ( ! isset( $this->active_langs[ $url_prefix ] ) )
			return false;
		return $this->active_langs[ $url_prefix ];
	}
	
	// PRIVATE/PROTECTED METHODS
	// =========================

	/**
	 * Merge two arrays of language objects. If a language exists in
	 * $langs_b that doesn't in $langs_a, it will be added to the 
	 * final array. If a language has a property in both arrays, the
	 * property value from $langs_b will overwrite the property value
	 * in $langs_a. If a language in $langs_b has a property that 
	 * doesn't exist in $langs_a then it will be added to that
	 * language in the final array.
	 *
	 * @param array $langs_a An array of language objects
	 * @param array $langs_b An array of language objects
	 * @return array An array of language objects
	 **/
	protected function merge_lang_sets( $langs_a, $langs_b ) {
		$langs = array();
		foreach ( $langs_a as $code => $lang_a ) {
			// Langs only in A get copied from A, simple.
			if ( ! isset( $langs_b[ $code ] ) ) {
				$langs[ $code ] = $lang_a;
				continue;
			}
			// The properties of langs in both A & B are merged
			$langs[ $code ] = $lang_a;
			$lang_b = $langs_b[ $code ];
			foreach ( $lang_b as $p => $v )
				$langs[ $code ]->$p = $v;
		}
		return $langs;
	}
	
	/**
	 * Checks if there is a POSTed request to process. Checks it's properly
	 * nonced up. Processes it. Redirects if there's no errors.
	 *
	 * @return void
	 **/
	protected function maybe_process_languages() {
		if ( ! isset( $_POST[ '_babble_nonce' ] ) )
			return;
		check_admin_referer( 'babble_lang_prefs', '_babble_nonce' );

		// Now save the language preferences for all languages

		$lang_prefs = array();
		$url_prefixes = array();
		foreach ( $this->available_langs as $code => $lang ) {
			$lang_pref = new stdClass;
			$lang_pref->display_name = @ $_POST[ 'display_name_' . $code ];
			$lang_pref->url_prefix = @ $_POST[ 'url_prefix_' . $code ];
			// Check we don't have more than one language using the same url prefix
			if ( array_key_exists( $lang_pref->url_prefix, $url_prefixes ) ) {
				$lang_1 = $this->format_code_lang( $code );
				$lang_2 = $this->format_code_lang( $url_prefixes[ $lang_pref->url_prefix ] );
				$msg = sprintf( __( 'The languages "%1$s" and "%2$s" are using the same URL Prefix. Each URL prefix should be unique.', 'babble' ), $lang_1, $lang_2 );
				$this->set_admin_error( $msg );
				$this->errors[ 'url_prefix_' . $lang_pref->url_prefix ] = true;
				$this->errors[ "url_prefix_$code" ] = true;
			} else {
				$url_prefixes[ $lang_pref->url_prefix ] = $code;
			}
			$lang_prefs[ $code ] = $lang_pref;
		}
		
		error_log( "SW: Available langs: " . print_r( $this->available_langs, true ) );
		error_log( "SW: Lang prefs: " . print_r( $lang_prefs, true ) );
		
		// Now save the active languages, i.e. the selected languages
		
		if ( ! $this->errors ) {
			$langs = $this->merge_lang_sets( $this->available_langs, $this->lang_prefs );
			$active_langs = array();
			foreach ( (array) @ $_POST[ 'active_langs' ] as $code )
				$active_langs[ $langs[ $code ]->url_prefix ] = $code;
			if ( count( $active_langs ) < 2 ) {
				$this->set_admin_error( __( 'You must set at least two languages as active.', 'babble' ) );
			} else {
				$this->active_langs = $active_langs;
				$this->update_option( 'active_langs', $this->active_langs );
				$this->langs = $langs;
				$this->update_option( 'langs', $this->langs );
			}
			if ( ! isset( $_POST[ 'public_langs' ] ) ) {
				$this->set_admin_error( __( 'You must set at least your default language as public.', 'babble' ) );
			} else {
				$public_langs = (array) $_POST[ 'public_langs' ];
				if ( ! in_array( @ $_POST[ 'default_lang' ], $public_langs ) )
					$this->set_admin_error( __( 'You must set your default language as public.', 'babble' ) );
			}
		}
		// Finish up, redirecting if we're all OK
		if ( ! $this->errors ) {
			// Save the public languages
			$this->update_option( 'public_langs', $public_langs );
			
			// First the default language
			$default_lang = @ $_POST[ 'default_lang' ];
			$this->update_option( 'default_lang', $default_lang );
			// Now the prefs
			$this->update_option( 'lang_prefs', $lang_prefs );
			// Now set a reassuring message and redirect back to the clean settings page
			$this->set_admin_notice( __( 'Your language settings have been saved.', 'babble' ) );
			$url = admin_url( 'options-general.php?page=babble_languages' );
			wp_redirect( $url );
			exit;
		}
	}
	
	/**
	 * Parse the files in wp-content/languages and work out what 
	 * languages we've got available. Populates self::available_langs
	 * with an array of language objects which look like:
  	 * 'ar' => 
  	 * 		object(stdClass)
  	 * 			public 'name' => string 'Arabic'
  	 * 			public 'code' => string 'ar'
  	 * 			public 'url_prefix' => string 'ar'
  	 * 			public 'text_direction' => string 'rtl'
 	 * 
	 * @return void
	 **/
	protected function parse_available_languages() {
		unset( $this->available_langs );
		$this->available_langs = array();
		foreach ( get_available_languages() as $lang_code ) {
			list( $prefix ) = explode( '_', $lang_code );
			$lang = array(
				'name' => $this->format_code_lang( $prefix ),
				'code' => $lang_code,
				'url_prefix' => $prefix,
				'text_direction' => $this->is_rtl( $lang_code ),
			);
			// Cast to an object, in case we want to start using actual classes
			// at some point in the future.
			$this->available_langs[ $lang_code ] = (object) $lang;
		}
		// Add in US English, which is the default on WordPress and has no language files
		$en = new stdClass;
		$en->name = 'English (US)';
		$en->code = 'en_US';
		$en->url_prefix = 'en';
		$en->text_direction = 'ltr';
		$this->available_langs[ 'en_US' ] = $en;
		$this->available_langs = apply_filters( 'bbl_available_langs', $this->available_langs );
		ksort( $this->available_langs );
		$this->update_option( 'available_langs', $this->available_langs );
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
			// Regex to find something looking like: $text_direction = 'rtl';
			return ( (bool) preg_match( '/\$text_direction\s?=\s?[\'|"]rtl[\'|"]\s?;/i', $locale_file_code ) ) ? 'rtl' : 'ltr';
		}
		return 'ltr';
	}
	
	/**
	 * Return the language name for the provided language code.
	 *
	 * This method is an identical copy of format_code_lang 
	 * in wp-admin/includes/ms.php which is only available on Multisite.
	 *
	 * @FIXME: We end up with a load of anglicised names, which doesn't seem super-friendly, internationally speaking.
	 * 
	 * @see format_code_lang()
	 *
	 * @param string $lang_short The language short code, e.g. 'en' (not 'en_GB')
	 * @return string The language name, e.g. 'English'
	 **/
	protected function format_code_lang( $code ) {
		$code = strtolower( substr( $code, 0, 2 ) );
		$lang_codes = array(
			'aa' => 'Afar', 'ab' => 'Abkhazian', 'af' => 'Afrikaans', 'ak' => 'Akan', 'sq' => 'Albanian', 'am' => 'Amharic', 'ar' => 'Arabic', 'an' => 'Aragonese', 'hy' => 'Armenian', 'as' => 'Assamese', 'av' => 'Avaric', 'ae' => 'Avestan', 'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir', 'bm' => 'Bambara', 'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali',
			'bh' => 'Bihari', 'bi' => 'Bislama', 'bs' => 'Bosnian', 'br' => 'Breton', 'bg' => 'Bulgarian', 'my' => 'Burmese', 'ca' => 'Catalan; Valencian', 'ch' => 'Chamorro', 'ce' => 'Chechen', 'zh' => 'Chinese', 'cu' => 'Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic', 'cv' => 'Chuvash', 'kw' => 'Cornish', 'co' => 'Corsican', 'cr' => 'Cree',
			'cs' => 'Czech', 'da' => 'Danish', 'dv' => 'Divehi; Dhivehi; Maldivian', 'nl' => 'Dutch; Flemish', 'dz' => 'Dzongkha', 'en' => 'English', 'eo' => 'Esperanto', 'et' => 'Estonian', 'ee' => 'Ewe', 'fo' => 'Faroese', 'fj' => 'Fijjian', 'fi' => 'Finnish', 'fr' => 'French', 'fy' => 'Western Frisian', 'ff' => 'Fulah', 'ka' => 'Georgian', 'de' => 'German', 'gd' => 'Gaelic; Scottish Gaelic',
			'ga' => 'Irish', 'gl' => 'Galician', 'gv' => 'Manx', 'el' => 'Greek, Modern', 'gn' => 'Guarani', 'gu' => 'Gujarati', 'ht' => 'Haitian; Haitian Creole', 'ha' => 'Hausa', 'he' => 'Hebrew', 'hz' => 'Herero', 'hi' => 'Hindi', 'ho' => 'Hiri Motu', 'hu' => 'Hungarian', 'ig' => 'Igbo', 'is' => 'Icelandic', 'io' => 'Ido', 'ii' => 'Sichuan Yi', 'iu' => 'Inuktitut', 'ie' => 'Interlingue',
			'ia' => 'Interlingua (International Auxiliary Language Association)', 'id' => 'Indonesian', 'ik' => 'Inupiaq', 'it' => 'Italian', 'jv' => 'Javanese', 'ja' => 'Japanese', 'kl' => 'Kalaallisut; Greenlandic', 'kn' => 'Kannada', 'ks' => 'Kashmiri', 'kr' => 'Kanuri', 'kk' => 'Kazakh', 'km' => 'Central Khmer', 'ki' => 'Kikuyu; Gikuyu', 'rw' => 'Kinyarwanda', 'ky' => 'Kirghiz; Kyrgyz',
			'kv' => 'Komi', 'kg' => 'Kongo', 'ko' => 'Korean', 'kj' => 'Kuanyama; Kwanyama', 'ku' => 'Kurdish', 'lo' => 'Lao', 'la' => 'Latin', 'lv' => 'Latvian', 'li' => 'Limburgan; Limburger; Limburgish', 'ln' => 'Lingala', 'lt' => 'Lithuanian', 'lb' => 'Luxembourgish; Letzeburgesch', 'lu' => 'Luba-Katanga', 'lg' => 'Ganda', 'mk' => 'Macedonian', 'mh' => 'Marshallese', 'ml' => 'Malayalam',
			'mi' => 'Maori', 'mr' => 'Marathi', 'ms' => 'Malay', 'mg' => 'Malagasy', 'mt' => 'Maltese', 'mo' => 'Moldavian', 'mn' => 'Mongolian', 'na' => 'Nauru', 'nv' => 'Navajo; Navaho', 'nr' => 'Ndebele, South; South Ndebele', 'nd' => 'Ndebele, North; North Ndebele', 'ng' => 'Ndonga', 'ne' => 'Nepali', 'nn' => 'Norwegian Nynorsk; Nynorsk, Norwegian', 'nb' => 'Bokmål, Norwegian, Norwegian Bokmål',
			'no' => 'Norwegian', 'ny' => 'Chichewa; Chewa; Nyanja', 'oc' => 'Occitan, Provençal', 'oj' => 'Ojibwa', 'or' => 'Oriya', 'om' => 'Oromo', 'os' => 'Ossetian; Ossetic', 'pa' => 'Panjabi; Punjabi', 'fa' => 'Persian', 'pi' => 'Pali', 'pl' => 'Polish', 'pt' => 'Portuguese', 'ps' => 'Pushto', 'qu' => 'Quechua', 'rm' => 'Romansh', 'ro' => 'Romanian', 'rn' => 'Rundi', 'ru' => 'Russian',
			'sg' => 'Sango', 'sa' => 'Sanskrit', 'sr' => 'Serbian', 'hr' => 'Croatian', 'si' => 'Sinhala; Sinhalese', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'se' => 'Northern Sami', 'sm' => 'Samoan', 'sn' => 'Shona', 'sd' => 'Sindhi', 'so' => 'Somali', 'st' => 'Sotho, Southern', 'es' => 'Spanish; Castilian', 'sc' => 'Sardinian', 'ss' => 'Swati', 'su' => 'Sundanese', 'sw' => 'Swahili',
			'sv' => 'Swedish', 'ty' => 'Tahitian', 'ta' => 'Tamil', 'tt' => 'Tatar', 'te' => 'Telugu', 'tg' => 'Tajik', 'tl' => 'Tagalog', 'th' => 'Thai', 'bo' => 'Tibetan', 'ti' => 'Tigrinya', 'to' => 'Tonga (Tonga Islands)', 'tn' => 'Tswana', 'ts' => 'Tsonga', 'tk' => 'Turkmen', 'tr' => 'Turkish', 'tw' => 'Twi', 'ug' => 'Uighur; Uyghur', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek',
			've' => 'Venda', 'vi' => 'Vietnamese', 'vo' => 'Volapük', 'cy' => 'Welsh','wa' => 'Walloon','wo' => 'Wolof', 'xh' => 'Xhosa', 'yi' => 'Yiddish', 'yo' => 'Yoruba', 'za' => 'Zhuang; Chuang', 'zu' => 'Zulu' );
		$lang_codes = apply_filters( 'lang_codes', $lang_codes, $code );
		$lang_codes = apply_filters( 'bbl_lang_codes', $lang_codes );
		return strtr( $code, $lang_codes );
	}

	/**
	 * Setup some initial language data, so the user's site doesn't immediately
	 * fail when the plugin is activated.
	 *
	 * @return void
	 **/
	protected function set_defaults() {
		// WPLANG is defined in wp-config.
		if ( defined( 'WPLANG' ) )
			$locale = WPLANG;

		// If multisite, check options.
		if ( is_multisite() && !defined('WP_INSTALLING') ) {
			$ms_locale = get_option('WPLANG');
			if ( $ms_locale === false )
				$ms_locale = get_site_option('WPLANG');

			if ( $ms_locale !== false )
				$locale = $ms_locale;
		}

		if ( empty( $locale ) )
			$locale = 'en_US';

		$url_prefix = strtolower( substr( $locale, 0, 2 ) );

		$this->active_langs = array( $url_prefix => $locale );

		$this->langs = array( $locale => $this->available_langs[ $locale ] );
		$this->langs[ $locale ]->url_prefix = $url_prefix;
		$this->langs[ $locale ]->display_name = $this->langs[ $locale ]->name;
		$this->default_lang = $locale;
		$this->public_langs = array( $locale );
	}
}

global $bbl_languages;
$bbl_languages = new Babble_Languages();
