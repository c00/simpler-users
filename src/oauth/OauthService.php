<?php
/**
 * Created by PhpStorm.
 * User: coo
 * Date: 5-12-17
 * Time: 17:59
 */

namespace c00\oauth;


use c00\users\User;

interface OauthService
{
    const GOOGLE = 'google';
    //Add others if you feel so inclined...
    //const FACEBOOK = 'facebook';

    public function getServiceName() : string;

    public function verify($data) : bool;

    /**
     * @return User
     */
    public function getUser() : User;

    /**
     * @return string
     */
    public function getOauthId(): string;

    /**
     * @return string
     */
    public function getEmail(): string;


}