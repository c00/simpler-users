<?php

namespace test;

use c00\oauth\OauthService;
use c00\users\User;

class MockOauthService implements OauthService
{
    private $data;

    public function getServiceName(): string
    {
        return 'mock';
    }

    public function verify($data): bool
    {
        $this->data = $data;

        return $data['correct'] ?? false;
    }

    public function getUser(): User
    {
        if (!$this->data) throw new \Exception("No Mock Response Data");

        return User::newSocialUser($this->data['email'], $this->getServiceName(), $this->data['oauthId']);
    }

    public function getOauthId(): string
    {
        if (!$this->data) throw new \Exception("No Mock Response Data");

        return $this->data['oauthId'];
    }

    public function getEmail(): string
    {
        if (!$this->data) throw new \Exception("No Mock Response Data");

        return $this->data['email'];
    }


    public function getMockData($correct = true){
        return [
            'email' => bin2hex(random_bytes(4)) . '@test.covle.com',
            'oauthId' => bin2hex(random_bytes(8)),
            'correct' => $correct
        ];
    }
}