<?php

use WHMCS\Database\Capsule;

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
    ->whereIn('status', ['Not Started', 'In Progress'])
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


    $url = "https://verification.didit.me/v3/session/";

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

        logActivity("Didit API Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!empty($result['session_id'])) {

        Capsule::table('mod_didit_sessions')->insert([

            'userid' => $userId,
            'session_id' => $result['session_id'],
            'session_token' => $result['session_token'] ?? null,
            'status' => $result['status'] ?? 'Not Started',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')

        ]);

        logActivity("Didit Session Created: {$result['session_id']} for User {$userId}");

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

    $url = "https://verification.didit.me/v3/session/{$sessionId}/generate-pdf";

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

        logActivity(
            "Didit CURL Error: " .
            curl_error($ch)
        );

        curl_close($ch);

        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    logActivity("Didit HTTP Code: {$httpCode}");
    logActivity("Didit Effective URL: {$effectiveUrl}");

    curl_close($ch);

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