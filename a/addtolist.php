<?php
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    echo "<script>alert('You should login first');</script>";
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "db.php"; // Include the database connection file

// Function to fetch categories from the database
function fetchCategories($conn) {
    $sql = "SELECT * FROM categories"; // Use the correct table name "categories"
    $result = $conn->query($sql);

    if (!$result) {
        die("Error fetching categories: " . $conn->error);
    }
    $categories = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row["category_id"]] = $row["category_name"];
        }
    }
    return $categories;
}

// Function to fetch organizers from the database
function fetchOrganizers($conn) {
    $sql = "SELECT * FROM organizers"; // Use the correct table name "Organizers"
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching organizers: " . $conn->error);
    }
    $organizers = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $organizers[$row["organizer_id"]] = $row["organizer_name"];
        }
    }
    return $organizers;
}

// Function to fetch events from the database and store category_id and organizer_id
function fetchEvents($conn) {
    $sql = "SELECT event_id, event_name, category_id, organizer_id FROM events"; 
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching events: " . $conn->error);
    }
    $events = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[$row["event_id"]] = array(
                "event_name" => $row["event_name"],
                "category_id" => $row["category_id"],
                "organizer_id" => $row["organizer_id"]
            );
        }
    }
    return $events;
}

function insertBulkAddData($conn, $fullName, $mobileNumbers, $emails, $designation, $organization, $address, $event_id, $category_id, $organizer_id) {
    // Insert data into the subscribers table
    $sql = "INSERT INTO subscribers (full_name, designation, organization, address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssss", $fullName, $designation, $organization, $address);
    
    if ($stmt->execute()) {
        // Get the ID of the inserted subscriber
        $subscriberId = $stmt->insert_id;
        
        // Ensure $mobileNumbers is an array even if a single string is passed
        if (is_string($mobileNumbers)) {
            // If it's a comma-separated string, split it into an array
            $mobileNumbers = explode(",", $mobileNumbers);
        } elseif (!is_array($mobileNumbers)) {
            die("Mobile numbers is not an array: " . var_export($mobileNumbers, true)); // Debugging if it's neither array nor string
        }

        // Insert mobile numbers into phone_numbers table, skipping empty values
        $phoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
        $phoneStmt = $conn->prepare($phoneSQL);
        if (!$phoneStmt) {
            die("Prepare failed for phone numbers: " . $conn->error);
        }

        foreach ($mobileNumbers as $mobileNumber) {
            // Skip empty phone numbers
            if (empty($mobileNumber)) {
                continue;
            }
            $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
            if (!$phoneStmt->execute()) {
                die("Execute failed for phone numbers: " . $phoneStmt->error);
            }
        }

        // Ensure $emails is an array even if a single string is passed
        if (is_string($emails)) {
            $emails = [$emails]; // Convert single email to an array
        } elseif (!is_array($emails)) {
            die("Emails is not an array: " . var_export($emails, true)); // Debugging if it's neither array nor string
        }

        // Insert emails into emails table, skipping empty values
        $emailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
        $emailStmt = $conn->prepare($emailSQL);
        if (!$emailStmt) {
            die("Prepare failed for emails: " . $conn->error);
        }

        foreach ($emails as $email) {
            // Skip empty emails
            if (empty($email)) {
                continue;
            }
            $emailStmt->bind_param("is", $subscriberId, $email);
            if (!$emailStmt->execute()) {
                die("Execute failed for emails: " . $emailStmt->error);
            }
        }
        
        // Insert into event_subscriber_mapping table
        $insertMappingSQL = "INSERT INTO event_subscriber_mapping ( subscriber_id, event_id, organizer_id, category_id) VALUES (?, ?, ?, ?)";
        $mappingStmt = $conn->prepare($insertMappingSQL);
        if (!$mappingStmt) {
            die("Prepare failed for event mapping: " . $conn->error);
        }
        $mappingStmt->bind_param("iiii", $subscriberId, $event_id, $organizer_id, $category_id);
        if (!$mappingStmt->execute()) {
            die("Execute failed for event mapping: " . $mappingStmt->error);
        }

        return true;
    } else {
        die("Execute failed: " . $stmt->error);
    }
}





