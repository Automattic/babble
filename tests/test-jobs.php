<?php

class Test_Jobs extends Babble_UnitTestCase {

	public function setUp() {
		parent::install_languages();
		parent::setUp();
	}
	public function test_create_post_jobs() {
		$bbl_jobs = Babble::get( 'jobs' );
		$post_id = $this->factory->post->create();

		$jobs = $bbl_jobs->create_post_jobs( $post_id, array( 'fr_FR' ) );
		$this->assertFalse( empty( $jobs ) );

		$job = get_post( $jobs[0] );
		$this->assertEquals( 'bbl_job', $job->post_type );
		$this->assertEquals( 'post|' . $post_id, get_post_meta( $job->ID, 'bbl_job_post', true ) );
	}

	public function test_get_object_jobs_returns_none() {

		$bbl_jobs = Babble::get( 'jobs' );
		$post_id = $this->factory->post->create();
		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertEquals( array(), $jobs );
	}

	public function test_get_object_jobs_new() {

		$bbl_jobs = Babble::get( 'jobs' );
		$post_id = $this->factory->post->create();
		$jobs    = $bbl_jobs->create_post_jobs( $post_id, array( 'fr_FR' ) );
		$job_id  = $jobs[0];

		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertTrue( isset( $jobs['fr_FR'] ) );
		$this->assertEquals( 1, count( $jobs ) );

		// grab the jobs again to hit the object cache
		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertTrue( isset( $jobs['fr_FR'] ) );
		$this->assertEquals( 1, count( $jobs ) );
	}

	public function test_get_object_jobs_after_deleting_job() {
		$bbl_jobs = Babble::get( 'jobs' );
		$post_id = $this->factory->post->create();
		$jobs    = $bbl_jobs->create_post_jobs( $post_id, array( 'fr_FR' ) );
		$job_id  = $jobs[0];

		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertTrue( isset( $jobs['fr_FR'] ) );
		$this->assertEquals( 1, count( $jobs ) );

		wp_delete_post( $job_id );

		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertEquals( 0, count( $jobs ) );

	}

	public function test_get_object_jobs_after_creating_job() {
		$bbl_jobs = Babble::get( 'jobs' );
		$post_id = $this->factory->post->create();
		$jobs    = $bbl_jobs->create_post_jobs( $post_id, array( 'fr_FR' ) );
		$job_id  = $jobs[0];

		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertTrue( isset( $jobs['fr_FR'] ) );
		$this->assertEquals( 1, count( $jobs ) );

		$bbl_jobs->create_post_jobs( $post_id, array( 'en_GB' ) );

		$jobs = $bbl_jobs->get_object_jobs( $post_id, 'post', 'post', array( 'new' ) );

		$this->assertTrue( isset( $jobs['fr_FR'] ) );
		$this->assertTrue( isset( $jobs['en_GB'] ) );
		$this->assertEquals( 2, count( $jobs ) );
	}
}
