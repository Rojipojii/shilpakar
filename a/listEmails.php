<?php  
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

// Include the database connection file
require_once "db.php"; // Ensure this file initializes the $conn variable for database connection

// Query to select unique emails and their corresponding subscriber_id where hidden = 1
$query = "SELECT DISTINCT email, subscriber_id FROM emails WHERE hidden = 1";
$result = mysqli_query($conn, $query);

// Store the query result to display later
$emailsContent = '';

// Query to select subscribers without any email
$subscribersWithoutEmailQuery = "
    SELECT s.subscriber_id, s.full_name
    FROM subscribers s
    WHERE NOT EXISTS (
        SELECT 1 
        FROM emails e 
        WHERE e.subscriber_id = s.subscriber_id
    )";
$subscribersWithoutEmailQuery = mysqli_query($conn, $subscribersWithoutEmailQuery);

$subscribersWithoutEmailContent = '';


// Query to select subscribers without any email
$subscribersWithoutPhoneQuery = "
    SELECT s.subscriber_id, s.full_name
    FROM subscribers s
    WHERE NOT EXISTS (
        SELECT 1 
        FROM phone_numbers p 
        WHERE p.subscriber_id = s.subscriber_id
    )";
$subscribersWithoutPhoneQuery = mysqli_query($conn, $subscribersWithoutPhoneQuery);

$subscribersWithoutPhoneContent = '';

if ($result) {
    // Check if any rows were returned
    if (mysqli_num_rows($result) > 0) {
        $emailsContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>";

        // Fetch and display each row of the result
        while ($row = mysqli_fetch_assoc($result)) {
            $subscriberId = $row['subscriber_id']; // Fetch subscriber_id
            $email = htmlspecialchars($row['email']); // Fetch email and sanitize

            // Make the email itself a clickable link that redirects to the edit_subscriber.php page
    $emailsContent .= "<tr>
    <td><a href='edit_subscriber.php?id=$subscriberId' style='text-decoration: underline; color: inherit;'
     onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
     onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$email</a></td>
  </tr>";
        }

        $emailsContent .= "</tbody></table>";
    } else {
        $emailsContent = "No emails found with hidden = 1.";
    }
} else {
    $emailsContent = "Error executing query: " . mysqli_error($conn);
}

// Counter for subscribers without emails
$subscribersWithoutEmailCount = 0;

// Subscribers without email content
if ($subscribersWithoutEmailQuery) {
    if (mysqli_num_rows($subscribersWithoutEmailQuery) > 0) {
        $subscribersWithoutEmailContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>";

        // Fetch and display each subscriber who doesn't have an email
        while ($row = mysqli_fetch_assoc($subscribersWithoutEmailQuery)) {
            $subscriberId = $row['subscriber_id']; // Fetch subscriber_id
            $fullname = htmlspecialchars($row['full_name']); // Fetch fullname and sanitize

            $subscribersWithoutEmailContent .= "<tr>
            <td><a href='edit_subscriber.php?id=$subscriberId'
             style='text-decoration: underline; color: inherit;'
             onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
             onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$fullname</a></td>
          </tr>";
          // Increment the counter
          $subscribersWithoutEmailCount++;
        }

        $subscribersWithoutEmailContent .= "</tbody></table>";

         // Append the total count to the content
         $subscribersWithoutEmailContent .= "<p><strong>Total Subscribers Without Emails: $subscribersWithoutEmailCount</strong></p>";
    } else {
        $subscribersWithoutEmailContent = "No subscribers found without an email.";
    }
} else {
    $subscribersWithoutEmailContent = "Error executing query: " . mysqli_error($conn);
}

// Counter for subscribers without emails
$subscribersWithoutPhoneCount = 0;

// Subscribers without email content
if ($subscribersWithoutPhoneQuery) {
    if (mysqli_num_rows($subscribersWithoutPhoneQuery) > 0) {
        $subscribersWithoutPhoneContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>";

        // Fetch and display each subscriber who doesn't have an email
        while ($row = mysqli_fetch_assoc($subscribersWithoutPhoneQuery)) {
            $subscriberId = $row['subscriber_id']; // Fetch subscriber_id
            $fullname = htmlspecialchars($row['full_name']); // Fetch fullname and sanitize

            $subscribersWithoutPhoneContent .= "<tr>
            <td><a href='edit_subscriber.php?id=$subscriberId'
             style='text-decoration: underline; color: inherit;'
             onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
             onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$fullname</a></td>
          </tr>";
          // Increment the counter
          $subscribersWithoutPhoneCount++;
        }

        $subscribersWithoutPhoneContent .= "</tbody></table>";

         // Append the total count to the content
         $subscribersWithoutPhoneContent .= "<p><strong>Total Subscribers Without Phone Numbers: $subscribersWithoutPhoneCount</strong></p>";
    } else {
        $subscribersWithoutPhoneContent = "No subscribers found without phone number.";
    }
} else {
    $subscribersWithoutphoneContent = "Error executing query: " . mysqli_error($conn);
}

// Close the database connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Emails and Subscribers Without Email</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body>
    <?php include("header.php"); ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-4">
                        <h2>Full Emails</h2>
                        <?php echo $emailsContent; ?>
                    </div>

                    <!-- Display the subscribers without emails section -->
                    <div class="col-md-4">
                        <h2>Subscribers Without Emails (<?= ($subscribersWithoutEmailCount); ?>)</h2>
                        <?php echo $subscribersWithoutEmailContent; ?>
                    </div>
                     <!-- Display the subscribers without emails section -->
                     <div class="col-md-4">
                        <h2>Subscribers Without Phone Numbers (<?= ($subscribersWithoutPhoneCount); ?>)</h2>
                        <?php echo $subscribersWithoutPhoneContent; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS (optional) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
