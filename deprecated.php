<?php

/**
 * Deprecated API functions.
 *
 * @since 1.4
 * @package Babble
 */

/*  Copyright 2013 Code For The People Ltd

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

global $bbl_translating;
$bbl_translating = true;

/**
 * Start doing translation.
 *
 * @return void
 **/
function bbl_start_translating() {
	global $bbl_translating;
	_deprecated_function( __FUNCTION__, 1.4 );
	$bbl_translating = true;
}

/**
 * Stop doing any translation.
 *
 * @return void
 **/
function bbl_stop_translating() {
	global $bbl_translating;
	_doing_it_wrong( __FUNCTION__, esc_html__( 'Instead of calling this function, you should pass a `bbl_translate` argument to `get_posts()` with a value of boolean false.', 'babble' ), 1.4 );
	$bbl_translating = false;
}

/**
 * Should we be doing any translation.
 *
 * @return boolean True for yes
 **/
function bbl_translating() {
	global $bbl_translating;
	_deprecated_function( __FUNCTION__, 1.4 );
	return $bbl_translating;
}
