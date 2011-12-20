<?php

/**
 * Class for handling comments.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Comment extends Babble_Plugin {
	
	public function __construct() {
		$this->setup( 'babble-comment', 'plugin' );

		$this->add_filter( 'comments_template_args' );
		$this->add_filter( 'preprocess_comment' );
	}

	/**
	 * Hooks the comments_template_args on Bbl_Comment_Query,
	 * and hopefully soon on WP_Comment_Query (Trac #19623),
	 * in order to ensure we get the comments from all the
	 * translated posts in this translation group.
	 *
	 * @param array $args The args for WP_Comment_Query in comments_template 
	 * @return array The args for WP_Comment_Query in comments_template 
	 **/
	public function comments_template_args( $args ) {
		if ( isset( $args[ 'post_id' ] ) && ! empty( $args[ 'post_id' ] ) ) {
			$posts = bbl_get_post_translations( $args[ 'post_id' ] );
			if ( isset( $args[ 'post__in' ] ) && ! is_array( $args[ 'post__in' ] ) )
				$args[ 'post__in' ] = array();
			foreach ( $posts as & $post )
				$args[ 'post__in' ][] = $post->ID;
			unset( $args[ 'post_id' ] );
		}
		return $args;
	}

	/**
	 * Hooks the WP preprocess_comment filter to ensure that when someone
	 * replies to a comment which has been included in a merged comment
	 * stream on a post in a different language, the reply is assigned 
	 * language post of the parent comment.
	 *
	 * @param array $comment_data The comment data  
	 * @return void
	 **/
	public function preprocess_comment( $comment_data ) {
		$parent_comment = get_comment( $comment_data[ 'comment_parent' ] );
		// If comment_post_ID exists in the data, the only acceptable
		// value is the same as the parent comment's comment_post_ID
		if ( $comment_data[ 'comment_post_ID' ] )
			$comment_data[ 'comment_post_ID' ] = $parent_comment->comment_post_ID;
		return $comment_data;
	}
	
	// PUBLIC METHODS
	// ==============

	
	// PRIVATE/PROTECTED METHODS
	// =========================

}

$bbl_comment = new Babble_Comment();

?>