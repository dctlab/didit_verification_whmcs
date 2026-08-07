<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

use WHMCS\Database\Capsule;

/*
|--------------------------------------------------------------------------
| WEBHOOK SIGNATURE VERIFICATION
|--------------------------------------------------------------------------
| Implements Didit's three signature variants per their official spec
| (https://docs.didit.me/integration/webhooks). Tried in Didit's own
| recommended order: V2 first (survives middleware re-encoding and
| authenticates the full body including `decision`), then raw-bytes,
| then Simple (envelope-only, weakest — treat `decision` as untrusted
| if this is the only one that verifies).
|
| PHP's default JSON handling does NOT reproduce Didit's canonical form.
| Three things have to be overridden or the digest silently never
| matches: decode as objects (not associative — an empty JSON object
| `{}` round-trips as `[]` through associative decode/encode, which
| changes the signed bytes), ksort with SORT_STRING (default numeric-ish
| comparison sorts "10" before "9"), and JSON_UNESCAPED_SLASHES |
| JSON_UNESCAPED_UNICODE on re-encode.
*/

function didit_webhook_timestamp_fresh($timestampHeader)
{
    if (empty($timestampHeader)) {
        return false;
    }

    return abs(time() - (int) $timestampHeader) <= 300;
}

function didit_canonicalize_for_signature($value)
{
    if ($value instanceof \stdClass) {

        $props = get_object_vars($value);
        ksort($props, SORT_STRING);

        $sorted = new \stdClass();

        foreach ($props as $key => $item) {
            $sorted->{$key} = didit_canonicalize_for_signature($item);
        }

        return $sorted;
    }

    if (is_array($value)) {
        return array_map('didit_canonicalize_for_signature', $value);
    }

    return $value;
}

