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


// Check if the 'merge_success' parameter is in the URL
if (isset($_GET['merge_success']) && $_GET['merge_success'] == 1) {
    $organizerMessage = "Subscribers merged successfully!";
} else {
    $organizerMessage = "";
}
// Fetch all events
$allEventsQuery = $conn->prepare("SELECT event_id, event_name FROM events");
$allEventsQuery->execute();
$allEventsResult = $allEventsQuery->get_result();
$allEvents = [];
while ($event = $allEventsResult->fetch_assoc()) {
    $allEvents[] = $event;
}

// Fetch all categories
$allCategoriesQuery = $conn->prepare("SELECT category_id, category_name FROM categories");
$allCategoriesQuery->execute();
$allCategoriesResult = $allCategoriesQuery->get_result();
$allCategories = [];
while ($category = $allCategoriesResult->fetch_assoc()) {
    $allCategories[] = $category;
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
            die('MySQL prepare error for phone query: ' . $conn->error);
        }
        $phoneQuery->bind_param("i", $subscriberID);
        $phoneQuery->execute();
        $phoneResult = $phoneQuery->get_result();
        $subscriberPhones = [];
        while ($phone = $phoneResult->fetch_assoc()) {
            $subscriberPhones[] = $phone['phone_number'];
        }
        $subscriberPhones = array_unique($subscriberPhones);
        $subscriberPhone = implode(', ', $subscriberPhones);

       // Fetch all emails associated with the subscriber
$emailQuery = $conn->prepare("SELECT email FROM emails WHERE subscriber_id = ?");
if ($emailQuery === false) {
    die('MySQL prepare error for email query: ' . $conn->error);
}
$emailQuery->bind_param("i", $subscriberID);
$emailQuery->execute();
$emailResult = $emailQuery->get_result();
$subscriberEmails = [];

while ($email = $emailResult->fetch_assoc()) {
    // Normalize the email to lowercase to ensure uniqueness
    $subscriberEmails[] = strtolower($email['email']);
}

// Use array_unique to remove duplicates
$subscriberEmails = array_unique($subscriberEmails);

// Convert the array back to a comma-separated string for display
$subscriberEmail = implode(', ', $subscriberEmails);


      // Fetch all designations and organizations associated with the subscriber
$doQuery = $conn->prepare("SELECT designation, organization FROM designation_organization WHERE subscriber_id = ?");
if ($doQuery === false) {
    die('MySQL prepare error for designation_organization query: ' . $conn->error);
}
$doQuery->bind_param("i", $subscriberID);
$doQuery->execute();
$doResult = $doQuery->get_result();

// Use an associative array to ensure unique designation-organization pairs
$uniquePairs = [];

// Normalize and add each pair
while ($row = $doResult->fetch_assoc()) {
    $designation = strtolower(trim($row['designation'] ?: '')); // Normalize designation (lowercase, trim whitespace)
    $organization = strtolower(trim($row['organization'] ?: '')); // Normalize organization (lowercase, trim whitespace)

    // Use a concatenated string as the key to ensure uniqueness
    $key = $designation . '|' . $organization;

    if (!isset($uniquePairs[$key])) {
        $uniquePairs[$key] = [
            'designation' => $row['designation'], // Keep original case for display
            'organization' => $row['organization'], // Keep original case for display
        ];
    }
}

$doQuery->close();

