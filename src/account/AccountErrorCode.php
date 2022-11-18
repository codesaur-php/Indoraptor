<?php

namespace Indoraptor\Account;

interface AccountErrorCode
{
    // Generic
    const ACCOUNT_NOT_FOUND = 5000;
    const ORGANIZATION_NOT_FOUND = 5001;
    const ACCOUNT_NOT_ACTIVE = 5002;
    
    // Auth error
    const INCORRECT_CREDENTIALS = 5100;
    
    // Account creation error
    const INSERT_DUPLICATE_ACCOUNT = 5200;
    const INSERT_DUPLICATE_NEWBIE = 5202;
    const INSERT_NEWBIE_FAILURE = 5203; // it also throws SQL 23000 error when PDO error mode set to PDO::ERRMODE_EXCEPTION
    
    // Account password reset error
    const INSERT_FORGOT_FAILURE = 5300; // it also throws SQL 23000 error when PDO error mode set to PDO::ERRMODE_EXCEPTION
    const UPDATE_PASSWORD_FAILURE = 5301; // it also throws SQL 23000 error when PDO error mode set to PDO::ERRMODE_EXCEPTION
}
