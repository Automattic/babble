<?php

class Test_Languages extends Babble_UnitTestCase {

	public function setUp() {
		$this->install_languages();

		parent::setUp();
	}

	public function test_is_rtl() {

		$this->assertFalse( Babble_Languages::is_rtl( 'en_US' ) );
		$this->assertFalse( Babble_Languages::is_rtl( 'fr_FR' ) );
		$this->assertFalse( Babble_Languages::is_rtl( 'invalid' ) );
		$this->assertTrue( Babble_Languages::is_rtl( 'ar' ) );

	}

}
