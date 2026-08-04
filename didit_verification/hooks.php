<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

/*
|--------------------------------------------------------------------------
| Load Module Hooks
|--------------------------------------------------------------------------
*/

$hooks = __DIR__ . '/hooks/';

if (is_dir($hooks)) {

    foreach (glob($hooks . '*.php') as $file) {

        require_once $file;

    }

}