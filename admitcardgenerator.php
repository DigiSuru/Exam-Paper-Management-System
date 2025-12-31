<?php
// Set redirect URL and delay time
$redirect_url = "https://lightslategrey-guanaco-416702.hostingersite.com/sa/sa/index.php"; // change to your destination URL
$delay_seconds = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="<?php echo $delay_seconds; ?>;url=<?php echo $redirect_url; ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redirecting...</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        color: #333;
    }
    .loader {
        border: 6px solid #e0e0e0;
        border-top: 6px solid #007bff;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .message {
        font-size: 18px;
    }
    .countdown {
        font-weight: bold;
        color: #007bff;
    }
</style>
</head>
<body>
    <div class="loader"></div>
    <div class="message">
        Redirecting in <span class="countdown"><?php echo $delay_seconds; ?></span> seconds...
    </div>

    <script>
        let seconds = <?php echo $delay_seconds; ?>;
        const countdown = document.querySelector('.countdown');
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) clearInterval(timer);
        }, 1000);
    </script>
</body>
</html>
