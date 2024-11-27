<?php
// Function to generate a random serial number
function generateSerialNumber() {
    return "SN" . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
}

// Function to generate a QR code URL using qrserver.com API
function generateQRCodeURL($data) {
    $url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($data) . "&size=200x200";
    return $url;
}

// Retrieve the name and event from the query parameter
$full_name = isset($_GET['name']) ? $_GET['name'] : 'User';
$event = isset($_GET['event']) ? $_GET['event'] : null;

// Generate a unique serial number
$serialNumber = generateSerialNumber();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <?php if ($event): ?>
        <!-- Display a personalized message if there's an activated event -->
        <div class="container text-center mt-5">
            <p class="h4">Hi, <?php echo $full_name; ?></p>
            <p class="h4">Welcome to <?php echo $event; ?></p>
            <!-- <p class="h4">Your Serial Number: <?php echo $serialNumber; ?></p>
            <img src="<?php echo generateQRCodeURL($serialNumber); ?>" alt="QR Code" class="img-fluid mt-3" style="max-width: 200px;"> -->
            <p class="h4 mt-3">Enjoy the show.</p>
            <a class="btn btn-outline-success mt-3" href="index.php">New Registration</a>
        </div>
    <?php else: ?>
        <!-- Display a generic thank you message if no event is activated -->
        <div class="container text-center mt-5">
            <p class="h4">Hi, <?php echo $full_name; ?></p>
            <!-- <p class="h4">Your Serial Number: <?php echo $serialNumber; ?></p>
            <img src="<?php echo generateQRCodeURL($serialNumber); ?>" alt="QR Code" class="img-fluid mt-3" style="max-width: 200px;"> -->
            <p class="h4 mt-3">Thank you for joining our mailing list!</p>
            <a class="btn btn-outline-success mt-3" href="index.php">New Registration</a>
        </div>
    <?php endif; ?>
</body>

<script>
        // Use setTimeout to redirect after 3 seconds
        setTimeout(function() {
            window.location.href = "index.php";
        }, 3000); // 3000 milliseconds = 3 seconds
    </script>

</html>
