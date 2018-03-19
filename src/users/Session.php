<?php

namespace c00\users;

use c00\common\AbstractDatabaseObject;
use c00\common\CovleDate;

class Session extends AbstractDatabaseObject
{
	/** @deprecated  */
    const EXPIRATION_DAYS = 30;

    public $id;
    public $token;
    /** @var  CovleDate */
    public $created;
    /** @var  CovleDate */
    public $expires;
    /** @var  CovleDate */
    public $lastAccess;

    public $userId;

    protected $_dataTypes = [
        'id' => 'int',
        'created' => CovleDate::class,
        'expires' => CovleDate::class,
        'lastAccess' => CovleDate::class,
        'userId' => 'int'
    ];

    public function generateToken(){
        $this->token = bin2hex(random_bytes(16));
    }

    public static function newSession(User &$user, $daysValid) : Session
    {
        $s = new Session();
        $s->created = CovleDate::now();
        $s->lastAccess = CovleDate::now();
        $s->expires = CovleDate::now()->addDays($daysValid);
        $s->generateToken();

        $s->userId = $user->id;
        $user->session = $s;

        return $s;
    }

    public function toShowable()
    {
        $a = parent::toShowable();

        $a['created'] = $this->created->toMiliseconds();
        $a['expires'] = $this->expires->toMiliseconds();
        $a['lastAccess'] = $this->lastAccess->toMiliseconds();

        return $a;
    }

}