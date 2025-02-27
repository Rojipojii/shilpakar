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

function fetchEvents($conn) {
    $sql = "SELECT event_id, event_name, organizer_id, event_date FROM events"; 
    $result = $conn->query($sql);

    if (!$result) {
        die("Error fetching events: " . $conn->error);
    }

    $events = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = array(
                "event_id" => $row["event_id"],
                "event_name" => $row["event_name"],
                "organizer_id" => $row["organizer_id"],
                "event_date" => $row["event_date"]
            );
        }
    }
    return $events;
}


// Function to clean and normalize the phone number
function cleanPhoneNumber($number) {
    // Remove any non-numeric characters
    return preg_replace('/[^0-9]/', '', $number);
}

if (isset($_POST["Submit"])) { 
    // Retrieve form data
    $fullName = $_POST['fullName'];
    $mobileNumber = $_POST['mobileNumber']; // Single phone number
    $email = strtolower(trim($_POST["email"])); // Single email
    $designation = trim($_POST["designation"]); // Preserve original case
    $organization = trim($_POST["organization"]);
    $organizerId = $_POST['organizer'];
    // $categoryId = $_POST['category'];
    $categories = isset($_POST["category"]) ? $_POST["category"] : [];

    // Insert new subscriber
    $insertSubscriberSQL = "INSERT INTO subscribers (full_name) VALUES (?)";
    $stmt = $conn->prepare($insertSubscriberSQL);
    $stmt->bind_param("s", $fullName);
    if ($stmt->execute()) {
        $subscriberId = $stmt->insert_id; // Get the newly inserted subscriber ID
    } else {
        die("Error inserting subscriber: " . $stmt->error);
    }
    $stmt->close();

    // Insert phone number linked to the subscriber_id
    $phoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
    $phoneStmt = $conn->prepare($phoneSQL);
    $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
    if (!$phoneStmt->execute()) {
        die("Error inserting phone number: " . $phoneStmt->error);
    }
    $phoneStmt->close();

    // Insert email linked to the subscriber_id
    $emailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
    $emailStmt = $conn->prepare($emailSQL);
    $emailStmt->bind_param("is", $subscriberId, $email);
    if (!$emailStmt->execute()) {
        die("Error inserting email: " . $emailStmt->error);
    }
    $emailStmt->close();

    // Insert designation and organization
    $doSQL = "INSERT INTO designation_organization (subscriber_id, designation, organization) VALUES (?, ?, ?)";
    $doStmt = $conn->prepare($doSQL);
    $doStmt->bind_param("iss", $subscriberId, $designation, $organization);
    if (!$doStmt->execute()) {
        die("Error inserting into designation_organization: " . $doStmt->error);
    }
    $doStmt->close();

    // Insert into event_subscriber_mapping table
    foreach ($categories as $category) {
    $mappingSQL = "INSERT INTO event_subscriber_mapping (subscriber_id, event_id, organizer_id, category_id) VALUES (?, NULL, ?, ?)";
    $mappingStmt = $conn->prepare($mappingSQL);
    $mappingStmt->bind_param("iii", $subscriberId, $organizerId, $category);
    if (!$mappingStmt->execute()) {
        die("Error inserting event mapping: " . $mappingStmt->error);
    }
    $mappingStmt->close();
    }

    // Redirect or display success message
    $subscriberMessage = "Subscriber added successfully!";
    // echo $subscriberMessage;
}

