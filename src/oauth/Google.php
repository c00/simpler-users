<?php

namespace c00\oauth;

class Google implements OauthService
{

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

        $clientId = $this->clientId;

        $client = new \Google_Client(['client_id' => $clientId]);

        $payload = $client->verifyIdToken($data->idToken);

        if (!$payload || $payload['aud'] !== $clientId) {
            return false;
        }
        $this->result = $payload;

        return true;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getOauthId() : string
    {
        throw new \Exception("not implemented");
    }

    public function getEmail() : string
    {
        throw new \Exception("not implemented");
    }

    public function getServiceName(): string
    {
        return OauthService::GOOGLE;
    }


}