<?php

namespace c00\users;

class Helper
{

    /** Check if script is being run from the command line or not.
     * @return bool
     */
    public static function is_cli()
    {
        if( defined('STDIN') ) return true;

        if( empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
            return true;
        }

        return false;
    }

}