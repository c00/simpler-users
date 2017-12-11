<?php

namespace c00\oauth;

use c00\users\User;

class Google implements OauthService
{
    /** @var  GoogleOauthResponse */
    private $data;
    public $result;
    public $clientId;
    public $clientSecret;

    public function __construct($settings = null)
    {
        if (is_array($settings)){
            $this->clientId = $settings['clientId'] ?? null;
            $this->clientSecret = $settings['clientSecret'] ?? null;
        }
    }


    public function verify($data): bool
    {
        $this->data = $data;

        $clientId = $this->clientId;

        $client = new \Google_Client(['client_id' => $clientId]);

        $payload = $client->verifyIdToken($data->idToken);

        if (!$payload || $payload['aud'] !== $clientId) {
            return false;
        }
        $this->result = $payload;

        return true;
    }

    public function getUser() : User
    {
        if (!$this->data) throw new \Exception("No Google Response Data");

        $u = User::newSocialUser($this->data->email, OauthService::GOOGLE, $this->data->userId);

        return $u;
    }

    public function getOauthId() : string
    {
        if (!$this->data) throw new \Exception("No Google Response Data");
        return $this->data->userId;
    }

    public function getEmail() : string
    {
        if (!$this->data) throw new \Exception("No Google Response Data");
        return $this->data->email;
    }

    public function getServiceName(): string
    {
        return OauthService::GOOGLE;
    }


}