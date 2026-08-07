<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

/*
|--------------------------------------------------------------------------
| Ensure Helpers Are Loaded
|--------------------------------------------------------------------------
| hooks.php is auto-loaded by WHMCS on every page request for every
| active addon module, independently of whether didit_verification.php
| (the main module file) is loaded for that request. The hook callbacks
| below rely on didit_get_client_id() / didit_log(), so helpers.php must
| be required here explicitly rather than assumed to already be present.
*/

require_once __DIR__ . '/helpers.php';

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