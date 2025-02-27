<?php 
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // If not logged in, redirect to login page
    header("Location: index.php?message=You should login first");
    exit();
}

// Include the necessary files
require_once "db.php";

$missingEmails = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Split emails by newlines, commas, or spaces (including multiple spaces)
    $emails = preg_split('/[\s,]+/', trim($_POST["emails"])); // Split by spaces and commas
    $emails = array_map('trim', $emails); // Trim any extra spaces

    foreach ($emails as $email) {
        // Sanitize the email to remove unwanted characters (except @, ., -, _)
        $sanitizedEmail = preg_replace("/[^a-zA-Z0-9@._-]/", "", $email);

        // Validate email format (basic validation)
        if (filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL)) {
            // Check if email exists in the database
            $sql = "SELECT email FROM emails WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $sanitizedEmail);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $missingEmails[] = $sanitizedEmail;
            }
            $stmt->close();
        } else {
            // If the email is invalid, ignore it
            continue;
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: auto;
        }
        textarea {
            width: 100%;
            height: 300px;
            font-size: 16px;
            padding: 10px;
        }
        button {
            margin-top: 10px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        .result {
            margin-top: 20px;
            font-size: 16px;
            color: black;
        }
    </style>
</head>
<body>
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">
    <h2>Email Checker</h2>
    <form action="" method="post">
            <label for="emails">Paste Emails Below:</label><br>
            <textarea name="emails" placeholder="Enter one email per line or separated by commas and spaces..." required></textarea><br>
            <button type="submit">Check</button>
        </form>
    
    <?php if (!empty($missingEmails)): ?>
        <div class="result">
            <h3>Emails not found in database:</h3>
            <pre><?php echo implode("\n", $missingEmails); ?></pre>
        </div>
    <?php endif; ?>
    </div>
    </div>

    <!-- Redirect Link to phonenumber.php -->
    <a href="phonenumber.php" class="btn btn-primary">Go to Phone Number Page</a>
</body>
</html>
