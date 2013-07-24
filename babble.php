<?php

/*
Plugin Name: Babble
Plugin URI: http://simonwheatley.co.uk/wordpress/babble
Description: Now with Taxonomies!
Version: Alpha 1.3
Author: Simon Wheatley
Author URI: http://simonwheatley.co.uk/wordpress/
*/
 
/*  Copyright 2011 Simon Wheatley

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
 * @copyright Copyright (C) Simon Wheatley (except where noted)
 */

require_once( 'class-babble-log.php' );

require_once( 'api.php' );

require_once( 'class-plugin.php' );

// require_once( 'class-jobs.php' );
require_once( 'class-languages.php' );
require_once( 'class-locale.php' );
require_once( 'class-post-public.php' );
require_once( 'class-comment.php' );
require_once( 'class-taxonomy.php' );
require_once( 'class-switcher-content.php' );
require_once( 'class-switcher-interface.php' );
require_once( 'class-admin-bar.php' );

require_once( 'miscellaneous.php' );