// Extract unique designations and organizations for display
$subscriberDesignations = array_column($uniquePairs, 'designation');
$subscriberOrganizations = array_column($uniquePairs, 'organization');




        // Fetch unique events attended by subscribers with the same phone number or email
        $eventsQuery = $conn->prepare("SELECT DISTINCT e.event_id, e.event_name, e.event_date
        FROM event_subscriber_mapping esm
        INNER JOIN events e ON esm.event_id = e.event_id
        WHERE esm.subscriber_id IN (
        SELECT s.subscriber_id
        FROM subscribers s
        LEFT JOIN phone_numbers pn ON s.subscriber_id = pn.subscriber_id
        LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
        WHERE pn.phone_number = ? OR e.email = ?
        )
        ORDER BY e.event_date DESC"); // Latest event first
        if ($eventsQuery === false) {
        die('MySQL prepare error for events query: ' . $conn->error);
        }
        $eventsQuery->bind_param("ss", $subscriberPhone, $subscriberEmail);
        $eventsQuery->execute();
$eventsResult = $eventsQuery->get_result();
$eventsAttended = [];

while ($event = $eventsResult->fetch_assoc()) {
    $eventUrl = "list?event_id=" . $event['event_id'];
    $eventsAttended[] = '<a href="' . $eventUrl . '" class="users-list-name" style="display: inline; text-decoration: underline; color: black;" onmouseover="this.style.textDecoration=\'none\'" onmouseout="this.style.textDecoration=\'underline\'">' . htmlspecialchars($event['event_name']) . '</a>';
}

$subscriber['events_attended'] = implode(', ', $eventsAttended);


       // Fetch the unique categories attended by the subscriber, including newly inserted ones 
$categoriesQuery = $conn->prepare("
SELECT DISTINCT c.category_id, c.category_name
FROM event_subscriber_mapping esm
INNER JOIN categories c ON esm.category_id = c.category_id
WHERE esm.subscriber_id = ?
");
if ($categoriesQuery === false) {
    die('MySQL prepare error for categories query: ' . $conn->error);
}
$categoriesQuery->bind_param("i", $subscriberID); // Bind subscriber ID for accurate results
$categoriesQuery->execute();
$categoriesResult = $categoriesQuery->get_result();
$categoriesAttended = [];

while ($category = $categoriesResult->fetch_assoc()) {
    $categoriesAttended[] = $category['category_id'];  // Store category_id instead of category_name
}
$subscriber['categories_attended'] = implode(',', $categoriesAttended);  // Store as comma-separated IDs for ease of use

// Fetch the unique organizers affiliated with the subscriber
$organizersQuery = $conn->prepare("
    SELECT DISTINCT o.organizer_id, o.organizer_name
    FROM event_subscriber_mapping esm
    INNER JOIN organizers o ON esm.organizer_id = o.organizer_id
    WHERE esm.subscriber_id = ?
");

if ($organizersQuery === false) {
    // Log the error and terminate the script
    die('MySQL prepare error for organizers query: ' . $conn->error);
}

// Bind subscriber ID for accurate results
$organizersQuery->bind_param("i", $subscriberID);

// Execute the query
if (!$organizersQuery->execute()) {
    // Log the error and terminate the script
    die('MySQL execution error for organizers query: ' . $organizersQuery->error);
}

// Retrieve the query results
$organizersResult = $organizersQuery->get_result();
$organizersAffiliated = [];

// Process the results
while ($organizer = $organizersResult->fetch_assoc()) {
    // Store organizer_id in the array
    $organizersAffiliated[] = $organizer['organizer_id'];
}

// Convert the array of organizer IDs to a comma-separated string
$subscriber['organizers_affiliated'] = implode(',', $organizersAffiliated);



    } else {
        header("Location: subscribers.php");
        exit();
    }
} else {
    header("Location: subscribers.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve edited subscriber details from the form
    $full_name = $_POST['full_name'];
    $phone_numbers = $_POST['phone_number'];
    $emails = $_POST['email'];
    $address = $_POST['address'];
    $designation = $_POST['designation']; // New field
    $organization = $_POST['organization']; // New field
    $selectedEvents = $_POST['events'] ?? [];
    $selectedCategories = $_POST['categories'] ?? [];

    // Update the subscriber details in the 'subscribers' table
    $updateStmt = $conn->prepare("UPDATE subscribers SET full_name = ?, address = ? WHERE subscriber_id = ?");
    $updateStmt->bind_param("ssi", $full_name, $address, $subscriberID);

    if ($updateStmt->execute()) {
        
        // Function to check for duplicate phone numbers and emails
function isDuplicate($conn, $table, $column, $value, $subscriberID = null) {
    $query = "SELECT $column FROM $table WHERE $column = ?";
    if ($subscriberID) {
        $query .= " AND subscriber_id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($subscriberID) {
        $stmt->bind_param("si", $value, $subscriberID);
    } else {
        $stmt->bind_param("s", $value);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0; // Returns true if duplicate found
}

// Function to handle insert/update for individual fields
function upsertField($conn, $table, $field, $values, $subscriberID, $fieldID) {
    // If values are empty, delete all records for this subscriber
    if (empty($values)) {
        $deleteStmt = $conn->prepare("DELETE FROM $table WHERE subscriber_id = ?");
        $deleteStmt->bind_param("i", $subscriberID);
        $deleteStmt->execute();
        return;
    }

    // Get all current IDs for this subscriber
    $query = $conn->prepare("SELECT $fieldID FROM $table WHERE subscriber_id = ?");
    $query->bind_param("i", $subscriberID);
    $query->execute();
    $result = $query->get_result();

    $existingIDs = [];
    while ($row = $result->fetch_assoc()) {
        $existingIDs[] = $row[$fieldID];
    }

    // Handle provided values
    foreach ($values as $value) {
        $value = trim($value);
        if (empty($value)) {
            continue; // Skip empty values
        }

        // Check if the record exists for this value
        $checkStmt = $conn->prepare("SELECT $fieldID FROM $table WHERE subscriber_id = ? AND $field = ?");
        $checkStmt->bind_param("is", $subscriberID, $value);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Record exists, remove it from the deletion list
            $row = $checkResult->fetch_assoc();
            $existingIDs = array_diff($existingIDs, [$row[$fieldID]]);
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO $table (subscriber_id, $field) VALUES (?, ?)");
            $insertStmt->bind_param("is", $subscriberID, $value);
            $insertStmt->execute();
        }
    }

    // Delete records for IDs not in the new values
    foreach ($existingIDs as $idToDelete) {
        $deleteStmt = $conn->prepare("DELETE FROM $table WHERE $fieldID = ?");
        $deleteStmt->bind_param("i", $idToDelete);
        $deleteStmt->execute();
    }
}

// Main processing
$errors = [];
$phone_numbers_to_insert = [];
$emails_to_insert = [];

// Check and upsert phone numbers (only insert non-duplicate numbers)
foreach ($phone_numbers as $phone) {
    if (empty($phone)) continue; // Skip empty values
    if (!isDuplicate($conn, 'phone_numbers', 'phone_number', $phone, $subscriberID)) {
        $phone_numbers_to_insert[] = $phone;
    } else {
        $errors[] = "Phone number $phone already exists in the database.";
    }
}

// Check and upsert emails (only insert non-duplicate emails)
foreach ($emails as $email) {
    if (empty($email)) continue; // Skip empty values
    if (!isDuplicate($conn, 'emails', 'email', $email, $subscriberID)) {
        $emails_to_insert[] = $email;
    } else {
        $errors[] = "Email $email already exists in the database.";
    }
}

// Process phone numbers and emails even if there are errors
upsertField($conn, 'phone_numbers', 'phone_number', $phone_numbers_to_insert, $subscriberID, 'phone_id');
upsertField($conn, 'emails', 'email', $emails_to_insert, $subscriberID, 'email_id');

function upsertDesignationOrganization($conn, $table, $designations, $organizations, $subscriberID) {
    // Ensure both arrays have the same number of elements
    $count = max(count($designations), count($organizations));
    
    // Fetch existing IDs and records for this subscriber
    $query = $conn->prepare("SELECT des_org_id, designation, organization FROM $table WHERE subscriber_id = ?");
    $query->bind_param("i", $subscriberID);
    $query->execute();
    $result = $query->get_result();
    
    $existingIDs = [];
    $existingRecords = [];
    while ($row = $result->fetch_assoc()) {
        $existingIDs[] = $row['des_org_id'];
        $existingRecords[] = ['designation' => $row['designation'], 'organization' => $row['organization']];
    }

    for ($i = 0; $i < $count; $i++) {
        $designation = isset($designations[$i]) ? trim($designations[$i]) : '';
        $organization = isset($organizations[$i]) ? trim($organizations[$i]) : '';

        if (empty($designation) && empty($organization)) {
            continue; // Skip empty rows
        }

        // Check if the record already exists (case-insensitive check)
        $found = false;
        foreach ($existingRecords as $index => $existingRecord) {
            // Compare both designation and organization case-sensitively (allow for case changes)
            if ($existingRecord['designation'] === $designation && $existingRecord['organization'] === $organization) {
                // Record exists, no need to insert, mark as found
                $existingIDs = array_diff($existingIDs, [$existingIDs[$index]]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Insert new record if no match is found
            $insertStmt = $conn->prepare(
                "INSERT INTO $table (subscriber_id, designation, organization) VALUES (?, ?, ?)"
            );
            $insertStmt->bind_param("iss", $subscriberID, $designation, $organization);
            $insertStmt->execute();
        } else {
            // If the record exists, perform an update (even if only the case changed)
            $updateStmt = $conn->prepare(
                "UPDATE $table SET designation = ?, organization = ? WHERE subscriber_id = ? AND des_org_id = ?"
            );
            $updateStmt->bind_param("ssii", $designation, $organization, $subscriberID, $existingIDs[$index]);
            $updateStmt->execute();
        }
    }

    // Delete old records if any
    foreach ($existingIDs as $idToDelete) {
        $deleteStmt = $conn->prepare("DELETE FROM $table WHERE des_org_id = ?");
        $deleteStmt->bind_param("i", $idToDelete);
        $deleteStmt->execute();
    }
}


upsertDesignationOrganization($conn, 'designation_organization', $designation, $organization, $subscriberID);


// Log errors if any
if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log($error);
    }
} else {
    echo "All operations completed successfully.";
}

// Start a transaction to ensure atomicity
$conn->begin_transaction();

// Check for existing categories
$existingCategoriesQuery = $conn->prepare("SELECT category_id FROM event_subscriber_mapping WHERE subscriber_id = ?");
$existingCategoriesQuery->bind_param("i", $subscriberID);
$existingCategoriesQuery->execute();
$existingCategoriesResult = $existingCategoriesQuery->get_result();
$existingCategories = [];
while ($row = $existingCategoriesResult->fetch_assoc()) {
    $existingCategories[] = $row['category_id'];
}

// Add new categories
foreach ($selectedCategories as $category) {
    if (!in_array($category, $existingCategories)) {
        $insertCategoryStmt = $conn->prepare("INSERT INTO event_subscriber_mapping (subscriber_id, category_id) VALUES (?, ?)");
        $insertCategoryStmt->bind_param("ii", $subscriberID, $category);
        if (!$insertCategoryStmt->execute()) {
            echo "Error: " . $insertCategoryStmt->error;
            $conn->rollback(); // Rollback on error
            exit;
        }
    }
}

// Remove unselected categories
foreach ($existingCategories as $category) {
    if (!in_array($category, $selectedCategories)) {
        $deleteCategoryStmt = $conn->prepare("DELETE FROM event_subscriber_mapping WHERE subscriber_id = ? AND category_id = ?");
        $deleteCategoryStmt->bind_param("ii", $subscriberID, $category);
        if (!$deleteCategoryStmt->execute()) {
            echo "Error: " . $deleteCategoryStmt->error;
            $conn->rollback(); // Rollback on error
            exit;
        }
    }
}

// Commit the transaction if all queries were successful
$conn->commit();


// Redirect back to subscribers page after updating
header("Location: subscribers.php");
exit();
} else {
// Error occurred while updating the subscriber details
$errorMessage = "Error updating subscriber details: " . $conn->error;
}
}
$subscriberDesignations = isset($subscriberDesignations) ? $subscriberDesignations : [];
$subscriberOrganizations = isset($subscriberOrganizations) ? $subscriberOrganizations : [];

// Calculate the maximum count
$maxCount = max(count($subscriberDesignations), count($subscriberOrganizations));


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscriber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>



div {
    white-space: normal; /* Default value, allows wrapping inline */
}

.users-list-name {
    display: inline; /* Ensures links appear inline */
    white-space: nowrap; /* Prevents breaking into multiple lines */
}
.users-list-name, .users-list-date {
    display: inline;
}

    /* Change the background color of selected options */
.select2-selection__choice {
    background-color: #f9e79f !important;  /* Example: Green background */
    color: black !important;  /* White text */
}

/* Optionally, change the hover color of selected options */
.select2-selection__choice:hover {
    background-color: #f9e79f !important;  /* Darker green on hover */
}

/* Change the border color for the selected items */
.select2-selection__choice {
    border: 1px solid #f9e79f !important;  /* Green border */
}

/* Change the background color of the selected option in the dropdown */
.select2-results__option[aria-selected="true"] {
    background-color: #f9e79f !important;  /* Example: Green background */
    color: black !important;  /* White text */
}

/* Change the background color of the hovered option */
.select2-results__option:hover {
    background-color: #f9e79f !important;  /* Darker green on hover */
    color: black !important;  /* White text on hover */
}
/* Style for the clear icon in Select2 */
.select2-selection__clear {
    color: black !important; /* Change to black or any visible color */
    font-size: 18px;         /* Adjust size if needed */
    cursor: pointer;         /* Ensure it appears clickable */
}
/* Target the cross (close) button inside the selected options */
.select2-selection__choice__remove {
    color: black !important;  /* Set cross button to black */
    font-weight: bold;        /* Make it stand out */
    cursor: pointer;          /* Ensure it looks clickable */
    margin-right: 5px;        /* Adjust spacing for better alignment */
}

/* Add hover effect for the cross button */
.select2-selection__choice__remove:hover {
    color: red !important;    /* Optional: Cross turns red on hover */
}
input {
    display: none;
}

/* Remove underline from add and remove buttons */
.add-phone, .remove-phone, .add-email, .remove-email, .add-row, .remove-row {
    text-decoration: none !important;
}

/* Optional: Add a hover effect if you want (like changing color on hover) */
.add-phone:hover, .remove-phone:hover, .add-email:hover, .remove-email:hover, .add-row:hover, .remove-row:hover {
    text-decoration: none; /* Ensure no underline on hover */
    color: #007bff; /* Change the color on hover (optional) */
}

.custom-btn {
        font-size: 20px;
        padding: 16px 26px;
    }
/* Remove underline from event links by default */
.event-link {
    text-decoration: none !important;
}

/* Remove underline when hovering over the event link */
.event-link:hover {
    text-decoration: none !important; /* Ensure no underline on hover */
}

#organizerNameError {
        color: red;
        font-size: 14px;
    }
/* Ensure no space between label and input */
h5.mb-0,
.form-label.mb-0 {
    margin-bottom: 0 !important; /* Forcefully remove any margin */
}

.form-control {
    margin-top: 0 !important; /* Remove margin from the top of the input field */
    padding-top: 0 !important; /* Remove padding from the top */
}

.form-group {
    margin-bottom: 0 !important; /* Remove bottom margin between form groups */
}


</style>
</head>

<body>
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">
        <!-- <h1>Edit Subscriber Details</h1> -->

        <form method="POST" class="container mt-4" >
            <br>

                        <!-- Display the merge success message if merge_success=1 is in the URL -->
       <div id="organizerResponseMessage">
            <?php
            if (!empty($organizerMessage)) {
                echo '<div class="alert alert-info">' . $organizerMessage . '</div>';
            }
            ?>
        </div>
    <div class="row">
        <!-- Name -->
        <?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>


<div class="row">
<div class="col-md-6 mb-2">
    <label for="full_name" class="form-label mb-0" style="font-size: 1.25rem; font-weight: 500;">Full Name</label><br>
    <input type="text" name="full_name" id="full_name" class="form-control mt-0" value="<?php echo htmlspecialchars($subscriber['full_name']); ?>" required>
</div>
        </div>


<div class="col-md-12 mt-4" id="phonenumber_email">
<div class="row">
<div class="col-md-6"> 
    <!-- Label instead of h5 for consistent styling -->
    <label for="phone_number" class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Phone Number</label>
    <?php if (!empty($subscriberPhones)): ?>
        <?php foreach ($subscriberPhones as $index => $phone): ?>
            <div class="mb-2 d-flex align-items-center" id="phone-row-<?php echo $index; ?>">
                <label for="phone_number" class="form-label mb-0"></label>
                <input 
                    type="text" 
                    name="phone_number[]" 
                    id="phone_number" 
                    class="form-control mt-0" 
                    value="<?php echo htmlspecialchars($phone); ?>" 
                    pattern="^\d{10}$" 
                    title="Phone number must be exactly 10 digits" 
                    required>
                <button type="button" class="btn btn-link ms-2 add-phone">+</button>
                <button type="button" class="btn btn-link ms-2 remove-phone">-</button>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="mb-2 d-flex align-items-center">
            <label for="phone_number" class="form-label mb-0"></label>
            <input 
                type="text" 
                name="phone_number[]" 
                id="phone_number" 
                class="form-control mt-0" 
                placeholder="Enter phone number" 
                pattern="^\d{10}$" 
                title="Phone number must be exactly 10 digits" 
                required>
            <button type="button" class="btn btn-link ms-2 add-phone">+</button>
            <button type="button" class="btn btn-link ms-2 remove-phone">-</button>
        </div>
    <?php endif; ?>
</div>


    
    <div class="col-md-6">
    <label for="email" class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Email</label>
        <?php if (!empty($subscriberEmails)): ?>
            <?php foreach ($subscriberEmails as $index => $email): ?>
                <div class="mb-2 d-flex align-items-center" id="email-row-<?php echo $index; ?>">
                    <label for="email" class="form-label"></label>
                    <input type="text" name="email[]" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" >
                    <button type="button" class="btn btn-link ms-2 add-email">+</button>
                    <button type="button" class="btn btn-link ms-2 remove-email">-</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="mb-2 d-flex align-items-center">
                <label for="email" class="form-label"></label>
                <input type="text" name="email[]" id="email" class="form-control" placeholder="Enter email">
                <button type="button" class="btn btn-link ms-2 add-email">+</button>
                <button type="button" class="btn btn-link ms-2 remove-email">-</button>
            </div>
        <?php endif; ?>
    </div>
</div>
        </div>


        <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to handle the addition of a new input field
        function addField(event, fieldName) {
            const container = event.target.closest('.mb-2').parentNode;
            const newField = container.querySelector('.mb-2').cloneNode(true);

            // Reset the value of the new field and ensure it is empty
            const input = newField.querySelector('input');
            input.value = '';

            // Append the new field to the container
            container.appendChild(newField);
        }

        // Function to handle the removal of an input field
        function removeField(event) {
            const container = event.target.closest('.mb-2');
            // Only remove the field if there are more than one
            if (container.parentNode.children.length > 1) {
                container.remove();
            }
        }

        // Restrict phone number input to 10 digits
        function restrictTo10Digits(event) {
            const input = event.target;

            if (input.name === 'phone_number[]') {
                // Allow only numbers and limit to 10 digits
                input.value = input.value.replace(/\D/g, '').slice(0, 10);
            }
        }

        // Use event delegation to handle clicks on the "Add" buttons
        document.body.addEventListener('click', function(event) {
            if (event.target.classList.contains('add-phone')) {
                addField(event, 'phone_number');
            }
            if (event.target.classList.contains('add-email')) {
                addField(event, 'email');
            }

            // Handle the "Remove" buttons
            if (event.target.classList.contains('remove-phone')) {
                removeField(event);
            }
            if (event.target.classList.contains('remove-email')) {
                removeField(event);
            }
        });

        // Attach the input event listener to restrict to 10 digits
        document.body.addEventListener('input', restrictTo10Digits);
    });
