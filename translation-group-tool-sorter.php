<?php

// From: http://seancode.blogspot.com/2008/01/php-usort-sort-array-of-objects.html

class SortPosts {
	static function post_type_descending( $m, $n ) {
		if ( $m->post_type == $n->post_type )
			return 0;

		return ( $m->post_type < $n->post_type ) ? -1 : 1;
	}
}
