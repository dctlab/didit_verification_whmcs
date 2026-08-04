<?php

require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

if (!Capsule::schema()->hasColumn('mod_didit_sessions', 'session_token')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->text('session_token')->nullable()->after('session_id');
    });
    echo "session_token column added successfully.";
} else {
    echo "Column already exists.";
}