if (isset($_POST["Submit"])) {
    // Retrieve form data
    $fullName = $_POST['fullName'];
    $mobileNumber = $_POST['mobileNumber']; // Single phone number or an array of phone numbers
    $email = $_POST['email']; // Single email or an array of emails
    $designation = $_POST['designation'];
    $organization = $_POST['organization'];
    $organizerId = $_POST['organizer'];
    $categoryId = $_POST['category'];

    // Check if the phone number or email exists and fetch the subscriber_id
    $existingSubscriberId = null;

    // Check for existing subscriber by phone number
    $phoneSQL = "SELECT subscriber_id FROM phone_numbers WHERE phone_number = ?";
    $phoneStmt = $conn->prepare($phoneSQL);
    foreach ((array)$mobileNumber as $number) {
        $phoneStmt->bind_param("s", $number);
        $phoneStmt->execute();
        $result = $phoneStmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $existingSubscriberId = $row['subscriber_id'];
            break;
        }
    }
    $phoneStmt->close();

    // If no match found by phone number, check by email
    if (!$existingSubscriberId) {
        $emailSQL = "SELECT subscriber_id FROM emails WHERE email = ?";
        $emailStmt = $conn->prepare($emailSQL);
        foreach ((array)$email as $emailAddr) {
            $emailStmt->bind_param("s", $emailAddr);
            $emailStmt->execute();
            $result = $emailStmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $existingSubscriberId = $row['subscriber_id'];
                break;
            }
        }
        $emailStmt->close();
    }

    // Use existing subscriber_id if found, otherwise create a new subscriber
    if ($existingSubscriberId) {
        $subscriberId = $existingSubscriberId;
    } else {
        // Insert new subscriber
        $insertSubscriberSQL = "INSERT INTO subscribers (full_name, designation, organization) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertSubscriberSQL);
        $stmt->bind_param("sss", $fullName, $designation, $organization);
        if ($stmt->execute()) {
            $subscriberId = $stmt->insert_id;
        } else {
            die("Error inserting subscriber: " . $stmt->error);
        }
        $stmt->close();
    }

    // Add new phone numbers linked to the subscriber_id
    $phoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
    $phoneStmt = $conn->prepare($phoneSQL);
    foreach ((array)$mobileNumber as $number) {
        $phoneStmt->bind_param("is", $subscriberId, $number);
        if (!$phoneStmt->execute()) {
            die("Error inserting phone number: " . $phoneStmt->error);
        }
    }
    $phoneStmt->close();

    // Add new emails linked to the subscriber_id
    $emailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
    $emailStmt = $conn->prepare($emailSQL);
    foreach ((array)$email as $emailAddr) {
        $emailStmt->bind_param("is", $subscriberId, $emailAddr);
        if (!$emailStmt->execute()) {
            die("Error inserting email: " . $emailStmt->error);
        }
    }
    $emailStmt->close();

    // Insert into event_subscriber_mapping table
    $mappingSQL = "
    INSERT INTO event_subscriber_mapping (subscriber_id, event_id, organizer_id, category_id) 
    VALUES (?, null, ?, ?)";
    $mappingStmt = $conn->prepare($mappingSQL);
    $mappingStmt->bind_param("iii", $subscriberId, $organizerId, $categoryId);
    if (!$mappingStmt->execute()) {
        die("Error inserting event mapping: " . $mappingStmt->error);
    }
    $mappingStmt->close();

    // Redirect with success message
    $subscriberMessage = "Subscriber added successfully!";
}


