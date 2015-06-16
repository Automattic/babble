<?php

class Roles_Test extends Babble_UnitTestCase {

	function setUp() {

		parent::setUp();

		$this->admin = $this->factory->user->create_and_get( array(
			'role' => 'administrator'
		) );
		$this->editor = $this->factory->user->create_and_get( array(
			'role' => 'editor'
		) );
		$this->author = $this->factory->user->create_and_get( array(
			'role' => 'author'
		) );
		$this->contributor = $this->factory->user->create_and_get( array(
			'role' => 'contributor'
		) );
		$this->subscriber = $this->factory->user->create_and_get( array(
			'role' => 'subscriber'
		) );
		$this->no_role = $this->factory->user->create_and_get( array(
			'role' => ''
		) );
		$this->translator = $this->factory->user->create_and_get( array(
			'role' => 'translator'
		) );

		if ( is_multisite() ) {
			$this->super = $this->factory->user->create_and_get( array(
				'role' => 'administrator'
			) );
			grant_super_admin( $this->super->ID );
		}

	}

	function testInit() {
		$this->assertTrue( (bool) did_action( 'admin_init' ) );
	}

	function testSuperAdminCaps() {

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test only runs in multisite' );
			return;
		}

		# Super Admins can manage translation jobs:
		$this->assertTrue( user_can( $this->super->ID, 'edit_bbl_jobs' ) );

	}

	function testAdminCaps() {

		# Admins can manage translation jobs:
		$this->assertTrue( user_can( $this->admin->ID, 'edit_bbl_jobs' ) );

	}

	function testEditorCaps() {

		# Editors can manage translation jobs:
		$this->assertTrue( user_can( $this->editor->ID, 'edit_bbl_jobs' ) );

	}

	function testAuthorCaps() {

		# Authors can manage translation jobs:
		$this->assertTrue( user_can( $this->author->ID, 'edit_bbl_jobs' ) );

	}

	function testContributorCaps() {

		# Contributors can manage translation jobs:
		$this->assertTrue( user_can( $this->contributor->ID, 'edit_bbl_jobs' ) );

	}

	function testSubscriberCaps() {

		# Subscribers cannot manage translation jobs:
		$this->assertFalse( user_can( $this->subscriber->ID, 'edit_bbl_jobs' ) );

	}

	function testNoRoleCaps() {

		# Users with no role cannot manage translation jobs:
		$this->assertFalse( user_can( $this->no_role->ID, 'edit_bbl_jobs' ) );

	}

	function testTranslatorCaps() {
		global $wp_roles;

		$this->assertTrue( $wp_roles->is_role( 'translator' ) );

		# Translators can manage translation jobs:
		$this->assertTrue( user_can( $this->translator->ID, 'edit_bbl_jobs' ) );

	}

}
