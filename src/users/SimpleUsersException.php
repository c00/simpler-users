<?php

namespace c00\users;


class SimpleUsersException extends \Exception {

	public static function new(string $message, $code = 500, $previous = null){
		$p = new SimpleUsersException($message, $code, $previous);
		return $p;
	}
}