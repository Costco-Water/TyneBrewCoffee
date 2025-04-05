<?php
http_response_code(405);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>405 Method Not Allowed - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="error-container">
        <div class="error-box">
            <h1>405 - Method Not Allowed</h1>
            <p>Sorry, the method you're trying to use is not allowed.</p>
            <p>Please try accessing this page correctly through the website navigation.</p>
            <a href="index.php" class="btn">Return to Home</a>
        </div>
    </div>
</body>
</html>