// Handle form submission for Bulk Add by Category
else if (isset($_POST["AddSubscribersCategory"])) {
    // Bulk Add by Category Form
    $category = isset($_POST["category"]) ? $_POST["category"] : null;

    // Process the uploaded CSV file
    if (isset($_FILES["csvFile"])) {
        $csvFile = $_FILES["csvFile"]["tmp_name"];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Initialize arrays to store encountered phone numbers and emails
            $encounteredPhoneNumbers = array();
            $encounteredEmails = array();

            // Skip the first row (header)
            fgetcsv($handle); // Skip the header row

            // Loop through the CSV file and insert records into the database
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                // Check if all fields in the row are empty
                $isEmptyRow = true;
                foreach ($data as $field) {
                    if (!empty($field)) {
                        $isEmptyRow = false;
                        break;
                    }
                }

                // Insert the record into the database only if the row is not empty
                if (!$isEmptyRow) {
                    $fullName = $data[0];
                    $mobileNumber = $data[1];
                    $email = $data[2];
                    $designation = isset($data[3]) ? $data[3] : null;
                    $organization = isset($data[4]) ? $data[4] : null;
                    $address = isset($data[5]) ? $data[5] : null;

                    // Check if the phone number or email has already been encountered in this CSV file
                    if (!in_array($mobileNumber, $encounteredPhoneNumbers) && !in_array($email, $encounteredEmails)) {
                        // Check if the phone number or email already exists in the database
                        $subscriberId = null;

                        // Check if the phone number exists
                        $phoneSQL = "SELECT subscriber_id FROM phone_numbers WHERE phone_number = ?";
                        $phoneStmt = $conn->prepare($phoneSQL);
                        $phoneStmt->bind_param("s", $mobileNumber);
                        $phoneStmt->execute();
                        $phoneResult = $phoneStmt->get_result();
                        if ($phoneResult->num_rows > 0) {
                            $row = $phoneResult->fetch_assoc();
                            $subscriberId = $row['subscriber_id'];
                        }
                        $phoneStmt->close();

                        // Check if the email exists (if phone number does not exist)
                        if (!$subscriberId) {
                            $emailSQL = "SELECT subscriber_id FROM emails WHERE email = ?";
                            $emailStmt = $conn->prepare($emailSQL);
                            $emailStmt->bind_param("s", $email);
                            $emailStmt->execute();
                            $emailResult = $emailStmt->get_result();
                            if ($emailResult->num_rows > 0) {
                                $row = $emailResult->fetch_assoc();
                                $subscriberId = $row['subscriber_id'];
                            }
                            $emailStmt->close();
                        }

                        // If no existing subscriber found, insert a new subscriber
                        if (!$subscriberId) {
                            // Insert the record into subscribers table
                            $insertSubscriberSQL = "INSERT INTO subscribers (full_name, designation, organization, address) VALUES (?, ?, ?, ?)";
                            $insertStmt = $conn->prepare($insertSubscriberSQL);
                            $insertStmt->bind_param("ssss", $fullName, $designation, $organization, $address);
                            if ($insertStmt->execute()) {
                                $subscriberId = $insertStmt->insert_id;
                            } else {
                                die("Error inserting subscriber: " . $insertStmt->error);
                            }
                            $insertStmt->close();
                        }

                        // Insert the phone number if it's not already associated with the subscriber
                        if ($mobileNumber && !in_array($mobileNumber, $encounteredPhoneNumbers)) {
                            $insertPhoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
                            $phoneStmt = $conn->prepare($insertPhoneSQL);
                            $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
                            if (!$phoneStmt->execute()) {
                                die("Error inserting phone number: " . $phoneStmt->error);
                            }
                            $phoneStmt->close();
                            $encounteredPhoneNumbers[] = $mobileNumber;  // Add to encountered array
                        }

                        // Insert the email if it's not already associated with the subscriber
                        if ($email && !in_array($email, $encounteredEmails)) {
                            $insertEmailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
                            $emailStmt = $conn->prepare($insertEmailSQL);
                            $emailStmt->bind_param("is", $subscriberId, $email);
                            if (!$emailStmt->execute()) {
                                die("Error inserting email: " . $emailStmt->error);
                            }
                            $emailStmt->close();
                            $encounteredEmails[] = $email;  // Add to encountered array
                        }

                        // Insert into event_subscriber_mapping table
                        $mappingSQL = "
                            INSERT INTO event_subscriber_mapping (subscriber_id, event_id, category_id)
                            VALUES (?, null, ?)";
                        $mappingStmt = $conn->prepare($mappingSQL);
                        $mappingStmt->bind_param("ii", $subscriberId, $category);
                        if (!$mappingStmt->execute()) {
                            die("Error inserting event mapping: " . $mappingStmt->error);
                        }
                        $mappingStmt->close();
                    }
                }
            }
            fclose($handle);
            // Redirect to the subscribers page after processing the CSV
            header("Location: subscribers.php");
            exit();
        } else {
            die("Error opening the CSV file.");
        }
    }
}


