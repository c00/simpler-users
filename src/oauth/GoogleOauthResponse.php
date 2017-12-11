<?php

namespace c00\oauth;

class GoogleOauthResponse
{
    public $displayName;
    public $email;
    public $familyName;
    public $givenName;
    public $idToken;
    public $imageUrl;
    public $userId;

    public function getFirstName() {
        if ($this->givenName) return $this->givenName;

        $names = explode(' ', $this->displayName);
        return $names[0];
    }

    public function getLastName() {
        if ($this->familyName) return $this->familyName;

        $names = explode(' ', $this->displayName);
        return $names[1] ?? '';
    }
}