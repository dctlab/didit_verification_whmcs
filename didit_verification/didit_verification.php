<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

use WHMCS\Database\Capsule;

/*
|--------------------------------------------------------------------------
| LOAD HOOKS
|--------------------------------------------------------------------------
*/

$hooksFile = __DIR__ . '/hooks.php';
if (file_exists($hooksFile)) {
    require_once $hooksFile;
}

require_once __DIR__ . '/helpers.php';

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

function didit_verification_config()
{
    return [
        "name" => "Didit KYC Verification",
        "description" => "Didit KYC Web Redirect Integration",
        "version" => "19.2",
        "author" => "Rejil",
        "fields" => [
            "api_key" => [
                "FriendlyName" => "Didit API Key",
                "Type" => "text",
                "Size" => "60",
            ],
            "friendly_name" => [
                "FriendlyName" => "Friendly Name",
                "Type" => "text",
                "Size" => "40",
                "Default" => "Didit KYC",
                "Description" => "Display name shown to clients in the client area.",
            ],
            "api_url" => [
                "FriendlyName" => "API URL",
                "Type" => "text",
                "Size" => "50",
                "Default" => "https://verification.didit.me",
            ],
            "workflow_id_b2b" => [
                "FriendlyName" => "B2B Workflow ID",
                "Type" => "text",
                "Size" => "40",
                "Description" => "Used for clients with a company name on file. See the General Settings tab for a dropdown of your actual workflows.",
            ],
            "workflow_id_b2c" => [
                "FriendlyName" => "B2C Workflow ID",
                "Type" => "text",
                "Size" => "40",
                "Description" => "Used for clients without a company name on file.",
            ],
            "workflow_id" => [
                "FriendlyName" => "Workflow ID (legacy)",
                "Type" => "text",
                "Size" => "40",
                "Description" => "Pre-B2B/B2C-split setting, kept as a fallback for upgrades. Prefer the B2B/B2C fields above.",
            ],
            "webhook_secret" => [
                "FriendlyName" => "Webhook Secret",
                "Type" => "text",
                "Size" => "60",
            ],

            // --- Provisioning control ---------------------------------

            "stop_provisioning" => [
                "FriendlyName" => "Stop Provisioning",
                "Type" => "dropdown",
                "Options" => "None,Products,Domains,Both",
                "Default" => "None",
                "Description" => "Block new orders of this type until KYC is approved.",
            ],
            "restricted_tlds" => [
                "FriendlyName" => "Restricted TLDs",
                "Type" => "text",
                "Size" => "60",
                "Description" => "Comma-separated (e.g. .in,.us,.eu). Blank = all domain orders require KYC when Stop Provisioning includes Domains.",
            ],
            "auto_accept_delay" => [
                "FriendlyName" => "Auto Accept Delay (minutes)",
                "Type" => "text",
                "Size" => "10",
                "Default" => "10",
                "Description" => "Delay before a blocked order is auto-accepted after KYC approval, via the daily cron.",
            ],

            // --- Reminders ---------------------------------------------

            "reminder_signup_days" => [
                "FriendlyName" => "Reminder After Signup (days)",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after registration to send the first nudge. Blank = disabled.",
            ],
            "reminder_signup_template" => [
                "FriendlyName" => "Signup Reminder Template",
                "Type" => "text",
                "Size" => "30",
                "Description" => "Email template name. Use the Cron Settings tab for a dropdown of your actual templates.",
            ],
            "reminder_1_days" => [
                "FriendlyName" => "First Reminder (days)",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after the signup reminder. Blank = disabled.",
            ],
            "reminder_1_template" => [
                "FriendlyName" => "First Reminder Template",
                "Type" => "text",
                "Size" => "30",
            ],
            "reminder_2_days" => [
                "FriendlyName" => "Second Reminder (days)",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after the first reminder. Blank = disabled.",
            ],
            "reminder_2_template" => [
                "FriendlyName" => "Second Reminder Template",
                "Type" => "text",
                "Size" => "30",
            ],
            "reminder_3_days" => [
                "FriendlyName" => "Third Reminder (days)",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after the second reminder. Blank = disabled.",
            ],
            "reminder_3_template" => [
                "FriendlyName" => "Third Reminder Template",
                "Type" => "text",
                "Size" => "30",
            ],

            // --- Enforcement ---------------------------------------------

            "enforcement_enabled" => [
                "FriendlyName" => "Enable Enforcement Actions",
                "Type" => "yesno",
                "Description" => "Master switch. Must be checked, AND the actions below must not be 'None', for any automatic suspend/cancel/terminate/close to ever run. Off by default — read the Service/Account Action settings carefully before enabling.",
            ],
            "service_action" => [
                "FriendlyName" => "Service Action",
                "Type" => "dropdown",
                "Options" => "None,Suspend,Cancel,Terminate",
                "Default" => "None",
                "Description" => "Applied to active services if KYC remains incomplete past the days below. Terminate is immediate and irreversible.",
            ],
            "service_action_days" => [
                "FriendlyName" => "Service Action Days",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after the last reminder (or signup, if no reminders configured) before the Service Action applies.",
            ],
            "account_action" => [
                "FriendlyName" => "Account Action",
                "Type" => "dropdown",
                "Options" => "None,Inactive,Close",
                "Default" => "None",
                "Description" => "Applied to the client account itself. Close cancels all invoices and services — cascades beyond just KYC-gated ones.",
            ],
            "account_action_days" => [
                "FriendlyName" => "Account Action Days",
                "Type" => "text",
                "Size" => "10",
                "Description" => "Days after the last reminder before the Account Action applies.",
            ],

            // --- Approval / rejection notification emails ---------------

            "template_approved" => [
                "FriendlyName" => "KYC Approval Email Template",
                "Type" => "text",
                "Size" => "30",
                "Description" => "Sent to the client automatically whenever their KYC becomes Approved — from a webhook or a manual admin action. Use the Configuration tab for a dropdown of your actual templates.",
            ],
            "template_declined" => [
                "FriendlyName" => "KYC Rejection Email Template",
                "Type" => "text",
                "Size" => "30",
                "Description" => "Sent to the client automatically whenever their KYC becomes Declined.",
            ],
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| ACTIVATE MODULE
|--------------------------------------------------------------------------
*/

function didit_verification_activate()
{
    require_once __DIR__ . '/helpers.php';

    didit_ensure_schema();

    return [
        'status' => 'success',
        'description' => 'Didit Verification Module Activated'
    ];
}


/*
|--------------------------------------------------------------------------
| UPGRADE MODULE
|--------------------------------------------------------------------------
| Called automatically by WHMCS when it detects the version in
| didit_verification_config() is newer than what's stored for this
| install — no manual reactivation or upgrade.php visit required.
*/

function didit_verification_upgrade($vars)
{
    require_once __DIR__ . '/helpers.php';

    didit_ensure_schema();
}


/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

function didit_verification_output()
{


require_once __DIR__ . '/helpers.php';

didit_ensure_schema();

echo didit_admin_v5_styles();

echo "<div class='didit-admin-v5'>";

/*
|--------------------------------------------------------------------------
| DOWNLOAD PDF
|--------------------------------------------------------------------------
*/

if (isset($_GET['download_pdf'])) {

    $sessionId = trim($_GET['download_pdf']);

    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if (!$session) {
        exit('Session not found');
    }

    if (empty($session->report_file)) {
        exit('PDF not generated');
    }

    $file = '/home/ishroot/webapps/mcs/myaccountdata/kyc_reports/' . $session->report_file;

    if (!file_exists($file)) {
        exit('File missing: ' . $file);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));

    readfile($file);
    exit;
}


if (isset($_GET['generate_pdf'])) {

    $sessionId = trim($_GET['generate_pdf']);

    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if ($session && $session->status == 'Approved') {

        $apiKey = Capsule::table('tbladdonmodules')
            ->where('module','didit_verification')
            ->where('setting','api_key')
            ->value('value');

        $result = didit_download_report(
            $session->session_id,
            $apiKey,
            $session->userid
        );

header("Location: addonmodules.php?module=didit_verification");
exit;

        if ($result) {

            echo "<div class='alert alert-success'>
                    PDF generated successfully: {$result}
                  </div>";

        } else {

            echo "<div class='alert alert-danger'>
                    PDF generation failed. Check Activity Log.
                  </div>";
        }
    }
}

/*
| RUN CRON NOW (manual trigger, for testing without waiting on WHMCS's
| daily cron cycle — same didit_run_cron() the DailyCronJob hook calls)
|--------------------------------------------------------------------------
*/

if (isset($_GET['run_cron_now'])) {

    if (!isset($_GET['confirm'])) {

        $enforcementOn = didit_get_setting('enforcement_enabled') == 'on';

        echo "<div class='alert alert-warning'>
            This will run every cron task immediately, including reminder emails" .
            ($enforcementOn ? " <strong>and enforcement actions (suspend/cancel/terminate/close), since Enable Enforcement Actions is currently ON</strong>" : " (enforcement is currently OFF, so no destructive actions will run)") . ".
            <br><br>
            <a href='addonmodules.php?module=didit_verification&run_cron_now=1&confirm=1' class='btn btn-warning btn-sm'>Yes, run it now</a>
            <a href='addonmodules.php?module=didit_verification' class='btn btn-default btn-sm'>Cancel</a>
        </div>";

    } else {

        didit_run_cron();

        echo "<div class='alert alert-success'>Cron run complete — check the Activity Log and Module Log for details.</div>";
    }
}

/*
| DELETE SESSION
|--------------------------------------------------------------------------
| Permanently removes a single session row. Requires an explicit
| confirm step (same pattern as Run Cron Now above) before anything
| happens — this can't be undone, and unlike suspending/declining a
| client it has no corresponding "undo" action anywhere else in this
| module. Logged to mod_didit_audit BEFORE the delete runs, so there's
| still a record that this session existed and was removed, by whom,
| even though the row itself is gone.
*/

if (isset($_GET['delete_session'])) {

    $sessionIdToDelete = trim($_GET['delete_session']);

    $sessionToDelete = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionIdToDelete)
        ->first();

    if (!$sessionToDelete) {

        echo "<div class='alert alert-danger'>Session not found.</div>";

    } elseif (!isset($_GET['confirm'])) {

        $clientName = Capsule::table('tblclients')->where('id', $sessionToDelete->userid)->value('firstname');

        echo "<div class='alert alert-warning'>
            Permanently delete this session? This cannot be undone.<br>
            <strong>Client:</strong> " . htmlspecialchars($clientName ?: "UserID {$sessionToDelete->userid}") . "<br>
            <strong>Session ID:</strong> <code>{$sessionToDelete->session_id}</code><br>
            <strong>Status:</strong> {$sessionToDelete->status}<br>
            <strong>Created:</strong> {$sessionToDelete->created_at}
            <br><br>
            <a href='addonmodules.php?module=didit_verification&delete_session=" . urlencode($sessionIdToDelete) . "&confirm=1' class='btn btn-danger btn-sm'>Yes, delete permanently</a>
            <a href='addonmodules.php?module=didit_verification&tab=online_kyc' class='btn btn-default btn-sm'>Cancel</a>
        </div>";

    } else {

        $adminId = $_SESSION['adminid'] ?? null;

        $adminUsername = $adminId
            ? (Capsule::table('tbladmins')->where('id', $adminId)->value('username') ?? 'unknown')
            : 'unknown';

        Capsule::table('mod_didit_audit')->insert([
            'session_id' => $sessionToDelete->session_id,
            'userid' => $sessionToDelete->userid,
            'admin_id' => $adminId,
            'admin_username' => $adminUsername,
            'action' => 'Delete',
            'previous_status' => $sessionToDelete->status,
            'new_status' => null,
            'reason' => 'Session permanently deleted by admin',
            'comment' => null,
            'email_sent' => 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        didit_log(
            'SessionDeleted',
            "SessionID={$sessionToDelete->session_id} | Admin={$adminUsername}",
            "PreviousStatus={$sessionToDelete->status} | UserID={$sessionToDelete->userid}"
        );

        logActivity("Didit Session Deleted | SessionID={$sessionToDelete->session_id} | Admin={$adminUsername}");

        Capsule::table('mod_didit_sessions')
            ->where('session_id', $sessionIdToDelete)
            ->delete();

        echo "<div class='alert alert-success'>Session deleted.</div>";
    }
}

/*
| SYNC SESSION WITH DIDIT (on-demand, single session)
|--------------------------------------------------------------------------
| Pulls this one session's current Session ID / Status / Verification
| Date live from Didit and updates the local record to match — a manual
| version of the daily cron sync, scoped to one row instead of a batch.
| Notably covers a gap the cron job doesn't: didit_cron_sync_pending_sessions()
| only re-checks sessions still Not Started/In Progress, so an
| already-resolved (Approved/Declined) local record that later turns
| out to disagree with Didit's side has nothing automatically catching
| it. This lets an admin force that check for one specific session on
| demand, e.g. after being told "Didit shows something different."
*/

if (isset($_GET['sync_session'])) {

    $sessionIdToSync = trim($_GET['sync_session']);

    $sessionToSync = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionIdToSync)
        ->first();

    if (!$sessionToSync) {

        echo "<div class='alert alert-danger'>Session not found.</div>";

    } else {

        $apiKey = didit_get_setting('api_key');

        if (empty($apiKey)) {

            echo "<div class='alert alert-warning'>No API key configured — can't sync with Didit.</div>";

        } else {

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => didit_get_api_url() . "/v3/session/{$sessionIdToSync}/decision/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            didit_log(
                'ManualSync',
                "SessionID={$sessionIdToSync}",
                "HTTP {$httpCode}" . ($curlError ? " | CURL Error: {$curlError}" : ''),
                '',
                $apiKey
            );

            if ($httpCode === 404) {

                Capsule::table('mod_didit_sessions')
                    ->where('id', $sessionToSync->id)
                    ->update(['deleted_upstream' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

                echo "<div class='alert alert-warning'>This session no longer exists on Didit (404) — flagged and excluded from the admin tables, same as the daily cron would do.</div>";

            } elseif ($curlError || $httpCode !== 200 || !$response) {

                echo "<div class='alert alert-danger'>Couldn't reach Didit — see the Module Log (ManualSync) for details.</div>";

            } else {

                $data = json_decode($response, true);
                $remoteStatus = $data['status'] ?? null;

                if (!$remoteStatus) {

                    echo "<div class='alert alert-danger'>Didit's response didn't include a status — see the Module Log (ManualSync) for the raw response.</div>";

                } else {

                    $mappedStatus = didit_map_status($remoteStatus);
                    $decisionDate = didit_extract_decision_date($data);

                    if ($mappedStatus === $sessionToSync->status) {

                        /*
                        | Status already matches — this does NOT touch
                        | updated_at to "now" (that was wrong: it would
                        | make an approval from weeks ago look like it
                        | just happened). Only updates the date if Didit's
                        | response actually included a real decision
                        | timestamp AND that's genuinely different from
                        | what's stored — otherwise the existing date is
                        | left completely alone.
                        */

                        if ($decisionDate && $decisionDate !== $sessionToSync->updated_at) {

                            Capsule::table('mod_didit_sessions')
                                ->where('id', $sessionToSync->id)
                                ->update(['updated_at' => $decisionDate]);

                            echo "<div class='alert alert-info'>Already up to date — Didit also shows <strong>{$mappedStatus}</strong> (raw: {$remoteStatus}). Verification Date corrected to {$decisionDate} from Didit's response.</div>";

                        } else {

                            echo "<div class='alert alert-info'>Already up to date — Didit also shows <strong>{$mappedStatus}</strong> (raw: {$remoteStatus})." .
                                (!$decisionDate ? " Didit's response didn't include a recognizable date field, so Verification Date was left unchanged — see the Module Log (ManualSync) for the raw response if you want to check what it actually returned." : "") .
                                "</div>";
                        }

                    } else {

                        didit_apply_status_change($sessionIdToSync, $mappedStatus, 'manual-sync');

                        if ($decisionDate) {

                            Capsule::table('mod_didit_sessions')
                                ->where('id', $sessionToSync->id)
                                ->update(['updated_at' => $decisionDate]);
                        }

                        echo "<div class='alert alert-success'>Synced — updated from <strong>{$sessionToSync->status}</strong> to <strong>{$mappedStatus}</strong> (Didit raw status: {$remoteStatus})." .
                            ($decisionDate ? " Verification Date set to {$decisionDate} from Didit's response." : " Didit's response didn't include a recognizable date field, so Verification Date reflects when this sync ran instead.") .
                            "</div>";
                    }
                }
            }
        }
    }
}

/*
| RESEND KYC EMAIL (from client summary page widget)
|--------------------------------------------------------------------------
*/

if (isset($_GET['resend_kyc_email'])) {

    $sessionId = trim($_GET['resend_kyc_email']);

    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if (!$session) {

        echo "<div class='alert alert-danger'>Session not found.</div>";

    } else {

        $templateExists = Capsule::table('tblemailtemplates')
            ->where('name', 'Didit KYC Status Update')
            ->exists();

        if (!$templateExists) {

            echo "<div class='alert alert-warning'>No 'Didit KYC Status Update' email template exists yet — create one under Setup &gt; Email Templates to enable this.</div>";

        } else {

            try {

                $apiResult = localAPI('SendEmail', [
                    'messagename' => 'Didit KYC Status Update',
                    'id' => $session->userid,
                    'customtype' => 'general',
                    'customvars' => base64_encode(serialize([
                        'status' => $session->status,
                        'admin_comment' => '',
                    ])),
                ]);

                if (($apiResult['result'] ?? '') === 'success') {

                    didit_log('ManualAction', "SessionID={$sessionId} | ResendEmail", "Resent to UserID={$session->userid}");
                    echo "<div class='alert alert-success'>KYC status email resent.</div>";

                } else {

                    echo "<div class='alert alert-danger'>Email failed: " . ($apiResult['message'] ?? 'unknown error') . "</div>";
                }

            } catch (\Throwable $e) {

                logActivity("Didit Resend Email Error: " . $e->getMessage());
                echo "<div class='alert alert-danger'>Email failed: " . $e->getMessage() . "</div>";
            }
        }
    }
}

    /*
    | MANUAL APPROVE / DECLINE / RESUBMIT
    |--------------------------------------------------------------------------
    | Shares the exact same status-application logic as the webhook
    | (didit_apply_status_change() in helpers.php) so a manual override
    | and an automatic webhook update can never behave differently for
    | the same status. Every action is recorded in mod_didit_audit.
    |
    | "Resubmit" deliberately sets status back to "In Progress" rather
    | than a new "Resubmitted" bucket — see helpers.php comments on
    | didit_ensure_schema() for why introducing a 5th status value is
    | riskier than it looks (several places whitelist the exact set of
    | 4 status strings for session-reuse/active-session checks).
    */

    if (isset($_POST['manual_action']) && isset($_POST['session_id'])) {

        check_token("WHMCS.admin.default");

        $sessionId = trim($_POST['session_id']);
        $action    = $_POST['manual_action'];
        $reason    = trim($_POST['reason'] ?? '');
        $comment   = trim($_POST['comment'] ?? '');
        $wantsEmail = isset($_POST['send_email']);

        $actionToStatus = [
            'Approve'  => 'Approved',
            'Decline'  => 'Declined',
            'Resubmit' => 'In Progress',
        ];

        if (!array_key_exists($action, $actionToStatus)) {

            echo "<div class='alert alert-danger'>Invalid action.</div>";

        } elseif (empty($reason)) {

            echo "<div class='alert alert-danger'>Please select a reason.</div>";

        } else {

            $session = Capsule::table('mod_didit_sessions')
                ->where('session_id', $sessionId)
                ->first();

            if (!$session) {

                echo "<div class='alert alert-danger'>Session not found.</div>";

            } else {

                $previousStatus = $session->status;
                $newStatus = $actionToStatus[$action];

                $adminId = $_SESSION['adminid'] ?? null;

                $adminUsername = $adminId
                    ? (Capsule::table('tbladmins')->where('id', $adminId)->value('username') ?? 'unknown')
                    : 'unknown';

                // Combine the preset reason + free-text comment into one
                // display string for mod_didit_sessions.reason (shown in
                // the View Details modal); mod_didit_audit keeps them as
                // separate columns for anyone who wants the raw split.
                $reasonForDisplay = $reason . ($comment ? " — {$comment}" : '');

                $userid = didit_apply_status_change($sessionId, $newStatus, "manual:{$adminUsername}", $reasonForDisplay ?: null);

                if ($userid === false) {
                    $userid = $session->userid;
                }

                /*
                | SYNC TO DIDIT (best-effort)
                |
                | Keeps Didit's own records aligned with a manual
                | override made here — without this, an admin manually
                | approving/declining someone only ever changed WHMCS's
                | local copy, and Didit's side would silently disagree
                | forever. See didit_sync_status_to_didit()'s docblock
                | for the caveat on this endpoint's request body not
                | being independently confirmed.
                */

                $diditSyncOk = didit_sync_status_to_didit($sessionId, $newStatus);
                $diditSyncNote = $diditSyncOk
                    ? ''
                    : (in_array($newStatus, ['Approved', 'Declined'], true)
                        ? ' (⚠ did not sync to Didit — see Module Log/SyncStatusToDidit)'
                        : '');

                /*
                | SEND EMAIL (best-effort)
                |
                | For Approve/Decline this reuses the same
                | didit_send_outcome_email() helper the webhook path
                | calls automatically — same template resolution
                | (template_approved/template_declined, falling back to
                | the generic auto-created template), so a manual
                | override and an automatic webhook outcome always use
                | the same email for the same result. Resubmit has no
                | dedicated outcome template (it's not a final result),
                | so it keeps using the generic template directly.
                */

                $emailSent = false;
                $emailNote = '';

                if ($wantsEmail) {

                    if (in_array($newStatus, ['Approved', 'Declined'], true)) {

                        $emailSent = didit_send_outcome_email($userid, $newStatus, $comment);

                        if (!$emailSent) {
                            $emailNote = ' (email not sent — check the configured template exists; see Activity Log for details)';
                        }

                    } else {

                        $templateExists = Capsule::table('tblemailtemplates')
                            ->where('name', 'Didit KYC Status Update')
                            ->exists();

                        if ($templateExists) {

                            try {

                                $apiResult = localAPI('SendEmail', [
                                    'messagename' => 'Didit KYC Status Update',
                                    'id' => $userid,
                                    'customtype' => 'general',
                                    'customvars' => base64_encode(serialize([
                                        'status' => $newStatus,
                                        'admin_comment' => $comment,
                                    ])),
                                ]);

                                $emailSent = ($apiResult['result'] ?? '') === 'success';

                                if (!$emailSent) {
                                    $emailNote = ' (email failed: ' . ($apiResult['message'] ?? 'unknown error') . ')';
                                }

                            } catch (\Throwable $e) {

                                logActivity("Didit Manual Action Email Error: " . $e->getMessage());
                                $emailNote = ' (email failed: ' . $e->getMessage() . ')';
                            }

                        } else {

                            $emailNote = ' (email NOT sent: no "Didit KYC Status Update" email template exists yet — create one under Setup > Email Templates)';
                        }
                    }
                }

                Capsule::table('mod_didit_audit')->insert([
                    'session_id' => $sessionId,
                    'userid' => $userid,
                    'admin_id' => $adminId,
                    'admin_username' => $adminUsername,
                    'action' => $action,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                    'comment' => $comment,
                    'email_sent' => $emailSent ? 1 : 0,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                didit_log(
                    'ManualAction',
                    "SessionID={$sessionId} | Action={$action} | Admin={$adminUsername} | Reason={$reason}",
                    "PreviousStatus={$previousStatus} | NewStatus={$newStatus} | EmailSent=" . ($emailSent ? 'yes' : 'no')
                );

                echo "<div class='alert alert-success'>Session {$action}d — status changed from <strong>{$previousStatus}</strong> to <strong>{$newStatus}</strong>.{$emailNote}{$diditSyncNote}</div>";
            }
        }
    }

    /*
    | SAVE ADMIN OVERRIDE
    */

    if (isset($_POST['userid'])) {

        check_token("WHMCS.admin.default");

        $userid = (int)$_POST['userid'];

        $disableSuspend = isset($_POST['disable_suspend']) ? 1 : 0;
        $disableOrder   = isset($_POST['disable_order_block']) ? 1 : 0;

        $exists = Capsule::table('mod_didit_overrides')
            ->where('userid',$userid)
            ->first();

        if ($exists) {

            Capsule::table('mod_didit_overrides')
                ->where('userid',$userid)
                ->update([
                    'disable_suspend'=>$disableSuspend,
                    'disable_order_block'=>$disableOrder,
                    'updated_at'=>date('Y-m-d H:i:s')
                ]);

        } else {

            Capsule::table('mod_didit_overrides')
                ->insert([
                    'userid'=>$userid,
                    'disable_suspend'=>$disableSuspend,
                    'disable_order_block'=>$disableOrder,
                    'updated_at'=>date('Y-m-d H:i:s')
                ]);
        }

        echo "<div class='alert alert-success'>Override updated successfully.</div>";
    }

    /*
    | TEST CONNECTION
    |--------------------------------------------------------------------------
    | Same GET /v3/workflows/ call used to populate the workflow dropdowns
    | — a successful response IS the connection test, no separate
    | lighter-weight endpoint needed.
    */

    if (isset($_GET['test_connection'])) {

        $workflows = didit_fetch_workflows();

        if ($workflows === false) {

            echo "<div class='alert alert-danger'>Connection failed — check the API Key and API URL. See the Module Log for details.</div>";

        } else {

            $count = count($workflows);
            echo "<div class='alert alert-success'>Connected — found {$count} workflow" . ($count === 1 ? '' : 's') . ".</div>";
        }
    }

    /*
    | SAVE GENERAL SETTINGS / CONFIGURATION
    |--------------------------------------------------------------------------
    | Writes directly to tbladdonmodules using the same setting names as
    | didit_verification_config()'s fields array, so this stays perfectly
    | interoperable with WHMCS's own native module settings page — either
    | UI edits the same underlying values.
    */

    if (isset($_POST['save_settings_group'])) {

        check_token("WHMCS.admin.default");

        $group = $_POST['save_settings_group'];

        $allowedFieldsByGroup = [
            'general' => ['api_key', 'friendly_name', 'api_url', 'workflow_id_b2b', 'workflow_id_b2c', 'webhook_secret'],
            'cron'    => [
                'reminder_signup_days', 'reminder_signup_template',
                'reminder_1_days', 'reminder_1_template',
                'reminder_2_days', 'reminder_2_template',
                'reminder_3_days', 'reminder_3_template',
            ],
            'configuration' => [
                'stop_provisioning', 'restricted_tlds', 'auto_accept_delay',
                'enforcement_enabled', 'service_action', 'service_action_days',
                'account_action', 'account_action_days',
                'template_approved', 'template_declined',
            ],
        ];

        $fields = $allowedFieldsByGroup[$group] ?? [];

        foreach ($fields as $field) {

            // yesno fields only appear in $_POST when checked — absence
            // means "off", not "leave unchanged".
            $value = $_POST[$field] ?? '';

            Capsule::table('tbladdonmodules')
                ->updateOrInsert(
                    ['module' => 'didit_verification', 'setting' => $field],
                    ['value' => $value]
                );
        }

        didit_log('SettingsSaved', "Group={$group}", "Admin=" . ($_SESSION['adminusername'] ?? 'unknown'));

        echo "<div class='alert alert-success'>Settings saved.</div>";
    }

    /*
    |--------------------------------------------------------------------------
    | TAB NAVIGATION
    |--------------------------------------------------------------------------
    | Everything below routes on $activeTab. Action handlers above this
    | point run regardless of which tab is active, so a form submitted
    | from any tab still processes correctly and its result message
    | shows on whichever tab the admin lands back on.
    */

    $tabs = [
        'dashboard'     => 'Dashboard',
        'settings'      => 'General Settings',
        'cron'          => 'Cron Settings',
        'configuration' => 'Configuration',
        'online_kyc'    => 'Online KYC',
        'logs'          => 'Logs',
        'documentation' => 'Documentation',
    ];

    $activeTab = $_GET['tab'] ?? 'dashboard';

    if (!array_key_exists($activeTab, $tabs)) {
        $activeTab = 'dashboard';
    }

    echo "<h2>Didit KYC Verification</h2><ul class='nav nav-tabs' style='margin-bottom:20px'>";

    foreach ($tabs as $key => $label) {

        $isActive = ($key === $activeTab) ? ' class="active"' : '';

        echo "<li{$isActive}><a href='addonmodules.php?module=didit_verification&tab={$key}'>{$label}</a></li>";
    }

    echo "</ul>";

    if ($activeTab === 'dashboard') {
        didit_render_tab_dashboard();
    }

    if ($activeTab === 'settings') {
        didit_render_tab_settings();
    }

    if ($activeTab === 'cron') {
        didit_render_tab_cron();
    }

    if ($activeTab === 'configuration') {
        didit_render_tab_configuration();
    }

    if ($activeTab === 'logs') {
        didit_render_tab_logs();
    }

    if ($activeTab === 'documentation') {
        didit_render_tab_documentation();
    }

    if ($activeTab === 'online_kyc') {

    echo "<h2>Didit Verification Dashboard</h2>";

    /*
    | Enforcement status banner — always visible on the dashboard, since
    | this setting can suspend/cancel/terminate/close real accounts and
    | should never be something an admin forgets is on.
    */

    $enforcementOn = didit_get_setting('enforcement_enabled') == 'on';
    $serviceAction = didit_get_setting('service_action') ?: 'None';
    $accountAction = didit_get_setting('account_action') ?: 'None';

    if ($enforcementOn && ($serviceAction !== 'None' || $accountAction !== 'None')) {

        echo "<div class='alert alert-danger'>
            ⚠ Enforcement Actions are <strong>ON</strong> — Service: <strong>{$serviceAction}</strong>, Account: <strong>{$accountAction}</strong>.
            The daily cron will automatically apply these to clients with incomplete KYC.
            <a href='addonmodules.php?module=didit_verification&run_cron_now=1' class='btn btn-xs btn-default' style='margin-left:8px'>Run Cron Now</a>
        </div>";

    } else {

        echo "<div class='alert alert-info'>
            Enforcement Actions are off — reminders and status sync still run daily, nothing destructive happens automatically.
            <a href='addonmodules.php?module=didit_verification&run_cron_now=1' class='btn btn-xs btn-default' style='margin-left:8px'>Run Cron Now</a>
        </div>";
    }

    // Raw row counts, matching the tables below (which now show every
    // historical session, not deduplicated to one-per-client) — kept
    // consistent so the numbers here always match what's actually in
    // the tables underneath.
    $total    = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->count();
    $approved = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status','Approved')->count();
    $declined = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status','Declined')->count();
    $progress = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status','In Progress')->count();
    $inReview = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status','In Review')->count();

    echo "
    <div class='row' style='margin-bottom:20px'>
        <div class='col-md-2'><div class='alert alert-info'><h4>Total</h4><h2>$total</h2></div></div>
        <div class='col-md-2'><div class='alert alert-success'><h4>Approved</h4><h2>$approved</h2></div></div>
        <div class='col-md-2'><div class='alert alert-danger'><h4>Declined</h4><h2>$declined</h2></div></div>
        <div class='col-md-2'><div class='alert alert-warning'><h4>In Progress</h4><h2>$progress</h2></div></div>
        <div class='col-md-3'><div class='alert' style='background:#d1ecf1;color:#0c5460'><h4>In Review</h4><h2>$inReview</h2></div></div>
    </div>
    ";

	/*
|--------------------------------------------------------------------------
| FILTER FORM
|--------------------------------------------------------------------------
*/

echo '

<form method="get" style="margin-bottom:20px">

<input type="hidden" name="module" value="didit_verification">
<input type="hidden" name="tab" value="online_kyc">

<div class="row">

<div class="col-md-3">
<input type="text"
name="search"
class="form-control"
placeholder="Search name or email"
value="'.($_GET['search'] ?? '').'">
</div>

<div class="col-md-2">
<select name="status" class="form-control">

<option value="">All Status</option>

<option value="Approved" '.(($_GET['status'] ?? '') === 'Approved' ? 'selected' : '').'>Approved</option>
<option value="Declined" '.(($_GET['status'] ?? '') === 'Declined' ? 'selected' : '').'>Declined</option>
<option value="In Progress" '.(($_GET['status'] ?? '') === 'In Progress' ? 'selected' : '').'>In Progress</option>
<option value="In Review" '.(($_GET['status'] ?? '') === 'In Review' ? 'selected' : '').'>In Review</option>
<option value="Not Started" '.(($_GET['status'] ?? '') === 'Not Started' ? 'selected' : '').'>Not Started</option>

</select>
</div>

<div class="col-md-2">
<input type="date"
name="date_from"
class="form-control"
value="'.($_GET['date_from'] ?? '').'">
</div>

<div class="col-md-2">
<input type="date"
name="date_to"
class="form-control"
value="'.($_GET['date_to'] ?? '').'">
</div>

<div class="col-md-3">

<button class="btn btn-primary">
Filter
</button>

<a href="addonmodules.php?module=didit_verification&tab=online_kyc"
class="btn btn-default">
Reset
</a>

</div>

</div>

</form>

';

    renderAdminTable(['Not Started','In Progress'], 'Sessions Started', 'page_started');
    renderAdminTable(['In Review'], 'In Review — Needs Manual Decision', 'page_review');
    renderAdminTable(['Approved'], 'Approved Clients', 'page_approved');
    renderAdminTable(['Declined'], 'Declined Clients', 'page_declined');

    didit_render_history_modal();
    didit_render_status_picker_modal();
    didit_render_manual_action_modal();



/*
|--------------------------------------------------------------------------
| ADMIN OVERRIDE PANEL
|--------------------------------------------------------------------------
*/

echo "<hr><h3>KYC Admin Overrides</h3>";

/*
|--------------------------------------------------------------------------
| FILTER FORM
|--------------------------------------------------------------------------
*/

echo '

<form method="get" style="margin-bottom:20px">

<input type="hidden" name="module" value="didit_verification">
<input type="hidden" name="tab" value="online_kyc">

<div class="row">

<div class="col-md-3">
<input type="text"
name="search"
class="form-control"
placeholder="Search name or email"
value="'.($_GET['search'] ?? '').'">
</div>

<div class="col-md-2">
<select name="status" class="form-control">

<option value="">All Status</option>
<option value="Approved" '.(($_GET['status'] ?? '') === 'Approved' ? 'selected' : '').'>Approved</option>
<option value="Declined" '.(($_GET['status'] ?? '') === 'Declined' ? 'selected' : '').'>Declined</option>
<option value="In Progress" '.(($_GET['status'] ?? '') === 'In Progress' ? 'selected' : '').'>In Progress</option>
<option value="In Review" '.(($_GET['status'] ?? '') === 'In Review' ? 'selected' : '').'>In Review</option>
<option value="Not Started" '.(($_GET['status'] ?? '') === 'Not Started' ? 'selected' : '').'>Not Started</option>

</select>
</div>

<div class="col-md-2">
<input type="date"
name="date_from"
class="form-control"
value="'.($_GET['date_from'] ?? '').'">
</div>

<div class="col-md-2">
<input type="date"
name="date_to"
class="form-control"
value="'.($_GET['date_to'] ?? '').'">
</div>

<div class="col-md-3">

<button class="btn btn-primary">Filter</button>

<a href="addonmodules.php?module=didit_verification&tab=online_kyc"
class="btn btn-default">
Reset
</a>

</div>

</div>

</form>
';


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$query = Capsule::table('mod_didit_sessions')
    ->leftJoin('tblclients','mod_didit_sessions.userid','=','tblclients.id')
    ->leftJoin('mod_didit_overrides','mod_didit_sessions.userid','=','mod_didit_overrides.userid')
    /*
    | Same latest-per-client self-join used in renderAdminTable() —
    | the plain GROUP BY userid this panel used to have doesn't
    | guarantee which row's status/other columns get returned per
    | group (MySQL's non-strict GROUP BY picks arbitrarily), which
    | would undermine the Approved-client auto-check below: it needs
    | each client's real current status, not whichever row happened
    | to be selected.
    */
    ->leftJoin('mod_didit_sessions as newer_sessions_ov', function ($join) {
        $join->on('newer_sessions_ov.userid', '=', 'mod_didit_sessions.userid')
            ->where(function ($q) {
                $q->whereColumn('newer_sessions_ov.updated_at', '>', 'mod_didit_sessions.updated_at')
                  ->orWhere(function ($q2) {
                      $q2->whereColumn('newer_sessions_ov.updated_at', '=', 'mod_didit_sessions.updated_at')
                         ->whereColumn('newer_sessions_ov.id', '>', 'mod_didit_sessions.id');
                  });
            });
    })
    ->whereNull('newer_sessions_ov.id')
    ->where('mod_didit_sessions.deleted_upstream', 0);


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if (!empty($_GET['search'])) {

    $search = $_GET['search'];

    $query->where(function($q) use ($search){

        $q->where('tblclients.firstname','LIKE',"%$search%")
        ->orWhere('tblclients.lastname','LIKE',"%$search%")
        ->orWhere('tblclients.email','LIKE',"%$search%")
        ->orWhere('mod_didit_sessions.email','LIKE',"%$search%")
        ->orWhere('mod_didit_sessions.userid','LIKE',"%$search%");

    });
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (!empty($_GET['status'])) {

    $query->where('mod_didit_sessions.status',$_GET['status']);

}


/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

if (!empty($_GET['date_from'])) {

    $query->whereDate(
        'mod_didit_sessions.updated_at',
        '>=',
        $_GET['date_from']
    );
}

if (!empty($_GET['date_to'])) {

    $query->whereDate(
        'mod_didit_sessions.updated_at',
        '<=',
        $_GET['date_to']
    );
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$page = isset($_GET['page_overrides']) ? (int)$_GET['page_overrides'] : 1;

$limit = 10;

$offset = ($page - 1) * $limit;

$total = $query->count();


$clients = $query
    ->select(
        'mod_didit_sessions.userid as session_userid',
        'tblclients.id as matched_client_id',
        'tblclients.firstname',
        'tblclients.lastname',
        'tblclients.email as client_email',
        'mod_didit_sessions.email as session_email',
        'mod_didit_sessions.status',
        'mod_didit_overrides.disable_suspend',
        'mod_didit_overrides.disable_order_block'
    )
    ->orderBy('mod_didit_sessions.userid','desc')
    ->limit($limit)
    ->offset($offset)
    ->get();


echo "<table class='table table-striped table-bordered'>

<thead>

<tr>

<th>Client</th>
<th>Email</th>
<th>Status</th>
<th>Disable Suspend</th>
<th>Disable Order Block</th>
<th>Save</th>

</tr>

</thead>";


foreach ($clients as $client) {

    $disableSuspend = $client->disable_suspend ?? 0;
    $disableOrder   = $client->disable_order_block ?? 0;

    /*
    | Approved clients default to both boxes checked — there's no
    | reason to have suspend/order-block automation armed against
    | someone whose KYC has already passed. This only affects the
    | checkbox's default DISPLAY when no override row exists yet
    | (disable_suspend/disable_order_block are null); once an admin
    | explicitly saves a value for a client, their actual saved
    | choice always wins, Approved or not.
    */

    if ($client->status === 'Approved') {
        if (is_null($client->disable_suspend)) {
            $disableSuspend = 1;
        }
        if (is_null($client->disable_order_block)) {
            $disableOrder = 1;
        }
    }

    /*
    | matched_client_id is NULL whenever mod_didit_sessions.userid doesn't
    | correspond to a real tblclients.id — surface that clearly instead of
    | silently dropping the row, so mismatches are visible rather than
    | invisible.
    */

    if ($client->matched_client_id) {
        $clientLabel = "{$client->firstname} {$client->lastname}";
        $emailLabel  = $client->client_email;
    } else {
        $clientLabel = "&#9888; Unmatched (stored userid: {$client->session_userid})";
        $emailLabel  = $client->session_email ?: '(no email recorded)';
    }

    echo "<tr" . (!$client->matched_client_id ? " style='background:#fff3cd'" : "") . ">

<form method='post'>

<td>{$clientLabel}</td>

<td>{$emailLabel}</td>

<td><strong>{$client->status}</strong></td>

<td>
<input type='checkbox'
name='disable_suspend'
".($disableSuspend ? "checked":"").">
</td>

<td>
<input type='checkbox'
name='disable_order_block'
".($disableOrder ? "checked":"").">
</td>

<td>

<input type='hidden' name='userid' value='{$client->session_userid}'>

".generate_token("WHMCS.admin.default")."

<button class='btn btn-primary btn-sm'>
Save
</button>

</td>

</form>

</tr>";

}

echo "</table>";



/*
|--------------------------------------------------------------------------
| PAGINATION LINKS
|--------------------------------------------------------------------------
*/

$totalPages = ceil($total / $limit);

echo "<div style='margin-top:10px'>";

for ($i = 1; $i <= $totalPages; $i++) {

    $active = ($i == $page) ? "btn-primary" : "btn-default";

    $params = $_GET;
    $params['page_overrides'] = $i;

    $url = '?' . http_build_query($params);

    echo "<a class='btn {$active} btn-sm'
            style='margin-right:5px'
            href='{$url}'>
            {$i}
          </a>";
}

echo "</div>";
}

echo "</div>"; // closes .didit-admin-v5

}

/*
|--------------------------------------------------------------------------
| TAB: DASHBOARD
|--------------------------------------------------------------------------
*/

function didit_render_tab_dashboard()
{
    $enforcementOn = didit_get_setting('enforcement_enabled') == 'on';
    $serviceAction = didit_get_setting('service_action') ?: 'None';
    $accountAction = didit_get_setting('account_action') ?: 'None';

    if ($enforcementOn && ($serviceAction !== 'None' || $accountAction !== 'None')) {

        echo "<div class='alert alert-danger'>
            ⚠ Enforcement Actions are <strong>ON</strong> — Service: <strong>{$serviceAction}</strong>, Account: <strong>{$accountAction}</strong>.
            <a href='addonmodules.php?module=didit_verification&tab=configuration' class='btn btn-xs btn-default' style='margin-left:8px'>Review settings</a>
        </div>";

    } else {

        echo "<div class='alert alert-info'>Enforcement Actions are off — nothing destructive happens automatically.</div>";
    }

    // Raw row counts — matches the Online KYC tables, which show every
    // historical session rather than one row per client.
    $total      = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->count();
    $approved   = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status', 'Approved')->count();
    $declined   = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status', 'Declined')->count();
    $progress   = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status', 'In Progress')->count();
    $notStarted = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status', 'Not Started')->count();
    $inReview   = Capsule::table('mod_didit_sessions')->where('deleted_upstream', 0)->where('status', 'In Review')->count();

    /*
    | Credit balance — GET /v3/billing/balance/. Cached 5 minutes inside
    | didit_fetch_balance() itself, so this doesn't hit Didit's API on
    | every dashboard load. Fails silently into a neutral "unavailable"
    | state rather than showing a broken widget if the API key isn't
    | set yet or the call fails for any reason.
    */

    $balanceData = didit_fetch_balance();

    if ($balanceData && ($balanceData['parsed'] ?? false)) {

        $balanceAmount = number_format((float) $balanceData['balance'], 2);
        $currency = htmlspecialchars($balanceData['currency']);

        echo "<div class='alert alert-info' style='display:flex;justify-content:space-between;align-items:center'>
            <span>💳 Didit credit balance: <strong>{$currency} {$balanceAmount}</strong></span>
            <a href='https://business.didit.me' target='_blank' class='btn btn-xs btn-default'>Top up</a>
        </div>";

    } elseif ($balanceData && !($balanceData['parsed'] ?? true)) {

        echo "<div class='alert alert-warning'>Credit balance check succeeded but the response format wasn't recognized — see the Module Log (FetchBalance) for the raw response.</div>";
    }
    // If $balanceData is false (no API key yet, or the call failed),
    // the widget is simply omitted rather than showing an error — this
    // isn't essential to the dashboard's core purpose and shouldn't be
    // a wall of red on a page an admin checks constantly.

    echo "
    <div class='row' style='margin-bottom:20px'>
        <div class='col-md-2'><div class='alert alert-info'><h4>Total</h4><h2>{$total}</h2></div></div>
        <div class='col-md-2'><div class='alert' style='background:#eee'><h4>Not Started</h4><h2>{$notStarted}</h2></div></div>
        <div class='col-md-2'><div class='alert alert-warning'><h4>In Progress</h4><h2>{$progress}</h2></div></div>
        <div class='col-md-2'><div class='alert' style='background:#d1ecf1;color:#0c5460'><h4>In Review</h4><h2>{$inReview}</h2></div></div>
        <div class='col-md-2'><div class='alert alert-success'><h4>Approved</h4><h2>{$approved}</h2></div></div>
        <div class='col-md-2'><div class='alert alert-danger'><h4>Declined</h4><h2>{$declined}</h2></div></div>
    </div>";

    echo "<div class='row'>
        <div class='col-md-3'><a href='addonmodules.php?module=didit_verification&tab=online_kyc' class='btn btn-block btn-default'>View all sessions</a></div>
        <div class='col-md-3'><a href='addonmodules.php?module=didit_verification&tab=online_kyc&status=In+Progress' class='btn btn-block btn-default'>Pending verifications</a></div>
        <div class='col-md-3'><a href='addonmodules.php?module=didit_verification&tab=cron' class='btn btn-block btn-default'>Cron settings</a></div>
        <div class='col-md-3'><a href='addonmodules.php?module=didit_verification&tab=logs' class='btn btn-block btn-default'>View logs</a></div>
    </div><br>";

    echo "<h3>Recent activity</h3>";

    $recent = Capsule::table('mod_didit_audit')
        ->leftJoin('tblclients', 'mod_didit_audit.userid', '=', 'tblclients.id')
        ->select('mod_didit_audit.*', 'tblclients.firstname', 'tblclients.lastname')
        ->orderBy('mod_didit_audit.created_at', 'desc')
        ->limit(10)
        ->get();

    if ($recent->isEmpty()) {

        echo "<div class='alert alert-info'>No manual actions recorded yet.</div>";

    } else {

        echo "<table class='table table-striped table-bordered'><thead><tr><th>Date</th><th>Client</th><th>Action</th><th>Status change</th><th>Admin</th></tr></thead>";

        foreach ($recent as $row) {

            $clientLabel = $row->firstname ? "{$row->firstname} {$row->lastname}" : "UserID {$row->userid}";

            echo "<tr>
                <td>{$row->created_at}</td>
                <td>{$clientLabel}</td>
                <td>{$row->action}</td>
                <td>{$row->previous_status} → {$row->new_status}</td>
                <td>{$row->admin_username}</td>
            </tr>";
        }

        echo "</table>";
    }
}

/*
|--------------------------------------------------------------------------
| TAB: GENERAL SETTINGS
|--------------------------------------------------------------------------
*/

function didit_render_tab_settings()
{
    $apiKey = didit_get_setting('api_key');
    $friendlyName = didit_get_setting('friendly_name') ?: 'Didit KYC';
    $apiUrl = didit_get_setting('api_url') ?: 'https://verification.didit.me';
    $webhookSecret = didit_get_setting('webhook_secret');
    $workflowB2b = didit_get_setting('workflow_id_b2b');
    $workflowB2c = didit_get_setting('workflow_id_b2c');

    $webhookUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . '/modules/addons/didit_verification/webhook.php';

    echo "<div class='alert alert-info'>Configure this exact URL as the webhook destination in your Didit Business Console:<br><code>{$webhookUrl}</code></div>";

    // Workflow dropdowns are populated live from Didit's API (GET
    // /v3/workflows/) when an API key is already saved — falls back to a
    // plain text field if that call fails (no key yet, network issue,
    // etc.) rather than showing a broken empty dropdown.
    //
    // Filtered per confirmed fields from Didit's Get Workflow docs
    // (uuid, workflow_type: "kyc"|"kyb", is_archived: bool): archived
    // workflows excluded from both, B2B restricted to type "kyb", B2C
    // restricted to type "kyc" — a workflow of the wrong type wouldn't
    // do what the field name promises even if left selectable.
    $allWorkflows = !empty($apiKey) ? didit_fetch_workflows($apiKey) : false;

    $activeWorkflows = [];

    if ($allWorkflows !== false) {
        foreach ($allWorkflows as $wf) {
            if (empty($wf['is_archived'])) {
                $activeWorkflows[] = $wf;
            }
        }
    }

    $b2bWorkflows = array_values(array_filter($activeWorkflows, fn($wf) => ($wf['workflow_type'] ?? '') === 'kyb'));
    $b2cWorkflows = array_values(array_filter($activeWorkflows, fn($wf) => ($wf['workflow_type'] ?? '') === 'kyc'));

    echo "<form method='post'>
        " . generate_token("WHMCS.admin.default") . "
        <input type='hidden' name='save_settings_group' value='general'>

        <div class='form-group'>
            <label>Friendly Name</label>
            <input type='text' class='form-control' name='friendly_name' value='" . htmlspecialchars($friendlyName) . "'>
            <p class='help-block'>Display name shown to clients.</p>
        </div>

        <div class='form-group'>
            <label>API URL</label>
            <input type='text' class='form-control' name='api_url' value='" . htmlspecialchars($apiUrl) . "'>
        </div>

        <div class='form-group'>
            <label>Didit API Key</label>
            <input type='text' class='form-control' name='api_key' value='" . htmlspecialchars($apiKey ?? '') . "'>
        </div>";

    $workflowFieldsConfig = [
        'workflow_id_b2b' => ['B2B Workflow', $workflowB2b, $b2bWorkflows, 'KYB'],
        'workflow_id_b2c' => ['B2C Workflow', $workflowB2c, $b2cWorkflows, 'KYC'],
    ];

    foreach ($workflowFieldsConfig as $fieldName => $meta) {

        [$label, $currentValue, $filteredWorkflows, $typeLabel] = $meta;

        echo "<div class='form-group'><label>Select {$label}</label>";

        if ($allWorkflows !== false && count($filteredWorkflows) > 0) {

            echo "<select name='{$fieldName}' class='form-control'>";
            echo "<option value=''>— none —</option>";

            foreach ($filteredWorkflows as $wf) {

                $wfId = $wf['uuid'] ?? $wf['id'] ?? $wf['workflow_id'] ?? '';
                $wfLabel = $wf['workflow_label'] ?? $wf['name'] ?? $wfId;
                $sel = ($wfId === $currentValue) ? 'selected' : '';

                echo "<option value='" . htmlspecialchars($wfId) . "' {$sel}>" . htmlspecialchars($wfLabel) . "</option>";
            }

            echo "</select>
                <p class='help-block'>Active {$typeLabel} workflows only — archived workflows and the other type are excluded.</p>";

        } elseif ($allWorkflows !== false) {

            echo "<input type='text' class='form-control' name='{$fieldName}' value='" . htmlspecialchars($currentValue ?? '') . "' placeholder='Workflow ID'>
                <p class='help-block'>No active {$typeLabel} workflows found in your Didit account — create one, or enter a workflow ID manually.</p>";

        } else {

            echo "<input type='text' class='form-control' name='{$fieldName}' value='" . htmlspecialchars($currentValue ?? '') . "' placeholder='Workflow ID'>
                <p class='help-block'>Enter a valid API Key above and save once to load your workflows as a dropdown instead.</p>";
        }

        echo "</div>";
    }

    echo "<p class='help-block'>New clients are matched by whether they have a company name on file — with one set, the B2B workflow is used; without one, B2C. Existing sessions aren't affected retroactively.</p>";

    echo "<div class='form-group'>
            <label>Webhook Secret</label>
            <input type='text' class='form-control' name='webhook_secret' value='" . htmlspecialchars($webhookSecret ?? '') . "'>
            <p class='help-block'>Must exactly match the shared secret configured on the Didit side for this webhook destination — every incoming webhook is rejected otherwise.</p>
        </div>

        <button class='btn btn-primary'>Save settings</button>
    </form>

    <a href='addonmodules.php?module=didit_verification&tab=settings&test_connection=1' class='btn btn-danger' style='margin-top:10px'>Test Connection</a>";
}

/*
|--------------------------------------------------------------------------
| TAB: CRON SETTINGS
|--------------------------------------------------------------------------
*/

function didit_template_select($fieldName, $currentValue, $templates)
{
    $html = "<select name='{$fieldName}' class='form-control'>";
    $html .= "<option value=''>— none —</option>";

    foreach ($templates as $tpl) {
        $sel = ($tpl->name === $currentValue) ? 'selected' : '';
        $html .= "<option value='" . htmlspecialchars($tpl->name) . "' {$sel}>" . htmlspecialchars($tpl->name) . "</option>";
    }

    return $html . "</select>";
}

function didit_render_tab_cron()
{
    $enforcementOn = didit_get_setting('enforcement_enabled') == 'on';

    echo "<div class='alert " . ($enforcementOn ? "alert-danger" : "alert-info") . "'>
        Daily cron runs automatically via WHMCS's own Automation Settings (Setup &gt; Automation Settings &gt; Daily Cron). No separate system crontab entry needed.
        <a href='addonmodules.php?module=didit_verification&run_cron_now=1' class='btn btn-xs btn-default' style='margin-left:8px'>Run cron now</a>
    </div>";

    $templates = Capsule::table('tblemailtemplates')
        ->where('type', 'general')
        ->orderBy('name')
        ->get(['name']);

    if ($templates->isEmpty()) {
        echo "<div class='alert alert-warning'>No 'general' type email templates exist yet — create at least one under Setup &gt; Email Templates before reminders can send.</div>";
    }

    echo "<h3>KYC Reminder</h3>";

    echo "<form method='post'>
        " . generate_token("WHMCS.admin.default") . "
        <input type='hidden' name='save_settings_group' value='cron'>

        <div class='form-group'>
            <label>KYC Reminder After Signup</label>
            " . didit_template_select('reminder_signup_template', didit_get_setting('reminder_signup_template'), $templates) . "
            <p class='help-block'>Send a gentle reminder to clients to complete KYC after registration.</p>
        </div>

        <div class='row'>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>First Reminder</label>
                    " . didit_template_select('reminder_1_template', didit_get_setting('reminder_1_template'), $templates) . "
                    <p class='help-block'>Send the first reminder prompting clients to complete KYC.</p>
                </div>
            </div>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>First Reminder Days</label>
                    <input type='text' class='form-control' name='reminder_1_days' value='" . htmlspecialchars(didit_get_setting('reminder_1_days') ?? '') . "'>
                    <p class='help-block'>Number of days after registration or order to send the first KYC reminder.</p>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Second Reminder</label>
                    " . didit_template_select('reminder_2_template', didit_get_setting('reminder_2_template'), $templates) . "
                    <p class='help-block'>Send the second reminder prompting clients to complete KYC.</p>
                </div>
            </div>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Second Reminder Days</label>
                    <input type='text' class='form-control' name='reminder_2_days' value='" . htmlspecialchars(didit_get_setting('reminder_2_days') ?? '') . "'>
                    <p class='help-block'>Number of days after the first reminder to send the second reminder.</p>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Third Reminder</label>
                    " . didit_template_select('reminder_3_template', didit_get_setting('reminder_3_template'), $templates) . "
                    <p class='help-block'>Send a final reminder prompting clients to complete KYC.</p>
                </div>
            </div>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Third Reminder Days</label>
                    <input type='text' class='form-control' name='reminder_3_days' value='" . htmlspecialchars(didit_get_setting('reminder_3_days') ?? '') . "'>
                    <p class='help-block'>Number of days after the second reminder to send the third reminder.</p>
                </div>
            </div>
        </div>

        <hr>

        <div class='form-group'>
            <label>Reminder after signup (days)</label>
            <input type='text' class='form-control' style='max-width:160px' name='reminder_signup_days' value='" . htmlspecialchars(didit_get_setting('reminder_signup_days') ?? '') . "'>
            <p class='help-block'>Blank disables this stage — and since First/Second/Third each count from the previous stage having fired, leaving this blank disables all of them.</p>
        </div>

        <button class='btn btn-primary'>Save reminder schedule</button>
    </form>";

    echo "<h3>Reminders sent</h3>";

    $reminderCounts = Capsule::table('mod_didit_reminders')
        ->select('reminder_type', Capsule::raw('count(*) as total'), Capsule::raw('max(sent_at) as last_sent'))
        ->groupBy('reminder_type')
        ->get();

    if ($reminderCounts->isEmpty()) {

        echo "<div class='alert alert-info'>No reminders sent yet.</div>";

    } else {

        echo "<table class='table table-bordered'><thead><tr><th>Stage</th><th>Total sent</th><th>Last sent</th></tr></thead>";

        foreach ($reminderCounts as $row) {
            echo "<tr><td>{$row->reminder_type}</td><td>{$row->total}</td><td>{$row->last_sent}</td></tr>";
        }

        echo "</table>";
    }
}

/*
|--------------------------------------------------------------------------
| TAB: CONFIGURATION
|--------------------------------------------------------------------------
*/

function didit_render_tab_configuration()
{
    echo "<h3>Provisioning control</h3>";

    $stopProvisioning = didit_get_setting('stop_provisioning') ?: 'None';
    $restrictedTlds = didit_get_setting('restricted_tlds') ?? '';
    $autoAcceptDelay = didit_get_setting('auto_accept_delay') ?: '10';

    echo "<form method='post'>
        " . generate_token("WHMCS.admin.default") . "
        <input type='hidden' name='save_settings_group' value='configuration'>

        <div class='form-group'>
            <label>Stop provisioning</label>
            <select name='stop_provisioning' class='form-control' style='max-width:220px'>";

    foreach (['None', 'Products', 'Domains', 'Both'] as $opt) {
        $sel = ($opt === $stopProvisioning) ? 'selected' : '';
        echo "<option value='{$opt}' {$sel}>{$opt}</option>";
    }

    echo "</select>
        </div>

        <div class='form-group'>
            <label>Restricted TLDs</label>
            <input type='text' class='form-control' name='restricted_tlds' value='" . htmlspecialchars($restrictedTlds) . "' placeholder='.in,.us,.eu'>
            <p class='help-block'>Comma-separated. Blank = all domain orders require KYC when Stop Provisioning includes Domains.</p>
        </div>

        <div class='form-group'>
            <label>Auto accept delay (minutes)</label>
            <input type='text' class='form-control' style='max-width:120px' name='auto_accept_delay' value='" . htmlspecialchars($autoAcceptDelay) . "'>
        </div>

        <hr>
        <h3 style='color:#a94442'>⚠ Enforcement actions</h3>
        <p class='help-block'>These can automatically suspend, cancel, or terminate services, and deactivate or close client accounts. Both the master switch below AND a non-\"None\" action are required before anything runs — read carefully.</p>

        <div class='checkbox'>
            <label><input type='checkbox' name='enforcement_enabled' value='on' " . (didit_get_setting('enforcement_enabled') == 'on' ? 'checked' : '') . "> Enable Enforcement Actions (master switch — off by default)</label>
        </div>

        <div class='row'>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Service action</label>
                    <select name='service_action' class='form-control'>";

    foreach (['None', 'Suspend', 'Cancel', 'Terminate'] as $opt) {
        $sel = ($opt === (didit_get_setting('service_action') ?: 'None')) ? 'selected' : '';
        echo "<option value='{$opt}' {$sel}>{$opt}</option>";
    }

    echo "</select>
                </div>
                <div class='form-group'>
                    <label>Service action days</label>
                    <input type='text' class='form-control' name='service_action_days' value='" . htmlspecialchars(didit_get_setting('service_action_days') ?? '') . "'>
                    <p class='help-block'>Days after the last reminder (or signup, if no reminders configured).</p>
                </div>
            </div>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>Account action</label>
                    <select name='account_action' class='form-control'>";

    foreach (['None', 'Inactive', 'Close'] as $opt) {
        $sel = ($opt === (didit_get_setting('account_action') ?: 'None')) ? 'selected' : '';
        echo "<option value='{$opt}' {$sel}>{$opt}</option>";
    }

    echo "</select>
                </div>
                <div class='form-group'>
                    <label>Account action days</label>
                    <input type='text' class='form-control' name='account_action_days' value='" . htmlspecialchars(didit_get_setting('account_action_days') ?? '') . "'>
                </div>
            </div>
        </div>

        <hr>
        <h3>KYC Approval / Rejection Emails</h3>";

    $outcomeTemplates = Capsule::table('tblemailtemplates')
        ->where('type', 'general')
        ->orderBy('name')
        ->get(['name']);

    if ($outcomeTemplates->isEmpty()) {
        echo "<div class='alert alert-warning'>No 'general' type email templates exist yet — create at least one under Setup &gt; Email Templates.</div>";
    }

    echo "<div class='row'>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>KYC Approval</label>
                    " . didit_template_select('template_approved', didit_get_setting('template_approved'), $outcomeTemplates) . "
                    <p class='help-block'>Select the email template that will be sent to the client when their KYC verification is approved.</p>
                </div>
            </div>
            <div class='col-md-6'>
                <div class='form-group'>
                    <label>KYC Rejection</label>
                    " . didit_template_select('template_declined', didit_get_setting('template_declined'), $outcomeTemplates) . "
                    <p class='help-block'>Select the email template that will be sent to the client when their KYC verification is rejected.</p>
                </div>
            </div>
        </div>

        <button class='btn btn-primary'>Save configuration</button>
    </form>";
}

/*
|--------------------------------------------------------------------------
| TAB: LOGS
|--------------------------------------------------------------------------
*/

function didit_render_tab_logs()
{
    echo "<div class='alert alert-info'>
        Deeper technical logs live in WHMCS's own admin tools:
        <a href='logs.php?type=module' target='_blank'>Module Log</a> (API calls, webhooks) ·
        <a href='logs.php?type=activity' target='_blank'>Activity Log</a> (everything else this module records)
    </div>";

    echo "<h3>Users (from WHMCS session records)</h3>";

    /*
    | Built from mod_didit_sessions rather than Didit's GET /v3/users/
    | list endpoint. That endpoint is real (confirmed via Didit's own
    | GitHub agent-skills repo) but its pagination behavior isn't
    | documented anywhere I could verify — a client could silently be
    | missing simply by falling on a page this module never fetched,
    | with no error to surface. Our own session table is guaranteed
    | complete for "every client who's ever interacted with this
    | module" by construction, so it's the more reliable source for
    | this specific view even though it means no live Didit-side count.
    */

    $diditClientIds = Capsule::table('mod_didit_sessions')
        ->select('userid')
        ->distinct()
        ->pluck('userid');

    if ($diditClientIds->isEmpty()) {

        echo "<div class='alert alert-info'>No clients have started KYC verification yet.</div>";

    } else {

        echo "<table class='table table-striped table-bordered'>
            <thead><tr><th>Client</th><th>WHMCS Client ID</th><th>Total Sessions</th><th>Last Approved</th><th>Last Approved Session</th></tr></thead>";

        foreach ($diditClientIds as $userid) {

            $client = Capsule::table('tblclients')->where('id', $userid)->first(['firstname', 'lastname']);
            $clientLabel = $client
                ? "<a href='clientssummary.php?userid={$userid}'>" . htmlspecialchars("{$client->firstname} {$client->lastname}") . "</a>"
                : "<span class='text-muted'>No matching WHMCS client</span>";

            $sessionCount = Capsule::table('mod_didit_sessions')->where('userid', $userid)->count();

            $lastApproved = Capsule::table('mod_didit_sessions')
                ->where('userid', $userid)
                ->where('status', 'Approved')
                ->orderBy('verified_at', 'desc')
                ->first(['verified_at', 'session_id']);

            $lastApprovedDate = $lastApproved->verified_at ?? '<span class="text-muted">—</span>';
            $lastApprovedSession = $lastApproved ? "<code style='font-size:11px'>{$lastApproved->session_id}</code>" : '<span class="text-muted">—</span>';

            echo "<tr>
                <td>{$clientLabel}</td>
                <td>{$userid}</td>
                <td>{$sessionCount}</td>
                <td>{$lastApprovedDate}</td>
                <td>{$lastApprovedSession}</td>
            </tr>";
        }

        echo "</table>";
    }

    echo "<h3>Manual actions (audit trail)</h3>";

    $audit = Capsule::table('mod_didit_audit')
        ->leftJoin('tblclients', 'mod_didit_audit.userid', '=', 'tblclients.id')
        ->select('mod_didit_audit.*', 'tblclients.firstname', 'tblclients.lastname')
        ->orderBy('mod_didit_audit.created_at', 'desc')
        ->limit(25)
        ->get();

    if ($audit->isEmpty()) {

        echo "<div class='alert alert-info'>No manual actions recorded yet.</div>";

    } else {

        echo "<table class='table table-striped table-bordered'><thead><tr><th>Date</th><th>Client</th><th>Action</th><th>Status change</th><th>Reason</th><th>Admin</th><th>Email sent</th></tr></thead>";

        foreach ($audit as $row) {

            $clientLabel = $row->firstname ? "{$row->firstname} {$row->lastname}" : "UserID {$row->userid}";

            echo "<tr>
                <td>{$row->created_at}</td>
                <td>{$clientLabel}</td>
                <td>{$row->action}</td>
                <td>{$row->previous_status} → {$row->new_status}</td>
                <td>" . htmlspecialchars($row->reason ?? '') . "</td>
                <td>{$row->admin_username}</td>
                <td>" . ($row->email_sent ? 'Yes' : 'No') . "</td>
            </tr>";
        }

        echo "</table>";
    }

    echo "<h3>Automatic enforcement actions</h3>";

    $enforcement = Capsule::table('mod_didit_enforcement_log')
        ->leftJoin('tblclients', 'mod_didit_enforcement_log.userid', '=', 'tblclients.id')
        ->select('mod_didit_enforcement_log.*', 'tblclients.firstname', 'tblclients.lastname')
        ->orderBy('mod_didit_enforcement_log.created_at', 'desc')
        ->limit(25)
        ->get();

    if ($enforcement->isEmpty()) {

        echo "<div class='alert alert-info'>No automatic enforcement actions taken yet.</div>";

    } else {

        echo "<table class='table table-striped table-bordered'><thead><tr><th>Date</th><th>Client</th><th>Type</th><th>Action</th><th>Result</th></tr></thead>";

        foreach ($enforcement as $row) {

            $clientLabel = $row->firstname ? "{$row->firstname} {$row->lastname}" : "UserID {$row->userid}";

            echo "<tr>
                <td>{$row->created_at}</td>
                <td>{$clientLabel}</td>
                <td>{$row->action_type}</td>
                <td>{$row->action_taken}</td>
                <td><code style='font-size:11px'>" . htmlspecialchars(substr($row->result ?? '', 0, 150)) . "</code></td>
            </tr>";
        }

        echo "</table>";
    }
}

/*
|--------------------------------------------------------------------------
| TAB: DOCUMENTATION
|--------------------------------------------------------------------------
*/

function didit_render_tab_documentation()
{
    $webhookUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . '/modules/addons/didit_verification/webhook.php';

    echo "<div style='max-width:800px'>
        <h3>Setup</h3>
        <ol>
            <li>Enter your API Key, Workflow ID, and Webhook Secret under <a href='addonmodules.php?module=didit_verification&tab=settings'>General Settings</a>.</li>
            <li>In the Didit Business Console, set the webhook destination to: <code>{$webhookUrl}</code></li>
            <li>Confirm <strong>Setup &gt; General Settings &gt; General &gt; Enable Module Debug Log</strong> is on, so the Module Log actually records entries.</li>
            <li>Confirm <strong>Setup &gt; Automation Settings &gt; Daily Cron</strong> is running, so reminders/sync/enforcement fire.</li>
        </ol>

        <h3>Client flow</h3>
        <p>Client clicks Complete Verification in the client area → redirected to Didit's hosted verification page → Didit sends a signed webhook on completion → this module verifies the signature, updates status, and unsuspends/suspends services accordingly.</p>

        <h3>Status values</h3>
        <p>Internally tracked as one of five buckets: <code>Not Started</code>, <code>In Progress</code>, <code>In Review</code>, <code>Approved</code>, <code>Declined</code>. <code>In Review</code> is kept distinct because it requires a human decision (per Didit's own docs, it's the one status explicitly categorized as &quot;Actionable&quot;). Everything else Didit reports (Abandoned, Expired, Resubmitted, Awaiting User, Kyc Expired) is still mapped down into these five — see the comment on <code>didit_map_status()</code> in helpers.php for the exact mapping and the reasoning behind it.</p>

        <h3>Support</h3>
        <p>See README.md in the module folder for full technical documentation, including the webhook signature verification scheme and the self-healing behavior for missed webhooks.</p>
    </div>";
}

/*
|--------------------------------------------------------------------------
| HISTORY MODAL (Eye icon — details + full status history)
|--------------------------------------------------------------------------
*/

function didit_render_history_modal()
{
    echo <<<HTML
<div class="modal fade" id="diditHistoryModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">KYC Details</h4>
      </div>
      <div class="modal-body">
        <table class="table table-bordered">
          <tr><th style="width:180px">Client Name</th><td id="diditHistClient"></td></tr>
          <tr><th>Verification Method</th><td id="diditHistMethod"></td></tr>
          <tr><th>Verification Email</th><td id="diditHistEmail"></td></tr>
          <tr><th>Session ID</th><td id="diditHistSession"></td></tr>
          <tr><th>KYC Status</th><td id="diditHistStatus"></td></tr>
        </table>

        <h4>Status History</h4>
        <table class="table table-striped table-bordered">
          <thead><tr><th style="width:160px">Date</th><th style="width:100px">Type</th><th>Detail</th></tr></thead>
          <tbody id="diditHistTimeline"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <a href="#" target="_blank" id="diditHistReportBtn" class="btn btn-primary" style="display:none">📄 Download PDF</a>
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function diditStatusBadgeClass(status) {
    if (status === 'Approved') return 'label label-success';
    if (status === 'Declined') return 'label label-danger';
    return 'label label-warning';
}

function diditOpenHistoryModal(data) {
    document.getElementById('diditHistClient').innerText = data.client;
    document.getElementById('diditHistMethod').innerText = data.method;
    document.getElementById('diditHistEmail').innerText = data.email;
    document.getElementById('diditHistSession').innerHTML = '<code>' + data.sessionId + '</code>';
    document.getElementById('diditHistStatus').innerHTML = '<span class="' + diditStatusBadgeClass(data.status) + '">' + data.status + '</span>';

    var timelineBody = document.getElementById('diditHistTimeline');
    timelineBody.innerHTML = '';

    (data.history || []).forEach(function (event) {
        var tr = document.createElement('tr');
        var typeClass = event.type === 'Manual' ? 'label-primary' : (event.type === 'Webhook' ? 'label-info' : 'label-default');
        tr.innerHTML = '<td>' + (event.date || '') + '</td>' +
            '<td><span class="label ' + typeClass + '">' + event.type + '</span></td>' +
            '<td>' + event.detail + '</td>';
        timelineBody.appendChild(tr);
    });

    if (timelineBody.innerHTML === '') {
        timelineBody.innerHTML = '<tr><td colspan="3" class="text-muted">No history recorded.</td></tr>';
    }

    var reportBtn = document.getElementById('diditHistReportBtn');
    if (data.reportUrl) {
        reportBtn.href = data.reportUrl;
        reportBtn.style.display = '';
    } else {
        reportBtn.style.display = 'none';
    }

    if (window.jQuery) {
        jQuery('#diditHistoryModal').modal('show');
    } else {
        document.getElementById('diditHistoryModal').style.display = 'block';
    }
}
</script>
HTML;
}

/*
|--------------------------------------------------------------------------
| STATUS PICKER MODAL (step 1 — opened by the row's Settings icon)
|--------------------------------------------------------------------------
| The single entry point for changing a session's status from the Online
| KYC table. "Save Changes" here just records which status was picked
| and immediately opens the reason modal (step 2, below) for that
| action — the actual update only happens once step 2 is submitted.
*/

function didit_render_status_picker_modal()
{
    echo <<<HTML
<div class="modal fade" id="diditStatusPickerModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Select new status</h4>
        <p class="text-muted" style="margin:4px 0 0">You can manually change the status to Approved, Declined, or Resubmitted. This is available for sessions currently In Progress, In Review, or Declined.</p>
      </div>
      <div class="modal-body">
        <div class="didit-status-option" data-action="Approve" onclick="diditPickStatus(this,'Approve')" style="cursor:pointer;padding:14px;border-radius:6px;margin-bottom:10px;background:#dff0d8;display:flex;justify-content:space-between;align-items:center">
          <strong style="color:#3c763d">✓ Approved</strong>
          <input type="radio" name="diditStatusRadio" value="Approve">
        </div>
        <div class="didit-status-option" data-action="Decline" onclick="diditPickStatus(this,'Decline')" style="cursor:pointer;padding:14px;border-radius:6px;margin-bottom:10px;background:#f2dede;display:flex;justify-content:space-between;align-items:center">
          <strong style="color:#a94442">✗ Declined</strong>
          <input type="radio" name="diditStatusRadio" value="Decline">
        </div>
        <div class="didit-status-option" data-action="Resubmit" onclick="diditPickStatus(this,'Resubmit')" style="cursor:pointer;padding:14px;border-radius:6px;margin-bottom:10px;background:#fcf8e3;display:flex;justify-content:space-between;align-items:center">
          <strong style="color:#8a6d3b">↻ Resubmitted</strong>
          <input type="radio" name="diditStatusRadio" value="Resubmit">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-block" onclick="diditConfirmStatusPick()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script>
var diditPickerSessionId = null;
var diditPickerClientLabel = null;
var diditPickerSelectedAction = null;

function diditOpenStatusPicker(sessionId, clientLabel) {
    diditPickerSessionId = sessionId;
    diditPickerClientLabel = clientLabel;
    diditPickerSelectedAction = null;

    document.querySelectorAll('.didit-status-option input[type=radio]').forEach(function (el) { el.checked = false; });

    if (window.jQuery) {
        jQuery('#diditStatusPickerModal').modal('show');
    } else {
        document.getElementById('diditStatusPickerModal').style.display = 'block';
    }
}

function diditPickStatus(el, action) {
    diditPickerSelectedAction = action;
    document.querySelectorAll('.didit-status-option input[type=radio]').forEach(function (el) { el.checked = false; });
    el.querySelector('input[type=radio]').checked = true;
}

function diditConfirmStatusPick() {
    if (!diditPickerSelectedAction) {
        alert('Select a status first.');
        return;
    }

    if (window.jQuery) {
        jQuery('#diditStatusPickerModal').modal('hide');
    } else {
        document.getElementById('diditStatusPickerModal').style.display = 'none';
    }

    diditOpenManualAction(diditPickerSessionId, diditPickerSelectedAction, diditPickerClientLabel);
}
</script>
HTML;
}

/*
|--------------------------------------------------------------------------
| REASON MODAL (step 2 — opened automatically after a status is picked)
|--------------------------------------------------------------------------
| Populated via JS once diditConfirmStatusPick() (above) hands off to
| it, then submitted as a normal form POST — consistent with the rest
| of this admin page, which doesn't use AJAX anywhere else.
*/

function didit_render_manual_action_modal()
{
    $token = generate_token("WHMCS.admin.default");

    echo <<<HTML
<div class="modal fade" id="diditManualActionModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" id="diditManualActionForm">
        {$token}
        <input type="hidden" name="session_id" id="diditModalSessionId">
        <input type="hidden" name="manual_action" id="diditModalAction">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="diditModalTitle">Manual Action</h4>
          <p class="text-muted" id="diditModalClientLabel" style="margin:0"></p>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Select a reason <span class="text-danger">*</span></label>
            <div id="diditModalReasonOptions"></div>
          </div>
          <div class="form-group">
            <label>Add note (optional)</label>
            <textarea class="form-control" name="comment" rows="3" placeholder="Shown to the client if you send an email"></textarea>
          </div>
          <div class="checkbox">
            <label><input type="checkbox" name="send_email" value="1"> Notify client by email</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="diditModalSubmit">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var diditReasonPresets = {
    'Approve': ['Passed verification', 'Manual override', 'False positive', 'Other'],
    'Decline': ['Failed verification', 'Suspicious document', 'Manual override', 'Other'],
    'Resubmit': ['Poor image quality', 'Expired document', 'Mismatched details', 'Other']
};

function diditOpenManualAction(sessionId, action, clientLabel) {
    document.getElementById('diditModalSessionId').value = sessionId;
    document.getElementById('diditModalAction').value = action;
    document.getElementById('diditModalTitle').innerText = action + ' Verification';
    document.getElementById('diditModalClientLabel').innerText = clientLabel + ' — Session ' + sessionId;

    var optionsHtml = '';
    var presets = diditReasonPresets[action] || ['Other'];
    presets.forEach(function (label, i) {
        var id = 'diditReason_' + i;
        optionsHtml += '<div class="radio">' +
            '<label><input type="radio" name="reason" id="' + id + '" value="' + label + '" ' + (i === 0 ? 'checked' : '') + ' required> ' + label + '</label>' +
            '</div>';
    });
    document.getElementById('diditModalReasonOptions').innerHTML = optionsHtml;

    var submitBtn = document.getElementById('diditModalSubmit');
    submitBtn.className = 'btn btn-primary';
    if (action === 'Approve') submitBtn.className = 'btn btn-success';
    if (action === 'Decline') submitBtn.className = 'btn btn-danger';
    if (action === 'Resubmit') submitBtn.className = 'btn btn-warning';
    submitBtn.innerText = 'Confirm ' + action;

    if (window.jQuery) {
        jQuery('#diditManualActionModal').modal('show');
    } else {
        document.getElementById('diditManualActionModal').style.display = 'block';
    }
}
</script>
HTML;
}

/*
|--------------------------------------------------------------------------
| ADMIN CLIENT TABLE
|--------------------------------------------------------------------------
*/

function renderAdminTable($statuses,$title,$pageParam = 'page')
{

    echo "<hr><h3>$title</h3>";

    /*
    | Shows every individual session row, including a client's full
    | history of past attempts — not deduplicated to "current session
    | only" (that was tried and reverted; some workflows genuinely want
    | to see every historical attempt at a glance in this list, not
    | just drill into it via the eye-icon history modal per row).
    | deleted_upstream is still excluded — sessions actually deleted on
    | Didit's side are a different, unrelated concern from this.
    */
    $query = Capsule::table('mod_didit_sessions')
        ->leftJoin('tblclients','mod_didit_sessions.userid','=','tblclients.id')
        ->where('mod_didit_sessions.deleted_upstream', 0)
        ->whereIn('mod_didit_sessions.status',$statuses);


    /*
    |--------------------------------------------------------------------------
    | SEARCH FILTER
    |--------------------------------------------------------------------------
    */

    if (!empty($_GET['search'])) {

        $search = $_GET['search'];

        $query->where(function($q) use ($search){

            $q->where('tblclients.firstname','LIKE',"%$search%")
            ->orWhere('tblclients.lastname','LIKE',"%$search%")
            ->orWhere('tblclients.email','LIKE',"%$search%")
            ->orWhere('mod_didit_sessions.email','LIKE',"%$search%")
            ->orWhere('mod_didit_sessions.userid','LIKE',"%$search%");

        });
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    if (!empty($_GET['status'])) {

        $query->where('mod_didit_sessions.status',$_GET['status']);

    }


    /*
    |--------------------------------------------------------------------------
    | DATE FILTER
    |--------------------------------------------------------------------------
    */

    if (!empty($_GET['date_from'])) {

        $query->whereDate(
            'mod_didit_sessions.updated_at',
            '>=',
            $_GET['date_from']
        );
    }

    if (!empty($_GET['date_to'])) {

        $query->whereDate(
            'mod_didit_sessions.updated_at',
            '<=',
            $_GET['date_to']
        );
    }



    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $page = isset($_GET[$pageParam]) ? (int)$_GET[$pageParam] : 1;

    $limit = 10;

    $offset = ($page - 1) * $limit;

    $total = $query->count();


    $records = $query
        ->select(
            'mod_didit_sessions.userid as session_userid',
            'tblclients.id as client_id',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.email as client_email',
            'mod_didit_sessions.email as session_email',
            'mod_didit_sessions.status',
            'mod_didit_sessions.session_id',
            'mod_didit_sessions.workflow_id',
            'mod_didit_sessions.reason',
            'mod_didit_sessions.updated_at',
            'mod_didit_sessions.created_at',
            'mod_didit_sessions.report_path',
	    'mod_didit_sessions.report_file'
        )
        ->orderBy('mod_didit_sessions.updated_at','desc')
        ->limit($limit)
        ->offset($offset)
        ->get();



    if ($records->isEmpty()) {

        echo "<div class='alert alert-info'>No records found.</div>";
        return;
    }



    echo "<table class='table table-striped table-bordered'>

    <thead>
    <tr>

    <th>Client</th>
    <th>Email</th>
    <th>Workflow</th>
    <th>Status</th>
    <th>Verification Date</th>
    <th>Session ID</th>
    <th>Report</th>
    <th>Actions</th>

    </tr>
    </thead>";



    foreach ($records as $row) {

        /*
        | client_id is NULL whenever mod_didit_sessions.userid doesn't
        | correspond to a real tblclients.id — show that clearly instead
        | of silently dropping the row from this list.
        */

        if ($row->client_id) {
            $clientName = "{$row->firstname} {$row->lastname}";
            $clientLabel = "<a href='clientssummary.php?userid={$row->client_id}'>" . htmlspecialchars($clientName) . "</a>";
            $emailLabel  = $row->client_email;
        } else {
            $clientName = "Unmatched (stored userid: {$row->session_userid})";
            $clientLabel = "&#9888; Unmatched (stored userid: {$row->session_userid})";
            $emailLabel  = $row->session_email ?: '(no email recorded)';
        }

        $reportButton = "";

	$generateButton = "";

if (
    $row->status === 'Approved' &&
    empty($row->report_file)
) {

    $generateButton =
    "<a href='addonmodules.php?module=didit_verification&generate_pdf={$row->session_id}'
    class='btn btn-xs btn-warning' title='Generate PDF'>
    📥 Generate
    </a>";
}
	

      if (!empty($row->report_file)) {

    $reportButton =
    "<a href='addonmodules.php?module=didit_verification&download_pdf={$row->session_id}'
    class='btn btn-xs btn-success' title='Download PDF'>
    📄 Download
    </a>";
}

        $clientLabelJs = htmlspecialchars($clientName, ENT_QUOTES);

        /*
        | Manual status changes only make sense for outcomes an admin
        | can meaningfully act on. Approved is already final, and Not
        | Started has no decision to override yet — showing Settings
        | there just invites accidental changes with no real purpose.
        */
        $settingsBtn = in_array($row->status, ['Declined', 'In Progress', 'In Review'], true)
            ? "<a href='javascript:void(0)' onclick=\"diditOpenStatusPicker('{$row->session_id}','{$clientLabelJs}')\" class='btn btn-xs btn-default' title='Change KYC status'>⚙ Settings</a>"
            : "";

        $deleteBtn = "<a href='addonmodules.php?module=didit_verification&tab=online_kyc&delete_session=" . urlencode($row->session_id) . "' class='btn btn-xs btn-danger' title='Delete this session permanently'>🗑</a>";

        $syncBtn = "<a href='addonmodules.php?module=didit_verification&tab=online_kyc&sync_session=" . urlencode($row->session_id) . "' class='btn btn-xs btn-default' title='Pull current Status/Verification Date from Didit'>🔄</a>";

        /*
        | Status history — merges three sources chronologically:
        | 1) the session's own creation (mod_didit_sessions.created_at)
        | 2) every webhook that touched this session (mod_didit_webhook_logs)
        | 3) every manual admin action (mod_didit_audit)
        | Queried per-row rather than once for the whole page, since the
        | table is already paginated to a handful of rows at a time.
        */

        $historyEvents = [];

        $historyEvents[] = [
            'date' => $row->created_at,
            'type' => 'Created',
            'detail' => 'Session created',
        ];

        $webhookEvents = Capsule::table('mod_didit_webhook_logs')
            ->where('session_id', $row->session_id)
            ->orderBy('created_at')
            ->get();

        foreach ($webhookEvents as $wh) {
            $historyEvents[] = [
                'date' => $wh->created_at,
                'type' => 'Webhook',
                'detail' => "Status → {$wh->event_type}",
            ];
        }

        $auditEvents = Capsule::table('mod_didit_audit')
            ->where('session_id', $row->session_id)
            ->orderBy('created_at')
            ->get();

        foreach ($auditEvents as $audit) {
            $historyEvents[] = [
                'date' => $audit->created_at,
                'type' => 'Manual',
                'detail' => "{$audit->action}: {$audit->previous_status} → {$audit->new_status}" .
                    ($audit->reason ? " — {$audit->reason}" : '') .
                    " (by {$audit->admin_username})",
            ];
        }

        usort($historyEvents, fn($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        $viewBtn = "<a href='javascript:void(0)' onclick='diditOpenHistoryModal(" . json_encode([
            'client'   => $clientName,
            'method'   => $row->workflow_id ?: '—',
            'email'    => $emailLabel,
            'status'   => $row->status,
            'sessionId' => $row->session_id,
            'reportUrl' => !empty($row->report_file) ? "addonmodules.php?module=didit_verification&download_pdf={$row->session_id}" : null,
            'history'  => $historyEvents,
        ], JSON_HEX_APOS | JSON_HEX_QUOT) . ")' class='btn btn-xs btn-info' title='View details and status history'>👁</a>";

        $statusBadgeClass = [
            'Approved' => 'label-success',
            'Declined' => 'label-danger',
            'In Progress' => 'label-warning',
            'In Review' => 'label-info',
            'Not Started' => 'label-default',
        ][$row->status] ?? 'label-default';

        $statusBadge = "<span class='label {$statusBadgeClass}'>{$row->status}</span>";

        $workflowLabel = $row->workflow_id ? substr($row->workflow_id, 0, 8) . '…' : '<span class="text-muted">—</span>';

        echo "<tr" . (!$row->client_id ? " style='background:#fff3cd'" : "") . ">

        <td>{$clientLabel}</td>

        <td>{$emailLabel}</td>

        <td><span title='{$row->workflow_id}'>{$workflowLabel}</span></td>

        <td>{$statusBadge}</td>

        <td>{$row->updated_at}</td>

        <td><code style='font-size:11px'>{$row->session_id}</code></td>

       <td>{$reportButton} {$generateButton}</td>

<td style='white-space:nowrap'>

{$viewBtn}
{$settingsBtn}
{$syncBtn}
{$deleteBtn}

</td>

        </tr>";
    }


    echo "</table>";



    /*
    |--------------------------------------------------------------------------
    | PAGINATION LINKS
    |--------------------------------------------------------------------------
    */

    $totalPages = ceil($total / $limit);

    echo "<div style='margin-top:10px'>";

    for ($i = 1; $i <= $totalPages; $i++) {

        $active = ($i == $page) ? "btn-primary" : "btn-default";

        $params = $_GET;
        $params[$pageParam] = $i;

        $url = '?' . http_build_query($params);

        echo "<a class='btn {$active} btn-sm'
                style='margin-right:5px'
                href='{$url}'>
                {$i}
              </a>";
    }

    echo "</div>";

}



/*
|--------------------------------------------------------------------------
| CLIENT AREA
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CLIENT AREA
|--------------------------------------------------------------------------
*/

function didit_verification_clientarea($vars)
{
    didit_ensure_schema();

    if (!isset($_SESSION['uid'])) {
        return [
            'templatefile' => 'clientarea',
            'requirelogin' => true
        ];
    }

    $userid = didit_get_client_id();

    if (empty($userid)) {
        return [
            'templatefile' => 'clientarea',
            'requirelogin' => true
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX STATUS CHECK
    |--------------------------------------------------------------------------
    */
    if (isset($_GET['ajax']) && $_GET['ajax'] == "status") {

        $session = didit_get_current_session($userid);

        header('Content-Type: application/json');

        echo json_encode([
            'status' => $session->status ?? 'Not Started',
            'session_url' => $session->session_url ?? null,
            'report_file' => $session->report_file ?? '',
            'verified_date' => !empty($session->verified_at)
                ? date('d M Y, h:i A', strtotime($session->verified_at))
                : '',
        ]);

        exit;
    }

    /*
    | HISTORY (client-safe — no admin identity or internal reasoning,
    | just what happened and when, plus the reason text ONLY when an
    | admin explicitly set one via a manual action — those are written
    | with the expectation a client might see them, unlike internal
    | webhook debug detail.)
    |--------------------------------------------------------------------------
    */

    if (isset($_GET['ajax']) && $_GET['ajax'] == "history") {

        $session = didit_get_current_session($userid);

        $events = [];

        if ($session) {

            $events[] = [
                'date' => $session->created_at,
                'label' => 'Verification started',
            ];

            $auditEvents = Capsule::table('mod_didit_audit')
                ->where('session_id', $session->session_id)
                ->orderBy('created_at')
                ->get();

            foreach ($auditEvents as $audit) {

                $label = $audit->new_status;

                if (!empty($audit->reason)) {
                    $label .= ' — ' . $audit->reason;
                }

                $events[] = [
                    'date' => $audit->created_at,
                    'label' => $label,
                ];
            }

            if ($session->status === 'Approved' && !empty($session->verified_at)) {

                $events[] = [
                    'date' => $session->verified_at,
                    'label' => 'Approved',
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['events' => $events]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | START VERIFICATION
    |--------------------------------------------------------------------------
    | Supports both a plain page load (?action=start, legacy full-page
    | redirect — kept for anyone with an old bookmark/link) and an AJAX
    | call (?action=start&ajax=1, returns JSON with the session URL for
    | the embedded iframe instead of navigating away).
    */
    if (isset($_GET['action']) && $_GET['action'] == "start") {

        $isAjax = isset($_GET['ajax']);

        $apiKey = didit_get_setting('api_key');

        // 'kyb' or 'kyc', from the client's own choice on the
        // verification page — only actually honored inside
        // didit_get_workflow_id_for_client() when they have a company
        // name on file; ignored (forced to KYC) otherwise.
        $chosenType = $_GET['type'] ?? null;

        $workflowId = didit_get_workflow_id_for_client($userid, $chosenType);

        if (!$apiKey || !$workflowId) {
            logActivity("Didit API Config Missing");

            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Didit configuration missing.']);
                exit;
            }

            exit("Didit configuration missing.");
        }


$existing = Capsule::table('mod_didit_sessions')
    ->where('userid', $userid)
    ->whereIn('status', ['Not Started', 'In Progress', 'In Review'])
    ->where('deleted_upstream', 0)
    ->orderBy('id', 'desc')
    ->first();

/*
| Reuse the existing in-flight session instead of deleting it.
|
| Deleting and recreating orphans the old session_id: if the client
| already completed verification on that session and Didit's webhook
| is simply a little late, webhook.php will look up a session_id that
| no longer exists and silently drop the status update forever.
*/
if ($existing && !empty($existing->session_url)) {

    logActivity(
        "Didit Existing Session Reused: {$existing->session_id}"
    );

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['session_url' => $existing->session_url, 'session_id' => $existing->session_id]);
        exit;
    }

    header("Location: " . $existing->session_url);
    exit;
}

        $email = Capsule::table('tblclients')
            ->where('id', $userid)
            ->value('email');

        $endpoint = didit_get_api_url() . "/v3/session/";

        $payload = [
            "workflow_id" => $workflowId,
            "vendor_data" => (string)$userid,
            "contact_details" => [
                "email" => $email
            ]
        ];

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "x-api-key: {$apiKey}",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {

            $curlError = curl_error($ch);

            didit_log(
                'CreateSession',
                json_encode($payload, JSON_PRETTY_PRINT),
                "CURL Error: {$curlError}",
                '',
                $apiKey
            );

            logActivity("Didit CURL Error: " . $curlError);
            curl_close($ch);

            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Unable to create verification session.']);
                exit;
            }

            exit("Unable to create verification session.");
        }

        curl_close($ch);

        didit_log(
            'CreateSession',
            json_encode($payload, JSON_PRETTY_PRINT),
            $response,
            '',
            $apiKey
        );

        logActivity("Didit API Response: " . $response);



        $result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {

    logActivity(
        "Didit Invalid JSON Response: " . $response
    );

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Invalid API response.']);
        exit;
    }

    exit("Invalid API response.");
}

if (empty($result['session_id'])) {

    logActivity(
        "Didit Session Creation Failed: " .
        print_r($result, true)
    );

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Verification session creation failed.']);
        exit;
    }

    exit("Verification session creation failed.");
}



        $redirectUrl =
            $result['url']
            ?? $result['verification_url']
            ?? $result['hosted_url']
            ?? $result['redirect_url']
            ?? null;

        try {

            Capsule::table('mod_didit_sessions')->insert([
                'userid'        => $userid,
                'session_id'    => $result['session_id'],
                'session_token' => $result['session_token'] ?? null,
                'status'        => $result['status'] ?? 'Not Started',
                'session_url'   => $redirectUrl,
                'email'         => $email,
                'workflow_id'   => $result['workflow_id'] ?? $workflowId,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

        } catch (\Throwable $e) {

            // Same idempotent-create race as didit_create_session() in
            // helpers.php — the row already existing here is fine.
            logActivity("Didit Session Insert Skipped (already exists): {$result['session_id']} — " . $e->getMessage());
        }

        if ($redirectUrl) {

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['session_url' => $redirectUrl, 'session_id' => $result['session_id']]);
                exit;
            }

            header("Location: " . $redirectUrl);
            exit;
        }

        logActivity(
            "Didit Redirect URL Missing: " .
            print_r($result, true)
        );

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Verification URL missing.']);
            exit;
        }

        exit("Verification URL missing.");
    }

    $session = didit_get_current_session($userid);

    $companyName = Capsule::table('tblclients')
        ->where('id', $userid)
        ->value('companyname');

 return [
    'pagetitle'    => 'KYC Verification',
    'templatefile' => 'clientarea',
    'requirelogin' => true,
    'vars' => [
        'status' => $session->status ?? 'Not Started',
        'report_file' => $session->report_file ?? '',
        'session_url' => $session->session_url ?? '',
        'friendly_name' => didit_get_setting('friendly_name') ?: 'KYC Verification',
        'has_company_name' => !empty(trim((string) $companyName)),
        'verified_date' => !empty($session->verified_at)
            ? date('d M Y, h:i A', strtotime($session->verified_at))
            : ''
    ]
];
}