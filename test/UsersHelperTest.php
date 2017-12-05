<?php

namespace test;

use c00\users\Helper;

class UsersHelperTest extends \PHPUnit_Framework_TestCase
{

    public function testIsCli() {
        $this->assertTrue(Helper::is_cli());
    }
}