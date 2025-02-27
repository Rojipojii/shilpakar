<?php  
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}
// After form submission or action:
    $_SESSION['from_list_emails'] = true;

// Include the database connection file
require_once "db.php"; // Ensure this file initializes the $conn variable for database connection

// Query to select unique emails and their corresponding subscriber_id where hidden = 1
$mailboxFullQuery = "SELECT DISTINCT email, subscriber_id FROM emails WHERE hidden = 1 ORDER BY email ASC";
$mailboxFullResult = mysqli_query($conn, $mailboxFullQuery);

// Store the query result to display later
$mailboxFullContent = '';

// Query to select subscribers without any email
$subscribersWithoutEmailQuery = "
    SELECT s.subscriber_id, s.full_name, p.phone_number
    FROM subscribers s
    LEFT JOIN phone_numbers p ON p.subscriber_id = s.subscriber_id
    WHERE NOT EXISTS (
        SELECT 1 
        FROM emails e 
        WHERE e.subscriber_id = s.subscriber_id
    )
    ORDER BY s.full_name ASC";
$subscribersWithoutEmailQuery = mysqli_query($conn, $subscribersWithoutEmailQuery);

$subscribersWithoutEmailContent = '';

// Query to select subscribers without any phone number
$subscribersWithoutPhoneQuery = "
    SELECT s.subscriber_id, s.full_name, e.email
    FROM subscribers s
    LEFT JOIN emails e ON e.subscriber_id = s.subscriber_id
    WHERE NOT EXISTS (
        SELECT 1 
        FROM phone_numbers p 
        WHERE p.subscriber_id = s.subscriber_id
    )
     ORDER BY s.full_name ASC, e.email ASC";
$subscribersWithoutPhoneQuery = mysqli_query($conn, $subscribersWithoutPhoneQuery);

$subscribersWithoutPhoneContent = '';

$mailboxFullCount = 0;
if ($mailboxFullResult) {
    if (mysqli_num_rows($mailboxFullResult) > 0) {
        $mailboxFullContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>";

        while ($row = mysqli_fetch_assoc($mailboxFullResult)) {
            $subscriberId = $row['subscriber_id'];
            $email = htmlspecialchars($row['email']);

            $mailboxFullContent .= "<tr>
                <td><a href='edit_subscriber.php?id=$subscriberId'
                style='text-decoration: underline; color: inherit;'
     onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
     onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$email</a></td>
              </tr>";

            $mailboxFullCount++;
        }

        $mailboxFullContent .= "</tbody></table>";
        // $mailboxFullContent .= "<p><strong>Total Subscribers with Full Mailbox: $mailboxFullCount</strong></p>";
    } else {
        $mailboxFullContent = "No subscribers found with a full mailbox.";
    }
} else {
    $mailboxFullContent = "Error executing query: " . mysqli_error($conn);
}

// Subscribers without email content
$subscribersWithoutEmailCount = 0;
if ($subscribersWithoutEmailQuery) {
    if (mysqli_num_rows($subscribersWithoutEmailQuery) > 0) {
        $subscribersWithoutEmailContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>";

        // For Subscribers Without Email
while ($row = mysqli_fetch_assoc($subscribersWithoutEmailQuery)) {
    $subscriberId = $row['subscriber_id']; // Fetch subscriber_id
    $fullname = htmlspecialchars($row['full_name']); // Fetch fullname and sanitize

    // Check if full_name exists
    if ($fullname) {
        $displayName = $fullname;
    } else {
        // If full_name doesn't exist, check if phone_number exists
        $phoneQuery = "SELECT phone_number FROM phone_numbers WHERE subscriber_id = $subscriberId LIMIT 1";
        $phoneResult = mysqli_query($conn, $phoneQuery);
        $phoneNumber = mysqli_fetch_assoc($phoneResult)['phone_number'];

        // If phone_number exists, display it
        if ($phoneNumber) {
            $displayName = $phoneNumber;
        } else {
            // If neither full_name nor phone_number exist, display subscriber_id
            $displayName = $subscriberId;
        }
    }

    $subscribersWithoutEmailContent .= "<tr>
    <td><a href='edit_subscriber.php?id=$subscriberId'
     style='text-decoration: underline; color: inherit;'
     onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
     onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$displayName</a></td>
  </tr>";
    // Increment the counter
    $subscribersWithoutEmailCount++;
}
        $subscribersWithoutEmailContent .= "</tbody></table>";

         // Append the total count to the content
        //  $subscribersWithoutEmailContent .= "<p><strong>Total Subscribers Without Emails: $subscribersWithoutEmailCount</strong></p>";
    } else {
        $subscribersWithoutEmailContent = "No subscribers found without an email.";
    }
} else {
    $subscribersWithoutEmailContent = "Error executing query: " . mysqli_error($conn);
}

// Subscribers without phone number content
$subscribersWithoutPhoneCount = 0;
if ($subscribersWithoutPhoneQuery) {
    if (mysqli_num_rows($subscribersWithoutPhoneQuery) > 0) {
        $subscribersWithoutPhoneContent .= "<table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>";

        // For Subscribers Without Phone
while ($row = mysqli_fetch_assoc($subscribersWithoutPhoneQuery)) {
    $subscriberId = $row['subscriber_id']; // Fetch subscriber_id
    $fullname = htmlspecialchars($row['full_name']); // Fetch fullname and sanitize

    // Check if full_name exists
    if ($fullname) {
        $displayName = $fullname;
    } else {
        // If full_name doesn't exist, check if email exists
        $emailQuery = "SELECT email FROM emails WHERE subscriber_id = $subscriberId LIMIT 1";
        $emailResult = mysqli_query($conn, $emailQuery);
        $email = mysqli_fetch_assoc($emailResult)['email'];

        // If email exists, display it
        if ($email) {
            $displayName = $email;
        } else {
            // If neither full_name nor email exist, display subscriber_id
            $displayName = $subscriberId;
        }
    }

    $subscribersWithoutPhoneContent .= "<tr>
    <td><a href='edit_subscriber.php?id=$subscriberId'
     style='text-decoration: underline; color: inherit;'
     onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
     onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$displayName</a></td>
  </tr>";
    // Increment the counter
    $subscribersWithoutPhoneCount++;
}

        $subscribersWithoutPhoneContent .= "</tbody></table>";

         // Append the total count to the content
        //  $subscribersWithoutPhoneContent .= "<p><strong>Total Subscribers Without Phone Numbers: $subscribersWithoutPhoneCount</strong></p>";
    } else {
        $subscribersWithoutPhoneContent = "No subscribers found without phone number.";
    }
} else {
    $subscribersWithoutPhoneContent = "Error executing query: " . mysqli_error($conn);
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
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-4">
                        <h2>Mailbox Full (<?= ($mailboxFullCount); ?>)</h2>
                        <?php echo $mailboxFullContent; ?>
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
