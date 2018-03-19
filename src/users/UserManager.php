<?php

namespace c00\users;

use c00\common\AbstractDatabase;
use c00\common\CovleDate;
use c00\oauth\Google;
use c00\oauth\OauthService;
use c00\QueryBuilder\Qry;
use Symfony\Component\HttpFoundation\Request;

class UserManager
{
    const TABLE_USER = 'user';
    const TABLE_SESSION = 'session';

    const SESSION_HEADER = 'x-auth';
    const SESSION_PARAM = 't';

    /** @var UserManagerSettings */
    protected $settings;

    protected $loggedIn = false;
    /** @var AbstractDatabase */
    protected $db;
    /** @var string|null */
    protected $loginError;

    /** @var OauthService[] */
    protected $oauthServices = [];


    /** @var User */
    public $user = null;

    public function __construct($db = null, $settings = null)
    {
        $this->db = $db;

        if (!$settings) {
        	$settings = new UserManagerSettings();
        	$settings->load();
        }
        $this->settings = $settings;
    }


	/**
	 *
	 * @param OauthService $s
	 *
	 * @throws SimpleUsersException
	 */
    public function addOauthService(OauthService $s) {

        if (isset($this->oauthServices[$s->getServiceName()])) {
            throw SimpleUsersException::new("Oauth Service '{$s->getServiceName()}' already added!");
        }

        $this->oauthServices[$s->getServiceName()] = $s;
    }

	public function getLoginError() {
        return $this->loginError;
    }

	/**
	 * @return User
	 * @throws SimpleUsersException
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function getLoggedInUser()
    {
        if ($this->user && $this->loggedIn) return $this->user;

        //Try to get user token from headers and params.
        if (!$this->tryGetUserFromRequest()) {
            throw SimpleUsersException::new("Not logged in.");
        }

        return $this->user;
    }

	/**
	 * @return User|null
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    private function tryGetUserFromRequest() {
        $r = Request::createFromGlobals();

        //Get header
        $token = $r->headers->get(self::SESSION_HEADER) ?? $r->query->get(self::SESSION_PARAM);

        if (!$token) {
            $this->loggedIn = false;
            return null;
        }

        $this->user = $this->getUserBySession($token);

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

	/**
	 * @param User $u
	 * @param $password string Set the users password (includes password strength check
	 *
	 * @return User
	 * @throws SimpleUsersException
	 * @throws \Exception
	 */
    public function addUser(User &$u, $password = null) : User
    {
        $u->email = strtolower($u->email);
        if ($password) $u->setPassword($password, $this->settings->minPasswordStrength);

        if ($this->emailExists($u->email)){
	        throw SimpleUsersException::new("Email {$u->email} already exists!");
        }

        $q = Qry::insert(self::TABLE_USER, $u);
        $u->id = (int) $this->db->insertRow($q);

        $this->user = $u;

        return $this->user;
    }

	/**
	 * @param string $email
	 * @param string $password
	 *
	 * @return User
	 * @throws SimpleUsersException
	 * @throws \Exception
	 */
	public function addNewUser(string $email, string $password) : User
	{
		$u = new User;
		$u->email = $email;
		$u->setPassword($password, $this->settings->minPasswordStrength);
		$u->created = CovleDate::now();
		$u->lastLogin = CovleDate::now();
		$u->active = true;

		return $this->addUser($u);
	}

	/**
	 * @param User $u
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function updateUser(User $u) : bool
    {
        $q = Qry::update(self::TABLE_USER, $u)
            ->where('id', '=', $u->id);

        return $this->db->updateRow($q);
    }

	/**
	 * @param $ids array|string
	 *
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function purgeUsers($ids) {
        if (!is_array($ids)) $ids = [$ids];

        $this->db->beginTransaction();

        foreach ($ids as $id) {
            $this->purgeUser($id);
        }

        $this->db->commitTransaction();
    }

	/**
	 * @param $id
	 *
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
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
	 *
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function touchSession(User &$user){
        $user->session->lastAccess = CovleDate::now();
        $user->session->expires = CovleDate::now()->addDays($this->settings->sessionDaysValid);
        $q = Qry::update(self::TABLE_SESSION, $user->session)
            ->where('id', '=', $user->session->id);

        $this->db->updateRow($q);

        return true;
    }

	/**
	 * @param $token
	 *
	 * @return User|null
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
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

	/**
	 * @param int $id
	 *
	 * @return User
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function getUserById(int $id) : User
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where('id', '=', $id)
            ->asClass($this->settings->userClass);

        return $this->db->getRow($q);
    }


	/**
	 * @param string $email
	 *
	 * @return User
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function getUserByEmail(string $email) : User
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where('email', '=', $email)
            ->asClass($this->settings->userClass);

        return $this->db->getRow($q);
    }

	/**
	 * @param bool $toShowable
	 *
	 * @return array|User[]
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function getUsers($toShowable = false) : array
    {
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->asClass($this->settings->userClass);

        /** @var User[] $users */
        $users =  $this->db->getRows($q, $toShowable);

