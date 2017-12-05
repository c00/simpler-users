<?php
/**
 * Created by PhpStorm.
 * User: coo
 * Date: 5-12-17
 * Time: 22:58
 */

namespace test;


use c00\common\AbstractSettings;

class TestSettings extends AbstractSettings
{
    public $host;
    public $user;
    public $pass;
    public $dbName;
    public $googleClientId;
    public $googleClientSecret;

    public function __construct()
    {
        parent::__construct();

        $this->path = __DIR__ . '/';
    }

    public function loadDefaults()
    {
        $this->host = "localhost";
        $this->user = "root";
        $this->pass = "";
        $this->dbName = "test_users";

        $this->googleClientId = 'someclientid';
        $this->googleClientSecret = 'someclientsecret';
    }


}