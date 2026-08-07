<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/helpers.php';

use WHMCS\Database\Capsule;

/*
|--------------------------------------------------------------------------
| CRASH SAFETY NET
|--------------------------------------------------------------------------
| A prior bug went undiagnosed for hours because a webhook could die
| partway through processing with NO trace anywhere — no Module Log
| entry, no Activity Log entry, nothing — because the failure happened
| before any logging call executed, or logModuleCall() itself silently
| swallowed an error (e.g. an oversized payload hitting a DB column
| limit). This wrapper guarantees that never happens again: every path
| through webhook processing is now covered by either the try/catch
| below (for exceptions) or this shutdown handler (for true PHP fatals —
| parse errors, out-of-memory, etc. — which try/catch cannot intercept).
|
| Failures are logged to THREE independent places so at least one is
| always visible regardless of settings:
|   1. didit_webhook_fatal.log — a plain file, works even if the DB
|      connection itself is what failed.
|   2. logActivity() — WHMCS Activity Log, does not depend on the
|      "Enable Module Debug Log" setting.
|   3. didit_log() — WHMCS Module Log, for consistency with everything
|      else this module logs there.
*/

function didit_log_fatal($message, $trace = '')
{
    file_put_contents(
        __DIR__ . '/didit_webhook_fatal.log',
        date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL .
        ($trace ? $trace . PHP_EOL : '') .
        str_repeat('-', 80) . PHP_EOL,
        FILE_APPEND
    );

    if (function_exists('logActivity')) {
        logActivity("Didit Webhook FATAL: {$message}");
    }

    if (function_exists('didit_log')) {
        didit_log('WebhookFatal', $message, $trace ?: 'Uncaught fatal — see didit_webhook_fatal.log');
    }
}

register_shutdown_function(function () {

    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {

        didit_log_fatal("{$error['message']} in {$error['file']}:{$error['line']}");

        if (!headers_sent()) {
            http_response_code(500);
        }
    }
});

try {

    didit_webhook_process();

} catch (\Throwable $e) {

    didit_log_fatal(
        get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
        $e->getTraceAsString()
    );

    http_response_code(500);
    echo "Internal error — see didit_webhook_fatal.log";
    exit;
}

/*
|--------------------------------------------------------------------------
| WEBHOOK PROCESSING
|--------------------------------------------------------------------------
*/