</script>

<div class="col-md-12 mt-4" id="designation-organization-wrapper">
    <div class="row">
        <!-- Designations Column -->
        <div class="col-md-6" id="designations-container">
        <label class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Designation</label>
            <?php for ($index = 0; $index < $maxCount; $index++): ?>
                <?php
                    $designation = isset($subscriberDesignations[$index]) ? htmlspecialchars($subscriberDesignations[$index]) : '';
                ?>
                <div class="designation-row mb-2" id="designation-row-<?php echo $index; ?>">
                    <input type="text" name="designation[<?php echo $index; ?>]" class="form-control" value="<?php echo $designation; ?>" placeholder="Enter designation">
                </div>
            <?php endfor; ?>

            <!-- Default empty row if no data exists -->
            <?php if ($maxCount === 0): ?>
                <div class="designation-row mb-2" id="designation-row-0">
                    <input type="text" name="designation[0]" class="form-control" placeholder="Enter designation">
                </div>
            <?php endif; ?>
        </div>

        <!-- Organizations Column -->
        <div class="col-md-6" id="organizations-container">
        <label class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Organization</label>
            <?php for ($index = 0; $index < $maxCount; $index++): ?>
                <?php
                    $organization = isset($subscriberOrganizations[$index]) ? htmlspecialchars($subscriberOrganizations[$index]) : '';
                ?>
                <div class="organization-row mb-2 d-flex align-items-center" id="organization-row-<?php echo $index; ?>">
                    <input type="text" name="organization[<?php echo $index; ?>]" class="form-control me-2" value="<?php echo $organization; ?>" placeholder="Enter organization">
                    <button type="button" class="btn btn-link add-row">+</button>
                    <button type="button" class="btn btn-link remove-row">-</button>
                
                </div>
            <?php endfor; ?>

            <!-- Default empty row if no data exists -->
            <?php if ($maxCount === 0): ?>
                <div class="organization-row mb-2 d-flex align-items-center" id="organization-row-0">
                    <input type="text" name="organization[0]" class="form-control me-2" placeholder="Enter organization">
                    <button type="button" class="btn btn-link add-row">+</button>
                    <button type="button" class="btn btn-link remove-row">-</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
   $(document).ready(function () {
    // Add new row
    $(document).on("click", ".add-row", function () {
        // Get the next index
        const newIndex = $(".designation-row").length;

        // Append new rows to both columns
        $("#designations-container").append(`
            <div class="designation-row mb-2" id="designation-row-${newIndex}">
                <input type="text" name="designation[${newIndex}]" class="form-control" placeholder="Enter designation">
            </div>
        `);

        $("#organizations-container").append(`
            <div class="organization-row mb-2 d-flex align-items-center" id="organization-row-${newIndex}">
                <input type="text" name="organization[${newIndex}]" class="form-control me-2" placeholder="Enter organization">
                <button type="button" class="btn btn-link add-row">+</button>
                <button type="button" class="btn btn-link remove-row">-</button>
            </div>
        `);
    });

    // Remove the specific row (both designation and organization)
    $(document).on("click", ".remove-row", function () {
        // Find the parent row of the clicked button
        const parentRow = $(this).closest('.organization-row');

        // Remove the clicked row and its corresponding designation row
        const rowIndex = parentRow.attr('id').split('-')[2]; // Get the row index
        $(`#designation-row-${rowIndex}`).remove();  // Remove the corresponding designation row
        parentRow.remove(); // Remove the organization row
    });
});

