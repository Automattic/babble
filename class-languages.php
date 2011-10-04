<?php

/**
 * Manages the languages available for the site.
 *
 * @package WordPress
 * @subpackage Babble
 * @since 0.1
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
	 * The languages selected for this site.
	 *
	 * @var array
	 **/
	protected $active_langs;
	
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
		$this->setup( 'babble-languages' );
		$this->add_action( 'admin_menu', 'admin_menu' );
		$this->add_action( 'load-settings_page_babble_languages', 'load_options' );
		if ( $this->available_langs = $this->get_option( 'available_langs', false ) ) {
			error_log( "Refresh available langs" );
			$this->parse_available_languages();
		}
		$this->lang_prefs = $this->get_option( 'lang_prefs', array() );
		$this->active_langs = $this->get_option( 'active_langs', array() );
	}
	
	// WP HOOKS
	// ========

	/**
	 * Hooks the WP admin_menu action 
	 *
	 * @param  
	 * @return void
	 **/
	public function admin_menu() {
		add_options_page( __( 'Available Languages', 'babble' ), __( 'Available Languages' ), 'manage_options', 'babble_languages', array( $this, 'options' ) );
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
				$lang->url_prefix = $lang->code_short;
			$lang->text_direction = ( @ isset( $_POST[ "text_direction_$code" ] ) ) ? $_POST[ "text_direction_$code" ] : @ $lang->text_direction;
			// This line must come after the text direction value is set
			$lang->input_lang_class = ( 'rtl' == $lang->text_direction ) ? 'lang-rtl' : 'lang-ltr' ;
			$lang->display_name = ( @ isset( $_POST[ "display_name_$code" ] ) ) ? $_POST[ "display_name_$code" ] : @ $lang->display_name;
			if ( ! $lang->display_name )
				$lang->display_name = $lang->names;
			// Note any url_prefix errors
			$lang->url_prefix_error = ( @ $this->errors[ "url_prefix_$code" ] ) ? 'babble-error' : '0' ;
			// Flag the active languages
			$lang->active = false;
			if ( in_array( $code, $this->active_langs ) )
				$lang->active = true;
			
		}
		$vars = array();
		$vars[ 'langs' ] = $langs;
		$this->render_admin( 'options-available-languages.php', $vars );
	}
	
	// PUBLIC METHODS
	// ==============

	// None… yet…
	
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

		// Now save the language preferences

		$lang_prefs = array();
		$url_prefixes = array();
		foreach ( $this->available_langs as $code => $lang ) {
			$lang_pref = new stdClass;
			$lang_pref->display_name = @ $_POST[ 'display_name_' . $code ];
			$lang_pref->url_prefix = @ $_POST[ 'url_prefix_' . $code ];
			$lang_pref->text_direction = @ $_POST[ 'text_direction_' . $code ];
			// Ensure text_direction is only 'ltr' or 'rtl'
			$lang_pref->text_direction = ( 'rtl' == $lang_pref->text_direction ) ? 'rtl' : 'ltr';
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
		
		// Now save the active languages
		
		if ( ! $this->errors ) {
			$langs = $this->merge_lang_sets( $this->available_langs, $this->lang_prefs );
			$active_langs = array();
			foreach ( (array) @ $_POST[ 'active_langs' ] as $code )
				$active_langs[ $langs[ $code ]->url_prefix ] = $code;
			if ( count( $active_langs ) < 2 ) {
				$this->set_admin_error( __( 'You must set at least two languages as active.', 'babble' ) );
				$this->errors[ 'active_langs' ];
			} else {
				$this->active_langs = $active_langs;
				$this->update_option( 'active_langs', $this->active_langs );
			}
		}
		
		// Finish up, redirecting if we're all OK
		if ( ! $this->errors ) {
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
  	 * 			public 'names' => string 'Arabic'
  	 * 			public 'code' => string 'ar'
  	 * 			public 'code_short' => string 'ar'
  	 * 			public 'text_direction' => string 'rtl'
 	 * 
	 * @return void
	 **/
	protected function parse_available_languages() {
		unset( $this->available_langs );
		$this->available_langs = array();
		foreach ( glob( WP_LANG_DIR . '/*.mo' ) as $mo_file ) {
			preg_match( '/(([a-z]+)(_[a-z]+)?)\.mo$/i', $mo_file, $matches );
			$lang = array(
				'names' => $this->format_code_lang( $matches[ 2 ] ),
				'code' => $matches[ 1 ],
				'code_short' => $matches[ 2 ],
				'text_direction' => $this->is_rtl( $matches[ 1 ] ),
			);
			// Cast to an object, in case we want to start using actual classes
			// at some point in the future.
			$this->available_langs[ $matches[ 1 ] ] = (object) $lang;
		}
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
	 * in wp-admin/includes/ms.php
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
		return strtr( $code, $lang_codes );
	}

}

$babble_languages = new Babble_Languages();

?>