<h2>KYC Verification</h2>

<div id="statusBox" class="alert text-center
    {if $status eq 'Approved'}alert-success
    {elseif $status eq 'Declined' || $status eq 'Rejected'}alert-danger
    {else}alert-warning{/if}">
    
    <h4>
        Current Status:
        <strong id="kycStatusText">{$status|default:"Pending"}</strong>
    </h4>

    {if $status eq "Approved" && $verified_date}
        <p id="verifiedDate" class="text-muted mb-0">
            Verified on: {$verified_date}
        </p>
    {/if}

</div>

<div class="text-center mt-3" id="kycActions">

    {if $status eq "Approved"}

        {if $report_file}
            <a href="view_kyc.php?file={$report_file}"
               target="_blank"
               class="btn btn-success btn-lg">
               📄 View KYC Report
            </a>
        {/if}

    {elseif $status eq "Declined" || $status eq "Rejected"}

        <a href="{$systemurl}index.php?m=didit_verification&action=start"
           class="btn btn-danger btn-lg">
           Retry Verification
        </a>

    {else}

        <a href="{$systemurl}index.php?m=didit_verification&action=start"
           class="btn btn-primary btn-lg">
           Complete Verification
        </a>

    {/if}

</div>

<script>
setInterval(function(){

    fetch("index.php?m=didit_verification&action=status")
    .then(res => res.json())
    .then(data => {

        if(data.status){

            let box = document.getElementById("statusBox");
            let statusText = document.getElementById("kycStatusText");
            let actions = document.getElementById("kycActions");

            let alertClass = "alert-warning";

            if(data.status === "Approved") alertClass = "alert-success";
            if(data.status === "Declined" || data.status === "Rejected") alertClass = "alert-danger";

            box.className = "alert text-center " + alertClass;
            statusText.innerText = data.status;

            // Reset actions
            actions.innerHTML = "";

            // Approved
            if(data.status === "Approved") {

                // Optional: reload page to get report + date
                location.reload();

            }

            // Declined
            else if(data.status === "Declined" || data.status === "Rejected") {

                actions.innerHTML = `
                    <a href="index.php?m=didit_verification&action=start"
                       class="btn btn-danger btn-lg">
                       Retry Verification
                    </a>
                `;
            }

            // Not Started / In Progress
            else {

                actions.innerHTML = `
                    <a href="index.php?m=didit_verification&action=start"
                       class="btn btn-primary btn-lg">
                       Complete Verification
                    </a>
                `;
            }

        }

    });

}, 5000);
</script>