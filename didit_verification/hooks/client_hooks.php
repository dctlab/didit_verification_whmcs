<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

use WHMCS\Database\Capsule;

/*
|--------------------------------------------------------------------------
| CLIENT DASHBOARD – KYC STATUS CARD
|--------------------------------------------------------------------------
*/

add_hook('ClientAreaHomepage', 1, function ($vars) {

    $userid = didit_get_client_id();

    if (empty($userid)) {
        return;
    }

    $session = didit_get_current_session($userid);

    $status      = $session->status ?? "Not Started";
    $report      = $session->report_file ?? '';
    $verifiedAt  = $session->verified_at ?? null;

    $color = "warning";
    $icon  = "⏳";

    $button = '
    <a href="index.php?m=didit_verification"
       class="btn btn-warning btn-sm mt-2">
       Complete Verification
    </a>';

    $verifiedDate = '';

    /*
    |--------------------------------------------------------------------------
    | APPROVED
    |--------------------------------------------------------------------------
    */

    if ($status === "Approved") {

        $color = "success";
        $icon  = "✔";

        // ✅ Show verification date
        if (!empty($verifiedAt)) {
            $verifiedDate = '
                <p class="text-muted mt-2" style="font-size:13px;">
                    Verified on: ' . date('d M Y, h:i A', strtotime($verifiedAt)) . '
                </p>';
        }

        // ✅ Show report button
        if (!empty($report)) {
            $button = '
            <a href="view_kyc.php?file='.$report.'"
               target="_blank"
               class="btn btn-success btn-sm mt-2">
               📄 View KYC Report
            </a>';
        } else {
            $button = '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DECLINED
    |--------------------------------------------------------------------------
    */

    if ($status === "Declined") {

        $color = "danger";
        $icon  = "✖";

        $button = '
        <a href="index.php?m=didit_verification"
           class="btn btn-danger btn-sm mt-2">
           Retry Verification
        </a>';
    }

    /*
    |--------------------------------------------------------------------------
    | IN REVIEW
    |--------------------------------------------------------------------------
    | A reviewer already has this session — there's nothing for the
    | client to do, and the default "Complete Verification" button set
    | above would be actively misleading here (nothing to complete;
    | they already finished their part). No button at all, distinct
    | color/icon from the generic "still in progress" warning state.
    */

    if ($status === "In Review") {

        $color = "info";
        $icon  = "🔎";
        $button = '<p class="text-muted mt-2" style="font-size:13px;">Being reviewed by our team — no action needed from you right now.</p>';
    }

    return '
    <div class="card shadow-sm mb-4 border-'.$color.'">
        <div class="card-body text-center">

            <h5 class="card-title mb-3">KYC Verification Status</h5>

            <h3 class="text-'.$color.'">
                '.$icon.' '.$status.'
            </h3>

            '.$verifiedDate.'

            '.$button.'

        </div>
    </div>';
});



/*
|--------------------------------------------------------------------------
| BLOCK ORDER UNTIL APPROVED
|--------------------------------------------------------------------------
*/

add_hook('ShoppingCartValidateCheckout', 1, function ($vars) {

    $userid = didit_get_client_id();

    if (empty($userid)) {
        return;
    }

    /*
    | Admin Override Check
    */

    $override = Capsule::table('mod_didit_overrides')
        ->where('userid', $userid)
        ->first();

    if ($override && $override->disable_order_block == 1) {
        return;
    }

    $session = didit_get_current_session($userid);

    $status = $session->status ?? "Not Started";

    if ($status !== "Approved") {

        return "KYC verification is required before placing an order. Please complete verification.";

    }

});


/*
|--------------------------------------------------------------------------
| OPTIONAL CART REDIRECT
|--------------------------------------------------------------------------
*/

add_hook('ClientAreaPage', 1, function ($vars) {

    $userid = didit_get_client_id();

    if (empty($userid)) {
        return;
    }

    $override = Capsule::table('mod_didit_overrides')
        ->where('userid',$userid)
        ->first();

    if ($override && $override->disable_order_block == 1) {
        return;
    }

    $session = didit_get_current_session($userid);

    $status = $session->status ?? "Not Started";

    if ($status === "Approved") {
        return;
    }

    $currentPage = basename($_SERVER['PHP_SELF']);

    if ($currentPage === 'index.php' && isset($_GET['m']) && $_GET['m'] === 'didit_verification') {
        return;
    }

    if ($currentPage === 'clientarea.php') {
        return;
    }

    if ($currentPage === 'cart.php') {

        header("Location: index.php?m=didit_verification&kyc_required=1");
        exit;

    }

});

