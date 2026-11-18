<?php
/**
 * Email settings for password-reset codes (free Gmail account).
 * See "Password reset sa pamamagitan ng email" in README.md for the 5 setup steps.
 *
 * Use the 16-letter Google APP PASSWORD, not the normal Gmail password.
 * This file is git-ignored: never publish it with a real password in it.
 */
return [
    'enabled'    => false,          // set to true after filling in username + password
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'secure'     => 'tls',
    'username'   => '',             // the Gmail address
    'password'   => '',             // the 16-letter app password
    'from_email' => '',             // leave '' to use the username
    'from_name'  => 'AgapAlert',
];