function didit_verify_signature_v2($rawBody, $signatureHeader, $timestampHeader, $secret)
{
    if (empty($signatureHeader) || !didit_webhook_timestamp_fresh($timestampHeader)) {
        return false;
    }

    // Decode as objects, NOT associative arrays — see note above.
    $decoded = json_decode($rawBody, false);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $canonical = json_encode(
        didit_canonicalize_for_signature($decoded),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return hash_equals(hash_hmac('sha256', $canonical, $secret), $signatureHeader);
}

function didit_verify_signature_raw($rawBody, $signatureHeader, $timestampHeader, $secret)
{
    if (empty($signatureHeader) || !didit_webhook_timestamp_fresh($timestampHeader)) {
        return false;
    }

    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
}

function didit_verify_signature_simple($decodedBody, $signatureHeader, $timestampHeader, $secret)
{
    if (empty($signatureHeader) || !didit_webhook_timestamp_fresh($timestampHeader)) {
        return false;
    }

    $canonical = implode(':', [
        $decodedBody['timestamp'] ?? '',
        $decodedBody['session_id'] ?? '',
        $decodedBody['status'] ?? '',
        $decodedBody['webhook_type'] ?? '',
    ]);

    return hash_equals(hash_hmac('sha256', $canonical, $secret), $signatureHeader);
}

/*
|--------------------------------------------------------------------------
| SYSTEM MODULE DEBUG LOG
|--------------------------------------------------------------------------
| Wraps WHMCS's native logModuleCall() so every outbound Didit API call
| and inbound webhook shows up under Utilities > Logs > Module Log,
| filterable by module ("didit_verification"). This must be enabled via
| Setup > General Settings > General > "Enable Module Debug Log" for
| entries to actually be recorded — WHMCS silently no-ops it otherwise.
|
| Pass $apiKey to have it redacted from the stored request/response
| (WHMCS replaces any occurrence of the value with "REDACTED_API_KEY").
*/

function didit_log($action, $request = '', $response = '', $processedData = '', $apiKey = null)
{
    $replaceVars = [];

    if (!empty($apiKey)) {
        $replaceVars[$apiKey] = 'REDACTED_API_KEY';
    }

    logModuleCall(
        'didit_verification',
        $action,
        $request,
        $response,
        $processedData,
        $replaceVars
    );
}

/*
|--------------------------------------------------------------------------
| RESOLVE THE ACTUAL WHMCS CLIENT ID
|--------------------------------------------------------------------------
| $_SESSION['uid'] is NOT reliably the tblclients.id — under WHMCS's
| unified user-account system it is the tblusers.id of whoever is
| logged in, which differs from the client ID for sub-accounts / linked
| contacts. Storing that raw value as mod_didit_sessions.userid causes
| the row to silently vanish from every admin query that joins to
| tblclients (they use an INNER JOIN), since it won't match any real
| tblclients.id.
|
| $_SESSION['ClientDetails']['userid'] is populated by WHMCS core with
| the correctly-resolved client ID for the active client context on
| every client-area page load (including addon module pages and the
| ClientAreaHomepage / ClientAreaPage / ShoppingCartValidateCheckout
| hooks), so it's always safe to use here.
| $_SESSION['uid'] is kept only as a last-resort fallback.
*/

function didit_get_client_id()
{
    return $_SESSION['ClientDetails']['userid'] ?? $_SESSION['uid'] ?? null;
}

/*
|--------------------------------------------------------------------------
| ENSURE EMAIL TEMPLATE EXISTS
|--------------------------------------------------------------------------
| Auto-creates the "Didit KYC Status Update" template on first use, via
| WHMCS's own WHMCS\Mail\Template model (an Eloquent model backed by
| tblemailtemplates) rather than a raw INSERT — this respects whatever
| casting/defaults WHMCS itself applies to that table, rather than this
| module guessing at the exact storage format of every column.
|
| Idempotent and safe to call from didit_ensure_schema() on every page
| load: checks existence first, and any failure is caught and logged
| rather than breaking the page that triggered it.
*/

function didit_ensure_email_template()
{
    $exists = Capsule::table('tblemailtemplates')
        ->where('name', 'Didit KYC Status Update')
        ->exists();

    if ($exists) {
        return true;
    }

    try {

        $template = new \WHMCS\Mail\Template();
        $template->type = 'general';
        $template->name = 'Didit KYC Status Update';
        $template->subject = 'KYC Verification Update: {$status}';
        $template->message =
            'Dear {$client_name},<br /><br />' .
            'Your KYC verification status has been updated to: <strong>{$status}</strong>.<br /><br />' .
            '{if $admin_comment}{$admin_comment}<br /><br />{/if}' .
            'If you have any questions, please contact our support team.<br /><br />' .
            'Regards,<br />{$signature}';
        $template->disabled = false;
        $template->custom = true;
        $template->language = '';
        $template->save();

        didit_log('EmailTemplateCreated', 'Didit KYC Status Update', 'Created successfully via WHMCS\Mail\Template');
        logActivity('Didit: auto-created "Didit KYC Status Update" email template');

        return true;

    } catch (\Throwable $e) {

        didit_log('EmailTemplateCreated', 'Didit KYC Status Update', 'FAILED: ' . $e->getMessage());
        logActivity('Didit: failed to auto-create email template — ' . $e->getMessage());

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| ENSURE SCHEMA IS UP TO DATE
|--------------------------------------------------------------------------
| Column/table checks are idempotent (hasTable/hasColumn guards), so this
| is cheap to call defensively from every entry point into the module
| (admin panel, client area, webhook) rather than relying on the admin
| remembering to reactivate the module or browse upgrade.php after a
| deploy. This is the single source of truth for the schema — activate(),
| upgrade.php, and this function should stay in sync if you add columns.
*/

function didit_ensure_schema()
{
    if (!Capsule::schema()->hasTable('mod_didit_sessions')) {

        Capsule::schema()->create('mod_didit_sessions', function ($table) {

            $table->increments('id');
            $table->integer('userid')->index();
            $table->string('session_id')->index();
            $table->text('session_token')->nullable();
            $table->string('status')->default('Not Started')->index();
            $table->string('report_path')->nullable();
            $table->string('report_file')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('session_url')->nullable();
            $table->string('email')->nullable();
            $table->string('workflow_id')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('deleted_upstream')->default(0);
            $table->timestamps();

        });

    } else {

        $columns = [
            'report_file'  => fn($table) => $table->string('report_file')->nullable(),
            'verified_at'  => fn($table) => $table->dateTime('verified_at')->nullable(),
            'session_url'  => fn($table) => $table->string('session_url')->nullable(),
            'email'        => fn($table) => $table->string('email')->nullable(),
            'workflow_id'  => fn($table) => $table->string('workflow_id')->nullable(),
            'reason'       => fn($table) => $table->text('reason')->nullable(),
            'deleted_upstream' => fn($table) => $table->boolean('deleted_upstream')->default(0),
        ];

        foreach ($columns as $column => $definition) {

            if (!Capsule::schema()->hasColumn('mod_didit_sessions', $column)) {

                Capsule::schema()->table('mod_didit_sessions', function ($table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    /*
    | DEDUPLICATE + PREVENT FUTURE DUPLICATE session_id ROWS
    |--------------------------------------------------------------------------
    | A race condition (multiple near-simultaneous webhook deliveries or
    | insert attempts for the same session, both checking "does a row
    | exist?" before either INSERT had committed) previously allowed
    | exact duplicate rows — same session_id, same timestamp — to be
    | created. This is a one-time migration (gated by a settings flag so
    | it doesn't re-run the dedup query on every page load once clean):
    | first collapse any existing duplicates (keep the lowest id per
    | session_id), then add a unique constraint so the database itself
    | refuses a second row for the same session_id going forward,
    | regardless of source. Callers that insert into this table now
    | catch the resulting exception and re-fetch the existing row
    | instead of erroring out.
    */

    if (didit_get_setting('session_id_dedup_done') != '1') {

        $duplicateSessionIds = Capsule::table('mod_didit_sessions')
            ->select('session_id')
            ->groupBy('session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('session_id');

        foreach ($duplicateSessionIds as $sessionId) {

            $keepId = Capsule::table('mod_didit_sessions')
                ->where('session_id', $sessionId)
                ->min('id');

            $removed = Capsule::table('mod_didit_sessions')
                ->where('session_id', $sessionId)
                ->where('id', '!=', $keepId)
                ->delete();

            logActivity("Didit: removed {$removed} duplicate row(s) for session_id={$sessionId}, kept id={$keepId}");
        }

        try {

            Capsule::schema()->table('mod_didit_sessions', function ($table) {
                $table->unique('session_id', 'mod_didit_sessions_session_id_unique');
            });

            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'didit_verification', 'setting' => 'session_id_dedup_done'],
                ['value' => '1']
            );

            logActivity("Didit: session_id unique constraint added successfully");

        } catch (\Throwable $e) {

            // Either the constraint already exists (fine — treat as
            // done) or duplicates still slipped through somehow (leave
            // the flag unset so this retries next page load rather
            // than silently giving up).
            if (stripos($e->getMessage(), 'Duplicate') === false) {

                Capsule::table('tbladdonmodules')->updateOrInsert(
                    ['module' => 'didit_verification', 'setting' => 'session_id_dedup_done'],
                    ['value' => '1']
                );
            }

            logActivity("Didit: session_id unique constraint step: " . $e->getMessage());
        }
    }

    if (!Capsule::schema()->hasTable('mod_didit_webhook_logs')) {

        Capsule::schema()->create('mod_didit_webhook_logs', function ($table) {

            $table->increments('id');
            $table->string('session_id')->nullable();
            $table->string('event_type')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamp('created_at')->nullable();

        });
    }

    if (!Capsule::schema()->hasTable('mod_didit_overrides')) {

        Capsule::schema()->create('mod_didit_overrides', function ($table) {

            $table->increments('id');
            $table->integer('userid')->unique();
            $table->boolean('disable_suspend')->default(0);
            $table->boolean('disable_order_block')->default(0);
            $table->dateTime('updated_at')->nullable();

        });
    }

    /*
    | Audit trail for manual admin actions (approve/decline/resubmit
    | overrides). Kept separate from mod_didit_webhook_logs, which is
    | specifically the raw inbound-webhook log — this table is specifically
    | "a human did this on purpose," which needs different fields
    | (admin identity, reason, comment) and a different retention/search
    | use case (compliance review, not debugging).
    */

    if (!Capsule::schema()->hasTable('mod_didit_audit')) {

        Capsule::schema()->create('mod_didit_audit', function ($table) {

            $table->increments('id');
            $table->string('session_id')->index();
            $table->integer('userid')->index();
            $table->integer('admin_id')->nullable();
            $table->string('admin_username')->nullable();
            $table->string('action');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('email_sent')->default(0);
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

        });
    }

    /*
    | Tracks which reminder emails have already been sent per client, so
    | the daily cron doesn't resend the same reminder every day it runs.
    | Keyed by userid + reminder_type rather than session_id, since the
    | signup reminder in particular applies to clients who don't have a
    | session at all yet.
    */

    if (!Capsule::schema()->hasTable('mod_didit_reminders')) {

        Capsule::schema()->create('mod_didit_reminders', function ($table) {

            $table->increments('id');
            $table->integer('userid')->index();
            $table->string('reminder_type')->index(); // signup | first | second | third
            $table->timestamp('sent_at')->nullable();

            $table->unique(['userid', 'reminder_type']);

        });
    }

    /*
    | Records every automatic enforcement action taken by cron (suspend/
    | cancel/terminate a service, deactivate/close an account) — kept
    | separate from mod_didit_audit (which is specifically human-initiated
    | manual actions) since these need their own review trail: "did the
    | cron do something destructive, to whom, and why."
    */

    if (!Capsule::schema()->hasTable('mod_didit_enforcement_log')) {

        Capsule::schema()->create('mod_didit_enforcement_log', function ($table) {

            $table->increments('id');
            $table->integer('userid')->index();
            $table->string('action_type'); // service | account
            $table->string('action_taken'); // Suspend | Cancel | Terminate | Inactive | Close
            $table->integer('target_id')->nullable(); // serviceid for service actions
            $table->text('result')->nullable();
            $table->timestamp('created_at')->nullable();

        });
    }

    didit_ensure_email_template();
}

/*
|--------------------------------------------------------------------------
| SEND OUTCOME EMAIL (Approved / Declined)
|--------------------------------------------------------------------------
| Looks up the template configured specifically for this outcome
| (template_approved / template_declined), falling back to the generic
| auto-created template if neither is set. Returns true/false rather
| than throwing, so a missing/misconfigured template never breaks the
| status-change flow that's calling it.
*/

/*
|--------------------------------------------------------------------------
| SYNC A MANUAL STATUS CHANGE BACK TO DIDIT
|--------------------------------------------------------------------------
| PATCH /v3/session/{id}/update-status/ — confirmed as a real endpoint
| (appears in Didit's own GitHub agent-skills repo, listed alongside the
| other session endpoints), same base URL and x-api-key auth as
| everything else. No confirmed example REQUEST BODY was available for
| this one though, so the exact field name/casing below ({"status":
| "Approved"|"Declined"}, matching Didit's own documented status
| casing) is this module's best inference, not a verified fact.
|
| Only called for Approve/Decline — those are Didit's own unambiguous
| status values. Resubmit isn't synced: it's not clear this endpoint
| even accepts a "resubmitted"-type value, and guessing wrong there
| risks corrupting a session's state on Didit's side, not just failing
| harmlessly. Every call is logged with the full raw response either
| way, so if the field name turns out to be wrong, the Module Log shows
| exactly what Didit said back rather than a silent no-op.
*/

function didit_sync_status_to_didit($sessionId, $status)
{
    if (!in_array($status, ['Approved', 'Declined'], true)) {
        return false;
    }

    $apiKey = didit_get_setting('api_key');

    if (empty($apiKey)) {
        return false;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => didit_get_api_url() . "/v3/session/{$sessionId}/update-status/",
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['status' => $status]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-api-key: {$apiKey}",
            "Content-Type: application/json",
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $success = !$curlError && $httpCode >= 200 && $httpCode < 300;

    didit_log(
        'SyncStatusToDidit',
        "SessionID={$sessionId} | Status={$status}",
        "HTTP {$httpCode}" . ($curlError ? " | CURL Error: {$curlError}" : '') . " | Response: " . substr((string) $response, 0, 500),
        '',
        $apiKey
    );

    if (!$success) {
        logActivity("Didit: failed to sync manual {$status} to Didit for SessionID={$sessionId} — HTTP {$httpCode}, see Module Log (SyncStatusToDidit)");
    }

    return $success;
}

function didit_send_outcome_email($userid, $status, $comment = '')
{
    if (!in_array($status, ['Approved', 'Declined'], true)) {
        return false;
    }

    $settingName = $status === 'Approved' ? 'template_approved' : 'template_declined';
    $templateName = didit_get_setting($settingName) ?: 'Didit KYC Status Update';

    $templateExists = Capsule::table('tblemailtemplates')
        ->where('name', $templateName)
        ->exists();

    if (!$templateExists) {
        logActivity("Didit: skipping {$status} notification for UserID={$userid} — configured template '{$templateName}' doesn't exist");
        return false;
    }

    try {

        $apiResult = localAPI('SendEmail', [
            'messagename' => $templateName,
            'id' => $userid,
            'customtype' => 'general',
            'customvars' => base64_encode(serialize([
                'status' => $status,
                'admin_comment' => $comment,
            ])),
        ]);

        $sent = ($apiResult['result'] ?? '') === 'success';

        logActivity("Didit Outcome Email | UserID={$userid} | Status={$status} | Template={$templateName} | Sent=" . ($sent ? 'yes' : 'no'));

        return $sent;

    } catch (\Throwable $e) {

        logActivity("Didit Outcome Email Error | UserID={$userid} | Status={$status}: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| APPLY A STATUS CHANGE (shared by webhook + manual admin approval)
|--------------------------------------------------------------------------
| Single source of truth for "what happens when a session's status
| changes" — updates the row, suspends/unsuspends services, downloads the
| PDF on approval, retries on decline. Used by both webhook.php (an
| automatic status change from Didit) and the manual approval/decline/
| resubmit admin action, so the two paths can never drift apart the way
| session-creation logic once did in this module.
|
| $source is purely descriptive (for logging) — pass 'webhook' or
| 'manual:{admin_username}' so log entries make it obvious which path
| triggered a given suspend/unsuspend/retry.
*/

function didit_apply_status_change($sessionId, $status, $source = 'webhook', $reason = null)
{
    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if (!$session) {
        return false;
    }

    $userid = $session->userid;

    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($status === 'Approved') {
        $updateData['verified_at'] = date('Y-m-d H:i:s');
    }

    // Only overwrite the stored reason when a new one is actually supplied —
    // a webhook update with no extractable reason shouldn't wipe out a
    // reason set by an earlier manual action, or vice versa.
    if ($reason !== null) {
        $updateData['reason'] = $reason;
    }

    Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->update($updateData);

    logActivity("Didit Status Applied | Source={$source} | UserID={$userid} | SessionID={$sessionId} | Status={$status}");

    /*
    | Auto-notify the client on webhook-originated Approved/Declined
    | outcomes only — manual admin actions have their own explicit
    | "Notify client by email" checkbox and handle their own send, so
    | auto-sending here too would double-email the client for the same
    | status change.
    */

    if ($source === 'webhook' && in_array($status, ['Approved', 'Declined'], true)) {
        didit_send_outcome_email($userid, $status);
    }

    $apiKey = didit_get_setting('api_key');

    $workflowId = didit_get_workflow_id_for_client($userid);

    /*
    | DECLINED → SUSPEND + RETRY
    */

    if ($status === "Declined") {

        $override = Capsule::table('mod_didit_overrides')
            ->where('userid', $userid)
            ->first();

        if (!$override || $override->disable_suspend != 1) {

            $services = Capsule::table('tblhosting')
                ->where('userid', $userid)
                ->where('domainstatus', 'Active')
                ->get();

            foreach ($services as $service) {

                localAPI('ModuleSuspend', [
                    'accountid' => $service->id,
                    'suspendreason' => 'KYC Verification Declined'
                ]);
            }
        }

        $activeSession = Capsule::table('mod_didit_sessions')
            ->where('userid', $userid)
            ->whereIn('status', ['Not Started', 'In Progress', 'In Review'])
            ->where('deleted_upstream', 0)
            ->first();

        if (!$activeSession) {

            $email = Capsule::table('tblclients')
                ->where('id', $userid)
                ->value('email');

            didit_create_session($userid, $email, $apiKey, $workflowId);

            logActivity("Didit Retry Session Created | Source={$source} | UserID={$userid}");
        }
    }

    /*
    | APPROVED → UNSUSPEND + DOWNLOAD REPORT
    */

    if ($status === "Approved") {

        $services = Capsule::table('tblhosting')
            ->where('userid', $userid)
            ->where('domainstatus', 'Suspended')
            ->get();

        foreach ($services as $service) {

            $result = localAPI('ModuleUnsuspend', [
                'accountid' => $service->id
            ]);

            logActivity("Didit Unsuspend Service {$service->id} | Source={$source}: " . json_encode($result));
        }

        $pdfResult = didit_download_report($sessionId, $apiKey, $userid);

        logActivity("Didit PDF Download Result | Source={$source}: " . ($pdfResult ?: 'FAILED'));
    }

    return $userid;
}

/*
|--------------------------------------------------------------------------
| CREATE VERIFICATION SESSION
|--------------------------------------------------------------------------
*/

function didit_create_session($userId, $email, $apiKey, $workflowId)
{

    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATE ACTIVE SESSION
    |--------------------------------------------------------------------------
    */

$activeSession = Capsule::table('mod_didit_sessions')
    ->where('userid', $userId)
    ->whereIn('status', ['Not Started', 'In Progress', 'In Review'])
    ->where('deleted_upstream', 0)
    ->orderBy('id', 'desc')
    ->first();

if ($activeSession) {

    logActivity(
        "Didit Existing Session Found: {$activeSession->session_id}"
    );

    return [
        'session_id' => $activeSession->session_id
    ];
}


    $url = didit_get_api_url() . "/v3/session/";

    $data = [
        "workflow_id" => $workflowId,
        "vendor_data" => (string) $userId,
        "contact_details" => [
            "email" => $email
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "x-api-key: $apiKey",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

logActivity(
    "Didit Create Session Response: " . $response
);

    if ($response === false) {

        $curlError = curl_error($ch);

        didit_log(
            'CreateSession',
            json_encode($data, JSON_PRETTY_PRINT),
            "CURL Error: {$curlError}",
            '',
            $apiKey
        );

        logActivity("Didit API Error: " . $curlError);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    didit_log(
        'CreateSession',
        json_encode($data, JSON_PRETTY_PRINT),
        $response,
        '',
        $apiKey
    );

    if (!empty($result['session_id'])) {

        $sessionUrl =
            $result['url']
            ?? $result['verification_url']
            ?? $result['hosted_url']
            ?? $result['redirect_url']
            ?? null;

        try {

            Capsule::table('mod_didit_sessions')->insert([

                'userid' => $userId,
                'session_id' => $result['session_id'],
                'session_token' => $result['session_token'] ?? null,
                'status' => $result['status'] ?? 'Not Started',
                'session_url' => $sessionUrl,
                'email' => $email,
                'workflow_id' => $result['workflow_id'] ?? $workflowId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')

            ]);

            logActivity("Didit Session Created: {$result['session_id']} for User {$userId}");

        } catch (\Throwable $e) {

            /*
            | Didit's own create-session API is idempotent for an
            | unfinished session matching the same vendor_data — a
            | near-simultaneous duplicate call here (double-click,
            | concurrent requests) can get back the same session_id
            | from Didit twice, and both would try to insert the same
            | row locally. The session_id unique constraint catches
            | that; the row already existing is fine, not a failure.
            */

            logActivity("Didit Session Insert Skipped (already exists): {$result['session_id']} — " . $e->getMessage());
        }

        return $result;
    }

    logActivity("Didit Session Creation Failed: " . $response);

    return false;
}


/*
|--------------------------------------------------------------------------
| DOWNLOAD VERIFICATION REPORT
|--------------------------------------------------------------------------
*/

function didit_download_report($sessionId, $apiKey, $userId)
{
    /*
    |--------------------------------------------------------------------------
    | ALREADY DOWNLOADED?
    |--------------------------------------------------------------------------
    */

    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if ($session && !empty($session->report_file)) {

        logActivity(
            "Didit PDF Already Exists: " .
            $session->report_file
        );

        return $session->report_file;
    }

    /*
    |--------------------------------------------------------------------------
    | DIDIT PDF ENDPOINT
    |--------------------------------------------------------------------------
    */

    $url = didit_get_api_url() . "/v3/session/{$sessionId}/generate-pdf";

    logActivity("Didit PDF URL: {$url}");

    $ch = curl_init($url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 60,

       CURLOPT_HTTPHEADER => [
    "x-api-key: {$apiKey}"
]

    ]);

    $pdf = curl_exec($ch);

    if ($pdf === false) {

        $curlError = curl_error($ch);

        didit_log(
            'DownloadReport',
            "GET {$url}",
            "CURL Error: {$curlError}",
            '',
            $apiKey
        );

        logActivity(
            "Didit CURL Error: " .
            $curlError
        );

        curl_close($ch);

        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    logActivity("Didit HTTP Code: {$httpCode}");
    logActivity("Didit Effective URL: {$effectiveUrl}");

    curl_close($ch);

    // Note: the raw PDF bytes are intentionally never passed to didit_log() —
    // only metadata, to keep the Module Log readable and avoid storing
    // binary blobs containing the client's ID/document data in plaintext.
    didit_log(
        'DownloadReport',
        "GET {$url}",
        "HTTP {$httpCode} | Effective URL: {$effectiveUrl} | Bytes: " . strlen($pdf),
        '',
        $apiKey
    );

    /*
    |--------------------------------------------------------------------------
    | HTTP ERRORS
    |--------------------------------------------------------------------------
    */

    if ($httpCode != 200) {

        logActivity(
            "Didit PDF Error HTTP {$httpCode}"
        );

        logActivity(
            "Didit Response: " .
            substr($pdf, 0, 1000)
        );

        file_put_contents(
            ROOTDIR . '/didit_last_response.txt',
            $pdf
        );

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE PDF
    |--------------------------------------------------------------------------
    */

    if (strpos($pdf, '%PDF') !== 0) {

        logActivity(
            "Didit PDF Error: Invalid PDF response"
        );

        file_put_contents(
            ROOTDIR . '/didit_last_response.txt',
            $pdf
        );

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE PDF OUTSIDE PUBLIC DIRECTORY
    |--------------------------------------------------------------------------
    */

    $dir = '/home/ishroot/webapps/mcs/myaccountdata/kyc_reports/';

    if (!is_dir($dir)) {

        if (!mkdir($dir, 0755, true)) {

            logActivity(
                "Didit PDF Error: Unable to create directory {$dir}"
            );

            return false;
        }

        logActivity(
            "Didit Created Directory: {$dir}"
        );
    }

    if (!is_writable($dir)) {

        logActivity(
            "Didit PDF Error: Directory not writable {$dir}"
        );

        return false;
    }

    $filename =
        "KYC_User_" .
        $userId .
        "_" .
        $sessionId .
        ".pdf";

    $path = $dir . $filename;

    if (file_put_contents($path, $pdf) === false) {

        logActivity(
            "Didit PDF Save Failed: {$path}"
        );

        return false;
    }

    logActivity(
        "Didit PDF Saved To: {$path}"
    );

    if (file_exists($path)) {

        logActivity(
            "Didit PDF Size: " .
            filesize($path) .
            " bytes"
        );

    } else {

        logActivity(
            "Didit PDF Error: File not found after save"
        );

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DATABASE
    |--------------------------------------------------------------------------
    */

    $updated = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->update([
            'report_file' => $filename,
            'updated_at'  => date('Y-m-d H:i:s')
        ]);

    logActivity(
        "Didit DB Update Result: {$updated}"
    );

    logActivity(
        "Didit Verification Report Saved: {$filename}"
    );

    return $filename;
}

/*
|--------------------------------------------------------------------------
| CRON: MODULE SETTINGS HELPER
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ADMIN UI: MODERN STYLE LAYER
|--------------------------------------------------------------------------
| Restyles the existing Bootstrap 3/4-era classes this module already
| emits everywhere (btn, alert, table, label, nav-tabs, modal, form
| controls) into a flatter, rounder, Bootstrap-5-ish look, scoped under
| .didit-admin-v5 so nothing leaks into the rest of WHMCS admin.
|
| Deliberately a CSS-only layer rather than a rewrite of every echo'd
| HTML string across this file — same markup, same classes, same PHP
| logic, just restyled. Loading the actual Bootstrap 5 framework was
| considered and rejected for the same reason as the client area: WHMCS
| admin already ships its own Bootstrap version, and stacking a second
| full framework on the same page risks conflicts this module can't
| predict without seeing the live theme.
*/

function didit_admin_v5_styles()
{
    return <<<HTML
<style>
.didit-admin-v5 { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }

.didit-admin-v5 h2 { font-weight: 600; margin-bottom: 20px; }
.didit-admin-v5 h3 { font-weight: 600; margin: 24px 0 14px; }

/* Tab nav — pill/underline style instead of boxed Bootstrap 3 tabs */
.didit-admin-v5 .nav-tabs { border-bottom: 1px solid #e9ecef; margin-bottom: 24px; }
.didit-admin-v5 .nav-tabs > li { margin-bottom: -1px; }
.didit-admin-v5 .nav-tabs > li > a {
    border: none; border-bottom: 2px solid transparent;
    border-radius: 0; color: #6c757d; font-weight: 500; padding: 10px 16px;
}
.didit-admin-v5 .nav-tabs > li > a:hover { border-color: transparent transparent #dee2e6; background: transparent; }
.didit-admin-v5 .nav-tabs > li.active > a,
.didit-admin-v5 .nav-tabs > li.active > a:hover {
    border-bottom-color: #2c5cc5; color: #2c5cc5; background: transparent;
}

/* Cards / alerts as flat rounded panels */
.didit-admin-v5 .alert {
    border: none; border-radius: 10px; padding: 16px 20px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.didit-admin-v5 .alert-info { background: #eaf1fb; color: #2c5cc5; }
.didit-admin-v5 .alert-success { background: #e8f5ed; color: #256029; }
.didit-admin-v5 .alert-warning { background: #fef6e6; color: #8a6d3b; }
.didit-admin-v5 .alert-danger { background: #fdecea; color: #a94442; }
.didit-admin-v5 .alert h4 { margin-top: 0; font-weight: 600; }

/* Buttons */
.didit-admin-v5 .btn {
    border-radius: 8px; font-weight: 500; border: none;
    box-shadow: none; transition: opacity .15s ease;
}
.didit-admin-v5 .btn:hover { opacity: .88; }
.didit-admin-v5 .btn-primary { background: #2c5cc5; }
.didit-admin-v5 .btn-success { background: #2e8b57; }
.didit-admin-v5 .btn-danger { background: #c0392b; }
.didit-admin-v5 .btn-warning { background: #e0a63a; color: #fff; }
.didit-admin-v5 .btn-info { background: #3aa0c8; }
.didit-admin-v5 .btn-default { background: #f1f3f5; color: #495057; }

/* Tables */
.didit-admin-v5 .table { background: #fff; border-radius: 10px; overflow: hidden; }
.didit-admin-v5 .table thead th {
    background: #f8f9fa; border-bottom: 2px solid #e9ecef !important;
    font-weight: 600; font-size: 13px; color: #495057; text-transform: uppercase;
    letter-spacing: .03em;
}
.didit-admin-v5 .table td, .didit-admin-v5 .table th { vertical-align: middle; border-color: #f1f3f5; }
.didit-admin-v5 .table-bordered { border: 1px solid #f1f3f5; }
.didit-admin-v5 .table-striped > tbody > tr:hover { background-color: #f4f7fd; }
.didit-admin-v5 .table code { background: #f1f3f5; color: #495057; padding: 2px 6px; border-radius: 4px; }

/* Status labels as pill badges */
.didit-admin-v5 .label {
    border-radius: 999px; font-weight: 600; padding: 4px 12px; font-size: 12px;
}
.didit-admin-v5 .label-success { background: #d4edda; color: #256029; }
.didit-admin-v5 .label-danger { background: #f8d7da; color: #a94442; }
.didit-admin-v5 .label-warning { background: #fff3cd; color: #8a6d3b; }
.didit-admin-v5 .label-info { background: #d1ecf1; color: #0c5460; }
.didit-admin-v5 .label-primary { background: #d6e0f8; color: #2c5cc5; }
.didit-admin-v5 .label-default { background: #f1f3f5; color: #495057; }

/* Form controls */
.didit-admin-v5 .form-control {
    border-radius: 8px; border: 1px solid #dee2e6; box-shadow: none;
    padding: 9px 12px;
}
.didit-admin-v5 .form-control:focus { border-color: #2c5cc5; box-shadow: 0 0 0 3px rgba(44,92,197,0.12); }
.didit-admin-v5 label { font-weight: 500; font-size: 13px; color: #495057; }
.didit-admin-v5 .help-block { font-size: 12px; color: #adb5bd; }

/* Modals */
.modal .modal-content { border: none; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
.modal .modal-header { border-bottom: 1px solid #f1f3f5; }
.modal .modal-footer { border-top: 1px solid #f1f3f5; }
</style>
HTML;
}

function didit_get_setting($name)
{
    return Capsule::table('tbladdonmodules')
        ->where('module', 'didit_verification')
        ->where('setting', $name)
        ->value('value');
}

/*
|--------------------------------------------------------------------------
| API BASE URL
|--------------------------------------------------------------------------
| Configurable via settings (defaults to Didit's production URL) rather
| than hardcoded, so a sandbox/staging base URL can be used without code
| changes. Trailing slash stripped so callers can always append "/v3/...".
*/

/*
|--------------------------------------------------------------------------
| CURRENT-STATUS COUNTS (per client, not raw row counts)
|--------------------------------------------------------------------------
| Same latest-per-client self-join used by renderAdminTable() and the
| Overrides panel, tested earlier against data mirroring a client with
| multiple historical sessions. A plain `count() where status = X` counts
| every session row that ever had that status, including ones long since
| superseded by a newer attempt (e.g. a Declined session that got an
| auto-retry session created, which this module does automatically) — so
| a stat card built that way answers a different question than the
| tables do, and drifts from them exactly when it matters most: right
| after retries/resubmissions happen. This answers the same question the
| tables answer — "what is this client's status right now" — so the
| dashboard cards and the tables underneath them never disagree.
*/

function didit_get_current_status_counts()
{
    $rows = Capsule::table('mod_didit_sessions')
        ->leftJoin('mod_didit_sessions as newer_status_count', function ($join) {
            $join->on('newer_status_count.userid', '=', 'mod_didit_sessions.userid')
                ->where(function ($q) {
                    $q->whereColumn('newer_status_count.updated_at', '>', 'mod_didit_sessions.updated_at')
                      ->orWhere(function ($q2) {
                          $q2->whereColumn('newer_status_count.updated_at', '=', 'mod_didit_sessions.updated_at')
                             ->whereColumn('newer_status_count.id', '>', 'mod_didit_sessions.id');
                      });
                });
        })
        ->whereNull('newer_status_count.id')
        ->where('mod_didit_sessions.deleted_upstream', 0)
        ->select('mod_didit_sessions.status', Capsule::raw('count(*) as total'))
        ->groupBy('mod_didit_sessions.status')
        ->pluck('total', 'status');

    $counts = [
        'not_started' => (int) ($rows['Not Started'] ?? 0),
        'in_progress' => (int) ($rows['In Progress'] ?? 0),
        'approved'    => (int) ($rows['Approved'] ?? 0),
        'declined'    => (int) ($rows['Declined'] ?? 0),
    ];

    $counts['total'] = array_sum($counts);

    return $counts;
}

function didit_get_api_url()
{
    $url = didit_get_setting('api_url') ?: 'https://verification.didit.me';

    return rtrim($url, '/');
}

/*
|--------------------------------------------------------------------------
| WORKFLOW SELECTION (B2B vs B2C)
|--------------------------------------------------------------------------
| A company name on file is required before B2B/KYB is even an option —
| without one, the client always goes through KYC, no choice shown or
| honored. With a company name present, $chosenType (from the client's
| own selection on the verification page) picks between the two; if
| that's somehow missing (an old link without the param, JS disabled,
| etc.) this falls back to B2B, matching the original auto-inferred
| behavior, rather than silently switching a business client to a
| personal KYC flow they didn't ask for.
|
| Falls back through: the matching B2B/B2C setting -> the other one ->
| the legacy single "workflow_id" setting (pre-B2B/B2C split), so
| upgrading from an older version of this module doesn't leave anyone
| with no workflow configured at all.
*/

/*
|--------------------------------------------------------------------------
| GET A CLIENT'S CURRENT SESSION
|--------------------------------------------------------------------------
| Single source of truth for "what session represents this client's
| current status" — six different places in this module (the admin
| client-summary widget, the homepage widget, the client area's own
| status/history endpoints and page render) used to each run this query
| independently, which is exactly how they drift apart over time.
|
| Policy: a resolved outcome (Approved or Declined) is sticky — it stays
| the displayed status even while a newer In Progress/Not Started
| session exists on top of it, since that newer session hasn't actually
| resolved to anything yet. Only a NEWER resolved outcome (a fresh
| Approved or Declined) supersedes a prior one. This deliberately
| replaced an earlier, narrower version of this function that only
| protected against a stray Not Started row outranking a real result —
| that version still let a client's Approved status flicker to "In
| Progress" the moment they started an unrelated retry, which wasn't
| the intended behavior: an approval shouldn't visibly disappear just
| because a new attempt is in flight and hasn't concluded either way.
*/

function didit_get_current_session($userid)
{
    $sessions = Capsule::table('mod_didit_sessions')
        ->where('userid', $userid)
        ->orderBy('updated_at', 'desc')
        ->orderBy('id', 'desc')
        ->get();

    if ($sessions->isEmpty()) {
        return null;
    }

    $lastResolved = $sessions->first(function ($s) {
        return in_array($s->status, ['Approved', 'Declined'], true);
    });

    if ($lastResolved) {
        return $lastResolved;
    }

    // No resolved outcome exists at all — fall back to the most recent
    // session regardless of status.
    return $sessions->first();
}

function didit_get_workflow_id_for_client($userid, $chosenType = null)
{
    $companyName = Capsule::table('tblclients')
        ->where('id', $userid)
        ->value('companyname');

    $hasCompanyName = !empty(trim((string) $companyName));

    $b2b = didit_get_setting('workflow_id_b2b');
    $b2c = didit_get_setting('workflow_id_b2c');
    $legacy = didit_get_setting('workflow_id');

    if (!$hasCompanyName) {
        // No company name — KYC only, no choice, regardless of what
        // (if anything) was passed in as $chosenType.
        return $b2c ?: ($b2b ?: $legacy);
    }

    $wantsB2b = ($chosenType === 'kyb') || ($chosenType === null);

    if ($wantsB2b) {
        return $b2b ?: ($b2c ?: $legacy);
    }

    return $b2c ?: ($b2b ?: $legacy);
}

/*
|--------------------------------------------------------------------------
| FETCH WORKFLOWS FROM DIDIT
|--------------------------------------------------------------------------
| Used both to populate the B2B/B2C workflow dropdowns in General
| Settings and by the Test Connection check — a successful call to this
| endpoint is exactly "the API key and URL are valid and can talk to
| Didit," so there's no need for a separate lighter-weight ping.
|
| Returns the decoded workflow list array on success, or false (with the
| reason logged) on failure — callers fall back to a plain text input
| when this returns false, rather than showing a broken empty dropdown.
*/

function didit_fetch_workflows($apiKey = null)
{
    $apiKey = $apiKey ?: didit_get_setting('api_key');

    if (empty($apiKey)) {
        return false;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => didit_get_api_url() . '/v3/workflows/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    didit_log('FetchWorkflows', 'GET /v3/workflows/', "HTTP {$httpCode}" . ($curlError ? " | CURL Error: {$curlError}" : ''), '', $apiKey);

    if ($curlError || $httpCode !== 200) {
        return false;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    // Didit's list endpoint may return either a bare array or an object
    // with a "results"/"workflows" key depending on API version — handle
    // both rather than assuming one shape.
    if (isset($data['results'])) {
        return $data['results'];
    }

    if (isset($data['workflows'])) {
        return $data['workflows'];
    }

    return is_array($data) ? $data : false;
}

/*
|--------------------------------------------------------------------------
| FETCH CREDIT BALANCE
|--------------------------------------------------------------------------
| GET /v3/billing/balance/ — confirmed via Didit's own documentation
| (their "hard rules" note explicitly states /v3/* endpoints live on
| verification.didit.me, not apx.didit.me, so this uses the same base
| URL and x-api-key auth as every other call in this module).
|
| The exact response field name for the balance amount isn't something
| I could confirm from documentation, so this checks several plausible
| names defensively rather than assuming one — same approach as
| didit_fetch_workflows() above for its results/workflows key.
|
| Cached for 5 minutes in tbladdonmodules so the dashboard doesn't hit
| Didit's API on every single page load.
*/

function didit_fetch_balance($apiKey = null)
{
    $apiKey = $apiKey ?: didit_get_setting('api_key');

    if (empty($apiKey)) {
        return false;
    }

    $cachedAt = didit_get_setting('balance_cached_at');

    if ($cachedAt && (time() - strtotime($cachedAt)) < 300) {

        $cached = didit_get_setting('balance_cached_value');

        if ($cached !== null && $cached !== '') {
            return json_decode($cached, true);
        }
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => didit_get_api_url() . '/v3/billing/balance/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    didit_log('FetchBalance', 'GET /v3/billing/balance/', "HTTP {$httpCode}" . ($curlError ? " | CURL Error: {$curlError}" : ''), '', $apiKey);

    if ($curlError || $httpCode !== 200) {
        return false;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return false;
    }

    $balanceValue = $data['balance']
        ?? $data['balance_usd']
        ?? $data['credit_balance']
        ?? $data['amount']
        ?? null;

    if ($balanceValue === null) {
        // Response came back fine but didn't match any field name we
        // guessed at — don't fabricate a number, just report we
        // couldn't parse it so the dashboard can say so honestly.
        return ['raw' => $data, 'parsed' => false];
    }

    $result = [
        'balance' => $balanceValue,
        'currency' => $data['currency'] ?? 'USD',
        'parsed' => true,
    ];

    Capsule::table('tbladdonmodules')->updateOrInsert(
        ['module' => 'didit_verification', 'setting' => 'balance_cached_value'],
        ['value' => json_encode($result)]
    );
    Capsule::table('tbladdonmodules')->updateOrInsert(
        ['module' => 'didit_verification', 'setting' => 'balance_cached_at'],
        ['value' => date('Y-m-d H:i:s')]
    );

    return $result;
}

/*
|--------------------------------------------------------------------------
| FETCH DIDIT USERS
|--------------------------------------------------------------------------
| GET /v3/users/ — confirmed real endpoint (via Didit's own GitHub agent
| skills repo: "GET /v3/users/, GET /v3/users/{vendor_data}/, PATCH
| /v3/users/{vendor_data}/, POST /v3/users/delete/"), same base URL and
| x-api-key auth as everything else. vendor_data on each Didit user is
| exactly the WHMCS client ID we send on every session, so this joins
| cleanly to our own client data.
|
| No confirmed example response body was available for this specific
| endpoint (unlike /v3/workflows/, which has one) — field extraction is
| deliberately defensive, trying several plausible names per value
| rather than assuming one, and returning null for anything that
| doesn't match rather than guessing. The caller is expected to render
| nulls as "—", not skip the row — a user with missing fields is still
| a real user.
*/

function didit_fetch_users($apiKey = null, $limit = 50)
{
    $apiKey = $apiKey ?: didit_get_setting('api_key');

    if (empty($apiKey)) {
        return false;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => didit_get_api_url() . '/v3/users/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    didit_log('FetchUsers', 'GET /v3/users/', "HTTP {$httpCode}" . ($curlError ? " | CURL Error: {$curlError}" : ''), '', $apiKey);

    if ($curlError || $httpCode !== 200) {
        return false;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $rawUsers = $data['results'] ?? $data['users'] ?? (is_array($data) ? $data : []);

    $users = [];

    foreach (array_slice($rawUsers, 0, $limit) as $u) {

        $vendorData = $u['vendor_data'] ?? null;

        $users[] = [
            'vendor_data'    => $vendorData,
            'session_count'  => $u['session_count'] ?? $u['sessions_count'] ?? $u['total_sessions'] ?? null,
            'last_status'    => $u['last_status'] ?? $u['status'] ?? null,
            'last_session_id' => $u['last_session_id'] ?? $u['session_id'] ?? null,
            'last_approved_at' => $u['last_approved_at'] ?? $u['approved_at'] ?? null,
            'updated_at'     => $u['updated_at'] ?? $u['last_activity'] ?? null,
        ];
    }

    return $users;
}

/*
|--------------------------------------------------------------------------
| CRON: SEND REMINDERS
|--------------------------------------------------------------------------
| Four reminder stages (signup, first, second, third), each gated on the
| previous one having already been sent — so if the signup reminder was
| never sent (days setting blank/disabled), later reminders don't fire
| based on a reference point that doesn't exist. Every send is recorded
| in mod_didit_reminders so re-running cron (or running it more than
| once a day) never double-sends.
*/

function didit_cron_send_reminders()
{
    $stages = [
        'signup' => ['setting' => 'reminder_signup_days', 'template_setting' => 'reminder_signup_template', 'after' => null],
        'first'  => ['setting' => 'reminder_1_days',       'template_setting' => 'reminder_1_template',       'after' => 'signup'],
        'second' => ['setting' => 'reminder_2_days',       'template_setting' => 'reminder_2_template',       'after' => 'first'],
        'third'  => ['setting' => 'reminder_3_days',       'template_setting' => 'reminder_3_template',       'after' => 'second'],
    ];

    // Only clients who have never reached Approved are reminder candidates.
    $approvedUserIds = Capsule::table('mod_didit_sessions')
        ->where('status', 'Approved')
        ->pluck('userid');

    $candidates = Capsule::table('tblclients')
        ->where('status', 'Active')
        ->whereNotIn('id', $approvedUserIds)
        ->get(['id', 'datecreated', 'email']);

    foreach ($stages as $stage => $config) {

        $days = didit_get_setting($config['setting']);

        if (empty($days) || !ctype_digit((string) $days)) {
            continue;
        }

        $templateName = didit_get_setting($config['template_setting']) ?: 'Didit KYC Status Update';

        $templateExists = Capsule::table('tblemailtemplates')
            ->where('name', $templateName)
            ->exists();

        if (!$templateExists) {
            logActivity("Didit Cron: skipping {$stage} reminder — configured template '{$templateName}' doesn't exist");
            continue;
        }

        foreach ($candidates as $client) {

            $override = Capsule::table('mod_didit_overrides')
                ->where('userid', $client->id)
                ->first();

            if ($override && $override->disable_order_block == 1) {
                // Treat "disable order block" as "leave this client alone"
                // for reminders too, since it's the closest existing
                // signal for "this client is exempted from KYC nudging."
                continue;
            }

            $alreadySent = Capsule::table('mod_didit_reminders')
                ->where('userid', $client->id)
                ->where('reminder_type', $stage)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            // Determine the reference date this stage counts from.
            if ($config['after'] === null) {
                $referenceDate = $client->datecreated;
            } else {
                $priorSent = Capsule::table('mod_didit_reminders')
                    ->where('userid', $client->id)
                    ->where('reminder_type', $config['after'])
                    ->value('sent_at');

                if (!$priorSent) {
                    continue; // prior stage hasn't fired yet — this one can't either
                }

                $referenceDate = $priorSent;
            }

            $dueDate = date('Y-m-d', strtotime($referenceDate . " +{$days} days"));

            if ($dueDate > date('Y-m-d')) {
                continue; // not due yet
            }

            try {

                $apiResult = localAPI('SendEmail', [
                    'messagename' => $templateName,
                    'id' => $client->id,
                    'customtype' => 'general',
                    'customvars' => base64_encode(serialize([
                        'status' => 'Reminder',
                        'admin_comment' => "This is a reminder to complete your KYC verification.",
                    ])),
                ]);

                $sent = ($apiResult['result'] ?? '') === 'success';

            } catch (\Throwable $e) {

                logActivity("Didit Cron Reminder Error | UserID={$client->id} | Stage={$stage}: " . $e->getMessage());
                $sent = false;
            }

            if ($sent) {

                Capsule::table('mod_didit_reminders')->insert([
                    'userid' => $client->id,
                    'reminder_type' => $stage,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);

                logActivity("Didit Cron Reminder Sent | UserID={$client->id} | Stage={$stage}");
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CRON: SYNC PENDING SESSIONS
|--------------------------------------------------------------------------
| Polls Didit for any session still in Not Started/In Progress locally,
| as a backstop against a missed webhook (network blip, a delivery that
| arrived before this module was deployed, etc.) — self-healing at the
| source of truth rather than waiting for a webhook that may never
| re-arrive. Capped per run to avoid hammering the API on a large
| backlog; catches up gradually across subsequent days if needed.
*/

function didit_cron_sync_pending_sessions($limit = 50)
{
    $apiKey = didit_get_setting('api_key');

    if (empty($apiKey)) {
        return;
    }

    $pending = Capsule::table('mod_didit_sessions')
        ->whereIn('status', ['Not Started', 'In Progress', 'In Review'])
        ->where('deleted_upstream', 0)
        ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))
        ->orderBy('updated_at', 'asc')
        ->limit($limit)
        ->get();

    foreach ($pending as $session) {

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => didit_get_api_url() . "/v3/session/{$session->session_id}/decision/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        /*
        | A 404 here means Didit no longer has this session — it was
        | deleted on their side (e.g. from the Business Console).
        | Without this check, a deleted session just sits in
        | mod_didit_sessions forever showing as Not Started/In Progress,
        | since nothing else would ever tell WHMCS it's gone. Flagged
        | rather than deleted locally, so the row (and its audit/webhook
        | history) stays queryable — it's just excluded from the admin
        | tables that represent "current, actionable sessions".
        */

        if ($httpCode === 404) {

            Capsule::table('mod_didit_sessions')
                ->where('id', $session->id)
                ->update([
                    'deleted_upstream' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            didit_log(
                'CronSync',
                "SessionID={$session->session_id}",
                'Session no longer exists on Didit (404) — flagged deleted_upstream, excluded from admin tables'
            );

            logActivity("Didit Cron Sync: SessionID={$session->session_id} deleted upstream (404) — flagged locally");

            continue;
        }

        if ($httpCode !== 200 || !$response) {
            continue;
        }

        $data = json_decode($response, true);
        $remoteStatus = $data['status'] ?? null;

        if (!$remoteStatus) {
            continue;
        }

        $mappedStatus = didit_map_status($remoteStatus);

        if ($mappedStatus !== $session->status) {

            didit_apply_status_change($session->session_id, $mappedStatus, 'cron:sync');

            didit_log(
                'CronSync',
                "SessionID={$session->session_id} | LocalStatus={$session->status}",
                "RemoteStatus={$remoteStatus} -> Applied={$mappedStatus}"
            );

            logActivity("Didit Cron Sync Applied | SessionID={$session->session_id} | {$session->status} -> {$mappedStatus}");
        }
    }
}

/*
|--------------------------------------------------------------------------
| STATUS MAPPING (shared by webhook + cron sync)
|--------------------------------------------------------------------------
| Same mapping table as webhook.php — extracted here so the cron sync
| function above doesn't duplicate it. See webhook.php's own comment for
| the reasoning on why Abandoned/Expired/Kyc Expired map to "In Progress"
| rather than "Declined".
*/

/*
|--------------------------------------------------------------------------
| EXTRACT A DECISION DATE FROM DIDIT'S RESPONSE
|--------------------------------------------------------------------------
| No confirmed example of GET /v3/session/{id}/decision/'s own response
| body was available — the one real example found (Didit's webhook
| payload docs) shows "timestamp" and "created_at" as Unix-epoch
| integers sitting alongside the decision object, not confirmed to be
| inside it. This checks several plausible field names defensively,
| accepting either a Unix timestamp (int/numeric string) or an ISO
| date string, and returns null if nothing matches — callers must NOT
| fall back to "now" when this returns null, since fabricating a sync
| timestamp as if it were the actual verification date would make an
| old decision look like it just happened.
*/

function didit_extract_decision_date($data)
{
    $candidate = $data['created_at']
        ?? $data['timestamp']
        ?? $data['decision_date']
        ?? $data['reviewed_at']
        ?? $data['completed_at']
        ?? $data['decision']['created_at']
        ?? $data['decision']['timestamp']
        ?? null;

    if ($candidate === null) {
        return null;
    }

    if (is_numeric($candidate)) {
        return date('Y-m-d H:i:s', (int) $candidate);
    }

    $parsed = strtotime((string) $candidate);

    return $parsed !== false ? date('Y-m-d H:i:s', $parsed) : null;
}

function didit_map_status($statusRaw)
{
    switch ($statusRaw) {

        case "Approved":
            return "Approved";

        case "Declined":
            return "Declined";

        case "Not Started":
            return "Not Started";

        /*
        | Kept as its own bucket rather than folded into "In Progress" —
        | per Didit's own documentation (docs.didit.me/integration/
        | verification-statuses), "In Review" is explicitly categorized
        | as "Actionable: requires manual intervention from your team",
        | distinct from the "Non-Terminal: still active" category that
        | Awaiting User/Resubmitted fall into. Collapsing it into "In
        | Progress" hid exactly the sessions an admin most needs to see
        | — ones sitting in Didit's console waiting on a human reviewer,
        | indistinguishable from a client who simply hasn't finished yet.
        */
        case "In Review":
            return "In Review";

        case "In Progress":
        case "Awaiting User":
        case "Resubmitted":
        case "Abandoned":
        case "Expired":
        case "Kyc Expired":
            return "In Progress";

        default:
            return "In Progress";
    }
}

/*
|--------------------------------------------------------------------------
| CRON: ENFORCEMENT ACTIONS
|--------------------------------------------------------------------------
| The destructive part of this module. Gated behind TWO independent
| opt-ins: the "enforcement_enabled" master switch AND the individual
| Service/Account Action being something other than "None". Missing
| either means nothing happens — this is deliberate; a single dropdown
| left at a non-default value should never be enough on its own to
| suspend, cancel, terminate, or close a real client's account.
|
| Every action taken is written to mod_didit_enforcement_log before the
| API call, not after — so even if the API call itself fails partway
| through, there's a record that this was attempted.
*/

function didit_cron_run_enforcement()
{
    if (didit_get_setting('enforcement_enabled') != 'on') {
        return;
    }

    $serviceAction = didit_get_setting('service_action') ?: 'None';
    $accountAction = didit_get_setting('account_action') ?: 'None';

    if ($serviceAction === 'None' && $accountAction === 'None') {
        return;
    }

    $serviceDays = (int) (didit_get_setting('service_action_days') ?: 0);
    $accountDays = (int) (didit_get_setting('account_action_days') ?: 0);

    $approvedUserIds = Capsule::table('mod_didit_sessions')
        ->where('status', 'Approved')
        ->pluck('userid');

    $candidates = Capsule::table('tblclients')
        ->where('status', 'Active')
        ->whereNotIn('id', $approvedUserIds)
        ->get(['id']);

    foreach ($candidates as $client) {

        $override = Capsule::table('mod_didit_overrides')
            ->where('userid', $client->id)
            ->first();

        if ($override && $override->disable_suspend == 1) {
            continue;
        }

        // Reference point: most recent reminder sent, or fall back to
        // signup date if reminders aren't configured at all — matches
        // the "starts counting after the last reminder" note in the
        // Enforcement Actions setting descriptions.
        $lastReminder = Capsule::table('mod_didit_reminders')
            ->where('userid', $client->id)
            ->orderBy('sent_at', 'desc')
            ->value('sent_at');

        if (!$lastReminder) {

            $lastReminder = Capsule::table('tblclients')
                ->where('id', $client->id)
                ->value('datecreated');
        }

        if (!$lastReminder) {
            continue;
        }

        $daysSince = (int) floor((time() - strtotime($lastReminder)) / 86400);

        if ($serviceAction !== 'None' && $daysSince >= $serviceDays && $serviceDays > 0) {
            didit_enforce_service_action($client->id, $serviceAction);
        }

        if ($accountAction !== 'None' && $daysSince >= $accountDays && $accountDays > 0) {
            didit_enforce_account_action($client->id, $accountAction);
        }
    }
}

function didit_enforce_service_action($userid, $action)
{
    $services = Capsule::table('tblhosting')
        ->where('userid', $userid)
        ->where('domainstatus', 'Active')
        ->get();

    foreach ($services as $service) {

        Capsule::table('mod_didit_enforcement_log')->insert([
            'userid' => $userid,
            'action_type' => 'service',
            'action_taken' => $action,
            'target_id' => $service->id,
            'result' => 'attempting',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        try {

            if ($action === 'Suspend') {

                $result = localAPI('ModuleSuspend', [
                    'serviceid' => $service->id,
                    'suspendreason' => 'KYC verification not completed',
                ]);

            } elseif ($action === 'Cancel') {

                $result = localAPI('AddCancelRequest', [
                    'serviceid' => $service->id,
                    'type' => 'Immediate',
                ]);

            } elseif ($action === 'Terminate') {

                $result = localAPI('ModuleTerminate', [
                    'serviceid' => $service->id,
                ]);

            } else {
                continue;
            }

            $resultText = json_encode($result);

        } catch (\Throwable $e) {

            $resultText = 'EXCEPTION: ' . $e->getMessage();
        }

        Capsule::table('mod_didit_enforcement_log')
            ->where('userid', $userid)
            ->where('target_id', $service->id)
            ->where('result', 'attempting')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->update(['result' => $resultText]);

        logActivity("Didit Cron Enforcement | UserID={$userid} | ServiceID={$service->id} | Action={$action} | Result={$resultText}");
    }
}

function didit_enforce_account_action($userid, $action)
{
    Capsule::table('mod_didit_enforcement_log')->insert([
        'userid' => $userid,
        'action_type' => 'account',
        'action_taken' => $action,
        'target_id' => null,
        'result' => 'attempting',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    try {

        if ($action === 'Inactive') {

            $result = localAPI('UpdateClient', [
                'clientid' => $userid,
                'status' => 'Inactive',
            ]);

        } elseif ($action === 'Close') {

            $result = localAPI('CloseClient', [
                'clientid' => $userid,
            ]);

        } else {
            return;
        }

        $resultText = json_encode($result);

    } catch (\Throwable $e) {

        $resultText = 'EXCEPTION: ' . $e->getMessage();
    }

    Capsule::table('mod_didit_enforcement_log')
        ->where('userid', $userid)
        ->where('action_type', 'account')
        ->where('result', 'attempting')
        ->orderBy('id', 'desc')
        ->limit(1)
        ->update(['result' => $resultText]);

    logActivity("Didit Cron Enforcement | UserID={$userid} | Action={$action} | Result={$resultText}");
}

/*
|--------------------------------------------------------------------------
| CRON: CLEANUP
|--------------------------------------------------------------------------
| Purely informational — flags sessions that have been sitting idle for
| a long time (matching Didit's own 7-day session expiry) so they're
| visible in the log, without silently changing their status or taking
| any action on them. Enforcement (above) is the only thing in this
| module allowed to act on a stale session, and only when explicitly
| enabled.
*/

function didit_cron_flag_stale_sessions()
{
    $stale = Capsule::table('mod_didit_sessions')
        ->whereIn('status', ['Not Started', 'In Progress', 'In Review'])
        ->where('deleted_upstream', 0)
        ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-7 days')))
        ->count();

    if ($stale > 0) {
        logActivity("Didit Cron: {$stale} session(s) idle 7+ days (Not Started/In Progress) — review the Online KYC dashboard");
    }
}

/*
|--------------------------------------------------------------------------
| CRON: ORCHESTRATOR
|--------------------------------------------------------------------------
*/

function didit_run_cron()
{
    didit_ensure_schema();

    try {
        didit_cron_send_reminders();
    } catch (\Throwable $e) {
        logActivity("Didit Cron Reminders Failed: " . $e->getMessage());
    }

    try {
        didit_cron_sync_pending_sessions();
    } catch (\Throwable $e) {
        logActivity("Didit Cron Sync Failed: " . $e->getMessage());
    }

    try {
        didit_cron_flag_stale_sessions();
    } catch (\Throwable $e) {
        logActivity("Didit Cron Cleanup Failed: " . $e->getMessage());
    }

    try {
        didit_cron_run_enforcement();
    } catch (\Throwable $e) {
        logActivity("Didit Cron Enforcement Failed: " . $e->getMessage());
    }

    logActivity("Didit Cron: run complete");
}