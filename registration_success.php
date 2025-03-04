<?php
// Include your database connection
require_once "db.php"; // Include the database connection file

// Function to generate a random serial number
function generateSerialNumber() {
    return "SN" . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
}

// Function to generate a QR code URL using qrserver.com API
function generateQRCodeURL($data) {
    $url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($data) . "&size=200x200";
    return $url;
}

// Retrieve query parameters
$full_name = isset($_GET['name']) ? $_GET['name'] : 'User';
$event = isset($_GET['event']) ? $_GET['event'] : null;
$popupMessage = isset($_GET['message']) ? urldecode($_GET['message']) : null;
$email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : null;

// Generate a unique serial number
$serialNumber = generateSerialNumber();

// Check if the email is received via POST 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = strtolower(trim($_POST['email']));

    // Check if email is not empty
    if (empty($email)) {
        $successmessage = "Error: Email address is empty.";
        $status = "error";
    } else {
        // Update the unsubscribed status to 0
        $sqlUpdateSubscription = "UPDATE emails SET unsubscribed = 0 WHERE email = ?";
        $stmtUpdate = $conn->prepare($sqlUpdateSubscription);
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("s", $email);

            if ($stmtUpdate->execute()) {
                $successmessage = "Subscription successfully updated!";
                $status = "success";
            } else {
                // Check the error message from the database
                $successmessage = "Error: Could not update subscription. " . $stmtUpdate->error;
                $status = "error";
            }

            // Close the statement
            $stmtUpdate->close();
        } else {
            $successmessage = "Error: Could not prepare SQL query.";
            $status = "error";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Popup styles */
        .popup-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f5c6cb;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1000;
            transition: all 0.5s ease;
        }

        .popup-message.show {
            display: block;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                right: -300px;
                opacity: 0;
            }
            to {
                right: 20px;
                opacity: 1;
            }
        }

        /* Button Styling */
        .btn-subscribe {
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <!-- Display the popup message if it exists -->
    <?php if (isset($popupMessage) && !empty($popupMessage)): ?>
        <div id="popupMessage" class="popup-message show">
            <?php echo htmlspecialchars($popupMessage); ?>
            <?php if ($popupMessage == "You have unsubscribed from our mailing list! Do you want to subscribe to it again?"): ?>
                <form action="registration_success.php" method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" class="btn btn-success btn-subscribe">Subscribe Again</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Display the success message if it exists -->
    <?php if (isset($successmessage) && !empty($successmessage)): ?>
        <div id="successMessage" class="popup-message show">
            <?php echo htmlspecialchars($successmessage); ?>
        </div>
    <?php endif; ?>


    <?php if ($event): ?>
        <div class="container text-center mt-5">
            <p class="h4">Hi, <?php echo htmlspecialchars($full_name); ?></p>
            <p class="h4">Welcome to <?php echo htmlspecialchars($event); ?></p>
            <p class="h4 mt-3">Enjoy the show.</p>
            <a class="btn btn-outline-success mt-3" href="index.php">New Registration</a>
        </div>
    <?php else: ?>
        <div class="container text-center mt-5">
            <p class="h4">Hi, <?php echo htmlspecialchars($full_name); ?></p>
            <p class="h4 mt-3">Thank you for joining our mailing list!</p>
            <a class="btn btn-outline-success mt-3" href="index.php">New Registration</a>
        </div>
    <?php endif; ?>

</body>

<script>
    // Show the popup message if it exists
    <?php if ($popupMessage): ?>
        setTimeout(function() {
            document.getElementById("popupMessage").classList.add("show");
        }, 100);  // Delay to ensure the page has loaded
    <?php endif; ?>

    // Redirect after 3 seconds
    setTimeout(function() {
        window.location.href = "index.php";
    }, 3000);
</script>

</html>