</script>


<div class="col-md-12 mt-4" id="category-organization-wrapper">
<div class="row">
    <!-- MultiSelect for Categories -->
    <div class="col-md-6 mb-3">
        <label for="categories" class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Categories</label>
        <select name="categories[]" id="categories" class="form-control select2" multiple="multiple">
        <?php 
// Ensure $categoriesAttended is an array, even if categories_attended is NULL
$categoriesAttended = !empty($subscriber['categories_attended']) 
                      ? explode(',', $subscriber['categories_attended']) 
                      : [];

// Sort categories alphabetically by category name
usort($allCategories, function($a, $b) {
    return strcmp($a['category_name'], $b['category_name']);
});

foreach ($allCategories as $category) { 
?>
    <option value="<?php echo $category['category_id']; ?>" 
            <?php echo in_array($category['category_id'], $categoriesAttended) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($category['category_name']); ?>
    </option>
<?php } ?>

        </select>
    </div>

<?php if (!empty($subscriber['organizers_affiliated'])): ?>
    <div class="col-md-6">

        <label class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Affiliated Organizers</label><br>
            <?php 
            // Fetch organizer names using IDs
            $organizerIDs = explode(',', $subscriber['organizers_affiliated']); // Assuming IDs are comma-separated
            $totalOrganizers = count($organizerIDs);
            $j = 1;

            foreach ($organizerIDs as $organizerID) {
                // Prepare the query to get organizer name
                $organizerQuery = $conn->prepare("SELECT organizer_name FROM organizers WHERE organizer_id = ?");
                if ($organizerQuery === false) {
                    die('MySQL prepare error: ' . $conn->error);
                }
                $organizerID = trim($organizerID); // Ensure no extra whitespace
                $organizerQuery->bind_param("i", $organizerID); // Bind the organizer ID
                $organizerQuery->execute();
                $result = $organizerQuery->get_result();
                $organizerName = $result->fetch_assoc()['organizer_name'] ?? 'Unknown Organizer';

                echo '<a href="list?organizer=' . urlencode($organizerID) . '" style="text-decoration: underline; color: black;" onmouseover="this.style.textDecoration=\'none\'" onmouseout="this.style.textDecoration=\'underline\'">' . htmlspecialchars($organizerName) . '</a>';

                // Add comma only if this is not the last organizer
                if ($j < $totalOrganizers) {
                    echo ', ';
                }
                $j++; // Increment counter
            }
            ?>
        </div>
        </div>
        </div>
