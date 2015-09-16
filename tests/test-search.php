<?php

class Test_Babble_Search extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_search() {
		global $wp;

		$this->factory;
		$this->factory->post;

		$args = array( 'post_content' => '1 in US English  UBIQUITOUS_WORD UNIQUE_WORD_1_EN_US and some more words which I do no care a jot about and will not search for.' );
		$en1 = $this->factory->post->create_and_get( $args );
		$args = array( 'post_content' => '1 in UK English UBIQUITOUS_WORD UNIQUE_WORD_1_EN_GB and some more words which I do no care a jot about and will not search for.' );
		$uk1 = $this->create_post_translation( $en1, 'en_GB', $args );
		$args = array( 'post_content' => '1 in French UBIQUITOUS_WORD UNIQUE_WORD_1_FR_FR and some more words which I do no care a jot about and will not search for.' );
		$fr1 = $this->create_post_translation( $en1, 'fr_FR', $args );

		$args = array( 'post_content' => '2 in US English UBIQUITOUS_WORD UNIQUE_WORD_1_EN_US UNIQUE_WORD_2_EN_US and some more words which I do no care a jot about and will not search for.' );
		$en2 = $this->factory->post->create_and_get( $args );
		$args = array( 'post_content' => '2 in UK English UBIQUITOUS_WORD UNIQUE_WORD_1_EN_GB UNIQUE_WORD_2_EN_GB and some more words which I do no care a jot about and will not search for.' );
		$uk2 = $this->create_post_translation( $en2, 'en_GB', $args );
		$args = array( 'post_content' => '2 in French UBIQUITOUS_WORD UNIQUE_WORD_1_FR_FR UNIQUE_WORD_2_FR_FR and some more words which I do no care a jot about and will not search for.' );
		$fr2 = $this->create_post_translation( $en2, 'fr_FR', $args );

		// UBIQUITOUS_WORD should be present in both US English posts
		$this->go_to( '/en/?s=UBIQUITOUS_WORD' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$expected_post_ids = wp_list_pluck( array( $en1, $en2 ), 'ID' );
		$this->assertEqualSets( $expected_post_ids, $matching_post_ids );

		// UNIQUE_WORD_2 should only be in the second US English post
		$this->go_to( '/en/?s=UNIQUE_WORD_2_EN_US' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$expected_post_ids = wp_list_pluck( array( $en2 ), 'ID' );
		$this->assertEqualSets( $expected_post_ids, $matching_post_ids );

		// UNIQUE_WORD_1_FR_FR should be present in no US English posts
		$this->go_to( '/en/?s=UNIQUE_WORD_1_FR_FR' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertEqualSets( array(), $matching_post_ids );

		$this->set_post_types_to_locale( 'fr_FR' );

		// UBIQUITOUS_WORD should be present in both French posts
		$this->go_to( '/fr/?s=UBIQUITOUS_WORD' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$expected_post_ids = wp_list_pluck( array( $fr1, $fr2 ), 'ID' );
		$this->assertEqualSets( $expected_post_ids, $matching_post_ids );

		// UNIQUE_WORD_2_FR_FR should only be in the second French post
		$this->go_to( '/fr/?s=UNIQUE_WORD_2_FR_FR' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$expected_post_ids = wp_list_pluck( array( $fr2 ), 'ID' );
		$this->assertEqualSets( $expected_post_ids, $matching_post_ids );

		// UNIQUE_WORD_1 should be present in no French posts
		$this->go_to( '/fr/?s=UNIQUE_WORD_1_EN_US' );
		$matching_post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertEqualSets( array(), $matching_post_ids );

	}

}
