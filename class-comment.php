<?php

/**
 * Class for handling comments.
 *
 * @package Babble
 * @since 0.1
 */
class Babble_Comment extends Babble_Plugin {

	public function __construct()
		$this->setup( 'babble-comment', 'plugin' );

		$this->add_filter( 'comments_template_args' );
		$this->add_filter( 'preprocess_comment' );
		$this->add_filter( 'get_comments_number', null, null, 2 );
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
		// If comment_post_ID exists in the data, the only acceptable
		// value is the same as the parent comment's comment_post_ID
		$parent_comment = get_comment( $comment_data[ 'comment_parent' ] );
		if ( $parent_comment && $comment_data[ 'comment_post_ID' ] )
			$comment_data[ 'comment_post_ID' ] = $parent_comment->comment_post_ID;
		return $comment_data;
	}

	/**
	 * Hooks the WP get_comments_number filter to get the number of comments
	 * across all posts in the translation group.
	 *
	 * @param int $count The number of comments on the single translation
	 * @param int $post_id The post ID of the single translation
	 * @return int The count of all comments on published posts in this translation group
	 **/
	public function get_comments_number( $count, $post_id ) {
		$translations = bbl_get_post_translations( $post_id );
		$count = 0;
		foreach ( $translations as & $translation ) {
			$post_status = get_post_status_object( $translation->post_status );
			// FIXME: I'm not entirely sure about using publicly_queryable here… what I want to avoid is draft, private, etc statii.
			if ( $post_status->publicly_queryable )
				$count += $translation->comment_count;
		}
		return $count;
	}

	// PUBLIC METHODS
	// ==============


	// PRIVATE/PROTECTED METHODS
	// =========================

}

global $bbl_comment;
$bbl_comment = new Babble_Comment();
