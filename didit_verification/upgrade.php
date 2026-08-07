<?php

require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

if (!Capsule::schema()->hasColumn('mod_didit_sessions', 'session_token')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->text('session_token')->nullable()->after('session_id');
    });
    echo "session_token column added successfully.";
} else {
    echo "session_token column already exists.";
}

if (!Capsule::schema()->hasColumn('mod_didit_sessions', 'session_url')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->string('session_url')->nullable()->after('status');
    });
    echo "session_url column added successfully.";
} else {
    echo "session_url column already exists.";
}

if (!Capsule::schema()->hasColumn('mod_didit_sessions', 'email')) {
    Capsule::schema()->table('mod_didit_sessions', function ($table) {
        $table->string('email')->nullable()->after('userid');
    });
    echo "email column added successfully.";
} else {
    echo "email column already exists.";
}