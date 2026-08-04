<h2>Complete Verification</h2>

<div id="didit-container"></div>

<script src="https://cdn.didit.me/web-sdk/latest/didit-sdk.js"></script>

<script>
const didit = new DiditSDK({
    sessionToken: "{$sessionToken}",
    container: "#didit-container",
    onComplete: function(result) {
        location.href = "clientarea.php";
    },
    onError: function(error) {
        console.error("Didit SDK Error", error);
    }
});

didit.render();
</script>