// Handle form submission for Bulk Add by Organizer
else if (isset($_POST["AddSubscribersOrganizer"])) {
    // Bulk Add by Organizer Form
    $category = isset($_POST["category"]) ? $_POST["category"] : null;
    $organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : null;

    // Process the uploaded CSV file
    if (isset($_FILES["csvFile"])) {
        $csvFile = $_FILES["csvFile"]["tmp_name"];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Initialize arrays to store encountered phone numbers and emails
            $encounteredPhoneNumbers = array();
            $encounteredEmails = array();

            // Skip the first row (header)
            fgetcsv($handle); // Skip the header row

            // Loop through the CSV file and insert records into the database
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                // Check if all fields in the row are empty
                $isEmptyRow = true;
                foreach ($data as $field) {
                    if (!empty($field)) {
                        $isEmptyRow = false;
                        break;
                    }
                }

                // Insert the record into the database only if the row is not empty
                if (!$isEmptyRow) {
                    $fullName = $data[0];
                    $mobileNumber = $data[1];
                    $email = $data[2];
                    $designation = isset($data[3]) ? $data[3] : null;
                    $organization = isset($data[4]) ? $data[4] : null;
                    $address = isset($data[5]) ? $data[5] : null;

                    // Check if the phone number or email has already been encountered in this CSV file
                    if (!in_array($mobileNumber, $encounteredPhoneNumbers) && !in_array($email, $encounteredEmails)) {
                        // Check if the phone number or email already exists in the database
                        $subscriberId = null;

                        // Check if the phone number exists
                        $phoneSQL = "SELECT subscriber_id FROM phone_numbers WHERE phone_number = ?";
                        $phoneStmt = $conn->prepare($phoneSQL);
                        $phoneStmt->bind_param("s", $mobileNumber);
                        $phoneStmt->execute();
                        $phoneResult = $phoneStmt->get_result();
                        if ($phoneResult->num_rows > 0) {
                            $row = $phoneResult->fetch_assoc();
                            $subscriberId = $row['subscriber_id'];
                        }
                        $phoneStmt->close();

                        // Check if the email exists (if phone number does not exist)
                        if (!$subscriberId) {
                            $emailSQL = "SELECT subscriber_id FROM emails WHERE email = ?";
                            $emailStmt = $conn->prepare($emailSQL);
                            $emailStmt->bind_param("s", $email);
                            $emailStmt->execute();
                            $emailResult = $emailStmt->get_result();
                            if ($emailResult->num_rows > 0) {
                                $row = $emailResult->fetch_assoc();
                                $subscriberId = $row['subscriber_id'];
                            }
                            $emailStmt->close();
                        }

                        // If no existing subscriber found, insert a new subscriber
                        if (!$subscriberId) {
                            // Insert the record into subscribers table
                            $insertSubscriberSQL = "INSERT INTO subscribers (full_name, designation, organization, address) VALUES (?, ?, ?, ?)";
                            $insertStmt = $conn->prepare($insertSubscriberSQL);
                            $insertStmt->bind_param("ssss", $fullName, $designation, $organization, $address);
                            if ($insertStmt->execute()) {
                                $subscriberId = $insertStmt->insert_id;
                            } else {
                                die("Error inserting subscriber: " . $insertStmt->error);
                            }
                            $insertStmt->close();
                        }

                        // Insert the phone number if it's not already associated with the subscriber
                        if ($mobileNumber && !in_array($mobileNumber, $encounteredPhoneNumbers)) {
                            $insertPhoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
                            $phoneStmt = $conn->prepare($insertPhoneSQL);
                            $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
                            if (!$phoneStmt->execute()) {
                                die("Error inserting phone number: " . $phoneStmt->error);
                            }
                            $phoneStmt->close();
                            $encounteredPhoneNumbers[] = $mobileNumber;  // Add to encountered array
                        }

                        // Insert the email if it's not already associated with the subscriber
                        if ($email && !in_array($email, $encounteredEmails)) {
                            $insertEmailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
                            $emailStmt = $conn->prepare($insertEmailSQL);
                            $emailStmt->bind_param("is", $subscriberId, $email);
                            if (!$emailStmt->execute()) {
                                die("Error inserting email: " . $emailStmt->error);
                            }
                            $emailStmt->close();
                            $encounteredEmails[] = $email;  // Add to encountered array
                        }

                        // Insert into event_subscriber_mapping table with the organizer and category
                        $mappingSQL = "
                            INSERT INTO event_subscriber_mapping (subscriber_id, event_id, category_id, organizer_id)
                            VALUES (?, null, ?, ?)";
                        $mappingStmt = $conn->prepare($mappingSQL);
                        $mappingStmt->bind_param("iii", $subscriberId, $category, $organizer);
                        if (!$mappingStmt->execute()) {
                            die("Error inserting event mapping: " . $mappingStmt->error);
                        }
                        $mappingStmt->close();
                    }
                }
            }
            fclose($handle);
            // Redirect to the subscribers page after processing the CSV
            header("Location: subscribers.php");
            exit();
        } else {
            die("Error opening the CSV file.");
        }
    }
}

