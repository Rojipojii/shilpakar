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

// Fetch all events with event_date
$allEventsQuery = $conn->prepare("SELECT event_id, event_name, event_date FROM events");
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

// Fetch all emails along with 'hidden' and 'does_not_exist' status associated with the subscriber
$emailQuery = $conn->prepare("SELECT email, hidden, does_not_exist FROM emails WHERE subscriber_id = ?");
if ($emailQuery === false) {
    die('MySQL prepare error for email query: ' . $conn->error);
}
$emailQuery->bind_param("i", $subscriberID);
$emailQuery->execute();
$emailResult = $emailQuery->get_result();

$subscriberEmails = [];
$hiddenEmails = []; // Store emails marked as hidden
$nonexistentEmails = []; // Store emails marked as non-existent

while ($row = $emailResult->fetch_assoc()) {
    // Normalize the email to lowercase for consistency
    $email = strtolower($row['email']);

    if ($row['does_not_exist'] == 1) {
        $nonexistentEmails[] = $email; // Add non-existent emails to a separate array
    } elseif ($row['hidden'] == 1) {
        $hiddenEmails[] = $email; // Add hidden emails to the separate array
    } else {
        $subscriberEmails[] = $email; // Add non-hidden emails to the main array
    }
}

// Remove duplicates
$subscriberEmails = array_unique($subscriberEmails);
$hiddenEmails = array_unique($hiddenEmails);
$nonexistentEmails = array_unique($nonexistentEmails);

