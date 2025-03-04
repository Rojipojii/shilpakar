<?php
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower(trim($_POST["email"]));

    // Check if the email exists in the database
    $sqlCheck = "SELECT unsubscribed FROM emails WHERE email = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("s", $email);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if ($row['unsubscribed'] == 1) {
            // Email is already unsubscribed
            $message = "Your email has already been unsubscribed.";
        } else {
            // Update to mark as unsubscribed
            $sqlUnsubscribe = "UPDATE emails SET unsubscribed = 1 WHERE email = ?";
            $stmtUnsubscribe = $conn->prepare($sqlUnsubscribe);
            $stmtUnsubscribe->bind_param("s", $email);
            $stmtUnsubscribe->execute();
            $message = "Your email has been unsubscribed.";
        }
    } else {
        $message = "Your email doesn't exist in our mailing list.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .btn-hover-normal:hover {
            background-color: transparent !important;
            color: red !important;
            border: 1px solid red !important;
        }

        .input-margin {
            margin-right: 15px; /* Adjust the margin as needed */
        }

        .button-margin {
            margin-right: 15px; /* Ensure the button has the same margin */
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2>Unsubscribe from Mailing List</h2><br>
            <?php if (isset($message)): ?>
                <div class="alert alert-info text-center"> <?php echo $message; ?> </div>
            <?php endif; ?>
            <form action="" method="post">
                <div class="mb-3">
                    <label for="email" class="form-label">Enter your email:</label>
                    <input type="email" id="email" name="email" class="form-control input-margin" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-danger btn-hover-normal button-margin">Unsubscribe</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