// Handle form submission for Bulk Add by Event
else if (isset($_POST["AddSubscribersEvent"])) {
    // Bulk Add by Event Form
    $event = isset($_POST["event"]) ? $_POST["event"] : null;
    $category = isset($_POST["category"]) ? $_POST["category"] : null;
    $organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : null;

    // Fetch category_id and organizer_id associated with the selected event
    $sql = "SELECT category_id, organizer_id FROM events WHERE event_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event);
    $stmt->execute();
    $stmt->bind_result($category_id, $organizer_id);
    $stmt->fetch();
    $stmt->close();

    // Process the uploaded CSV file
    if (isset($_FILES["csvFile"])) {
        $csvFile = $_FILES["csvFile"]["tmp_name"];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Initialize arrays to store encountered phone numbers and emails
            $encounteredPhoneNumbers = array();
            $encounteredEmails = array();

            // Skip the first row (header)
            fgetcsv($handle); // Skip the header row

            // Loop through the CSV file and insert records into the database
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                // Check if all fields in the row are empty
                $isEmptyRow = true;
                foreach ($data as $field) {
                    if (!empty($field)) {
                        $isEmptyRow = false;
                        break;
                    }
                }

                // Insert the record into the database only if the row is not empty
                if (!$isEmptyRow) {
                    $fullName = $data[0];
                    $mobileNumber = $data[1];
                    $email = $data[2];
                    $designation = isset($data[3]) ? $data[3] : null;
                    $organization = isset($data[4]) ? $data[4] : null;
                    $address = isset($data[5]) ? $data[5] : null;

                    // Check if the phone number or email has already been encountered in this CSV file
                    if (!in_array($mobileNumber, $encounteredPhoneNumbers) && !in_array($email, $encounteredEmails)) {
                        // Insert the record into the database with the event_id, category_id, and organizer_id
                        if (insertBulkAddData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $event, $category_id, $organizer_id)) {
                            // Record added successfully, update encountered phone numbers and emails
                            $encounteredPhoneNumbers[] = $mobileNumber;
                            $encounteredEmails[] = $email;
                        } else {
                            die("Error inserting CSV data into the database.");
                        }
                    }
                }
            }
            fclose($handle);
            // Redirect to the subscribers page after processing the CSV
            header("Location: subscribers.php");
            exit();
        } else {
            die("Error opening the CSV file.");
        }
    }
}


// Fetch categories from the database (same as before)
$categories = fetchCategories($conn);

