<?php

/**
 * Class for handling all the options
 *
 * All options are filtered in a way you can have a "shadow" option for each option.
 * 
 * In theory this works with all  WordPress core options as well as any plugin option
 * 
 * TODO: There is something preventing this from working well with widgets, needs investigation
 * 
 * 
 * @package Babble
 * @since Alpha 1
 */
class Babble_Options extends Babble_Plugin {
	
    
    /**
	 * Used to store the values when saving options in different languages
	 *
	 * @var boolean
	 **/
	protected $options_restore;
    
	/**
	 * Construction time!
	 *
	 * @return void
	 **/
	public function __construct() {
        
        $this->options_restore = array();
        
        $this->add_action('init', null, 20);
        
        $this->add_action('update_option', null, null, 3);
        $this->add_action('updated_option', null, null, 3);
        
        
	}

	/**
	 * Hooks the WP admin_init action 
	 *
	 * @return void
	 **/
	public function log( $msg ) {
		if ( $this->logging )
			error_log( "[$this->session] BABBLE LOG: $msg" );
	}
    
    /**
	 * Checks if a option is a core Babble option based on its prefix
     * 
     * TODO: It would be nice if all plugin options used the same prefix
	 *
	 * @param string $option_name name of the option
	 * @return bool true if is a Babble option, false if its not
	 **/
    protected function is_babble_option($option_name) {
        
        return preg_match('/^bbl_\S+/', $option_name) || preg_match('/^bbl-\S+/', $option_name) || preg_match('/^babble-\S+/', $option_name);
        
    }
    
    /**
	 * Register the pre_option_ hooks
	 *
	 * @return void
	 **/
    public function init() {
        global $wpdb;

        
        //TODO: This looks bad, but thats because there is not a generic get_option hook :( Maybe create a ticket on trac but I (leogermani) have been in this discssion before
        
        $all_options = $wpdb->get_col("SELECT option_name FROM $wpdb->options");
        
        foreach ($all_options as $o) {
            if (!$this->is_babble_option($o)) {
                $this->add_filter('pre_option_' . $o, 'pre_option');
            }
        }
        
    }
    
    
    /**
	 * Hooks update_option
	 *
	 * @return void
	 **/
    public function update_option($option, $old_value, $new_value) {
        
        if ($this->is_babble_option($option))
            return false;
        
        if (bbl_get_current_lang_code() == bbl_get_default_lang_code())
            return false;
        
        // We need to save the value before its updated so we can restore it afterwards
        // On the updated_option hook we can not trust $old_value, because its already filtered in the get_option() call
        
        // save value
        global $wpdb;
        $this->options_restore[$option] = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = '$option' LIMIT 1");
        
    }
    
    /**
	 * Hooks updated_option
	 *
	 * @return void
	 **/
    public function updated_option($option, $old_value, $new_value) {

        if ($this->is_babble_option($option))
            return false;
        
        if (bbl_get_current_lang_code() == bbl_get_default_lang_code())
            return false;
        
        global $wpdb;
        
        // restore old_value
        $old_value = $this->options_restore[$option];
        
        $wpdb->update( $wpdb->options, array( 'option_value' => $old_value ), array( 'option_name' => $option ) );
        
        //save new value to the language option
        update_option('bbl_' . bbl_get_current_lang_code() . '_' . $option, $new_value);

    }
    
    /**
	 * Hooks pre_option
	 *
	 * @return void
	 **/
    public function pre_option($r) {
        
        $option = str_replace('pre_option_', '', current_filter());
        
        global $wpdb;
        
        $default_language = bbl_get_default_lang_code();
        $current_language = bbl_get_current_lang_code();
        
        if ($current_language != $default_language && $value = get_option('bbl_' . $current_language . '_' . $option, false) ) {
            return $value;
        } else {
            return false;
        }
        

    }

}

global $bbl_options;
$bbl_options = new Babble_Options();

?>
