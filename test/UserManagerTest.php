<?php

namespace test;

use c00\common\CovleDate;
use c00\QueryBuilder\Qry;
use c00\users\LoginError;
use c00\users\User;
use c00\users\UserManager;

class UserManagerTest extends \PHPUnit_Framework_TestCase
{

    /** @var User[] */
    private $users = [];
    private $passwords = [];
    private $oauthData = [];

    /** @var  UserManager */
    private $um;
    /** @var  TestHelper */
    private $th;

    protected function setUp()
    {
        parent::setUp();

        $this->th = new TestHelper();
        $this->um = new UserManager($this->th->db);
    }

    public function testCreateAndLogin(){

        //create user
        $this->assertNull($this->um->user);
        $u = $this->createUser();
        $this->assertTrue(is_numeric($u->id));
        $this->assertEquals(1, $u->id);
        $this->assertNotNull($this->um->user);
        $this->assertEquals($this->um->user->email, $u->email);

        //reset user
        $this->um->user = null;
        $this->assertNull($this->um->user);

        //login
        $this->assertTrue($this->um->login($u->email, $this->passwords[$u->email]));
        $this->assertNotNull($this->um->user);
        $this->assertEquals($this->um->user->email, $u->email);
    }

    public function testWrongLogin(){
        //create user
        $u = $this->createUser();
        $this->um->user = null;

        //login with wrong password
        $this->assertFalse($this->um->login($u->email, 'notthepassword'));
        $this->assertEquals($this->um->getLoginError(), LoginError::PASSWORD_INVALID);
        $this->assertNotNull($this->um->user);

        //Reset and login wth wrong email address
        $this->um->user = null;
        $this->assertFalse($this->um->login('elon@musk.com', '12345'));
        $this->assertEquals($this->um->getLoginError(), LoginError::EMAIL_UNKNOWN);

        //Reset and try disabled account.
        $u->active = false;
        $this->um->updateUser($u);

        $this->um->user = null;
        $this->assertFalse($this->um->login($u->email, $this->passwords[$u->email]));
        $this->assertEquals($this->um->getLoginError(), LoginError::USER_INACTIVE);
    }

    public function testSession() {
        $u = $this->createUser();
        $this->um->login($u->email, $this->passwords[$u->email]);

        $session = $this->um->user->session;


        //Check session
        $this->assertTrue($this->um->checkSession($session->token));

        //Expire session
        $this->um->expireSession($session->token);
        $this->assertFalse($this->um->checkSession($session->token));

        //Expire all sessions
        $this->um->login($u->email, $this->passwords[$u->email]);
        $this->um->login($u->email, $this->passwords[$u->email]);
        $this->um->login($u->email, $this->passwords[$u->email]);

        $this->assertEquals(4, $this->getSessionCount($u));
        $this->assertEquals(3, $this->getSessionCount($u, true));

        $this->um->expireSessions($u->id);

        $this->assertEquals(4, $this->getSessionCount($u));
        $this->assertEquals(0, $this->getSessionCount($u, true));
    }

    public function testNoLogin() {
        $this->expectException(\Exception::class);

        $this->um->getLoggedInUser();
    }

    public function testOauth() {
        $normalUser = $this->createUser();
        $oauthUser = $this->createOauthUser();

        $this->assertNotNull($normalUser);

        //This proves that allowCreate = true works.
        $this->assertNotNull($oauthUser);

        $this->assertEquals(1, $normalUser->id);
        $this->assertEquals(2, $oauthUser->id);

        $allowCreate = false;
        $allowExpand = false;

        //Fail verification
        $mockData = $this->oauthData[$oauthUser->email];
        $mockData['correct'] = false;
        $this->um->user = null;

        $this->assertFalse($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNull($this->um->user);
        $this->assertEquals(LoginError::OAUTH_VERIFICATION_FAILED, $this->um->getLoginError());

        //Pass verification, known email (should be good)
        $mockData['correct'] = true;

        $this->assertTrue($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNotNull($this->um->user);

        //Pass verification, unknown email (should fail)
        $this->um->user = null;
        $mockData['email'] = "not-an-existing-email@test.covle.com";
        $this->assertFalse($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNull($this->um->user);
        $this->assertEquals(LoginError::EMAIL_UNKNOWN, $this->um->getLoginError());

        //Try to login as non-oauth user
        $mockData['email'] = $normalUser->email;
        $this->um->user = null;
        $this->assertFalse($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNotNull($this->um->user);
        $this->assertEquals(LoginError::OAUTH_ID_UNKNOWN, $this->um->getLoginError());

        //Allow Expansion
        $allowExpand = true;
        $this->um->user = null;
        $this->assertTrue($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNotNull($this->um->user);
        $this->assertEquals($this->um->user->oauthId, $oauthUser->oauthId);

        //Login as expanded user
        $allowExpand = false;
        $this->um->user = null;
        $this->assertTrue($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNotNull($this->um->user);
        $this->assertEquals($this->um->user->oauthId, $oauthUser->oauthId);

        //Disable user and try to login again
        $this->um->user->active = false;
        $this->um->updateUser($this->um->user);

        $this->um->user = null;
        $this->assertFalse($this->um->processOauthLogin('mock', $mockData, $allowCreate, $allowExpand));
        $this->assertNotNull($this->um->user);
    }

    private function getSessionCount(User $user, $activeOnly = false) {
        $q = Qry::select()
            ->count('id', 'cnt')
            ->from(UserManager::TABLE_SESSION)
            ->where('userId', '=', $user->id);

        if ($activeOnly) {
            $q->where('expires', '>', CovleDate::now()->toSeconds());
        }

        return $this->th->db->getValue($q);
    }

    private function createUser() : User
    {
        $email = bin2hex(random_bytes(4)) . '@covle.com';
        $password = bin2hex(random_bytes(16));

        $u = $this->um->addNewUser($email, $password);

        $this->users[$email] = $u;
        $this->passwords[$email] = $password;

        return $u;
    }

    private function createOauthUser() : User
    {
        $mockService = new MockOauthService();
        $this->um->addOauthService($mockService);

        $mockData = $mockService->getMockData();
        $mockService->verify($mockData);

        $u = $mockService->getUser();
        $this->um->processOauthLogin($mockService->getServiceName(), $mockData, true);

        $this->users[$u->email] = $this->um->user;
        $this->oauthData[$u->email] = $mockData;

        return $this->um->user;
    }

}