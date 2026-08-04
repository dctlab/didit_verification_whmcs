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
        "version" => "19.0",
        "author" => "Rejil",
        "fields" => [
            "api_key" => [
                "FriendlyName" => "Didit API Key",
                "Type" => "text",
                "Size" => "60",
            ],
            "workflow_id" => [
                "FriendlyName" => "Workflow ID",
                "Type" => "text",
                "Size" => "40",
            ],
            "webhook_secret" => [
                "FriendlyName" => "Webhook Secret",
                "Type" => "text",
                "Size" => "60",
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
$table->timestamps();

        });

    } else {

        if (!Capsule::schema()->hasColumn('mod_didit_sessions','report_file')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->string('report_file')->nullable();
    });
}

if (!Capsule::schema()->hasColumn('mod_didit_sessions','verified_at')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->dateTime('verified_at')->nullable();
    });
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

    return [
        'status' => 'success',
        'description' => 'Didit Verification Module Activated'
    ];
}


/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

function didit_verification_output()
{


require_once __DIR__ . '/helpers.php';

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

    echo "<h2>Didit Verification Dashboard</h2>";

    $total = Capsule::table('mod_didit_sessions')->count();
    $approved = Capsule::table('mod_didit_sessions')->where('status','Approved')->count();
    $declined = Capsule::table('mod_didit_sessions')->where('status','Declined')->count();
    $progress = Capsule::table('mod_didit_sessions')->where('status','In Progress')->count();

    echo "
    <div class='row' style='margin-bottom:20px'>
        <div class='col-md-3'><div class='alert alert-info'><h4>Total</h4><h2>$total</h2></div></div>
        <div class='col-md-3'><div class='alert alert-success'><h4>Approved</h4><h2>$approved</h2></div></div>
        <div class='col-md-3'><div class='alert alert-danger'><h4>Declined</h4><h2>$declined</h2></div></div>
        <div class='col-md-3'><div class='alert alert-warning'><h4>In Progress</h4><h2>$progress</h2></div></div>
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

<option value="Approved">Approved</option>
<option value="Declined">Declined</option>
<option value="In Progress">In Progress</option>
<option value="Not Started">Not Started</option>

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

<a href="addonmodules.php?module=didit_verification"
class="btn btn-default">
Reset
</a>

</div>

</div>

</form>

';

    renderAdminTable(['Not Started','In Progress'], 'Sessions Started');
    renderAdminTable(['Approved'], 'Approved Clients');
    renderAdminTable(['Declined'], 'Declined Clients');



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
<option value="Approved">Approved</option>
<option value="Declined">Declined</option>
<option value="In Progress">In Progress</option>
<option value="Not Started">Not Started</option>

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

<a href="addonmodules.php?module=didit_verification"
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
    ->join('tblclients','mod_didit_sessions.userid','=','tblclients.id')
    ->leftJoin('mod_didit_overrides','tblclients.id','=','mod_didit_overrides.userid');


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
        ->orWhere('tblclients.email','LIKE',"%$search%");

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

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$limit = 10;

$offset = ($page - 1) * $limit;

$total = $query->count();


$clients = $query
    ->select(
        'tblclients.id as userid',
        'tblclients.firstname',
        'tblclients.lastname',
        'tblclients.email',
        'mod_didit_sessions.status',
        'mod_didit_overrides.disable_suspend',
        'mod_didit_overrides.disable_order_block'
    )
    ->groupBy('tblclients.id')
    ->orderBy('tblclients.id','desc')
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

    echo "<tr>

<form method='post'>

<td>{$client->firstname} {$client->lastname}</td>

<td>{$client->email}</td>

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

<input type='hidden' name='userid' value='{$client->userid}'>

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
    $params['page'] = $i;

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
| ADMIN CLIENT TABLE
|--------------------------------------------------------------------------
*/

function renderAdminTable($statuses,$title)
{

    echo "<hr><h3>$title</h3>";

    $query = Capsule::table('mod_didit_sessions')
        ->join('tblclients','mod_didit_sessions.userid','=','tblclients.id')
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
            ->orWhere('tblclients.email','LIKE',"%$search%");

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

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    $limit = 10;

    $offset = ($page - 1) * $limit;

    $total = $query->count();


    $records = $query
        ->select(
            'tblclients.id as client_id',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.email',
            'mod_didit_sessions.status',
            'mod_didit_sessions.session_id',
            'mod_didit_sessions.updated_at',
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
    <th>Status</th>
    <th>Session</th>
    <th>Date</th>
    <th>Report</th>
    <th>Generate PDF</th>
    <th>Profile</th>

    </tr>
    </thead>";



    foreach ($records as $row) {

        $profile = "clientssummary.php?userid=".$row->client_id;

        $reportButton = "";

	$generateButton = "";

if (
    $row->status === 'Approved' &&
    empty($row->report_file)
) {

    $generateButton =
    "<a href='addonmodules.php?module=didit_verification&generate_pdf={$row->session_id}'
    class='btn btn-xs btn-warning'>
    📥 Generate PDF
    </a>";
}
	

      if (!empty($row->report_file)) {

    $reportButton =
    "<a href='addonmodules.php?module=didit_verification&download_pdf={$row->session_id}'
    class='btn btn-xs btn-success'>
    📄 Download PDF
    </a>";
}


        echo "<tr>

        <td>{$row->firstname} {$row->lastname}</td>

        <td>{$row->email}</td>

        <td><strong>{$row->status}</strong></td>

        <td>{$row->session_id}</td>

        <td>{$row->updated_at}</td>

       <td>{$reportButton}</td>

<td>{$generateButton}</td>

<td>

<a href='$profile'
class='btn btn-sm btn-primary'>
View Client
</a>

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
        $params['page'] = $i;

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
    if (!isset($_SESSION['uid'])) {
        return [
            'templatefile' => 'clientarea',
            'requirelogin' => true
        ];
    }

    $userid = $_SESSION['uid'];

    /*
    |--------------------------------------------------------------------------
    | AJAX STATUS CHECK
    |--------------------------------------------------------------------------
    */
    if (isset($_GET['ajax']) && $_GET['ajax'] == "status") {

        $session = Capsule::table('mod_didit_sessions')
            ->where('userid', $userid)
            ->orderBy('id', 'desc')
            ->first();

        header('Content-Type: application/json');

        echo json_encode([
            'status' => $session->status ?? 'Not Started'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | START VERIFICATION
    |--------------------------------------------------------------------------
    */
    if (isset($_GET['action']) && $_GET['action'] == "start") {

        $apiKey = Capsule::table('tbladdonmodules')
            ->where('module', 'didit_verification')
            ->where('setting', 'api_key')
            ->value('value');

        $workflowId = Capsule::table('tbladdonmodules')
            ->where('module', 'didit_verification')
            ->where('setting', 'workflow_id')
            ->value('value');

        if (!$apiKey || !$workflowId) {
            logActivity("Didit API Config Missing");
            exit("Didit configuration missing.");
        }


$existing = Capsule::table('mod_didit_sessions')
    ->where('userid', $userid)
    ->whereIn('status', ['Not Started', 'In Progress'])
    ->orderBy('id', 'desc')
    ->first();

if ($existing) {

    logActivity(
        "Didit Existing Session Found: {$existing->session_id}"
    );

    // Remove old unfinished session
    Capsule::table('mod_didit_sessions')
        ->where('id', $existing->id)
        ->delete();
}




        $email = Capsule::table('tblclients')
            ->where('id', $userid)
            ->value('email');

        $endpoint = "https://verification.didit.me/v3/session/";

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
            logActivity("Didit CURL Error: " . curl_error($ch));
            curl_close($ch);
            exit("Unable to create verification session.");
        }

        curl_close($ch);

        logActivity("Didit API Response: " . $response);



        $result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {

    logActivity(
        "Didit Invalid JSON Response: " . $response
    );

    exit("Invalid API response.");
}

if (empty($result['session_id'])) {

    logActivity(
        "Didit Session Creation Failed: " .
        print_r($result, true)
    );

    exit("Verification session creation failed.");
}



        Capsule::table('mod_didit_sessions')->insert([
            'userid'        => $userid,
            'session_id'    => $result['session_id'],
            'session_token' => $result['session_token'] ?? null,
            'status'        => $result['status'] ?? 'Not Started',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        $redirectUrl =
            $result['url']
            ?? $result['verification_url']
            ?? $result['hosted_url']
            ?? $result['redirect_url']
            ?? null;

        if ($redirectUrl) {
            header("Location: " . $redirectUrl);
            exit;
        }

        logActivity(
            "Didit Redirect URL Missing: " .
            print_r($result, true)
        );

        exit("Verification URL missing.");
    }

    $session = Capsule::table('mod_didit_sessions')
        ->where('userid', $userid)
        ->orderBy('id', 'desc')
        ->first();

  
 return [
    'pagetitle'    => 'KYC Verification',
    'templatefile' => 'clientarea',
    'requirelogin' => true,
    'vars' => [
        'status' => $session->status ?? 'Not Started',
        'report_file' => $session->report_file ?? '',
        'verified_date' => !empty($session->verified_at)
            ? date('d M Y, h:i A', strtotime($session->verified_at))
            : ''
    ]
];
}