<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/helpers.php';

use WHMCS\Database\Capsule;

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

if (isset($_SERVER['HTTP_X_DIDIT_TEST_WEBHOOK'])) {

    logActivity("Didit Test Webhook Received");

    http_response_code(200);
    echo "Test webhook OK";
    exit;
}


if (!$payload) {
    http_response_code(200);
    echo "Webhook endpoint ready";
    exit;
}

logActivity("Didit RAW Payload: " . $payload);

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
    logActivity("Didit Invalid JSON: " . $payload);

    http_response_code(200);
    echo "Invalid JSON";
    exit;
}

/*
|--------------------------------------------------------------------------
| DEBUG FULL PAYLOAD
|--------------------------------------------------------------------------
*/

logActivity("Didit Parsed Payload: " . print_r($data, true));



/*
|--------------------------------------------------------------------------
| SUPPORT MULTIPLE DIDIT PAYLOAD FORMATS
|--------------------------------------------------------------------------
*/

$sessionId =
    $data['session_id']
    ?? $data['data']['session_id']
    ?? $data['verification_session_id']
    ?? $data['session']['id']
    ?? $data['resource']['id']
    ?? null;

$statusRaw =
    $data['status']
    ?? $data['data']['status']
    ?? $data['verification_status']
    ?? $data['decision']
    ?? $data['review_status']
    ?? '';

logActivity(
    "Didit Extracted Values | SessionID={$sessionId} | Status={$statusRaw}"
);

/*
|--------------------------------------------------------------------------
| VERIFY SIGNATURE
|--------------------------------------------------------------------------
*/

$receivedSignature = $_SERVER['HTTP_X_SIGNATURE_V2']
    ?? $_SERVER['HTTP_X_SIGNATURE_SIMPLE']
    ?? '';

$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';

$webhookSecret = Capsule::table('tbladdonmodules')
    ->where('module', 'didit_verification')
    ->where('setting', 'webhook_secret')
    ->value('value');

/*if ($webhookSecret && $receivedSignature) {

    $payloadToSign = $timestamp . '.' . $payload;

    $expectedSignature = hash_hmac(
        'sha256',
        $payloadToSign,
        $webhookSecret
    );

if (!hash_equals($expectedSignature, $receivedSignature)) {

    logActivity(
        "Didit Signature Failed | Received={$receivedSignature} | Expected={$expectedSignature} | Timestamp={$timestamp}"
    );

    // TEMPORARY - DO NOT KEEP IN PRODUCTION
    logActivity("Didit Signature Bypassed For Debug");
}*/

/*
|--------------------------------------------------------------------------
| VALIDATE PAYLOAD
|--------------------------------------------------------------------------
*/

if (empty($sessionId)) {

    logActivity(
        "Didit Missing Session ID: " .
        print_r($data, true)
    );

    http_response_code(200);
    echo "Missing session_id";
    exit;
}

if (empty($statusRaw)) {

    logActivity(
        "Didit Missing Status: " .
        print_r($data, true)
    );

    $statusRaw = 'pending';
}

/*
|--------------------------------------------------------------------------
| NORMALIZE STATUS
|--------------------------------------------------------------------------
*/

$statusRawClean = strtolower(trim($statusRaw));

// convert spaces → underscores
$statusRawClean = str_replace(' ', '_', $statusRawClean);

$status = "Not Started";

switch ($statusRawClean) {

    case "approved":
    case "completed":
    case "verified":
    case "success":
        $status = "Approved";
        break;

    case "declined":
    case "rejected":
    case "failed":
    case "denied":
        $status = "Declined";
        break;

    case "in_progress":
    case "pending":
    case "review":
    case "under_review":
    case "processing":
        $status = "In Progress";
        break;

    default:
        $status = "In Progress";
}

logActivity(
    "Didit Status Debug: RAW={$statusRaw} | CLEAN={$statusRawClean} | FINAL={$status}"
);

/*
|--------------------------------------------------------------------------
| LOG WEBHOOK
|--------------------------------------------------------------------------
*/

Capsule::table('mod_didit_webhook_logs')->insert([
    'session_id' => $sessionId,
    'event_type' => $status,
    'payload' => $payload,
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

    logActivity(
        "Didit Session Not Found | SessionID: {$sessionId}"
    );

    http_response_code(200);
    echo "Session not found";
    exit;
}

$userid = $session->userid;

logActivity(
    "Didit Session Found | UserID: {$userid} | SessionID: {$sessionId}"
);

/*
|--------------------------------------------------------------------------
| UPDATE SESSION STATUS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| UPDATE SESSION
|--------------------------------------------------------------------------
*/

$updateData = [
    'status' => $status,
    'updated_at' => date('Y-m-d H:i:s')
];

if ($status === 'Approved') {
    $updateData['verified_at'] = date('Y-m-d H:i:s');
}

Capsule::table('mod_didit_sessions')
    ->where('session_id', $sessionId)
    ->update($updateData);

logActivity(
    "Didit Status Updated | UserID={$userid} | Status={$status}"
);

/*
|--------------------------------------------------------------------------
| GET MODULE SETTINGS
|--------------------------------------------------------------------------
*/

$apiKey = Capsule::table('tbladdonmodules')
    ->where('module','didit_verification')
    ->where('setting','api_key')
    ->value('value');

$workflowId = Capsule::table('tbladdonmodules')
    ->where('module','didit_verification')
    ->where('setting','workflow_id')
    ->value('value');

/*
|--------------------------------------------------------------------------
| DECLINED → AUTO SUSPEND + RETRY
|--------------------------------------------------------------------------
*/

if ($status === "Declined") {

    /*
    | CHECK ADMIN OVERRIDE
    */

    $override = Capsule::table('mod_didit_overrides')
        ->where('userid',$userid)
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

    /*
    |--------------------------------------------------------------------------
    | AUTO RETRY VERIFICATION (ONLY IF NO ACTIVE SESSION)
    |--------------------------------------------------------------------------
    */

    $activeSession = Capsule::table('mod_didit_sessions')
        ->where('userid',$userid)
        ->whereIn('status',['Not Started','In Progress'])
        ->first();

    if (!$activeSession) {

        $email = Capsule::table('tblclients')
            ->where('id', $userid)
            ->value('email');

        didit_create_session($userid,$email,$apiKey,$workflowId);

        logActivity("Didit Auto Retry Created for User {$userid}");
    }
}
/*
|--------------------------------------------------------------------------
| APPROVED → UNSUSPEND + DOWNLOAD REPORT
|--------------------------------------------------------------------------
*/

if ($status === "Approved") {

    logActivity("Didit Approved Session: {$sessionId}");

    $services = Capsule::table('tblhosting')
        ->where('userid', $userid)
        ->where('domainstatus', 'Suspended')
        ->get();

    foreach ($services as $service) {

        $result = localAPI('ModuleUnsuspend', [
            'accountid' => $service->id
        ]);

        logActivity(
            "Didit Unsuspend Service {$service->id}: " .
            json_encode($result)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD VERIFICATION REPORT
    |--------------------------------------------------------------------------
    */

    $pdfResult = didit_download_report(
        $sessionId,
        $apiKey,
        $userid
    );

    logActivity(
        "Didit PDF Download Result: " .
        ($pdfResult ?: 'FAILED')
    );
}
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

exit;