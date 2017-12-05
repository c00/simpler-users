<?php
/**
 * Created by PhpStorm.
 * User: coo
 * Date: 5-12-17
 * Time: 17:59
 */

namespace c00\oauth;


interface OauthService
{
    const GOOGLE = 'google';
    //Add others if you feel so inclined...
    //const FACEBOOK = 'facebook';

    public function getServiceName() : string;

    public function verify($data) : bool;

    /**
     * @return mixed
     */
    public function getResult();

    /**
     * @return string
     */
    public function getOauthId(): string;

    /**
     * @return string
     */
    public function getEmail(): string;


}