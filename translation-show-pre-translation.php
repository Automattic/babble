<?php
/*
Plugin Name: Babble: Publish Prior to Translation
Plugin URI:  http://babbleplugin.com/
Description: When a post is queued for translation, immediately show the default language content while awaiting translation
Version:     1.0
Author:      Automattic
Author URI:  https://automattic.com/
Text Domain: babble
Domain Path: /languages/
License:     GPL v2 or later

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

add_action( 'bbl_create_empty_translation', '__return_true' );