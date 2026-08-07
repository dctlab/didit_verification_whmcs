<?php

if (!defined("WHMCS")) {
    die("Access denied");
}

$_ADDONLANG['module_title']        = 'Didit KYC Verification';
$_ADDONLANG['dashboard_title']     = 'Didit Verification Dashboard';
$_ADDONLANG['client_page_title']   = 'KYC Verification';

$_ADDONLANG['status_not_started']  = 'Not Started';
$_ADDONLANG['status_in_progress']  = 'In Progress';
$_ADDONLANG['status_approved']     = 'Approved';
$_ADDONLANG['status_declined']     = 'Declined';

$_ADDONLANG['btn_start']           = 'Complete Verification';
$_ADDONLANG['btn_retry']           = 'Retry Verification';
$_ADDONLANG['btn_view_report']     = 'View KYC Report';
$_ADDONLANG['btn_download_pdf']    = 'Download PDF';
$_ADDONLANG['btn_generate_pdf']    = 'Generate PDF';
$_ADDONLANG['btn_save']            = 'Save';
$_ADDONLANG['btn_view_client']     = 'View Client';

$_ADDONLANG['column_client']            = 'Client';
$_ADDONLANG['column_email']             = 'Email';
$_ADDONLANG['column_status']            = 'Status';
$_ADDONLANG['column_session']           = 'Session';
$_ADDONLANG['column_date']              = 'Date';
$_ADDONLANG['column_report']            = 'Report';
$_ADDONLANG['column_profile']           = 'Profile';
$_ADDONLANG['column_disable_suspend']   = 'Disable Suspend';
$_ADDONLANG['column_disable_order']     = 'Disable Order Block';

$_ADDONLANG['label_verified_on']   = 'Verified On';
$_ADDONLANG['label_current_status'] = 'Current Status';
$_ADDONLANG['label_unmatched']     = 'Unmatched (stored userid: %s)';
$_ADDONLANG['label_no_email']      = '(no email recorded)';
$_ADDONLANG['label_no_client_match'] = 'No matching client';

$_ADDONLANG['no_records_found']    = 'No records found.';
$_ADDONLANG['error_generic']       = 'An error occurred. Please try again.';
$_ADDONLANG['error_config_missing'] = 'Didit configuration missing.';
$_ADDONLANG['error_session_failed'] = 'Verification session creation failed.';
$_ADDONLANG['error_redirect_missing'] = 'Verification URL missing.';

$_ADDONLANG['success_override_saved'] = 'Override updated successfully.';
$_ADDONLANG['success_kyc_required']   = 'KYC verification is required before placing an order. Please complete verification.';
