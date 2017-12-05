<?php

namespace c00\users;

use c00\common\AbstractDatabaseObject;
use c00\common\CovleDate;
use ZxcvbnPhp\Zxcvbn;

class User extends AbstractDatabaseObject
{
    const MINIMUM_PASSWORD_STRENGTH = 1;

    public $id;
    public $email;
    /** @var  CovleDate */
    public $created;
    /** @var  CovleDate */
    public $lastLogin;
    public $active;
    public $passwordHash;
    public $oauthId;
    public $oauthService;

    /** @var Session */
    public $session;

    protected $_dataTypes = [
        'created' => CovleDate::class,
        'lastLogin' => CovleDate::class,
        'id' => 'int',
        'active' => 'bool'
    ];

    public function setPassword(string $password){
        if (strlen($password) > 72) throw new \Exception("Password too long!");

        $strength = [];
        if ($this->testPassWordStrength($password, $strength) < self::MINIMUM_PASSWORD_STRENGTH) throw new \Exception("Password sucks too hard.");

        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    public static function newUser(string $email, string $password) : User
    {
        $u = new User;
        $u->email = $email;
        $u->setPassword($password);
        $u->created = CovleDate::now();
        $u->lastLogin = CovleDate::now();
        $u->active = true;

        return $u;
    }

    public static function newSocialUser(string $email, string $service = OauthService::GOOGLE, string $oauthId) : User
    {
        $u = new User;
        $u->email = $email;
        $u->created = CovleDate::now();
        $u->lastLogin = CovleDate::now();
        $u->active = true;
        $u->oauthService = $service;

        $u->oauthId = $oauthId;

        $u->validateEmail();

        return $u;
    }

    private function validateEmail(){
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Bad email address");
        }

    }

    public function testPassWordStrength($password, &$strength = []) : int
    {
        $userData = [$this->email];

        $z = new Zxcvbn();
        $strength = $z->passwordStrength($password, $userData);

        return $strength['score'];
    }

    public function toShowable()
    {
        $a =  parent::toShowable();

        $a['created'] = $this->created->toMiliseconds();
        $a['lastLogin'] = $this->created->toMiliseconds();

        unset($a['passwordHash']);
        unset($a['oauthService']);
        unset($a['oauthId']);

        if (isset($a['session'])) $a['session'] = $this->session->toShowable();

        return $a;
    }

}