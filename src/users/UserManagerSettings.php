<?php

namespace c00\users;


use c00\common\AbstractSettings;

class UserManagerSettings extends AbstractSettings {
	/** @var string The amount of says a new or touched session will be valid for. */
	public $sessionDaysValid;
	/** @var string The header used to store the token. Default: 'x-auth' */
	public $sessionHeader;
	/** @var string The Query parameter to store the token. Default: 't' */
	public $sessionParam;
	/** @var string The fq class name of the User object to use when saving and getting users. Be sure to extend c00/users/User */
	public $userClass;
	/** @var int Minimum Password Strength between 0 and 4. 0 being none, 4 being super tight. */
	public $minPasswordStrength;

	public function loadDefaults() {
		$this->sessionDaysValid = 30;
		$this->sessionHeader = 'x-auth';
		$this->sessionParam = 't';
		$this->userClass = User::class;
		$this->minPasswordStrength = 1;
	}


}