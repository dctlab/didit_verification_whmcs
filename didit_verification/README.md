# Didit Verification WHMCS Module

## Overview

The **Didit Verification WHMCS Module** is a custom WHMCS addon that integrates the **Didit Identity Verification (KYC) API** to automate customer identity verification within the WHMCS ecosystem.

The module provides a complete end-to-end KYC workflow, from verification session creation to automatic service management, while keeping both clients and administrators informed throughout the verification process.

---

# Core Features

## Client KYC Verification

* Launch identity verification directly from the WHMCS Client Area.
* Secure integration with the Didit Identity Verification platform.
* Supports hosted verification flow without leaving the customer experience.

## Automatic Verification Session Creation

* Automatically creates a new Didit verification session for the client.
* Associates the verification session with the WHMCS client account.
* Stores session details for future tracking and reporting.
* Reuses an existing in-flight session rather than creating a duplicate if
  the client returns before finishing (see Technical Notes — this used to
  delete-and-recreate, which orphaned webhooks; it no longer does).

## Hosted Verification Page

* Redirects clients to the secure Didit-hosted verification page.
* Supports document verification, selfie verification, and workflow-based
  identity checks configured in Didit.
* Returns users to WHMCS after verification completion.

## Webhook Processing

* Receives real-time webhook notifications from Didit.
* Verifies Didit's HMAC-SHA256 signature before processing anything
  (see Technical Notes for the exact scheme).
* Processes verification events automatically.
* Logs all webhook requests for auditing and troubleshooting.
* Updates verification records without manual intervention.
* Self-heals: if a webhook arrives for a session with no local database
  row (e.g. a session created directly via Didit's console rather than
  through this module), the row is reconstructed from the webhook's own
  `vendor_data` and contact details rather than the update being dropped.

## Automatic Status Synchronization

Synchronizes verification status between Didit and WHMCS, including:

* Not Started
* In Progress
* Approved
* Declined

Status updates are reflected automatically in both the Client Area and the WHMCS Admin Area.

## Automatic Service Suspension & Unsuspension

Based on the verification result, the module can automatically:

**Approved**
* Unsuspend suspended hosting services
* Mark customer as verified
* Record verification timestamp

**Declined**
* Suspend active hosting services
* Prevent unauthorized service usage
* Automatically create a new verification session for retry

Administrative overrides are supported to disable automatic suspension or order blocking for selected clients (KYC Admin Overrides panel).

## Verification Report Download

* Downloads the official verification PDF report from Didit after approval.
* Stores reports securely **outside** the public web directory.
* Associates reports with the customer's verification record.
* Allows administrators and clients to view/download the report.

## Admin Dashboard Integration

Provides administrators with a dedicated KYC summary, including:

* Total / Approved / Declined / In Progress counts
* Searchable, filterable, paginated session list
* Session information, report access, and generate/download PDF actions
* A per-client KYC panel injected into the standard Admin Client Summary page

## Client Area Integration

Customers can manage their verification directly within WHMCS:

* Current KYC status
* Start / Retry Verification button
* Automatic status refresh (polls every 5 seconds)
* Verification completion triggers an automatic page reload

## Activity & Module Debug Logging

* WHMCS System Activity Log entries for key lifecycle events.
* Full WHMCS **Module Debug Log** integration (`Utilities → Logs → Module
  Log`, filterable by module) for every outbound Didit API call and every
  inbound webhook — with the API key automatically redacted from stored
  requests/responses.
* Verification session creation, webhook payloads, status changes,
  suspend/unsuspend actions, and PDF download events are all logged.

## Security

* Server-side session and API handling — the Didit API key never reaches
  the browser.
* Full HMAC-SHA256 webhook signature verification (all three of Didit's
  signature variants, with 5-minute replay protection).
* Secure PDF storage outside the public web directory.
* Session tracking and audit logging via the Module Debug Log.

---

## Automation Workflow

```text
Client Starts Verification
        │
        ▼
Create (or Reuse) Didit Verification Session
        │
        ▼
Redirect to Didit Hosted Verification
        │
        ▼
Customer Completes Identity Verification
        │
        ▼
Didit Sends Webhook (HMAC-signed)
        │
        ▼
Signature Verified → WHMCS Processes Verification Result
        │
        ├───────────────┐
        ▼               ▼
    Approved         Declined
        │               │
        ▼               ▼
Download PDF     Suspend Services
        │               │
        ▼               ▼
Unsuspend Services  Create Retry Session
        │
        ▼
Update Client & Admin Status
```

---

## Installation

1. Copy the `didit_verification` folder to `modules/addons/`.
2. In WHMCS Admin → **System Settings → Addon Modules**, find "Didit KYC
   Verification" and click **Activate**. This creates the module's
   database tables.
3. Configure **API Key**, **Workflow ID**, and **Webhook Secret** (from
   your Didit Business Console → API & Webhooks) in the module's settings
   page.
4. In the Didit Business Console, set the webhook destination URL to:
   `https://yourdomain.com/modules/addons/didit_verification/webhook.php`
5. Confirm **Setup → General Settings → General → "Enable Module Debug
   Log"** is turned on so the Module Log actually records entries.

---

## Technical Notes

Implementation detail for maintainers — not required reading to use the module.

**Client ID resolution.** `$_SESSION['uid']` is not reliably the WHMCS
`tblclients.id` — under WHMCS's unified user-account system it can be the
login (`tblusers`) ID of a linked contact/sub-account instead. All
client-facing code resolves the client ID via
`$_SESSION['ClientDetails']['userid']` (`didit_get_client_id()` in
`helpers.php`), which WHMCS itself populates correctly on every
client-area page load. A one-time repair script, `reconcile_client_ids.php`,
is included to fix any session rows stored with the wrong ID before this
was fixed.

**Webhook signature verification.** Implements Didit's documented spec
(`docs.didit.me/integration/webhooks`) — `X-Signature-V2` (canonical,
middleware-safe) is tried first, then raw-bytes `X-Signature`, then the
envelope-only `X-Signature-Simple` as a last resort. PHP's default JSON
handling does not reproduce Didit's canonical form; `helpers.php` decodes
as `stdClass` (not associative — an empty JSON object round-trips to `[]`
otherwise and breaks the digest), sorts keys with `SORT_STRING`, and
re-encodes with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`. Requests
older than 5 minutes or failing all three methods are rejected with a 401.

**Self-healing webhook.** If `webhook.php` receives a status update for a
`session_id` with no matching `mod_didit_sessions` row, it reconstructs
one from the webhook's own `vendor_data` (the WHMCS client ID echoed back
on every webhook) and `decision.contact_details.email`, rather than
dropping the update. This covers sessions created outside the module
(directly via Didit's console/API) as well as any future data-loss bug.

**Schema self-healing.** `didit_ensure_schema()` in `helpers.php` is the
single source of truth for the module's tables/columns and is called at
the top of every real entry point (admin panel, client area, webhook) —
new columns get added automatically on next page load after a deploy, no
manual reactivation or `upgrade.php` visit required. `didit_verification_upgrade()`
also runs it via WHMCS's standard version-bump mechanism.

**Admin visibility.** Dashboard queries use a `LEFT JOIN` to `tblclients`
rather than an inner join, so a session row is never silently hidden if
its `userid` doesn't resolve to a real client — it's shown highlighted
with the raw stored `userid` and session-recorded email instead, so a
data problem is visible rather than invisible.

**Language file.** `lang/english.php` is provided per WHMCS module
convention but is not yet wired into the UI strings (which are currently
inline in `didit_verification.php`/`templates/clientarea.tpl`) — a
follow-up if full localization is needed.
