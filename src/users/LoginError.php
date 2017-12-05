<?php

namespace c00\users;

abstract class LoginError
{
    const EMAIL_UNKNOWN = 'email-unknown';
    const PASSWORD_INVALID = 'password-invalid';
    const USER_INACTIVE = 'user-inactive';
    const OAUTH_VERIFICATION_FAILED = 'google-verification-failed';
    const OAUTH_ID_UNKNOWN = 'oauth-id-unknown';
}