<?php
/*
Plugin Name: Babble: Global Variables
Plugin URI:  http://babbleplugin.com/
Description: Back-compat plugin for sites which need to retain Babble's global variables, which were used prior to version 1.6.
Version:     1.0
Author:      Automattic
Author URI:  https://automattic.com/
Text Domain: babble
Domain Path: /languages/
License:     GPL v2 or later

Copyright 2011-2015 Automattic Ltd

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
 * Adds Babble's components as global variables.
 */
function babble_globals() {
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
}

add_action( 'plugins_loaded', 'babble_globals', 1 );
