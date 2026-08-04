/modules/addons/didit_verification/
│
├── didit_verification.php
├── helpers.php
├── webhook.php
├── hooks.php
├── clientarea.php
│
├── /templates/
│   └── clientarea.tpl
│
└── /attachments/kyc_reports/   (create manually)


6️⃣ templates/clientarea.tpl
<h2>KYC Status: {$status}</h2>

{if $status == "Declined"}
<form method="post">
    <button class="btn btn-primary">Retry Verification</button>
</form>
{/if}
7️⃣ Lagom 2 CSS

Add to:

/templates/lagom2/assets/css/custom.css
.kyc-badge {
    padding:4px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.kyc-badge.verified { background:#28a745; color:#fff; }
.kyc-badge.rejected { background:#dc3545; color:#fff; }
.kyc-badge.pending { background:#ffc107; color:#000; }

In Lagom navbar add:

{$kyc_badge nofilter}

Then clear template cache.

🚀 YOU NOW HAVE

✔ Enterprise KYC Engine
✔ PDF Storage
✔ Auto Suspend / Unsuspend
✔ Lagom 2 Badge
✔ Webhook Security
✔ Production Structure