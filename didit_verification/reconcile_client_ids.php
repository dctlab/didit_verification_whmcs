<?php

/*
|--------------------------------------------------------------------------
| ONE-TIME REPAIR: mod_didit_sessions.userid -> tblclients.id
|--------------------------------------------------------------------------
| Fixes rows created before the didit_get_client_id() fix, where userid
| was stored as $_SESSION['uid'] (a tblusers login ID) instead of the
| actual tblclients.id. Those rows are invisible to the admin dashboard
| because it INNER JOINs to tblclients.
|
| USAGE:
|   1. Browse to this file once as an admin (dry run — no writes happen).
|      It lists every affected session and, where resolvable, the
|      correct client_id it would be repointed to.
|   2. Re-run with ?apply=1 to actually perform the updates.
|
| Safe to leave in place / re-run at any time: rows that already match a
| real tblclients.id are left untouched.
*/

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/helpers.php';

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Access denied");
}

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';

header('Content-Type: text/plain');

echo $apply
    ? "APPLY MODE — updates will be written.\n\n"
    : "DRY RUN — no changes will be made. Add ?apply=1 to the URL to apply fixes.\n\n";

/*
|--------------------------------------------------------------------------
| FIND SESSIONS WHOSE userid DOES NOT MATCH A REAL CLIENT
|--------------------------------------------------------------------------
*/

$validClientIds = Capsule::table('tblclients')->pluck('id')->all();

$orphaned = Capsule::table('mod_didit_sessions')
    ->whereNotIn('userid', $validClientIds)
    ->get();

if ($orphaned->isEmpty()) {
    echo "No mismatched sessions found. Nothing to do.\n";
    exit;
}

echo count($orphaned) . " session(s) have a userid that doesn't match any tblclients.id:\n\n";

/*
|--------------------------------------------------------------------------
| RESOLVE VIA tblusers_clients
|--------------------------------------------------------------------------
| Column names are introspected rather than hardcoded, since they can
| differ slightly across WHMCS versions.
*/

$resolvable = 0;
$unresolvable = 0;

if (!Capsule::schema()->hasTable('tblusers_clients')) {

    echo "tblusers_clients table not found on this install — cannot auto-resolve.\n";
    echo "Session rows below will need to be corrected manually.\n\n";

    foreach ($orphaned as $row) {
        echo "  session_id={$row->session_id} | stored userid={$row->userid} | status={$row->status}\n";
    }

    exit;
}

$pivotColumns = Capsule::schema()->getColumnListing('tblusers_clients');

$userCol = in_array('user_id', $pivotColumns) ? 'user_id' : (in_array('userid', $pivotColumns) ? 'userid' : null);
$clientCol = in_array('client_id', $pivotColumns) ? 'client_id' : (in_array('clientid', $pivotColumns) ? 'clientid' : null);

if (!$userCol || !$clientCol) {

    echo "Could not identify user/client columns on tblusers_clients (found: " . implode(', ', $pivotColumns) . ").\n";
    echo "Session rows below will need to be corrected manually.\n\n";

    foreach ($orphaned as $row) {
        echo "  session_id={$row->session_id} | stored userid={$row->userid} | status={$row->status}\n";
    }

    exit;
}

echo "Resolving via tblusers_clients.{$userCol} -> tblusers_clients.{$clientCol}\n\n";

foreach ($orphaned as $row) {

    $mapping = Capsule::table('tblusers_clients')
        ->where($userCol, $row->userid)
        ->first();

    if (!$mapping) {

        $unresolvable++;

        echo "  UNRESOLVED | session_id={$row->session_id} | stored userid={$row->userid} (no matching tblusers_clients row)\n";

        continue;
    }

    $correctClientId = $mapping->$clientCol;

    $resolvable++;

    echo "  " . ($apply ? "FIXED  " : "WOULD FIX") .
        " | session_id={$row->session_id} | userid {$row->userid} -> {$correctClientId}\n";

    if ($apply) {

        Capsule::table('mod_didit_sessions')
            ->where('id', $row->id)
            ->update(['userid' => $correctClientId]);

        didit_log(
            'ReconcileClientId',
            "session_id={$row->session_id} | old userid={$row->userid}",
            "new userid={$correctClientId}"
        );
    }
}

echo "\n";
echo "Resolved: {$resolvable}\n";
echo "Unresolved (need manual review): {$unresolvable}\n";

if (!$apply && $resolvable > 0) {
    echo "\nRe-run this script with ?apply=1 to write the {$resolvable} fix(es) above.\n";
}
