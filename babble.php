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

require_once 'class-babble-log.php';

require_once 'api.php';
require_once 'deprecated.php';
require_once 'widget.php';

require_once 'class-plugin.php';

require_once 'class-jobs.php';
require_once 'class-meta.php';
require_once 'class-languages.php';
require_once 'class-locale.php';
require_once 'class-post-public.php';
require_once 'class-comment.php';
require_once 'class-taxonomy.php';
require_once 'class-switcher-content.php';
require_once 'class-switcher-interface.php';
require_once 'class-admin-bar.php';
require_once 'class-translator.php';

require_once 'miscellaneous.php';

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


// Globals

global $bbl_log;
$bbl_log = Babble::get( 'log' );

global $bbl_jobs;
$bbl_jobs = Babble::get( 'jobs' );

global $bbl_languages;
$bbl_languages = Babble::get( 'languages' );

global $bbl_locale;
$bbl_locale = Babble::get( 'locale' );

global $bbl_post_public;
$bbl_post_public = Babble::get( 'post_public' );

global $bbl_comment;
$bbl_comment = Babble::get( 'comment' );

global $bbl_taxonomies;
$bbl_taxonomies = Babble::get( 'taxonomies' );

global $bbl_switcher_menu;
$bbl_switcher_menu = Babble::get( 'switcher_menu' );

global $bbl_switcher_interface;
$bbl_switcher_interface = Babble::get( 'switcher_interface' );

global $bbl_admin_bar;
$bbl_admin_bar = Babble::get( 'admin_bar' );

global $bbl_translator;
$bbl_translator = Babble::get( 'translator' );
