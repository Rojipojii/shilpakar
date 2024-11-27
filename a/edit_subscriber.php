<?php
session_start();

// Include the necessary files
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // If not logged in, redirect to login page
    header("Location: index.php?message=You should login first");
    exit();
}

// Fetch subscriber details based on the ID passed through URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $subscriberID = $_GET['id'];

    // Prepare and execute query to fetch subscriber details from the 'subscribers' table
    $stmt = $conn->prepare("SELECT * FROM subscribers WHERE subscriber_id = ?");
    if ($stmt === false) {
        die('MySQL prepare error: ' . $conn->error); // Added error handling for prepare statement
    }
    $stmt->bind_param("i", $subscriberID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Subscriber found, fetch details
        $subscriber = $result->fetch_assoc();

        // Fetch all phone numbers associated with the subscriber
$phoneQuery = $conn->prepare("SELECT phone_number FROM phone_numbers WHERE subscriber_id = ?");
if ($phoneQuery === false) {
    die('MySQL prepare error for phone query: ' . $conn->error); // Added error handling for phone query
}
$phoneQuery->bind_param("i", $subscriberID);
$phoneQuery->execute();
$phoneResult = $phoneQuery->get_result();
$subscriberPhones = [];
while ($phone = $phoneResult->fetch_assoc()) {
    $subscriberPhones[] = $phone['phone_number'];
}
// Remove duplicates using array_unique
$subscriberPhones = array_unique($subscriberPhones);
$subscriberPhone = implode(', ', $subscriberPhones); // Join all unique phone numbers with commas

// Fetch all emails associated with the subscriber
$emailQuery = $conn->prepare("SELECT email FROM emails WHERE subscriber_id = ?");
if ($emailQuery === false) {
    die('MySQL prepare error for email query: ' . $conn->error); // Added error handling for email query
}
$emailQuery->bind_param("i", $subscriberID);
$emailQuery->execute();
$emailResult = $emailQuery->get_result();
$subscriberEmails = [];
while ($email = $emailResult->fetch_assoc()) {
    $subscriberEmails[] = $email['email'];
}
// Remove duplicates using array_unique
$subscriberEmails = array_unique($subscriberEmails);
$subscriberEmail = implode(', ', $subscriberEmails); // Join all unique emails with commas


        // Fetch unique events attended by subscribers with the same phone number or email
        $eventsQuery = $conn->prepare("SELECT DISTINCT e.event_name
                                       FROM event_subscriber_mapping esm
                                       INNER JOIN events e ON esm.event_id = e.event_id
                                       WHERE esm.subscriber_id IN (
                                           SELECT s.subscriber_id
                                           FROM subscribers s
                                           LEFT JOIN phone_numbers pn ON s.subscriber_id = pn.subscriber_id
                                           LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
                                           WHERE pn.phone_number = ? OR e.email = ?
                                       )");
        if ($eventsQuery === false) {
            die('MySQL prepare error for events query: ' . $conn->error); // Added error handling for events query
        }
        $eventsQuery->bind_param("ss", $subscriberPhone, $subscriberEmail);
        $eventsQuery->execute();
        $eventsResult = $eventsQuery->get_result();
        $eventsAttended = [];

        while ($event = $eventsResult->fetch_assoc()) {
            $eventsAttended[] = $event['event_name'];
        }

        // Prepare the string for events attended by the subscriber
        $eventsAttendedString = implode(', ', $eventsAttended);

        // Store the names of events attended by the subscriber in a string
        $subscriber['events_attended'] = $eventsAttendedString;

        // Fetch unique categories attended by subscribers with the same phone number or email
$categoriesQuery = $conn->prepare("SELECT DISTINCT c.category_name
                                  FROM event_subscriber_mapping esm
                                  INNER JOIN categories c ON esm.category_id = c.category_id
                                  WHERE esm.subscriber_id IN (
                                      SELECT s.subscriber_id
                                      FROM subscribers s
                                      LEFT JOIN phone_numbers pn ON s.subscriber_id = pn.subscriber_id
                                      LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
                                      WHERE pn.phone_number = ? OR e.email = ?
                                  )");

        if ($categoriesQuery === false) {
            die('MySQL prepare error for categories query: ' . $conn->error); // Added error handling for categories query
        }
        $categoriesQuery->bind_param("ss", $subscriberPhone, $subscriberEmail);
        $categoriesQuery->execute();
        $categoriesResult = $categoriesQuery->get_result();
        $categoriesAttended = [];

        while ($category = $categoriesResult->fetch_assoc()) {
            $categoriesAttended[] = $category['category_name'];
        }

        // Prepare the string for categories attended by the subscriber
        $categoriesAttendedString = implode(', ', $categoriesAttended);

        // Store the names of categories attended by the subscriber in a string
        $subscriber['categories_attended'] = $categoriesAttendedString;
    } else {
        // Subscriber not found, redirect back to subscribers page
        header("Location: subscribers.php");
        exit();
    }
} else {
    // ID not provided, redirect back to subscribers page
    header("Location: subscribers.php");
    exit();
}

// Handle form submission to update subscriber details
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve edited subscriber details from the form
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $designation = $_POST['designation'];
    $organization = $_POST['organization'];
    $address = $_POST['address'];

    // Update the subscriber details in the 'subscribers' table
    $updateStmt = $conn->prepare("UPDATE subscribers SET full_name = ?, designation = ?, organization = ?, address = ? WHERE subscriber_id = ?");
    if ($updateStmt === false) {
        die('MySQL prepare error for update statement: ' . $conn->error); // Added error handling for update statement
    }
    $updateStmt->bind_param("ssssi", $full_name, $designation, $organization, $address, $subscriberID);

    if ($updateStmt->execute()) {
        // Update the phone number in the 'phone_numbers' table
        $updatePhoneStmt = $conn->prepare("UPDATE phone_numbers SET phone_number = ? WHERE subscriber_id = ?");
        if ($updatePhoneStmt === false) {
            die('MySQL prepare error for phone update: ' . $conn->error); // Added error handling for phone update
        }
        $updatePhoneStmt->bind_param("si", $phone_number, $subscriberID);

        // Update the email in the 'emails' table
        $updateEmailStmt = $conn->prepare("UPDATE emails SET email = ? WHERE subscriber_id = ?");
        if ($updateEmailStmt === false) {
            die('MySQL prepare error for email update: ' . $conn->error); // Added error handling for email update
        }
        $updateEmailStmt->bind_param("si", $email, $subscriberID);

        // Execute updates for phone number and email
        if ($updatePhoneStmt->execute() && $updateEmailStmt->execute()) {
            // Redirect back to subscribers page after updating
            header("Location: subscribers.php");
            exit();
        } else {
            // Error occurred while updating phone/email
            $errorMessage = "Error updating phone/email: " . $conn->error;
        }
    } else {
        // Error occurred while updating the subscriber details
        $errorMessage = "Error updating subscriber details: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscriber</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <?php include("header.php"); ?>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="row mb-2">
                <h2>Edit Subscriber</h2>
                <?php if (isset($errorMessage)) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $subscriber['full_name']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile_number" name="phone_number" value="<?php echo htmlspecialchars($subscriberPhone); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($subscriberEmail); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="designation" class="form-label">Designation</label>
                                <input type="text" class="form-control" id="designation" name="designation" value="<?php echo $subscriber['designation']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="organization" class="form-label">Organization</label>
                                <input type="text" class="form-control" id="organization" name="organization" value="<?php echo $subscriber['organization']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo $subscriber['address']; ?>">
                            </div>
                        </div>
                    </div>
                    <!-- Displaying categories associated with events attended -->
                    <div class="mb-3">
                        <label for="categories_attended" class="form-label">Categories</label>
                        <textarea class="form-control" id="categories_attended" name="categories_attended" rows="4"><?php echo $subscriber['categories_attended']; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-outline-success">Save</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
