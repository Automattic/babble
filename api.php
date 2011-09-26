<?php

/**
 * Translations and languages API.
 *
 * @package WordPress
 * @subpackage Languages
 * @since ???
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
 * Returns the current language code.
 *
 * @FIXME: Currently does not check for language validity, though perhaps we should check that elsewhere and redirect?
 *
 * @return string A language code
 * @author Simon Wheatley
 **/
function sil_get_current_lang_code() {
	// Outside the admin area, it's a WP Query Variable
	if ( ! is_admin() )
		return get_query_var( 'lang' ) ? get_query_var( 'lang' ) : SIL_DEFAULT_LANGUAGE;
	// In the admin area, it's a GET param
	return @ $_GET[ 'lang' ] ? $_GET[ 'lang' ] : SIL_DEFAULT_LANGUAGE;
}

?>