        return $users;
    }

	/**
	 * @param string $token
	 *
	 * @return Session
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    private function getSession(string $token): Session
    {
        $q = Qry::select()
            ->from(self::TABLE_SESSION)
            ->where('token', '=', $token)
            ->asClass(Session::class);

        return $this->db->getrow($q);
    }

	/**
	 * @param string $token
	 *
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function expireSession(string $token){
        $s = $this->getSession($token);
        $s->expires = CovleDate::now()->addSeconds(-1);

        $q = Qry::update(self::TABLE_SESSION, $s)->where('token', '=', $s->token);
        $this->db->updateRow($q);
    }

	/**
	 * @param null $userId
	 *
	 * @return Session[]
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
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

	/**
	 * @param $userId
	 *
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function expireSessions($userId){
        $expires = CovleDate::now()->addSeconds(-1)->toSeconds();

        $q = Qry::update(self::TABLE_SESSION, ['expires' => $expires])
            ->where('userId', '=', $userId);

        $this->db->updateRow($q);

    }

	/**
	 * @param string $token
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function checkSession(string $token) : bool
    {
        $this->user = $this->getUserBySession($token);

        if(!$this->user || !$this->user->active) return false;

        $this->loggedIn = true;

        $this->touchSession($this->user);

        return true;
    }

	/**
	 * @param string $email
	 * @param string $pass
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
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

	/**
	 * @param User $u
	 *
	 * @return bool|string
	 * @throws \Exception
	 */
    private function createNewSession(User &$u){
        $session = Session::newSession($u, $this->settings->sessionDaysValid);

        $q = Qry::insert(self::TABLE_SESSION, $session);
        return $this->db->insertRow($q);
    }

	/**
	 * @param User $user
	 * @param string $newPassword
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    public function savePassword(User &$user, string $newPassword) : bool
    {
        $user->setPassword($newPassword, $this->settings->minPasswordStrength);

        $q = Qry::update(self::TABLE_USER, [ 'passwordHash' => $user->passwordHash ])
            ->where('id', '=', $user->id);
        
        return $this->db->updateRow($q);
    }

	/**
	 * @param User $user
	 * @param string $pass
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
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

    /**
     * @param string $serviceName The name of the oAuth Service, e.g. 'google'
     * @param $data mixed The data the service requires to verify.
     * @param bool $allowCreate Create the user if it doesn't exist?
     * @param bool $allowExpand Add oAuth info to the user if the email address already exists?
     * @return bool true on success.
     * @throws \Exception
     */
    public function processOauthLogin(string $serviceName, $data, bool $allowCreate = false, bool $allowExpand = false) : bool
    {
        $service = $this->oauthServices[$serviceName] ?? null;
        if (!$service)  throw new \Exception("Service $serviceName unknown.");

        if (!$service->verify($data)) {
            $this->loginError = LoginError::OAUTH_VERIFICATION_FAILED;
            return false;
        }

        if (!$this->emailExists($service->getEmail())) {
            //Email address doesn't exist yet.

            if ($allowCreate) {
                //Create user
                $user = User::newSocialUser($service->getEmail(), $serviceName, $service->getOauthId());
                $this->addUser($user);
                $this->user = $user;


            } else {
                //Don't create user.
                $this->loginError = LoginError::EMAIL_UNKNOWN;
                return false;
            }
        } else {
            //Email address is known.
            $user = $this->getUserByEmail($service->getEmail());
            $this->user= $user;

            if ($user->oauthId == $service->getOauthId() && $user->oauthService == $service->getServiceName()) {
                //Existing user. Login.

            } else if ($allowExpand && !$user->oauthId) {
                //Expand user
                $user->oauthId = $service->getOauthId();
                $user->oauthService = $service->getServiceName();
                $this->updateUser($user);

            } else {
                //Some info isn't matching up.
                $this->loginError = LoginError::OAUTH_ID_UNKNOWN;
                return false;
            }
        }

        //All good, do some final checks
        if (!$this->user->active) {
            $this->loginError = LoginError::USER_INACTIVE;
            return false;
        }


        $this->createNewSession($this->user);
        return true;

    }

	/**
	 * @param $oauthId
	 * @param $service
	 *
	 * @return mixed|null
	 * @throws \Exception
	 * @throws \c00\QueryBuilder\QueryBuilderException
	 */
    private function getUserBySocialUserId($oauthId, $service)
    {
    	//todo: Figure out why this is unused?
        $q = Qry::select()
            ->from(self::TABLE_USER)
            ->where("oauthId", '=', $oauthId)
            ->wherE('oauthService', '=', $service)
            ->asClass($this->settings->userClass);

        if (!$this->db->rowExists($q)) return null;

        return $this->db->getRow($q);
    }
    //endregion

}