// Fetch organizers from the database (same as before)
$organizers = fetchOrganizers($conn);

// Fetch events from the database (same as before)
$events = fetchEvents($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <title>Register</title>
</head>
<body>
    <?php include("header.php"); ?>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-12">
                <!-- Display the subscriber message -->
<div id="subscriberResponseMessage">
    <?php
    if (!empty($subscriberMessage)) {
        echo '<div class="alert alert-info">' . $subscriberMessage . '</div>';
    }
    ?>
</div>
<div class="row">
<div class="col-md-4">
                <h2>Single Add</h2>
                <form action="addtolist.php" method="post">
                    <div class="mb-3">
                        <label for="mobileNumber" class="form-label">Mobile Number:</label>
                        <input type="text" id="mobilenumber" name="mobileNumber" pattern="[0-9]{10}" minlength="10" maxlength="10" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name:</label>
                        <input type="text" id="fullName" name="fullName"  class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email"   class="form-control" >
                        <!-- pattern=".+@example\.com" -->
                    </div>
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation:</label>
                        <input type="text" id="designation" name="designation" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="organization" class="form-label">Organization:</label>
                        <input type="text" id="organization" name="organization" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="organizer" class="form-label">Organizer:</label>
                        <select id="organizer" name="organizer" class="form-select" required>
                            <option value="" disabled selected>Select Organizer</option>
                            <?php
                            // Sort the categories array by their values (category names)
                             asort($organizers);
                            foreach ($organizers as $organizerId => $organizerName) {
                                echo "<option value='$organizerId'>$organizerName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
    <label for="category" class="form-label">Category:</label>
    <select id="category" name="category" class="form-select" required>
        <option value="" disabled selected>Select Category</option>
        <?php
        // Sort the categories array by their values (category names)
        asort($categories);
        
        // Output the sorted options
        foreach ($categories as $categoryId => $categoryName) {
            echo "<option value='$categoryId'>$categoryName</option>";
        }
        ?>
    </select>
</div>

                    
                    <button type="submit" name="Submit" class="btn btn-outline-success">Add Subscriber</button>
                </form>
</div>

<div class="col-md-2">
</div>

<div class="col-md-6">
<h2>Bulk Add</h2>
<ul class="nav nav-tabs" id="bulkAddTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="categoryTab" data-bs-toggle="tab" data-bs-target="#categorySection" type="button" role="tab" aria-controls="categorySection" aria-selected="true">Bulk Add by Category</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="organizerTab" data-bs-toggle="tab" data-bs-target="#organizerSection" type="button" role="tab" aria-controls="organizerSection" aria-selected="false">Bulk Add by Organizer</button>
    </li>
    <!-- Add the new tab button here -->
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="eventTab" data-bs-toggle="tab" data-bs-target="#eventSection" type="button" role="tab" aria-controls="eventSection" aria-selected="false">Bulk Add by Event</button>
    </li>
</ul>

<div class="tab-content" id="bulkAddTabsContent">
<div class="tab-pane fade show" id="categorySection" role="tabpanel" aria-labelledby="categoryTab">
<!-- Bulk Add by Category Form -->
<form id="bulkAddCategoryForm" action="addtolist.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label"><i class="bi bi-eyedropper"></i>Choose a CSV file:</label>
                        <input type="file" name="csvFile" accept=".csv" class="form-control" required>
                        <p class="form-text text-muted">
                            <em>Note: The CSV file should have 5 columns in this order - Full Name, Mobile Number, Email, Designation, Organization.</em>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label for="category2" class="form-label">Category:</label>
                        <select id="category2" name="category" class="form-select" required>
                            <option value="" disabled selected required>Select Category</option>
                            <?php

                            // Sort the categories array by their values (category names)
                            asort($categories);

                            foreach ($categories as $categoryId => $categoryName) {
                                echo "<option value='$categoryId'>$categoryName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="AddSubscribersCategory" class="btn btn-outline-success">Add Subscribers</button>
                                </form>
</div>

<div class="tab-pane fade" id="organizerSection" role="tabpanel" aria-labelledby="organizerTab">
<!-- Bulk Add by Organizer Form -->
<form id="bulkAddOrganizerForm" action="addtolist.php" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                        <label for="csvFile" class="form-label"><i class="bi bi-eyedropper"></i>Choose a CSV file:</label>
                        <input type="file" name="csvFile" accept=".csv" class="form-control" required>
                        <p class="form-text text-muted">
                            <em>Note: The CSV file should have 5 columns in this order - Full Name, Mobile Number, Email, Designation, Organization.</em>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label for="category2" class="form-label">Category:</label>
                        <select id="category2" name="category" class="form-select" required>
                            <option value="" disabled selected>Select Category</option>
                            <?php
                            // Sort the categories array by their values (category names)
                             asort($categories);
                            foreach ($categories as $categoryId => $categoryName) {
                                echo "<option value='$categoryId'>$categoryName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    

                    <div class="mb-3">
                        <label for="organizer2" class="form-label">Organizer:</label>
                        <select id="organizer2" name="organizer" class="form-select" required>
                            <option value="" disabled selected required>Select Organizer</option>
                            <?php

                            // Sort the categories array by their values (category names)
                             asort($organizers);
                            foreach ($organizers as $organizerId => $organizerName) {
                                echo "<option value='$organizerId'>$organizerName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="AddSubscribersOrganizer" class="btn btn-outline-success">Add Subscribers</button>
 </form>
</div>                       
<div class="tab-pane fade" id="eventSection" role="tabpanel" aria-labelledby="eventTab">
    <!-- Bulk Add by Event Form -->
    <form id="bulkAddEventForm" action="addtolist.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="csvFile" class="form-label"><i class="bi bi-eyedropper"></i>Choose a CSV file:</label>
            <input type="file" name="csvFile" accept=".csv" class="form-control" required>
            <p class="form-text text-muted">
                <em>Note: The CSV file should have 5 columns in this order - Full Name, Mobile Number, Email, Designation, Organization.</em>
            </p>
        </div>
        <div class="mb-3">
            <label for="event" class="form-label">Event:</label>
            <select id="event" name="event" class="form-select" required>
                <option value="" disabled selected>Select Event</option>
                <?php
                // Sort the categories array by their values (category names)
                 asort($events);
                foreach ($events as $eventId => $event) {
                echo "<option value='$eventId'>" . $event["event_name"] . "</option>";
                 }
                ?>
            </select>
        </div>
        <button type="submit" name="AddSubscribersEvent" class="btn btn-outline-success">Add Subscribers</button>
    </form>
</div>

</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Add this script to your HTML, after including Bootstrap -->
<script>
    var bulkAddTabs = new bootstrap.Tab(document.getElementById('bulkAddTabs'));
    var bulkAddTabsContent = new bootstrap.Tab(document.getElementById('bulkAddTabsContent'));
    bulkAddTabs.show();

    bulkAddTabsContent.addEventListener('shown.bs.tab', function (event) {
        var tabContentId = event.target.getAttribute('href');
        var tabContent = document.querySelector(tabContentId + ' form');
        tabContent.reset(); // Reset form fields when switching tabs
    });
</script>

</script>
 <script>
                    // Function to display the success modal
                       function showSuccessModal(message) {
                       var modal = document.getElementById("successModal");
                       var successMessage = document.getElementById("successMessage");
                    // Set the success message in the modal
                    successMessage.textContent = message;
                    // Display the modal
                    modal.style.display = "block";
                    // Close the modal when the user clicks the close button
                    var closeBtn = document.getElementsByClassName("close")[0];
                    closeBtn.onclick = function () {
                    modal.style.display = "none";
                    };
                    // Close the modal when the user clicks outside the modal
                    window.onclick = function (event) {
                    if (event.target == modal) {
                    modal.style.display = "none";
                    }
                    };
                    }
                    // Check if the successMessage variable is set, and if so, display the success modal
                    if (typeof successMessage !== "undefined") {
                    showSuccessModal(successMessage);
                    }
                    </script>

    
</body>
</html>