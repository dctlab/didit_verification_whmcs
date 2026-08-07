<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

use WHMCS\Database\Capsule;

add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {

    $userid = $vars['userid'];

    $session = didit_get_current_session($userid);

    if (!$session) {
        return;
    }

    $status      = $session->status ?? "Not Started";
    $report      = $session->report_file ?? '';
    $verifiedAt  = $session->verified_at ?? null;

    // Format verification date
    $verifiedDate = '';
    if (!empty($verifiedAt)) {
        $verifiedDate = date('d M Y, h:i A', strtotime($verifiedAt));
    }

    // Status styling
    $color = "default";
    $icon  = "⚠";

    if ($status === "Approved") {
        $color = "success";
        $icon  = "✔";
    } elseif ($status === "Declined") {
        $color = "danger";
        $icon  = "✖";
    } elseif ($status === "In Progress") {
        $color = "warning";
        $icon  = "⏳";
    } elseif ($status === "In Review") {
        $color = "info";
        $icon  = "🔎";
    }

    // Report button
    $button = '';
    if (!empty($report)) {
        $button = '
            <tr>
                <td>Report</td>
                <td>
                    <a href="../view_kyc.php?file='.$report.'&admin=1"
                       target="_blank"
                       class="btn btn-sm btn-primary">
                       📄 View Report
                    </a>
                </td>
            </tr>
        ';
    }

    // Verified date row
    $verifiedRow = '';
    if ($status === "Approved" && !empty($verifiedDate)) {
        $verifiedRow = '
            <tr>
                <td>Verified On</td>
                <td>'.$verifiedDate.'</td>
            </tr>
        ';
    }

    // Action buttons row — matches the existing "Resend KYC Email" /
    // "View KYC Status" pattern from the reference module. Resend only
    // shows when there's an email template configured to send (same
    // best-effort check as the manual-action handler), so this button
    // never silently no-ops.
    $hasTemplate = Capsule::table('tblemailtemplates')
        ->where('name', 'Didit KYC Status Update')
        ->exists();

    $resendUrl = "addonmodules.php?module=didit_verification&resend_kyc_email={$session->session_id}";
    $viewStatusUrl = "addonmodules.php?module=didit_verification&search=" . urlencode($session->email ?? '');

    $actionButtons = '
        <tr>
            <td colspan="2">
                ' . ($hasTemplate
                    ? '<a href="' . $resendUrl . '" class="btn btn-xs btn-warning">Resend KYC Email</a> '
                    : ''
                ) . '
                <a href="' . $viewStatusUrl . '" class="btn btn-xs btn-info">View KYC Status</a>
            </td>
        </tr>
    ';

echo '<script>
$(document).ready(function(){

    if($("#kycsummarybox").length === 0){

        var kycBox = `
        <div id="kycsummarybox" class="clientssummarybox">
            <div class="title">KYC Verification</div>

            <table class="clientssummarystats" cellspacing="0" cellpadding="4">

                <tr>
                    <td width="110">KYC Status</td>
                    <td>
                        <span class="badge bg-'.$color.'">
                            '.$icon.' '.$status.'
                        </span>
                    </td>
                </tr>

                '.$verifiedRow.'

                '.$button.'

                '.$actionButtons.'

            </table>
        </div>`;

        $(".clientssummarybox").first().after(kycBox);

    }

});
</script>';

});