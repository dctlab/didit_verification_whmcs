<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

use WHMCS\Database\Capsule;

/*
|--------------------------------------------------------------------------
| DAILY CRON
|--------------------------------------------------------------------------
| Runs as part of WHMCS's own daily cron (Automation Settings > Daily
| Cron), rather than requiring a separate system crontab entry — this is
| WHMCS's documented, standard mechanism for addon module automation.
|
| All the actual logic lives in didit_run_cron() (helpers.php), which is
| also directly callable for manual testing — see the "Run Cron Now"
| link on the module's Dashboard tab.
*/

add_hook('DailyCronJob', 1, function ($vars) {

    didit_run_cron();

});
