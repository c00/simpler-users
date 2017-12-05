<?php

namespace c00\wannaTrain;

use c00\common\AbstractDatabase;
use c00\common\CovleDate;
use c00\log\Log;
use c00\oauth\Google;
use c00\oauth\OauthService;
use c00\QueryBuilder\components\WhereGroup;
use c00\QueryBuilder\Qry;
use c00\social\GoogleOauthResponse;
use c00\common\Helper as H;
use c00\users\LoginError;
use c00\users\Session;
use c00\users\User;

class UserManager
{
    const TABLE_USER = 'user';
    const TABLE_SESSION = 'session';

    protected $loggedIn = false;
    /** @var AbstractDatabase */
    protected $db;
    protected $loginError;

    /** @var User */
    public $user = null;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function getLoginError() {
        return $this->loginError;
    }

    public function getLoggedInUser()
    {
        if (!$this->loggedIn || !$this->user){
            throw new \Exception("Not logged in");
        }

        return $this->user;
    }

    public function isLoggedIn() : bool
    {
        return ($this->loggedIn && $this->user);
    }

    public function emailExists($email) : bool
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where('email', '=', $email);

        return $this->db->rowExists($q);
    }

    public function addUser(User &$u) : User
    {
        $u->email = strtolower($u->email);

        if ($this->emailExists($u->email)){
            throw new \Exception("Email {$u->email} already exists!");
        }

        $q = Qry::insert(self::TABLE_USER, $u);
        $u->id = $this->db->insertRow($q);

        $this->user = $u;

        return $this->user;
    }

    public function updateUser(User $u) : bool
    {
        $q = Qry::update(self::TABLE_USER, $u)
            ->where('id', '=', $u->id);

        return $this->db->updateRow($q);
    }

    /**
     * @param $ids array|int User Id(s) to purge
     */
    public function purgeUsers($ids) {
        if (!is_array($ids)) $ids = [$ids];

        $this->db->beginTransaction();

        foreach ($ids as $id) {
            $this->purgeUser($id);
        }

        $this->db->commitTransaction();
    }

    protected function purgeUser($id)
    {
        //Session
        $this->db->deleteRows(
            Qry::delete(self::TABLE_SESSION)->where('userId', '=', $id)
        );

        //User
        $this->db->deleteRows(
            Qry::delete(self::TABLE_USER)->where('id', '=', $id)
        );
    }

    /**
     * Updates a session to be valid for another Session::EXPIRATION_DAYS
     * @param User $user
     * @return bool
     */
    public function touchSession(User &$user){
        $user->session->lastAccess = CovleDate::now();
        $user->session->expires = CovleDate::now()->addDays(Session::EXPIRATION_DAYS);
        $q = Qry::update(self::TABLE_SESSION, $user->session)
            ->where('id', '=', $user->session->id);

        $this->db->updateRow($q);

        return true;
    }

    /**
     * @param $token
     * @return User|null
     */
    private function getUserBySession($token)
    {
        $now = CovleDate::now()->toSeconds();

        $q = Qry::select()
            ->from(self::TABLE_SESSION)
            ->where('token', '=', $token)
            ->where('expires', '>', $now)
            ->asClass(Session::class);

        if (!$this->db->rowExists($q)) return null;

        /** @var Session $session */
        $session = $this->db->getRow($q);

        $user = $this->getUserById($session->userId);
        $user->session = $session;

        return $user;
    }

    public function getUserById(int $id) : User
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where('id', '=', $id)
            ->asClass(User::class);

        return $this->db->getRow($q);
    }

    public function getUserByEmail(string $email) : User
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where('email', '=', $email)
            ->asClass(User::class);

        return $this->db->getRow($q);
    }

    /**
     * @param bool $toShowable
     * @return User[]|array
     */
    public function getUsers($toShowable = false) : array
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->asClass(User::class);

        /** @var User[] $users */
        $users =  $this->db->getRows($q, $toShowable);

        return $users;
    }

    private function getSession(string $token): Session
    {
        $q = Qry::select()
            ->from(self::TABLE_SESSION)
            ->where('token', '=', $token)
            ->asClass(Session::class);

        return $this->db->getrow($q);
    }

    public function expireSession(string $token){
        $s = $this->getSession($token);
        $s->expires = CovleDate::now()->addSeconds(-1);

        $q = Qry::update(self::TABLE_SESSION, $s)->where('token', '=', $s->token);
        $this->db->updateRow($q);
    }

    /**
     * @param $userId
     * @return Session[]
     */
    public function getSessions($userId = null): array
    {
        $q = Qry::select()
            ->from(self::TABLE_SESSION)
            ->asClass(Session::class);

        if ($userId){
            $q->where('userId', '=', $userId);
        }

        return $this->db->getRows($q);
    }

    public function expireSessions($userId){
        $expires = CovleDate::now()->addSeconds(-1)->toSeconds();

        $q = Qry::update(self::TABLE_SESSION, ['expires' => $expires])
            ->where('userId', '=', $userId);

        $this->db->updateRow($q);

    }

    public function deleteSession(string $token) {
        $q = Qry::delete(self::TABLE_SESSION)->where('token', '=', $token);

        $this->db->deleteRows($q);
    }

    public function deleteSessions($userId) {
        $q = Qry::delete(self::TABLE_SESSION)->where('userId', '=', $userId);

        $this->db->deleteRows($q);
    }

    public function checkSession(string $token) : bool
    {
        $this->user = $this->getUserBySession($token);

        if(!$this->user) return false;

        $this->touchSession($this->user);

        return true;
    }

    public function login(string $email, string $pass) : bool
    {
        if (!$this->emailExists($email)){
            $this->loginError = LoginError::EMAIL_UNKNOWN;
            return false;
        }

        $user = $this->getUserByEmail($email);
        $this->user = $user;

        //Check password
        if (!$this->checkPassword($user, $pass)){
            $this->loginError = LoginError::PASSWORD_INVALID;
            return false;
        }

        //Check if user is disabled
        if (!$user->active){
            $this->loginError = LoginError::USER_INACTIVE;
            return false;
        }

        //All good, create new session.
        $this->createNewSession($this->user);

        $this->loggedIn = true;
        $this->loginError = null;

        return true;
    }

    private function createNewSession(User &$u){
        $session = Session::newSession($u);

        $q = Qry::insert(self::TABLE_SESSION, $session);
        return $this->db->insertRow($q);
    }

    public function savePassword(User &$user, string $newPassword) : bool
    {
        $user->setPassword($newPassword);

        $q = Qry::update(self::TABLE_USER, [ 'passwordHash' => $user->passwordHash ])
            ->where('id', '=', $user->id);
        
        return $this->db->updateRow($q);
    }

    public function checkPassword(User &$user, string $pass) : bool
    {
        if (strlen($pass) > 72) return false;

        if (!password_verify($pass, $user->passwordHash)) return false;

        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)){
            $this->savePassword($user, $pass);
        }

        return true;
    }

    //region Social
    public function processGoogleLogin($data, $allowCreate = false) : bool
    {
        $service = new Google();
        if (!$service->verify($data)) {
            $this->loginError = LoginError::GOOGLE_VERIFICATION_FAILED;
            return false;
        }

        /* Scenarios:
        1. Google userId exists, that's our user.
        2. Google userId doesn't exist, allowCreate = true, create new.
        3. Google userId doesn't exist, allowCreate = false, reject.
        */

        $user = $this->getUserBySocialUserId($service->getOauthId(), OauthService::GOOGLE);
        $this->user= $user;

        if (!$user && $allowCreate) {
            //Scenario 2: Create user
            $user = User::newSocialUser($service->getEmail(), OauthService::GOOGLE, $service->getOauthId());
            $this->addUser($user);
            $this->user = $user;
        } else if (!$user && !$allowCreate) {
            //scenario 3 reject
            $this->loginError = LoginError::OAUTH_ID_UNKNOWN;
            return false;
        }

        //Everything is good! Set up the session
        $this->createNewSession($this->user);

        return true;
    }

    /**
     * @param $oauthId string The social user id
     * @param $service string 'google', 'facebook' or 'twitter'
     * @return User|null
     */
    private function getUserBySocialUserId($oauthId, $service)
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where("oauthId", '=', $oauthId)
            ->wherE('oauthService', '=', $service)
            ->asClass(User::class);

        if (!$this->db->rowExists($q)) return null;

        return $this->db->getRow($q);
    }
    //endregion

}