// Convert the arrays to comma-separated strings if needed
$subscriberEmailString = implode(', ', $subscriberEmails);
$hiddenEmailString = implode(', ', $hiddenEmails);
$nonexistentEmailString = implode(', ', $nonexistentEmails);


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
$eventsQuery = $conn->prepare("
SELECT DISTINCT e.event_id, e.event_name, e.event_date
FROM event_subscriber_mapping esm
INNER JOIN events e ON esm.event_id = e.event_id
WHERE esm.subscriber_id = ?
");
if ($eventsQuery === false) {
die('MySQL prepare error for events query: ' . $conn->error);
}
$eventsQuery->bind_param("i", $subscriberID);
$eventsQuery->execute();       
$eventsResult = $eventsQuery->get_result();
$eventsAttended = [];

while ($event = $eventsResult->fetch_assoc()) {
        $eventsAttended[] = $event['event_id']; 
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
    $phone_numbers = $_POST['phone_number'] ;
    $emails = $_POST['email'];
    $address = $_POST['address'];
    $designation = $_POST['designation'] ?? []; // New field
    $organization = $_POST['organization'] ?? []; // New field
    $selectedEvents = $_POST['events_attended'] ?? [];
    $selectedCategories = $_POST['categories'] ?? [];
    

    // Update the subscriber details in the 'subscribers' table
    $updateStmt = $conn->prepare("UPDATE subscribers SET full_name = ?, address = ? WHERE subscriber_id = ?");
    $updateStmt->bind_param("ssi", $full_name, $address, $subscriberID);

    if ($updateStmt->execute()) {
        
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

                // Insert new record
                $insertStmt = $conn->prepare("INSERT INTO $table (subscriber_id, $field) VALUES (?, ?)");
                $insertStmt->bind_param("is", $subscriberID, $value);
                $insertStmt->execute();
            }

            // Delete records for IDs not in the new values
            foreach ($existingIDs as $idToDelete) {
                $deleteStmt = $conn->prepare("DELETE FROM $table WHERE $fieldID = ?");
                $deleteStmt->bind_param("i", $idToDelete);
                $deleteStmt->execute();
            }
        }

        // Process phone numbers and emails without checking for duplicates
        upsertField($conn, 'phone_numbers', 'phone_number', $phone_numbers, $subscriberID, 'phone_id');
        upsertField($conn, 'emails', 'email', $emails, $subscriberID, 'email_id');

        function upsertDesignationOrganization($conn, $table, $designations, $organizations, $subscriberID) {
            // Ensure both arrays have the same number of elements
            $count = max(count($designations), count($organizations));
            
            // Fetch existing IDs and records for this subscriber
            $query = $conn->prepare("SELECT des_org_id, designation, organization FROM $table WHERE subscriber_id = ?");
            $query->bind_param("i", $subscriberID);
            $query->execute();
            $result = $query->get_result();
            
            $existingIDs = [];
            while ($row = $result->fetch_assoc()) {
                $existingIDs[] = $row['des_org_id'];
            }
            $result->close();
        
            // Track if any valid records are inserted
            $hasValidData = false;
        
            for ($i = 0; $i < $count; $i++) {
                $designation = isset($designations[$i]) ? trim($designations[$i]) : '';
                $organization = isset($organizations[$i]) ? trim($organizations[$i]) : '';
        
                if (!empty($designation) || !empty($organization)) {
                    // Insert new record if valid data exists
                    $insertStmt = $conn->prepare(
                        "INSERT INTO $table (subscriber_id, designation, organization) VALUES (?, ?, ?)"
                    );
                    $insertStmt->bind_param("iss", $subscriberID, $designation, $organization);
                    $insertStmt->execute();
                    $insertStmt->close();
                    $hasValidData = true;
                }
            }
        
            // Delete old records only if we successfully inserted valid new data or if no data remains
            if (!$hasValidData || $count > 0) {
                foreach ($existingIDs as $idToDelete) {
                    $deleteStmt = $conn->prepare("DELETE FROM $table WHERE des_org_id = ?");
                    $deleteStmt->bind_param("i", $idToDelete);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }
            }
        
            // If no valid data was provided and table cleanup occurred, handle accordingly (optional)
            if (!$hasValidData && empty($existingIDs)) {
                // Handle cases where no records remain (if necessary)
            }
        }       

        // Insert designation and organization
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

// Fetch existing categories for the subscriber
$existingCategoriesQuery = $conn->prepare("
    SELECT category_id 
    FROM event_subscriber_mapping 
    WHERE subscriber_id = ?
");
$existingCategoriesQuery->bind_param("i", $subscriberID);
$existingCategoriesQuery->execute();
$existingCategoriesResult = $existingCategoriesQuery->get_result();

$existingCategories = [];
while ($row = $existingCategoriesResult->fetch_assoc()) {
    $existingCategories[] = $row['category_id'];
}

// Fetch existing events for the subscriber
$existingEventsQuery = $conn->prepare("
    SELECT event_id 
    FROM event_subscriber_mapping 
    WHERE subscriber_id = ?
");
$existingEventsQuery->bind_param("i", $subscriberID);
$existingEventsQuery->execute();
$existingEventsResult = $existingEventsQuery->get_result();

$existingEvents = [];
while ($row = $existingEventsResult->fetch_assoc()) {
    $existingEvents[] = $row['event_id'];
}

// Adding new categories
foreach ($selectedCategories as $categoryID) {
    if (!in_array($categoryID, $existingCategories)) {
        $insertCategoryStmt = $conn->prepare("
            INSERT INTO event_subscriber_mapping (subscriber_id, category_id) 
            VALUES (?, ?)
        ");
        $insertCategoryStmt->bind_param("ii", $subscriberID, $categoryID);
        if (!$insertCategoryStmt->execute()) {
            throw new Exception("Error inserting category_id $categoryID: " . $insertCategoryStmt->error);
        }
        $existingCategories[] = $categoryID; // Update the existing categories list
    }
}

// Adding new events
$eventDetailsQuery = $conn->prepare("
    SELECT event_id, event_name, event_date, organizer_id 
    FROM events 
    WHERE event_id = ?
");
foreach ($selectedEvents as $eventID) {
    if (!in_array($eventID, $existingEvents)) {
        // Fetch event details
        $eventDetailsQuery->bind_param("i", $eventID);
        $eventDetailsQuery->execute();
        $eventDetailsResult = $eventDetailsQuery->get_result();

        if ($eventDetails = $eventDetailsResult->fetch_assoc()) {
            // Extract event details
            $eventID = $eventDetails['event_id'];
            $organizerID = $eventDetails['organizer_id'];

            // Insert the event without involving categories
            $insertEventStmt = $conn->prepare("
                INSERT INTO event_subscriber_mapping (subscriber_id, event_id, organizer_id) 
                VALUES (?, ?, ?)
            ");
            $insertEventStmt->bind_param("iii", $subscriberID, $eventID, $organizerID);
            if (!$insertEventStmt->execute()) {
                throw new Exception("Error inserting event_id {$eventDetails['event_id']}: " . $insertEventStmt->error);
            }
        } else {
            throw new Exception("Event ID $eventID not found in events table.");
        }
    }
}


foreach ($existingCategories as $categoryID) {
    if (!in_array($categoryID, $selectedCategories)) {
        // Directly delete the category from event_subscriber_mapping
        $deleteCategoryStmt = $conn->prepare("
            DELETE FROM event_subscriber_mapping 
            WHERE subscriber_id = ? AND category_id = ?
        ");
        $deleteCategoryStmt->bind_param("ii", $subscriberID, $categoryID);
        if (!$deleteCategoryStmt->execute()) {
            throw new Exception("Error deleting category_id $categoryID: " . $deleteCategoryStmt->error);
        }
    }
}


// Remove unselected events
foreach ($existingEvents as $eventID) {
    if (!in_array($eventID, $selectedEvents)) {
        $deleteEventStmt = $conn->prepare("
            DELETE FROM event_subscriber_mapping 
            WHERE subscriber_id = ? AND event_id = ?
        ");
        $deleteEventStmt->bind_param("ii", $subscriberID, $eventID);
        if (!$deleteEventStmt->execute()) {
            throw new Exception("Error deleting event_id $eventID: " . $deleteEventStmt->error);
        }
    }
}

// Commit the transaction if all queries were successful
$conn->commit();



// After form submission, check if the user came from the merge page
if (isset($_SESSION['from_merge_page']) && $_SESSION['from_merge_page'] === true) {
    unset($_SESSION['from_merge_page']); // Clear session variable
    header("Location: merge.php"); // Redirect to merge page
    exit(); // Ensure no further code is executed
}
// If no session variables are set, redirect to subscribers.php or another default page
else {
    header("Location: dashboard.php");
    exit();
}
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
.select2-container {
    margin-left: 0 !important;
}
    /* Change the background color of selected options */
.select2-selection__choice {
    background-color: #f9e79f !important;  /* Example: Green background */
    color: black !important;  /* White text */
    border: 1px solid #f9e79f !important;  /* Green border */
    margin-right: 10px;  /* Adjust the margin as needed */
    margin-bottom: 5px;  /* Optional: Add space below the options */
}

/* Optionally, change the hover color of selected options */
.select2-selection__choice:hover {
    background-color: #f9e79f !important;  /* Darker green on hover */
}

.select2-selection__rendered {
    padding-left: 0 !important;
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

.error-message {
            color: red;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .hidden-email {
        color: red; /* Hidden emails in red */
        font-weight: bold;
    }
    .regular-email {
        color: black; /* Regular emails in default color */
    }
</style>
</head>

<body>
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">
        <!-- <h1>Edit Subscriber Details</h1> -->

        <form method="POST" class="container mt-4" onsubmit="return validateForm()">
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
            <?php
                // Check the length of the phone number
                $phoneLength = strlen($phone);
                $phoneClass = '';
                if ($phoneLength < 10) {
                    $phoneClass = 'text-danger';  // Red color for short phone numbers
                } elseif ($phoneLength > 10) {
                    $phoneClass = 'text-danger';  // Yellow color for long phone numbers
                }
            ?>
            <div class="mb-2 d-flex align-items-center" id="phone-row-<?php echo $index; ?>">
                <label for="phone_number" class="form-label mb-0"></label>
                <input 
                    type="text" 
                    name="phone_number[]" 
                    id="phone_number" 
                    class="form-control mt-0 <?php echo $phoneClass; ?>" 
                    value="<?php echo htmlspecialchars($phone); ?>" 
                    pattern="^\d{10}$" 
                    title="Phone number must be exactly 10 digits" 
                >
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
            >
            <button type="button" class="btn btn-link ms-2 add-phone">+</button>
            <button type="button" class="btn btn-link ms-2 remove-phone">-</button>
        </div>
    <?php endif; ?>
</div>



<div class="col-md-6">
    <label for="email" class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Email</label>

    <?php
    // Merge all email statuses into one list to loop over
    $allEmails = array_merge($subscriberEmails, $hiddenEmails, $nonexistentEmails);
    ?>

    <?php if (!empty($allEmails)): ?>
        <?php foreach ($allEmails as $index => $email): ?>
            <?php
                $emailEscaped = htmlspecialchars($email);
                $isHidden = in_array($email, $hiddenEmails);
                $doesNotExist = in_array($email, $nonexistentEmails);
            ?>
            <div class="mb-2 d-flex align-items-center" id="email-row-<?php echo $index; ?>">
                <input type="text" name="email[]" class="form-control regular-email" 
                       value="<?php echo $emailEscaped; ?>" 
                       <?php 
                       if ($isHidden) {
                           echo 'style="color: red; font-style: italic;" readonly';
                       } elseif ($doesNotExist) {
                           echo 'style="color: blue; font-style: italic;" readonly';
                       }
                       ?>>

                <!-- Toggle Email Visibility Button -->
                <button type="button" id="emailToggleBtn_<?php echo $index; ?>"
                        class="btn btn-sm"
                        onclick="toggleEmailVisibility('<?php echo $emailEscaped; ?>', <?php echo $index; ?>)">
                    <i class="bi bi-envelope-exclamation" 
                       style="color: <?php echo $isHidden ? 'red' : 'grey'; ?>;"
                       title="Mailbox Full"></i>
                </button>

                <!-- Email Doesn't Exist Button -->
                <button type="button" id="emailDoesNotExistBtn_<?php echo $index; ?>"
                        class="btn btn-sm"
                        onclick="toggleEmailNonExistence('<?php echo $emailEscaped; ?>', <?php echo $index; ?>)">
                    <i class="bi bi-envelope-slash" 
                       style="color: <?php echo $doesNotExist ? 'blue' : 'grey'; ?>;"
                       title="Email Doesn’t Exist/Unsubscribed"></i>
                </button>

                <button type="button" class="btn btn-link ms-2 add-email">+</button>
                <button type="button" class="btn btn-link ms-2 remove-email">-</button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($allEmails)): ?>
        <!-- Placeholder for new email input -->
        <div class="mb-2 d-flex align-items-center">
            <input type="text" name="email[]" class="form-control" placeholder="Enter email">
            <button type="button" class="btn btn-link ms-2 add-email">+</button>
            <button type="button" class="btn btn-link ms-2 remove-email">-</button>
        </div>
    <?php endif; ?>
</div>



<script>
function toggleEmailVisibility(email, index) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_email_visibility.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var inputField = document.querySelector(`#email-row-${index} input`);
                    var visibilityIcon = document.querySelector(`#emailToggleBtn_${index} i`);
                    var visibilityButton = document.querySelector(`#emailToggleBtn_${index}`);
                    var nonexistentIcon = document.querySelector(`#emailDoesNotExistBtn_${index} i`);
                    var nonexistentButton = document.querySelector(`#emailDoesNotExistBtn_${index}`);

                    // Toggle visibility state
                    var isHidden = inputField.style.color === "red";

                    if (isHidden) {
                        // Set email to visible
                        inputField.removeAttribute("readonly");
                        inputField.style.color = "";
                        inputField.style.fontStyle = "";
                        visibilityIcon.style.color = "grey"; // Grey icon when visible
                        visibilityButton.classList.remove("btn-outline-danger");
                        visibilityButton.classList.add("btn-outline-secondary"); // Grey button
                    } else {
                        // Set email to hidden
                        inputField.setAttribute("readonly", "true");
                        inputField.style.color = "red";
                        inputField.style.fontStyle = "italic";
                        visibilityIcon.style.color = "red"; // Red icon when hidden
                        visibilityButton.classList.remove("btn-outline-secondary");
                        visibilityButton.classList.add("btn-outline-danger"); // Red button

                        // Ensure "nonexistent" is turned off
                        nonexistentIcon.style.color = "grey";
                        nonexistentButton.classList.remove("btn-outline-primary");
                        nonexistentButton.classList.add("btn-outline-secondary"); // Grey button
                    }
                } else {
                    console.error("Server response indicates failure:", response.message);
                }
            } catch (error) {
                console.error("Error parsing server response:", error);
            }
        }
    };

    xhr.send("email=" + encodeURIComponent(email) + "&toggle=visibility");
}


function toggleEmailNonExistence(email, index) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_email_visibility.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var inputField = document.querySelector(`#email-row-${index} input`);
                    var nonexistentIcon = document.querySelector(`#emailDoesNotExistBtn_${index} i`);
                    var nonexistentButton = document.querySelector(`#emailDoesNotExistBtn_${index}`);
                    var visibilityIcon = document.querySelector(`#emailToggleBtn_${index} i`);
                    var visibilityButton = document.querySelector(`#emailToggleBtn_${index}`);

                    if (inputField.style.color === "blue") {
                        inputField.style.color = "";
                        inputField.style.fontStyle = "";
                        inputField.removeAttribute("readonly");
                        nonexistentIcon.style.color = "grey"; // Mark as existent
                        nonexistentButton.classList.remove("btn-outline-primary");
                        nonexistentButton.classList.add("btn-outline-secondary"); // Grey button
                    } else {
                        inputField.style.color = "blue";
                        inputField.style.fontStyle = "italic";
                        inputField.setAttribute("readonly", "true");
                        nonexistentIcon.style.color = "blue"; // Mark as non-existent
                        nonexistentButton.classList.remove("btn-outline-secondary");
                        nonexistentButton.classList.add("btn-outline-primary"); // Blue button
                    }

                    // If nonexistent is toggled ON, make visibility button grey
                    visibilityIcon.style.color = "grey";
                    visibilityButton.classList.remove("btn-outline-danger");
                    visibilityButton.classList.add("btn-outline-secondary"); // Grey button
                } else {
                    console.error("Server response indicates failure:", response.message);
                }
            } catch (error) {
                console.error("Error parsing server response:", error);
            }
        }
    };

    xhr.send("email=" + encodeURIComponent(email) + "&toggle=nonexistent");
}

    </script>

        <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to handle the addition of a new input field
        function addField(event, fieldName) {
            const container = event.target.closest('.mb-2').parentNode;
            const newField = container.querySelector('.mb-2').cloneNode(true);

            // Reset the value of the new field and ensure it is empty
            const input = newField.querySelector('input');
            input.value = '';
            input.setCustomValidity(''); 

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

       // Validate phone number input for 10 digits only if not empty
       function validatePhoneNumber(input) {
            const value = input.value.trim();

            // If not empty, enforce the 10-digit rule
            if (value && !/^\d{10}$/.test(value)) {
                input.setCustomValidity('Phone number must be exactly 10 digits.');
            } else {
                input.setCustomValidity(''); // Reset validity
            }
        }

        // Restrict phone number input to numbers only and limit to 10 digits
        function restrictTo10Digits(event) {
            const input = event.target;

            if (input.name === 'phone_number[]') {
                // Restrict input to numbers and limit to 10 digits
                input.value = input.value.replace(/\D/g, '').slice(0, 10);

                // Validate the input dynamically
                validatePhoneNumber(input);
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
        // Validate all phone numbers before form submission
        document.querySelector('form').addEventListener('submit', function(event) {
            const phoneInputs = document.querySelectorAll('input[name="phone_number[]"]');
            phoneInputs.forEach(validatePhoneNumber);

            // Prevent submission if any field is invalid
            if (!this.checkValidity()) {
                event.preventDefault();
            }
        });
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

                echo '<a href="list?organizer_id=' . urlencode($organizerID) . '" style="text-decoration: underline; color: black;" onmouseover="this.style.textDecoration=\'none\'" onmouseout="this.style.textDecoration=\'underline\'">' . htmlspecialchars($organizerName) . '</a>';

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

    <div class="col-md-12 mt-4" id="events-wrapper">
    <div class="row">
        <div class="col-md-12">
            <label for="events" class="form-label custom-label" style="font-size: 1.25rem; font-weight: 500;">Events Attended</label>
            <select name="events_attended[]" id="events_attended" class="form-control select2" multiple="multiple">
                <?php 
                // Assuming events_attended is a comma-separated list of event IDs
                $eventsAttended = !empty($subscriber['events_attended'])
                                  ? explode(',', $subscriber['events_attended'])
                                  : []; 

                // Sort events by event_date in descending order (latest first)
                usort($allEvents, function($a, $b) {
                    return strtotime($b['event_date']) - strtotime($a['event_date']);
                });

                foreach ($allEvents as $event) { 
                ?>
                    <option value="<?php echo $event['event_id']; ?>" 
            <?php echo in_array($event['event_id'], $eventsAttended) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($event['event_name']); ?>
    </option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>



    <!-- Add margin-bottom to create space -->
<div style="margin-top: 20px;"></div> 
<div class="row">
    <!-- Submit Button -->
    <div class="col-md-12 d-flex justify-content-center"  style="margin-bottom: 20px;">
        <!-- Save Button -->
        <button type="submit" class="btn btn-outline-success custom-btn" style="margin-right: 10px;">Save</button>

        <button type="submit" class="btn btn-outline-warning custom-btn">Cancel</button>
        
        <!-- Delete Button (Redirect to delete.php with subscriber_id) -->
        <!-- Delete Button (Redirect to delete.php with subscriber_id) -->
<a href="delete.php?subscriber_id=<?php echo $subscriberID; ?>" class="btn btn-outline-danger custom-btn ml-2" onclick="return confirm('Are you sure you want to delete this subscriber?');">Delete</a>

    </div>
</div>




</div>
</div>


</form>


<script>
    function validateForm() {
        <?php if (!empty($errorMessage)) { ?>
            alert('<?php echo htmlspecialchars($errorMessage); ?>');
            return false; // Prevent form submission
        <?php } ?>
        return true; // Allow form submission if no error
    }
</script>

<script> 
$(document).ready(function() {
    // Initialize Select2 for categories
    $('#categories').select2({
        placeholder: "Pick value",   // Placeholder text for categories
        allowClear: true,           // Option to clear selection
        tags: true,                 // Enable tagging
        width: '100%'               // Ensure it fits the container
    });

    // Initialize Select2 for events attended
    $('#events_attended').select2({
        placeholder: "Select events",
        allowClear: true,
        tags: true,
        width: '100%'
    });
});
</script>

</body>

</html>