<?php endif; ?>



<?php if (!empty($subscriber['events_attended'])): ?>
    <div class="row">
        <div class="col-md-12">
        <label class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Events Attended</label><br>
            <?php 
            // Output the events as HTML links
            $events = explode(',', $subscriber['events_attended']); // assuming events are comma-separated
            $totalEvents = count($events); // Get the total number of events
            $i = 1; // Initialize counter
            foreach ($events as $event) {
                echo '<a href="#" class="event-link" style="text-decoration: underline; color: black;" onmouseover="this.style.textDecoration=\'none\'" onmouseout="this.style.textDecoration=\'underline\'">' . trim($event) . '</a>';

                // Add comma only if this is not the last event
                if ($i < $totalEvents) {
                    echo ', ';
                }
                $i++; // Increment counter
            }
            ?>
        </div>
    </div>
<?php endif; ?>



    </div>

    <div class="row">
    <!-- Submit Button -->
    <div class="col-md-12 d-flex justify-content-center">
        <button type="submit" class="btn btn-success custom-btn">Save</button>
    </div>
</div>


</form>

<script>
    $(document).ready(function() {
        // Initialize Select2 for categories
        $('#categories').select2({
            placeholder: "Pick value",   // Placeholder text for categories
            allowClear: true,           // Option to clear selection
            tags: true,                 // Enable tagging
            width: '100%'               // Ensure it fits the container
        });

    });
</script>
</body>

</html>
