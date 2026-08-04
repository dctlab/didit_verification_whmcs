<?php

use WHMCS\Database\Capsule;

add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {

    $userid = $vars['userid'];

    $session = Capsule::table('mod_didit_sessions')
        ->where('userid', $userid)
        ->orderBy('updated_at', 'desc')
        ->first();

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

            </table>
        </div>`;

        $(".clientssummarybox").first().after(kycBox);

    }

});
</script>';

});