function didit_webhook_process()
{
    didit_ensure_schema();

    /*
    |--------------------------------------------------------------------------
    | READ RAW PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payload = file_get_contents("php://input");

    file_put_contents(
        __DIR__ . '/didit_raw_payload.log',
        date('Y-m-d H:i:s') . PHP_EOL .
        $payload . PHP_EOL .
        str_repeat('-', 80) . PHP_EOL,
        FILE_APPEND
    );

    // Module Log entry for the raw receipt. Payload can be large; capped
    // here so a size issue can never take down logging for the rest of
    // the request (each didit_log() call is independent).
    didit_log(
        'WebhookReceived',
        "Headers: " . json_encode(getallheaders() ?: []),
        substr($payload, 0, 20000) . (strlen($payload) > 20000 ? "\n...[truncated, " . strlen($payload) . " bytes total]" : '')
    );

    if (isset($_SERVER['HTTP_X_DIDIT_TEST_WEBHOOK'])) {

        logActivity("Didit Test Webhook Received");

        http_response_code(200);
        echo "Test webhook OK";
        return;
    }

    if (!$payload) {
        http_response_code(200);
        echo "Webhook endpoint ready";
        return;
    }

    logActivity("Didit RAW Payload: " . substr($payload, 0, 5000));

    /*
    |--------------------------------------------------------------------------
    | PARSE PAYLOAD
    |--------------------------------------------------------------------------
    */

    $data = json_decode($payload, true);

    file_put_contents(
        __DIR__ . '/didit_webhook_debug.log',
        date('Y-m-d H:i:s') . PHP_EOL .
        print_r($data, true) . PHP_EOL .
        str_repeat('-', 80) . PHP_EOL,
        FILE_APPEND
    );

    if (json_last_error() !== JSON_ERROR_NONE) {

        didit_log('Webhook', substr($payload, 0, 5000), 'REJECTED: Invalid JSON');

        logActivity("Didit Invalid JSON");

        http_response_code(200);
        echo "Invalid JSON";
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT ENVELOPE FIELDS
    |--------------------------------------------------------------------------
    | Per Didit's documented V3 envelope, these are always top-level on
    | every session event (status.updated / data.updated): session_id,
    | status, webhook_type, vendor_data.
    */

    $sessionId   = $data['session_id'] ?? null;
    $statusRaw   = $data['status'] ?? '';
    $webhookType = $data['webhook_type'] ?? '';

    /*
    | vendor_data is the WHMCS client ID echoed back on every webhook —
    | used below to self-heal a missing local session row instead of
    | dropping the status update.
    */

    $vendorData  = $data['vendor_data'] ?? null;
    $clientEmail = $data['decision']['contact_details']['email'] ?? null;

    /*
    | Extract a human-readable reason for display in the admin "View
    | Details" panel — prefers a reviewer's own comment (manual_review
    | trigger), then falls back to the first warning found across any
    | per-feature verification array in the decision object. Not every
    | webhook carries one (e.g. a plain "In Progress" data.updated has
    | nothing to report), so this can legitimately end up null.
    */

    $reason = $data['decision']['reviews'][0]['comment'] ?? null;

    if (empty($reason) && !empty($data['decision'])) {

        $verificationArrays = [
            'id_verifications', 'liveness_checks', 'face_matches',
            'poa_verifications', 'aml_screenings', 'ip_analyses',
            'phone_verifications', 'email_verifications', 'nfc_verifications',
        ];

        foreach ($verificationArrays as $arrayKey) {

            $firstWarning = $data['decision'][$arrayKey][0]['warnings'][0] ?? null;

            if ($firstWarning) {

                $risk = $firstWarning['risk'] ?? '';
                $desc = $firstWarning['short_description'] ?? $firstWarning['long_description'] ?? '';

                $reason = trim($risk . ($risk && $desc ? ': ' : '') . $desc);
                break;
            }
        }
    }

    logActivity(
        "Didit Extracted Values | SessionID={$sessionId} | Status={$statusRaw}"
    );

    /*
    |--------------------------------------------------------------------------
    | VERIFY SIGNATURE
    |--------------------------------------------------------------------------
    | Per Didit's spec (docs.didit.me/integration/webhooks), tried in
    | their recommended order. Each function independently checks the
    | timestamp is within the 5-minute replay window.
    */

    $signatureV2     = $_SERVER['HTTP_X_SIGNATURE_V2'] ?? '';
    $signatureRaw    = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $signatureSimple = $_SERVER['HTTP_X_SIGNATURE_SIMPLE'] ?? '';
    $timestamp       = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';

    $webhookSecret = Capsule::table('tbladdonmodules')
        ->where('module', 'didit_verification')
        ->where('setting', 'webhook_secret')
        ->value('value');

    if (empty($webhookSecret)) {

        didit_log('Webhook', substr($payload, 0, 2000), 'REJECTED: webhook_secret not configured — cannot verify signature');

        logActivity("Didit Webhook Rejected: webhook_secret is not configured in module settings");

        http_response_code(401);
        echo "Webhook secret not configured";
        return;
    }

    $signatureVerified = false;
    $verifiedVia = null;

    if (didit_verify_signature_v2($payload, $signatureV2, $timestamp, $webhookSecret)) {
        $signatureVerified = true;
        $verifiedVia = 'X-Signature-V2';
    } elseif (didit_verify_signature_raw($payload, $signatureRaw, $timestamp, $webhookSecret)) {
        $signatureVerified = true;
        $verifiedVia = 'X-Signature';
    } elseif (didit_verify_signature_simple($data, $signatureSimple, $timestamp, $webhookSecret)) {
        // Simple only authenticates the envelope, not the decision body —
        // treat decision data from a Simple-only verification with
        // appropriate caution.
        $signatureVerified = true;
        $verifiedVia = 'X-Signature-Simple';
    }

    if (!$signatureVerified) {

        didit_log(
            'Webhook',
            "Headers: V2=" . (!empty($signatureV2) ? 'present' : 'missing') .
            " | Raw=" . (!empty($signatureRaw) ? 'present' : 'missing') .
            " | Simple=" . (!empty($signatureSimple) ? 'present' : 'missing') .
            " | Timestamp={$timestamp}",
            'REJECTED: Signature verification failed on all methods (or timestamp outside 5-minute window)'
        );

        logActivity("Didit Webhook Signature Invalid | SessionID={$sessionId}");

        http_response_code(401);
        echo "Invalid signature";
        return;
    }

    didit_log(
        'Webhook',
        "SessionID={$sessionId}",
        "Signature verified via {$verifiedVia}"
    );

    logActivity("Didit Webhook Signature Verified | Method={$verifiedVia} | SessionID={$sessionId}");

    /*
    |--------------------------------------------------------------------------
    | VALIDATE PAYLOAD
    |--------------------------------------------------------------------------
    */

    if (empty($sessionId)) {

        didit_log('Webhook', substr($payload, 0, 2000), 'REJECTED: Missing session_id');

        logActivity("Didit Missing Session ID");

        http_response_code(200);
        echo "Missing session_id";
        return;
    }

    if (empty($statusRaw)) {

        logActivity("Didit Missing Status");

        $statusRaw = 'pending';
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE STATUS
    |--------------------------------------------------------------------------
    | Mapping lives in didit_map_status() (helpers.php), shared with the
    | cron sync job — see that function's docblock for the reasoning on
    | Abandoned/Expired/Kyc Expired mapping to "In Progress" rather than
    | "Declined".
    */

    $status = didit_map_status($statusRaw);

    logActivity("Didit Status Debug: RAW={$statusRaw} | FINAL={$status}");

    /*
    |--------------------------------------------------------------------------
    | LOG WEBHOOK
    |--------------------------------------------------------------------------
    */

    Capsule::table('mod_didit_webhook_logs')->insert([
        'session_id' => $sessionId,
        'event_type' => $status,
        'payload'    => substr($payload, 0, 60000),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    logActivity("Didit Webhook: {$sessionId} -> {$status}");

    /*
    |--------------------------------------------------------------------------
    | FIND SESSION
    |--------------------------------------------------------------------------
    */

    $session = Capsule::table('mod_didit_sessions')
        ->where('session_id', $sessionId)
        ->first();

    if (!$session) {

        /*
        | SELF-HEAL: reconstruct the missing row from the webhook payload
        | itself, rather than silently dropping a real status update.
        | Requires vendor_data to be present and look like a real WHMCS
        | client ID (numeric) — if we can't be reasonably sure who this
        | belongs to, we still refuse rather than guess.
        */

        if (!empty($vendorData) && ctype_digit((string) $vendorData)) {

            try {

                Capsule::table('mod_didit_sessions')->insert([
                    'userid'     => (int) $vendorData,
                    'session_id' => $sessionId,
                    'status'     => 'Not Started',
                    'email'      => $clientEmail,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                didit_log(
                    'Webhook',
                    "SessionID={$sessionId} | VendorData={$vendorData} | Email={$clientEmail}",
                    'SELF-HEALED: no local row existed — reconstructed from webhook payload'
                );

                logActivity("Didit Self-Healed Missing Session | SessionID={$sessionId} | UserID={$vendorData}");

            } catch (\Throwable $e) {

                /*
                | Almost certainly a duplicate-key hit on the session_id
                | unique constraint — another near-simultaneous webhook
                | for this same session won the race and inserted first.
                | That's fine: the row exists now regardless of which
                | request created it, so just proceed to use it rather
                | than treating this as a failure.
                */

                didit_log(
                    'Webhook',
                    "SessionID={$sessionId}",
                    'Self-heal insert hit an existing row (race with another delivery) — using it: ' . $e->getMessage()
                );
            }

            $session = Capsule::table('mod_didit_sessions')
                ->where('session_id', $sessionId)
                ->first();

        } else {

            didit_log(
                'Webhook',
                "SessionID={$sessionId} | ExtractedStatus={$statusRaw} | VendorData=" . ($vendorData ?? 'null'),
                'REJECTED: No matching row and no usable vendor_data to reconstruct one — status update dropped'
            );

            logActivity("Didit Session Not Found | SessionID: {$sessionId}");

            http_response_code(200);
            echo "Session not found";
            return;
        }
    }

    $userid = $session->userid;

    logActivity("Didit Session Found | UserID: {$userid} | SessionID: {$sessionId}");

    /*
    |--------------------------------------------------------------------------
    | APPLY STATUS CHANGE (shared with manual admin approval — see
    | didit_apply_status_change() in helpers.php)
    |--------------------------------------------------------------------------
    */

    didit_apply_status_change($sessionId, $status, 'webhook', $reason);

    didit_log(
        'Webhook',
        "SessionID={$sessionId} | RawStatus={$statusRaw}",
        "OK: UserID={$userid} | NormalizedStatus={$status}"
    );

    logActivity("Didit Status Updated | UserID={$userid} | Status={$status}");

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "received" => true,
        "status" => $status
    ]);
}
