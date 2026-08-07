<div class="didit-kyc-wrap">

<style>
{literal}
.didit-kyc-wrap { max-width: 720px; margin: 0 auto; }
.didit-kyc-wrap * { box-sizing: border-box; }
.didit-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
    padding: 24px;
    margin-bottom: 20px;
}
.didit-status-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.didit-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 14px;
}
.didit-status-not-started { background: #f1f3f5; color: #495057; }
.didit-status-in-progress { background: #fff3cd; color: #8a6d3b; }
.didit-status-in-review { background: #d1ecf1; color: #0c5460; }
.didit-status-approved { background: #d4edda; color: #256029; }
.didit-status-declined { background: #f8d7da; color: #a94442; }
.didit-verified-date { color: #6c757d; font-size: 13px; margin-top: 4px; }

.didit-timeline {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 8px 0 24px;
}
.didit-timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.didit-timeline-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    background: #f1f3f5;
    color: #adb5bd;
    border: 2px solid #e9ecef;
    transition: all .2s ease;
}
.didit-timeline-circle.active { background: #2c5cc5; color: #fff; border-color: #2c5cc5; }
.didit-timeline-circle.done { background: #2c5cc5; color: #fff; border-color: #2c5cc5; }
.didit-timeline-label { font-size: 12px; color: #868e96; }
.didit-timeline-label.active { color: #2c5cc5; font-weight: 600; }
.didit-timeline-line {
    width: 70px;
    height: 2px;
    background: #e9ecef;
    margin: 0 8px 22px;
}
.didit-timeline-line.done { background: #2c5cc5; }

.didit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 13px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    text-decoration: none;
    transition: opacity .15s ease;
}
.didit-btn:hover { opacity: .92; text-decoration: none; }
.didit-btn-primary { background: #2c5cc5; color: #fff; }
.didit-btn-danger { background: #c0392b; color: #fff; }
.didit-btn-success { background: #2e8b57; color: #fff; }
.didit-btn-outline { background: #fff; color: #2c5cc5; border: 1px solid #2c5cc5; }
.didit-hint { text-align: center; font-size: 12px; color: #adb5bd; margin-top: 10px; }

.didit-iframe-wrap { display: none; margin-top: 16px; }
.didit-iframe-wrap #diditEmbedContainer { height: 700px; overflow: hidden; border-radius: 10px; }

.didit-type-choice { margin-bottom: 20px; }
.didit-type-choice-label {
    display: block; font-weight: 600; font-size: 14px; color: #495057; margin-bottom: 10px;
}
.didit-type-options { display: flex; gap: 12px; }
.didit-type-option {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 16px; border: 2px solid #e9ecef; border-radius: 10px;
    font-weight: 600; font-size: 15px; color: #495057; cursor: pointer;
    transition: all .15s ease;
}
.didit-type-option:hover { border-color: #c7d5f2; background: #f8f9fc; }
.didit-type-option.active { border-color: #2c5cc5; background: #eaf1fb; color: #2c5cc5; }
.didit-type-option input[type="radio"] { width: 18px; height: 18px; accent-color: #2c5cc5; margin: 0; }

.didit-loading {
    display: none;
    text-align: center;
    padding: 40px 0;
    color: #868e96;
}
.didit-spinner {
    width: 32px; height: 32px;
    border: 3px solid #e9ecef;
    border-top-color: #2c5cc5;
    border-radius: 50%;
    margin: 0 auto 12px;
    animation: didit-spin 0.8s linear infinite;
}
@keyframes didit-spin { to { transform: rotate(360deg); } }

.didit-history-toggle {
    background: none; border: none; color: #2c5cc5; font-size: 13px;
    cursor: pointer; padding: 0; margin-top: 4px;
}
.didit-history-list { display: none; margin-top: 14px; border-top: 1px solid #f1f3f5; padding-top: 14px; }
.didit-history-item { display: flex; gap: 12px; font-size: 13px; padding: 6px 0; }
.didit-history-date { color: #adb5bd; min-width: 140px; }
.didit-history-label { color: #495057; }
{/literal}
</style>

<h2 style="margin-bottom:20px">{$friendly_name|default:"KYC Verification"}</h2>

<div class="didit-card">
    <button class="didit-history-toggle" id="diditHelpToggle">Hide "what to expect" ▴</button>
    <div id="diditHelpContent" style="display:block;margin-top:14px">

    {if $has_company_name}
    <p style="font-weight:600;margin-bottom:6px;color:#495057">Business (KYB) or Individual (KYC)?</p>
    <p style="font-size:13px;color:#6c757d;margin-bottom:16px">
        Choose <strong>Business</strong> if you're verifying on behalf of your company — this may ask for company registration details in addition to your own ID.
        Choose <strong>Individual</strong> if you're simply verifying your own identity as a person. If you're not sure, Individual is usually the right choice.
    </p>
    {/if}

    <p style="font-weight:600;margin-bottom:6px;color:#495057">The three steps</p>
    <ul style="font-size:13px;color:#6c757d;padding-left:18px;margin-bottom:16px">
        <li style="margin-bottom:6px"><strong>Initiate</strong> — confirm your verification type (if shown) and click Proceed.</li>
        <li style="margin-bottom:6px"><strong>Processing</strong> — we create your secure verification session with Didit, our identity verification partner. This takes a few seconds.</li>
        <li><strong>Verification</strong> — complete the verification directly on this page: scan your ID document and take a quick selfie for a face match. Usually takes under two minutes.</li>
    </ul>

    <p style="font-weight:600;margin-bottom:6px;color:#495057">What you'll need</p>
    <ul style="font-size:13px;color:#6c757d;padding-left:18px;margin-bottom:16px">
        <li style="margin-bottom:6px">A valid government-issued photo ID (passport, national ID, or driver's license).</li>
        <li>A device with a camera, for a live selfie — you can scan a QR code partway through to continue on your phone if you started on a computer.</li>
    </ul>

    <p style="font-size:13px;color:#6c757d">
        Your documents are processed securely and are not stored on our servers directly — they're handled by Didit under encryption, and only the verification result is shared back with us.
    </p>
    </div>
</div>

<div class="didit-card didit-status-card" id="diditStatusCard">
    <div>
        <span id="diditStatusBadge" class="didit-status-badge didit-status-{$status|lower|replace:' ':'-'}">
            {$status|default:"Not Started"}
        </span>
        {if $status eq "Approved" && $verified_date}
            <div class="didit-verified-date" id="diditVerifiedDate">Verified on {$verified_date}</div>
        {/if}
    </div>
    <div>
        {if $status eq "Approved" && $report_file}
            <a href="view_kyc.php?file={$report_file}" target="_blank" id="diditReportBtn" class="didit-btn didit-btn-outline" style="width:auto;padding:8px 16px">📄 View Report</a>
        {/if}
    </div>
</div>

<div class="didit-card">

    <div class="didit-timeline" id="diditTimeline">
        <div class="didit-timeline-step">
            <div class="didit-timeline-circle" id="diditStep1">1</div>
            <div class="didit-timeline-label" id="diditStep1Label">Initiate</div>
        </div>
        <div class="didit-timeline-line" id="diditLine1"></div>
        <div class="didit-timeline-step">
            <div class="didit-timeline-circle" id="diditStep2">2</div>
            <div class="didit-timeline-label" id="diditStep2Label">Processing</div>
        </div>
        <div class="didit-timeline-line" id="diditLine2"></div>
        <div class="didit-timeline-step">
            <div class="didit-timeline-circle" id="diditStep3">3</div>
            <div class="didit-timeline-label" id="diditStep3Label">Verification</div>
        </div>
    </div>

    <div id="diditActionArea">
        {if $has_company_name && $status neq "Approved" && $status neq "In Review"}
            <div class="didit-type-choice">
                <label class="didit-type-choice-label">Verifying as:</label>
                <div class="didit-type-options">
                    <label class="didit-type-option" id="diditTypeOptionKyb">
                        <input type="radio" name="diditVerificationType" value="kyb"> Business (KYB)
                    </label>
                    <label class="didit-type-option active" id="diditTypeOptionKyc">
                        <input type="radio" name="diditVerificationType" value="kyc" checked> Individual (KYC)
                    </label>
                </div>
            </div>
        {/if}
        {if $status eq "Approved"}
            <p style="text-align:center;color:#495057">Your identity has been verified.</p>
        {elseif $status eq "In Review"}
            <p style="text-align:center;color:#495057">Your verification is being reviewed by our team. We'll notify you once a decision is made — no action is needed from you right now.</p>
        {elseif $status eq "Declined"}
            <button class="didit-btn didit-btn-danger" id="diditActionBtn">↻ Retry Verification</button>
        {elseif $status eq "In Progress" && $session_url}
            <button class="didit-btn didit-btn-primary" id="diditActionBtn">Resume Verification →</button>
        {else}
            <button class="didit-btn didit-btn-primary" id="diditActionBtn">Proceed with Verification →</button>
        {/if}
        <p class="didit-hint">🔒 Your data is encrypted and processed securely. Document images are not stored on our servers.</p>
    </div>

    <div class="didit-loading" id="diditLoading">
        <div class="didit-spinner"></div>
        Preparing your verification session…
    </div>

    <div class="didit-iframe-wrap" id="diditIframeWrap">
        <div id="diditEmbedContainer"></div>
    </div>

</div>

<div class="didit-card">
    <button class="didit-history-toggle" id="diditHistoryToggle">Show status history ▾</button>
    <div class="didit-history-list" id="diditHistoryList">
        <p style="color:#adb5bd;font-size:13px">Loading…</p>
    </div>
</div>

</div>

<script src="https://unpkg.com/@didit-protocol/sdk-web/dist/didit-sdk.umd.min.js"></script>
<script>
{literal}
(function () {

    var currentStatus = {/literal}"{$status|default:'Not Started'}"{literal};
    var currentSessionUrl = {/literal}"{$session_url|default:''}"{literal};
    var hasCompanyName = {/literal}{if $has_company_name}true{else}false{/if}{literal};
    var pollTimer = null;

    function typeChoiceHtml() {
        if (!hasCompanyName) {
            return '';
        }
        return '<div class="didit-type-choice">' +
            '<label class="didit-type-choice-label">Verifying as:</label>' +
            '<div class="didit-type-options">' +
            '<label class="didit-type-option" id="diditTypeOptionKyb"><input type="radio" name="diditVerificationType" value="kyb"> Business (KYB)</label>' +
            '<label class="didit-type-option active" id="diditTypeOptionKyc"><input type="radio" name="diditVerificationType" value="kyc" checked> Individual (KYC)</label>' +
            '</div></div>';
    }

    function getSelectedVerificationType() {
        var checked = document.querySelector('input[name="diditVerificationType"]:checked');
        return checked ? checked.value : null;
    }

    function bindTypeChoiceToggle() {
        var options = document.querySelectorAll('.didit-type-option');
        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                options.forEach(function (o) { o.classList.remove('active'); });
                opt.classList.add('active');
            });
        });
    }

    function statusBadgeClass(status) {
        if (status === 'Approved') return 'didit-status-approved';
        if (status === 'Declined') return 'didit-status-declined';
        if (status === 'In Progress') return 'didit-status-in-progress';
        if (status === 'In Review') return 'didit-status-in-review';
        return 'didit-status-not-started';
    }

    function updateTimeline(status, showingIframe) {
        var step1 = document.getElementById('diditStep1');
        var step2 = document.getElementById('diditStep2');
        var step3 = document.getElementById('diditStep3');
        var line1 = document.getElementById('diditLine1');
        var line2 = document.getElementById('diditLine2');
        var label1 = document.getElementById('diditStep1Label');
        var label2 = document.getElementById('diditStep2Label');
        var label3 = document.getElementById('diditStep3Label');

        [step1, step2, step3].forEach(function (el) { el.className = 'didit-timeline-circle'; });
        [label1, label2, label3].forEach(function (el) { el.className = 'didit-timeline-label'; });
        line1.className = 'didit-timeline-line';
        line2.className = 'didit-timeline-line';

        if (status === 'Not Started') {
            step1.className += ' active';
            label1.className += ' active';
        } else if (status === 'In Progress' && !showingIframe) {
            step1.className += ' done';
            line1.className += ' done';
            step2.className += ' active';
            label2.className += ' active';
        } else if (status === 'In Progress' && showingIframe) {
            step1.className += ' done';
            line1.className += ' done';
            step2.className += ' done';
            line2.className += ' done';
            step3.className += ' active';
            label3.className += ' active';
        } else {
            // Approved or Declined — everything complete
            step1.className += ' done';
            line1.className += ' done';
            step2.className += ' done';
            line2.className += ' done';
            step3.className += ' done';
        }
    }

    function renderActionButton(status) {
        var area = document.getElementById('diditActionArea');

        if (status === 'Approved') {
            area.innerHTML = '<p style="text-align:center;color:#495057">Your identity has been verified.</p>';
            return;
        }

        if (status === 'In Review') {
            area.innerHTML = '<p style="text-align:center;color:#495057">Your verification is being reviewed by our team. We\'ll notify you once a decision is made — no action is needed from you right now.</p>';
            return;
        }

        var label = 'Proceed with Verification →';
        var cls = 'didit-btn-primary';

        if (status === 'Declined') {
            label = '↻ Retry Verification';
            cls = 'didit-btn-danger';
        } else if (status === 'In Progress' && currentSessionUrl) {
            label = 'Resume Verification →';
        }

        area.innerHTML = typeChoiceHtml() +
            '<button class="didit-btn ' + cls + '" id="diditActionBtn">' + label + '</button>' +
            '<p class="didit-hint">🔒 Your data is encrypted and processed securely. Document images are not stored on our servers.</p>';

        bindTypeChoiceToggle();

        document.getElementById('diditActionBtn').addEventListener('click', startVerification);
    }

    function showEmbeddedVerification(url) {
        document.getElementById('diditLoading').style.display = 'none';
        document.getElementById('diditActionArea').style.display = 'none';
        document.getElementById('diditIframeWrap').style.display = 'block';
        updateTimeline('In Progress', true);

        // Didit's own sanctioned embed method — a plain <iframe src="..">
        // pointed at the same URL gets frame-busted by Didit's app itself
        // (anti-clickjacking behavior on identity-verification pages), so
        // this SDK is required rather than optional polish.
        DiditSdk.shared.startVerification({
            url: url,
            configuration: {
                embedded: true,
                embeddedContainerId: 'diditEmbedContainer'
            }
        });
    }

    function startVerification() {
        var selectedType = getSelectedVerificationType();

        document.getElementById('diditActionArea').style.display = 'none';
        document.getElementById('diditLoading').style.display = 'block';
        updateTimeline('In Progress', false);

        var url = 'index.php?m=didit_verification&action=start&ajax=1';
        if (selectedType) {
            url += '&type=' + encodeURIComponent(selectedType);
        }

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.session_url) {
                    currentSessionUrl = data.session_url;
                    showEmbeddedVerification(data.session_url);
                } else {
                    document.getElementById('diditLoading').innerHTML =
                        '<p style="color:#c0392b">' + (data.error || 'Something went wrong. Please try again.') + '</p>';
                }
            })
            .catch(function () {
                document.getElementById('diditLoading').innerHTML =
                    '<p style="color:#c0392b">Something went wrong. Please try again.</p>';
            });
    }

    function applyStatus(data) {
        var badge = document.getElementById('diditStatusBadge');
        badge.className = 'didit-status-badge ' + statusBadgeClass(data.status);
        badge.innerText = data.status;

        currentStatus = data.status;

        if (data.status === 'Approved') {

            document.getElementById('diditIframeWrap').style.display = 'none';
            document.getElementById('diditLoading').style.display = 'none';
            document.getElementById('diditActionArea').style.display = 'block';
            updateTimeline('Approved', false);
            renderActionButton('Approved');

            if (data.verified_date && !document.getElementById('diditVerifiedDate')) {
                var d = document.createElement('div');
                d.className = 'didit-verified-date';
                d.id = 'diditVerifiedDate';
                d.innerText = 'Verified on ' + data.verified_date;
                badge.parentNode.appendChild(d);
            }

            if (data.report_file) {
                var existing = document.getElementById('diditReportBtn');
                if (!existing) {
                    var btn = document.createElement('a');
                    btn.href = 'view_kyc.php?file=' + encodeURIComponent(data.report_file);
                    btn.target = '_blank';
                    btn.id = 'diditReportBtn';
                    btn.className = 'didit-btn didit-btn-outline';
                    btn.style.cssText = 'width:auto;padding:8px 16px';
                    btn.innerText = '📄 View Report';
                    document.getElementById('diditStatusCard').children[1].appendChild(btn);
                }
            }

            stopPolling();

        } else if (data.status === 'Declined') {

            document.getElementById('diditIframeWrap').style.display = 'none';
            document.getElementById('diditLoading').style.display = 'none';
            document.getElementById('diditActionArea').style.display = 'block';
            updateTimeline('Declined', false);
            renderActionButton('Declined');
            stopPolling();

        } else if (data.status === 'In Review') {

            document.getElementById('diditIframeWrap').style.display = 'none';
            document.getElementById('diditLoading').style.display = 'none';
            document.getElementById('diditActionArea').style.display = 'block';
            updateTimeline('In Review', false);
            renderActionButton('In Review');
            // Polling keeps running — In Review can still resolve to
            // Approved or Declined, unlike those two terminal states.
        }
    }

    function poll() {
        fetch('index.php?m=didit_verification&ajax=status')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status && data.status !== currentStatus) {
                    applyStatus(data);
                }
            })
            .catch(function () { /* transient network error — try again next tick */ });
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // Official SDK callbacks — replaces guessing at a raw postMessage
    // shape, which isn't publicly documented and isn't guaranteed stable.
    if (window.DiditSDK) {

        var DiditSdk = window.DiditSDK.DiditSdk;

        DiditSdk.shared.onComplete = function (result) {

            if (result.type === 'completed') {
                poll();
            } else if (result.type === 'cancelled') {
                // User backed out — leave them on the current state with
                // the action button available again rather than stuck on
                // the embed container.
                document.getElementById('diditIframeWrap').style.display = 'none';
                document.getElementById('diditActionArea').style.display = 'block';
                updateTimeline(currentStatus, false);
            } else if (result.type === 'failed') {
                document.getElementById('diditIframeWrap').innerHTML =
                    '<p style="color:#c0392b;text-align:center">' +
                    (result.error && result.error.message ? result.error.message : 'Verification failed. Please try again.') +
                    '</p>';
            }
        };

        DiditSdk.shared.onStateChange = function (state, error) {

            if (state === 'error') {
                document.getElementById('diditLoading').style.display = 'none';
                document.getElementById('diditActionArea').style.display = 'block';
                updateTimeline(currentStatus, false);
            }
        };
    }

    var initialBtn = document.getElementById('diditActionBtn');
    if (initialBtn) {
        initialBtn.addEventListener('click', startVerification);
    }

    bindTypeChoiceToggle();

    document.getElementById('diditHelpToggle').addEventListener('click', function () {
        var content = document.getElementById('diditHelpContent');
        var isOpen = content.style.display === 'block';
        content.style.display = isOpen ? 'none' : 'block';
        this.innerText = isOpen ? 'Show "what to expect" ▾' : 'Hide "what to expect" ▴';
    });

    document.getElementById('diditHistoryToggle').addEventListener('click', function () {
        var list = document.getElementById('diditHistoryList');
        var isOpen = list.style.display === 'block';
        list.style.display = isOpen ? 'none' : 'block';
        this.innerText = isOpen ? 'Show status history ▾' : 'Hide status history ▴';

        if (!isOpen && !list.dataset.loaded) {
            fetch('index.php?m=didit_verification&ajax=history')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    list.dataset.loaded = '1';
                    if (!data.events || data.events.length === 0) {
                        list.innerHTML = '<p style="color:#adb5bd;font-size:13px">No history yet.</p>';
                        return;
                    }
                    list.innerHTML = data.events.map(function (ev) {
                        return '<div class="didit-history-item"><span class="didit-history-date">' + ev.date + '</span><span class="didit-history-label">' + ev.label + '</span></div>';
                    }).join('');
                })
                .catch(function () {
                    list.innerHTML = '<p style="color:#c0392b;font-size:13px">Could not load history.</p>';
                });
        }
    });

    updateTimeline(currentStatus, currentStatus === 'In Progress' && !!currentSessionUrl && false);

    if (currentStatus !== 'Approved' && currentStatus !== 'Declined') {
        pollTimer = setInterval(poll, 5000);
    }

})();
{/literal}
</script>
