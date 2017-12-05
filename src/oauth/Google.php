<?php
/**
 * Created by PhpStorm.
 * User: coo
 * Date: 5-12-17
 * Time: 18:44
 */

namespace c00\oauth;


class Google implements OauthService
{

    public $result;

    public $clientId;


    public function __construct($settings = null)
    {

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

    public function getOauthId()
    {
        throw new \Exception("not implemented");
    }

    public function getEmail()
    {
        throw new \Exception("not implemented");
    }


}