<?php
/*
Plugin Name: Babble
Plugin URI:  http://babbleplugin.com/
Description: Multilingual WordPress done right
Version:     1.5.1
Author:      Automattic
Author URI:  https://automattic.com/
Text Domain: babble
Domain Path: /languages/
License:     GPL v2 or later

Copyright 2011-2015 Simon Wheatley, Code For The People Ltd, & Automattic Ltd

                _____________
               /      ____   \
         _____/       \   \   \
        /\    \        \___\   \
       /  \    \                \
      /   /    /          _______\
     /   /    /          \       /
    /   /    /            \     /
    \   \    \ _____    ___\   /
     \   \    /\    \  /       \
      \   \  /  \____\/    _____\
       \   \/        /    /    / \
        \           /____/    /___\
         \                        /
          \______________________/


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

/**
 * Main plugin information and requires.
 *
 * @package Babble
 * @since Alpha 1
 * @copyright Copyright (c) Simon Wheatley & Code For The People Ltd (except where noted)
 */

define( 'BABBLE_PLUGIN_DIR', dirname(__FILE__) );

require_once BABBLE_PLUGIN_DIR . '/class-babble-log.php';

require_once BABBLE_PLUGIN_DIR . '/api.php';
require_once BABBLE_PLUGIN_DIR . '/deprecated.php';
require_once BABBLE_PLUGIN_DIR . '/widget.php';

require_once BABBLE_PLUGIN_DIR . '/class-plugin.php';

require_once BABBLE_PLUGIN_DIR . '/class-jobs.php';
require_once BABBLE_PLUGIN_DIR . '/class-meta.php';
require_once BABBLE_PLUGIN_DIR . '/class-languages.php';
require_once BABBLE_PLUGIN_DIR . '/class-locale.php';
require_once BABBLE_PLUGIN_DIR . '/class-post-public.php';
require_once BABBLE_PLUGIN_DIR . '/class-comment.php';
require_once BABBLE_PLUGIN_DIR . '/class-taxonomy.php';
require_once BABBLE_PLUGIN_DIR . '/class-switcher-content.php';
require_once BABBLE_PLUGIN_DIR . '/class-switcher-interface.php';
require_once BABBLE_PLUGIN_DIR . '/class-admin-bar.php';
require_once BABBLE_PLUGIN_DIR . '/class-translator.php';

require_once BABBLE_PLUGIN_DIR . '/miscellaneous.php';

final class Babble {

	private static $registry = array();

	private function __construct() {
	}

	public static function set( $name, $instance ) {
		self::$registry[ $name ] = $instance;
	}

	public static function get( $name ) {
		if ( array_key_exists( $name, self::$registry ) ) {
			return self::$registry[ $name ];
		} else {
			return null;
		}
	}

}

function babble_init() {
	load_plugin_textdomain( 'babble', false, dirname( plugin_basename( __FILE__ ) ) . '/locale' );
}
add_action( 'init', 'babble_init' );

// Registry

Babble::set( 'log',                new Babble_Log );
Babble::set( 'jobs',               new Babble_Jobs );
Babble::set( 'languages',          new Babble_Languages );
Babble::set( 'locale',             new Babble_Locale );
Babble::set( 'post_public',        new Babble_Post_Public );
Babble::set( 'comment',            new Babble_Comment );
Babble::set( 'taxonomies',         new Babble_Taxonomies );
Babble::set( 'switcher_menu',      new Babble_Switcher_Menu );
Babble::set( 'switcher_interface', new Babble_Switcher_Interface );
Babble::set( 'admin_bar',          new Babble_Admin_bar );
Babble::set( 'translator',         new Babble_Translator );