// Handle form submission for Bulk Add by Organizer
else if (isset($_POST["AddSubscribersOrganizer"])) {
    $categories = isset($_POST["category"]) ? $_POST["category"] : []; // Handle multiple categories
    $organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : null;
    $insertedCount = 0; // Initialize counter

    if (isset($_FILES["csvFile"])) {
        $csvFile = $_FILES["csvFile"]["tmp_name"];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header row

            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                $isEmptyRow = true;
                foreach ($data as $field) {
                    if (!empty($field)) {
                        $isEmptyRow = false;
                        break;
                    }
                }

                if (!$isEmptyRow) {
                    $fullName = trim($data[0]);
                    $mobileNumber = isset($data[1]) ? cleanPhoneNumber(trim($data[1])) : null;
                    $email = isset($data[2]) ? strtolower(trim($data[2])) : null;
                    $designation = isset($data[3]) ? trim($data[3]) : null;
                    $organization = isset($data[4]) ? trim($data[4]) : null; 
                    $address = isset($data[5]) ? trim($data[5]) : null;

                    // Insert subscriber
                    $insertSubscriberSQL = "INSERT INTO subscribers (full_name, address) VALUES (?, ?)";
                    $insertStmt = $conn->prepare($insertSubscriberSQL);
                    $insertStmt->bind_param("ss", $fullName, $address);
                    if ($insertStmt->execute()) {
                        $subscriberId = $insertStmt->insert_id;
                        $insertedCount++; 
                    } else {
                        die("Error inserting subscriber: " . $insertStmt->error);
                    }
                    $insertStmt->close();

                    // Insert phone number if available
                    if (!empty($mobileNumber)) {
                        $insertPhoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
                        $phoneStmt = $conn->prepare($insertPhoneSQL);
                        $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
                        if (!$phoneStmt->execute()) {
                            die("Error inserting phone number: " . $phoneStmt->error);
                        }
                        $phoneStmt->close();
                    }

                    // Insert email if available
                    if (!empty($email)) {
                        $insertEmailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
                        $emailStmt = $conn->prepare($insertEmailSQL);
                        $emailStmt->bind_param("is", $subscriberId, $email);
                        if (!$emailStmt->execute()) {
                            die("Error inserting email: " . $emailStmt->error);
                        }
                        $emailStmt->close();
                    }

                    // Insert designation and organization if available
                    if ($designation || $organization) {
                        $insertDOSQL = "INSERT INTO designation_organization (subscriber_id, designation, organization) VALUES (?, ?, ?)";
                        $doStmt = $conn->prepare($insertDOSQL);
                        $designation = $designation ?? '';
                        $organization = $organization ?? '';
                        $doStmt->bind_param("iss", $subscriberId, $designation, $organization);
                        if (!$doStmt->execute()) {
                            die("Error inserting into designation_organization: " . $doStmt->error);
                        }
                        $doStmt->close();
                    }

                    // Insert mapping for multiple categories
                    foreach ($categories as $category) {
                        $mappingSQL = "INSERT INTO event_subscriber_mapping (subscriber_id, event_id, category_id, organizer_id)
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

            // Redirect with count parameter
            header("Location: subscribers.php?addedCount=" . $insertedCount);
            exit();
        } else {
            die("Error opening the CSV file.");
        }
    }
}

else if (isset($_POST["AddSubscribersEvent"])) {
    $categories = isset($_POST["category"]) ? $_POST["category"] : []; // Handle multiple categories
    $event = isset($_POST["event"]) ? $_POST["event"] : null;
    // $insertedCount = 0; // Initialize counter

    if (isset($_FILES["csvFile"])) {
        $csvFile = $_FILES["csvFile"]["tmp_name"];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            fgetcsv($handle);
            
            $organizerIdSQL = "SELECT organizer_id FROM events WHERE event_id = ?";
            $organizerStmt = $conn->prepare($organizerIdSQL);
            $organizerStmt->bind_param("i", $event);
            $organizerStmt->execute();
            $organizerStmt->bind_result($organizerId);
            $organizerStmt->fetch();
            $organizerStmt->close();

            if (!$organizerId) {
                die("Organizer not found for the selected event.");
            }

            $insertedCount = 0;

            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                $isEmptyRow = true;
                foreach ($data as $field) {
                    if (!empty($field)) {
                        $isEmptyRow = false;
                        break;
                    }
                }

                if (!$isEmptyRow) {
                    $fullName = $data[0];
                    $mobileNumber = isset($data[1]) ? cleanPhoneNumber(trim($data[1])) : null;
                    $email = isset($data[2]) ? strtolower(trim($data[2])) : null;
                    $designation = isset($data[3]) ? trim($data[3]) : null;
                    $organization = isset($data[4]) ? trim($data[4]) : null; 
                    $address = isset($data[5]) ? $data[5] : null;

                    $insertSubscriberSQL = "INSERT INTO subscribers (full_name, address) VALUES (?, ?)";
                    $insertStmt = $conn->prepare($insertSubscriberSQL);
                    $insertStmt->bind_param("ss", $fullName, $address);
                    if ($insertStmt->execute()) {
                        $subscriberId = $insertStmt->insert_id;
                        $insertedCount++;
                    } else {
                        die("Error inserting subscriber: " . $insertStmt->error);
                    }
                    $insertStmt->close();

                    if (!empty($mobileNumber)) {
                        $insertPhoneSQL = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
                        $phoneStmt = $conn->prepare($insertPhoneSQL);
                        $phoneStmt->bind_param("is", $subscriberId, $mobileNumber);
                        if (!$phoneStmt->execute()) {
                            die("Error inserting phone number: " . $phoneStmt->error);
                        }
                        $phoneStmt->close();
                    }

                    if (!empty($email)) {
                        $insertEmailSQL = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";
                        $emailStmt = $conn->prepare($insertEmailSQL);
                        $emailStmt->bind_param("is", $subscriberId, $email);
                        if (!$emailStmt->execute()) {
                            die("Error inserting email: " . $emailStmt->error);
                        }
                        $emailStmt->close();
                    }

                    if ($designation || $organization) {
                        $designationValue = $designation ?? null;
                        $organizationValue = $organization ?? null;

                        $insertDOSQL = "INSERT INTO designation_organization (subscriber_id, designation, organization) VALUES (?, ?, ?)";
                        $doStmt = $conn->prepare($insertDOSQL);
                        $doStmt->bind_param("iss", $subscriberId, $designationValue, $organizationValue);
                        if (!$doStmt->execute()) {
                            die("Error inserting designation and organization: " . $doStmt->error);
                        }
                        $doStmt->close();
                    }

                    
                    // Insert mapping for multiple categories
                    foreach ($categories as $category) {
                        $mappingSQL = "INSERT INTO event_subscriber_mapping (subscriber_id, event_id, category_id, organizer_id)
                                       VALUES (?, ?, ?, null)";
                        $mappingStmt = $conn->prepare($mappingSQL);
                        $mappingStmt->bind_param("iii", $subscriberId, $event, $category);
                        if (!$mappingStmt->execute()) {
                            die("Error inserting event mapping: " . $mappingStmt->error);
                        }
                        $mappingStmt->close();
                    }
                }
            }
            fclose($handle);

            header("Location: subscribers.php?addedCount=$insertedCount");
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
/* Set default background to white for dropdown options */
.select2-results__option {
    background-color: white !important;
    color: black !important;
}

/* Change background color of selected options */
.select2-results__option[aria-selected="true"] {
    background-color: #f9e79f !important;
    color: black !important;
}

/* Change background color of selected tags in the selection box */
.select2-selection__choice {
    background-color: #f9e79f !important;
    color: black !important;
    border: 1px solid #d4ac0d !important;
}
</style>



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
    <label for="category" class="form-label">Category:</label>
    <select id="category" name="category[]" class="form-select" multiple required>
        <!-- <option value="" disabled selected>Select Category</option> -->
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


                    
                    <button type="submit" name="Submit" class="btn btn-outline-success">Add Subscriber</button>
                </form>
</div>

<div class="col-md-2">
</div>

<div class="col-md-6">
<h2>Bulk Add</h2>
<ul class="nav nav-tabs" id="bulkAddTabs" role="tablist">
        <!-- Add the new tab button here -->
        <li class="nav-item" role="presentation">
        <button class="nav-link active" id="eventTab" data-bs-toggle="tab" data-bs-target="#eventSection" type="button" role="tab" aria-controls="eventSection" aria-selected="true">Bulk Add by Event</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="organizerTab" data-bs-toggle="tab" data-bs-target="#organizerSection" type="button" role="tab" aria-controls="organizerSection" aria-selected="false">Bulk Add by Organizer</button>
    </li>
</ul>

<div class="tab-content" id="bulkAddTabsContent">
<div class="tab-pane show active" id="eventSection" role="tabpanel" aria-labelledby="eventTab">
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
                        <label for="category3" class="form-label">Category:</label>
                        <select id="category3" name="category[]" class="form-select" multiple required>
                            <!-- <option value="" disabled selected></option> -->
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
    <label for="event" class="form-label">Event:</label>
    <select id="event" name="event" class="form-select" required>
        <option value="" disabled selected>Select Event</option>
        <?php
        // Sort the events array by the event date in descending order (latest events at the top)
        usort($events, function($a, $b) {
            $dateA = strtotime($a["event_date"]);
            $dateB = strtotime($b["event_date"]);
            return $dateB - $dateA; // Sort in descending order
        });

        // Loop through the sorted events and display each as an option
        foreach ($events as $event) {
            // echo "<option value='" . htmlspecialchars($event["event_id"]) . "'>" . htmlspecialchars($event["event_name"]) . " (ID: " . htmlspecialchars($event["event_id"]) . ")</option>";
            echo "<option value='" . htmlspecialchars($event["event_id"]) . "'>" . htmlspecialchars($event["event_name"]) . "</option>";
        }        
        ?>
    </select>
    </div>

        <button type="submit" name="AddSubscribersEvent" class="btn btn-outline-success">Add Subscribers</button>
    </form>
</div>


<div class="tab-pane" id="organizerSection" role="tabpanel" aria-labelledby="organizerTab">
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
                    <select id="category2" name="category[]" class="form-select" multiple required>
                            <!-- <option value="" disabled selected></option> -->
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
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    $('#category, #category2, #category3').select2({
        // placeholder: "Pick categories",
        allowClear: true,
        tags: true,
        width: '100%'
    });
});
</script>


<!-- Add this script to your HTML, after including Bootstrap -->
<script>
    var bulkAddTabs = new bootstrap.Tab(document.getElementById('bulkAddTabs'));
    var bulkAddTabsContent = new bootstrap.Tab(document.getElementById('bulkAddTabsContent'));
    bulkAddTabs.show();

    bulkAddTabsContent.addEventListener('shown.bs.tab', function (event) {
    var tabContentId = event.target.getAttribute('href');
    if (!tabContentId) return;
    var tabContent = document.querySelector(tabContentId + ' form');
    if (tabContent) {
        tabContent.reset();
    }
});

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>