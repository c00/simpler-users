<?php

namespace test;

use c00\common\Database;

class TestHelper
{
    /** @var \c00\common\Database */
    public $db;
    /** @var \PDO */
    private $pdo;
    /** @var TestSettings */
    private $settings;

    public function __construct()
    {
        $this->setUp();
    }

    private function setUp(){

        //Settings
        $this->settings = new TestSettings();
        $this->settings->load();

        //create settings if it doesn't exist
        if (!file_exists(__DIR__ . '/settings.json')) $this->settings->save();

        //Abstract Database instance
        $this->db = new Database($this->settings->host, $this->settings->user, $this->settings->pass, $this->settings->dbName);

        //PDO instance
        $this->pdo = new \PDO(
            "mysql:charset=utf8mb4;host={$this->settings->host};dbname={$this->settings->dbName}",
            $this->settings->user,
            $this->settings->pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]
        );

        //Run fixture. This removes all content in the database and resets to the primary set.
        $sql = file_get_contents(__DIR__ . '/../src/assets/create.sql');
        $this->pdo->exec($sql);
    }


}