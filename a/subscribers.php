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


require_once "db.php"; // Include the database connection file

// Check if the 'merge_success' parameter is in the URL
if (isset($_GET['merge_success']) && $_GET['merge_success'] == 1) {
    $organizerMessage = "Subscribers merged successfully!";
} else {
    $organizerMessage = "";
}

// Initialize the serial number
$serialNumber = 1;

// Initialize arrays to store unique phone numbers and emails
$uniquePhoneNumbers = [];
$uniqueEmails = [];

// Function to generate VCF for a given subscriber
function generateVCF($conn, $subscriberID, $uniquePhoneNumbers, $uniqueEmails) {
    // Fetch subscriber details
    $sql = "SELECT * FROM subscribers WHERE subscriber_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $subscriber = $result->fetch_assoc();

        // Define the line break for VCF
        $eol = "\r\n";

        // Start building the VCard content
        $vcfContent = "BEGIN:VCARD" . $eol;
        $vcfContent .= "VERSION:3.0" . $eol;
        $vcfContent .= "FN:" . $subscriber['full_name'] . $eol;

        // Add phone numbers to the VCard content
        foreach ($uniquePhoneNumbers as $phoneNumber) {
            $vcfContent .= "TEL:" . $phoneNumber . $eol;
        }

        // Add emails to the VCard content
        foreach ($uniqueEmails as $email) {
            $vcfContent .= "EMAIL:" . $email . $eol;
        }

        // Fetch and include multiple designations and organizations from designation_organization table
$desigOrgSql = "SELECT designation, organization FROM designation_organization WHERE subscriber_id = ?";
$desigOrgStmt = $conn->prepare($desigOrgSql);
$desigOrgStmt->bind_param("i", $subscriberID);
$desigOrgStmt->execute();
$desigOrgResult = $desigOrgStmt->get_result();

if ($desigOrgResult->num_rows > 0) {
    while ($row = $desigOrgResult->fetch_assoc()) {
        // Include designation if it exists
        if (!empty($row['designation'])) {
            $vcfContent .= "TITLE:" . $row['designation'] . $eol;
        }

        // Include organization if it exists
        if (!empty($row['organization'])) {
            $vcfContent .= "ORG:" . $row['organization'] . $eol;
        }
    }
}


        // Include address if it exists
        if (!empty($subscriber['address'])) {
            $vcfContent .= "ADR;TYPE=WORK:" . $subscriber['address'] . $eol;
        }

        $vcfContent .= "END:VCARD" . $eol;

        // Send appropriate headers for download
        header("Content-Type: text/vcard");
        header("Content-Disposition: attachment; filename=" . $subscriber['full_name'] . ".vcf");
        echo $vcfContent;
        exit;
    }
}

function fetchSubscriberContacts($conn, $subscriberID) {
    global $uniquePhoneNumbers, $uniqueEmails;

    // Fetch all emails from the 'emails' table
    $sqlEmail = "SELECT email FROM emails WHERE subscriber_id = ?";
    $stmtEmail = $conn->prepare($sqlEmail);
    $stmtEmail->bind_param("i", $subscriberID);
    $stmtEmail->execute();
    $resultEmail = $stmtEmail->get_result();

    // Normalize emails to lowercase and add to the uniqueEmails array
    while ($emailData = $resultEmail->fetch_assoc()) {
        $normalizedEmail = strtolower($emailData['email']);  // Convert email to lowercase
        $uniqueEmails[] = $normalizedEmail;
    }

    // Fetch all phone numbers from the 'phone_numbers' table
    $sqlPhone = "SELECT phone_number FROM phone_numbers WHERE subscriber_id = ?";
    $stmtPhone = $conn->prepare($sqlPhone);
    $stmtPhone->bind_param("i", $subscriberID);
    $stmtPhone->execute();
    $resultPhone = $stmtPhone->get_result();

    while ($phoneData = $resultPhone->fetch_assoc()) {
        $uniquePhoneNumbers[] = $phoneData['phone_number'];
    }

    // Remove duplicates in both arrays (emails and phone numbers)
    $uniqueEmails = array_unique($uniqueEmails);
    $uniquePhoneNumbers = array_unique($uniquePhoneNumbers);
}


// Handle VCF download action
if (isset($_GET["id"])) {
    $subscriberID = $_GET["id"];
    if (is_numeric($subscriberID)) {
        fetchSubscriberContacts($conn, $subscriberID);
        generateVCF($conn, $subscriberID, $uniquePhoneNumbers, $uniqueEmails);
    }
}

// Function to fetch subscribers with matching phone numbers or emails
function fetchMatchingContacts($conn) {
    $sql = "
        SELECT 
            t1.subscriber_id AS subscriber1_id, 
            t2.subscriber_id AS subscriber2_id,
            t1.phone_number AS matching_phone, 
            t1.email AS matching_email
        FROM (
            SELECT subscriber_id, phone_number, email
            FROM (
                SELECT subscriber_id, phone_number, NULL AS email
                FROM phone_numbers
                UNION ALL
                SELECT subscriber_id, NULL AS phone_number, email
                FROM emails
            ) AS combined
        ) t1
        JOIN (
            SELECT subscriber_id, phone_number, email
            FROM (
                SELECT subscriber_id, phone_number, NULL AS email
                FROM phone_numbers
                UNION ALL
                SELECT subscriber_id, NULL AS phone_number, email
                FROM emails
            ) AS combined
        ) t2
        ON (t1.phone_number = t2.phone_number OR t1.email = t2.email)
        AND t1.subscriber_id <> t2.subscriber_id
        GROUP BY t1.subscriber_id, t2.subscriber_id, t1.phone_number, t1.email
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }

    return $matches;
}

// Handle record deletion
if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {
    $deleteID = $_GET["delete"];
    
    // Check if the user confirmed the deletion
    if (isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
        // Begin transaction to ensure all deletions are handled atomically
        $conn->begin_transaction();
        
        try {
            // Delete the record from the event_subscriber_mapping table
            $deleteMappingSQL = "DELETE FROM event_subscriber_mapping WHERE subscriber_id = ?";
            $deleteMappingStmt = $conn->prepare($deleteMappingSQL);
            if ($deleteMappingStmt === false) {
                throw new Exception("Error preparing delete statement for event_subscriber_mapping: " . $conn->error);
            }
            $deleteMappingStmt->bind_param("i", $deleteID);
            if (!$deleteMappingStmt->execute()) {
                throw new Exception("Error executing delete statement for event_subscriber_mapping: " . $deleteMappingStmt->error);
            }

            // Delete the subscriber's email from the emails table
            $deleteEmailSQL = "DELETE FROM emails WHERE subscriber_id = ?";
            $deleteEmailStmt = $conn->prepare($deleteEmailSQL);
            if ($deleteEmailStmt === false) {
                throw new Exception("Error preparing delete statement for emails: " . $conn->error);
            }
            $deleteEmailStmt->bind_param("i", $deleteID);
            if (!$deleteEmailStmt->execute()) {
                throw new Exception("Error executing delete statement for emails: " . $deleteEmailStmt->error);
            }

            // Delete the subscriber's phone number from the phone_numbers table
            $deletePhoneSQL = "DELETE FROM phone_numbers WHERE subscriber_id = ?";
            $deletePhoneStmt = $conn->prepare($deletePhoneSQL);
            if ($deletePhoneStmt === false) {
                throw new Exception("Error preparing delete statement for phone_numbers: " . $conn->error);
            }
            $deletePhoneStmt->bind_param("i", $deleteID);
            if (!$deletePhoneStmt->execute()) {
                throw new Exception("Error executing delete statement for phone_numbers: " . $deletePhoneStmt->error);
            }

            // Finally, delete the subscriber from the subscribers table
            $deleteSubscriberSQL = "DELETE FROM subscribers WHERE subscriber_id = ?";
            $deleteSubscriberStmt = $conn->prepare($deleteSubscriberSQL);
            if ($deleteSubscriberStmt === false) {
                throw new Exception("Error preparing delete statement for subscribers: " . $conn->error);
            }
            $deleteSubscriberStmt->bind_param("i", $deleteID);
            if (!$deleteSubscriberStmt->execute()) {
                throw new Exception("Error executing delete statement for subscribers: " . $deleteSubscriberStmt->error);
            }

            // Commit the transaction if all queries were successful
            $conn->commit();

            // Use AJAX to refresh the table without a full page reload
            echo "<script>
            alert('Record deleted successfully.');
            </script>";
            exit();
        } catch (Exception $e) {
            // Rollback the transaction if something goes wrong
            $conn->rollback();
            die("Error executing delete statement: " . $e->getMessage());
        }
    } else {  
        // Prompt the user for confirmation before deleting
        echo "<script>
                var confirmDelete = confirm('Are you sure you want to delete this record?');
                if (confirmDelete) {
                    // Use AJAX to perform the delete action
                    var xhr = new XMLHttpRequest();
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            alert('Record deleted successfully.');
                            location.reload();  
                        }
                    };
                    xhr.open('GET', 'subscribers.php?delete=" . $deleteID . "&confirm=yes', true);
                    xhr.send();
                }
              </script>";
    }
}

// Initialize filters from GET parameters
$eventFilter = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$categoryFilter = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$organizerFilter = isset($_GET['organizer_id']) ? intval($_GET['organizer_id']) : null;
$filter = isset($_GET['filter']) ? $_GET['filter'] : null;

// Prepare SQL query and statement
$stmt = null;

if (!empty($eventFilter)) {

    // Fetch event details
    $eventDetailsSql = "SELECT event_name, event_date FROM events WHERE event_id = ?";
    $eventDetailsStmt = $conn->prepare($eventDetailsSql);
    $eventDetailsStmt->bind_param("i", $eventFilter);
    $eventDetailsStmt->execute();
    $eventDetailsResult = $eventDetailsStmt->get_result();
    
    
    $sql = "SELECT subscribers.*, 
               COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
        FROM subscribers
        LEFT JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
        WHERE event_subscriber_mapping.event_id = ?
        GROUP BY subscribers.subscriber_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eventFilter);
} elseif (!empty($categoryFilter)) {

     // Fetch category name
     $categorySql = "SELECT category_name FROM categories WHERE category_id = ?";
     $categoryStmt = $conn->prepare($categorySql);
     $categoryStmt->bind_param("i", $categoryFilter);
     $categoryStmt->execute();
     $categoryResult = $categoryStmt->get_result();

    $sql = "SELECT subscribers.*, 
               COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
        FROM subscribers
        LEFT JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
        WHERE event_subscriber_mapping.category_id = ?
        GROUP BY subscribers.subscriber_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $categoryFilter);
} elseif (!empty($organizerFilter)) {

     // Fetch organizer name
     $organizerSql = "SELECT organizer_name FROM organizers WHERE organizer_id = ?";
     $organizerStmt = $conn->prepare($organizerSql);
     $organizerStmt->bind_param("i", $organizerFilter);
     $organizerStmt->execute();
     $organizerResult = $organizerStmt->get_result();

    $sql = "SELECT subscribers.*, 
               COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
        FROM subscribers
        LEFT JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
        WHERE event_subscriber_mapping.organizer_id = ?
        GROUP BY subscribers.subscriber_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $organizerFilter);
} elseif ($filter === 'repeated') {
    $sql = "SELECT subscribers.*, 
                   COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
            FROM subscribers
            LEFT JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
            GROUP BY subscribers.subscriber_id
            HAVING COUNT(event_subscriber_mapping.subscriber_id) > 1
            ORDER BY subscribers.subscriber_id DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT subscribers.*, 
       COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
FROM subscribers
LEFT JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
GROUP BY subscribers.subscriber_id
ORDER BY subscribers.subscriber_id DESC;
";
    $stmt = $conn->prepare($sql);
}



// Execute the statement
$stmt->execute();
$result = $stmt->get_result();

?>
 
<!DOCTYPE html>
<html lang="en">  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List</title>  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Link to Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.css">

  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

  <style>
        .highlighted {
            background-color: #ffcccb; /* Change the color to your preference */
        }
        #organizerNameError {
        color: red;
        font-size: 14px;
    }

    .error-box {
  border: 2px solid green;        /* Green border */
  background-color: #d4edda;      /* Light green background */
  color: black;                   /* Black text color */
  padding: 15px;                  /* Space inside the box */
  margin: 10px 0;                 /* Space above and below the box */
  border-radius: 5px;             /* Rounded corners */
  font-size: 16px;                /* Text size */
  font-weight: bold;              /* Bold text */
}   
    </style>
</head>
<body >
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">

      <?php
// Display event details if available
if (isset($eventDetailsResult) && $eventDetailsResult !== null && $eventDetailsResult->num_rows > 0) {
    $eventDetails = $eventDetailsResult->fetch_assoc();

    // Convert event_date to desired format
    $formattedDate = date("d F Y", strtotime($eventDetails['event_date']));

    // Fetch total number of attendees for the event
    $attendeesSql = "SELECT COUNT(*) AS total_attendees FROM event_subscriber_mapping WHERE event_id = ?";
    $attendeesStmt = $conn->prepare($attendeesSql);
    $attendeesStmt->bind_param("i", $eventFilter);
    $attendeesStmt->execute();
    $attendeesResult = $attendeesStmt->get_result();
    $attendeesCount = $attendeesResult->fetch_assoc()['total_attendees'];

    echo "<h3>" . htmlspecialchars($eventDetails['event_name']) . " (" . $formattedDate . ") - " . $attendeesCount . " attendees</h3>";
}
?>

<?php
// Check if categoryResult is set and has rows
if (isset($categoryResult) && $categoryResult !== null && $categoryResult->num_rows > 0) {
    $category = $categoryResult->fetch_assoc();

    // Fetch total number of attendees for the category
    $attendeesSql = "SELECT COUNT(DISTINCT subscriber_id) AS total_attendees FROM event_subscriber_mapping WHERE category_id = ? AND subscriber_id IS NOT NULL";
    $attendeesStmt = $conn->prepare($attendeesSql);
    $attendeesStmt->bind_param("i", $categoryFilter);
    $attendeesStmt->execute();
    $attendeesResult = $attendeesStmt->get_result();
    $attendeesCount = $attendeesResult->fetch_assoc()['total_attendees'];

    echo "<h3>" . htmlspecialchars($category['category_name']) . " - " . $attendeesCount . "</h3>";
}
?>

<?php
// Check if organizerResult is set and has rows
if (isset($organizerResult) && $organizerResult !== null && $organizerResult->num_rows > 0) {
    $organizer = $organizerResult->fetch_assoc();

    // Fetch total number of attendees for the organizer
    $attendeesSql = "SELECT COUNT(DISTINCT subscriber_id) AS total_attendees FROM event_subscriber_mapping WHERE organizer_id = ? AND subscriber_id IS NOT NULL";
    $attendeesStmt = $conn->prepare($attendeesSql);
    $attendeesStmt->bind_param("i", $organizerFilter);
    $attendeesStmt->execute();
    $attendeesResult = $attendeesStmt->get_result();
    $attendeesCount = $attendeesResult->fetch_assoc()['total_attendees'];

    echo "<h3>" . htmlspecialchars($organizer['organizer_name']) . " - " . $attendeesCount . "</h3>";
}
?>




        <div class="row mb-2">
<div class="card">
              <div class="card-header">
              </div>
              <div class="card-body">

              <?php
// Fetch matching contacts
$matchingContacts = fetchMatchingContacts($conn);
$matchedSubscribers = [];

// Process matching contacts to build an array of subscriber IDs with matches
foreach ($matchingContacts as $match) {
    $matchedSubscribers[] = $match['subscriber1_id'];
    $matchedSubscribers[] = $match['subscriber2_id'];
}

// Ensure the list of matched subscribers is unique
$matchedSubscribers = array_unique($matchedSubscribers);
?>
<div id="error-message" class="error-box" style="display: none;"></div>


                <!-- Table to display data or search results -->
                <table id="example1" class="table table-bordered table-striped">
                <thead>
        <tr>
            <!-- <th>Serial Number</th> -->
            <th>Select</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Designation</th>
            <th>Organization</th>
            <th>Events</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Add JavaScript to scroll to the edited row
        echo "<script>document.getElementById('row_" . $row["subscriber_id"] . "');</script>";
          
        // Select box for merge
        echo '<td style="text-align: center; vertical-align: top;">
        <input class="form-check-input subscriber-checkbox" type="checkbox" value="' . htmlspecialchars($row["subscriber_id"]) . '">
      </td>';


        // Display full name
        echo "<td>" . ($row["full_name"] ? $row["full_name"] : '') . "</td>";

        // Fetch the phone numbers and emails
        $phoneNumber = !empty($row["phone_number"]) ? $row["phone_number"] : '';
        $email = !empty($row["email"]) ? $row["email"] : '';

        // Initialize arrays to store phone numbers and emails
        $phoneNumbers = [];
        $emails = [];

        // Add phone number and email from the current subscriber
        if ($phoneNumber) {
            $phoneNumbers[] = $phoneNumber;
        }

        if ($email) {
            $emails[] = $email;
        }

        // Fetch all phone numbers for the subscriber from the database
        $sqlPhone = "SELECT phone_number FROM phone_numbers WHERE subscriber_id = ?";
        $stmtPhone = $conn->prepare($sqlPhone);
        $stmtPhone->bind_param("i", $row["subscriber_id"]);
        $stmtPhone->execute();
        $resultPhone = $stmtPhone->get_result();
        while ($phoneData = $resultPhone->fetch_assoc()) {
            $phoneNumbers[] = $phoneData['phone_number']; // Add each phone number to the array
        }


// Fetch all emails and their visibility (hidden) status for the subscriber
$sqlEmail = "SELECT email, hidden FROM emails WHERE subscriber_id = ?";
$stmtEmail = $conn->prepare($sqlEmail);
$stmtEmail->bind_param("i", $row["subscriber_id"]);  // Bind the subscriber_id parameter
$stmtEmail->execute();
$resultEmail = $stmtEmail->get_result();

// Initialize arrays to store emails and their visibility
$emails = [];
while ($emailData = $resultEmail->fetch_assoc()) {
    // Normalize email to lowercase and store visibility
    $normalizedEmail = strtolower(trim($emailData['email']));
    $emails[] = ['email' => $normalizedEmail, 'hidden' => $emailData['hidden']];
}

// Deduplicate emails while preserving the visibility status
$uniqueEmails = [];
foreach ($emails as $emailData) {
    $uniqueEmails[$emailData['email']] = $emailData;  // Using the email as the key ensures uniqueness
}

// Remove duplicates by making the arrays unique
$phoneNumbers = array_unique($phoneNumbers);
// Now $uniqueEmails contains only unique emails
$emails = array_values($uniqueEmails);  // Reset the array's keys

// Display unique phone numbers and emails as comma-separated values
$phoneNumbersString = implode(', ', $phoneNumbers);
$emailAddresses = array_map(function($emailData) {
    return $emailData['email'];
}, $emails);
$emailsString = implode(', ', $emailAddresses); // Now we use the email addresses array


        // Display phone numbers and emails
        echo "<td>" . ($phoneNumbersString ? $phoneNumbersString : '&mdash;') . "</td>";  // Display "—" if no phone numbers


        
// Now display the emails and their visibility toggle button
echo "<td>";
if (!empty($emails)) {
    foreach ($emails as $emailData) {
        // Display the email address
        $email = htmlspecialchars($emailData['email']);  // Safely handle special characters
        echo $email . "<br>";

        // Check the email visibility for each email
        $emailHidden = $emailData['hidden'];

        // Display the toggle button based on the current visibility
        $buttonText = $emailHidden == 1 ? 'full' : 'not';
        $buttonClass = $emailHidden == 1 ? 'btn-outline-primary' : 'btn-outline-secondary';
        
        // Generate the button with unique ID and email as the parameter
        echo "<button id='emailToggleBtn_" . $email . "' class='btn $buttonClass btn-sm' onclick='toggleEmailVisibility(\"$email\")'>$buttonText</button><br>";
    }
} else {
    // Display '—' if no emails are available
    echo '&mdash;';
}
echo "</td>";



         // Fetch designations and organizations
$designations = [];
$organizations = [];

// Fetch designations and organizations for the subscriber from the designation_organization table
$sqlDesignationOrganization = "
    SELECT designation, organization 
    FROM designation_organization 
    WHERE subscriber_id = ?
";
$stmtDesignationOrganization = $conn->prepare($sqlDesignationOrganization);
$stmtDesignationOrganization->bind_param("i", $row["subscriber_id"]);
$stmtDesignationOrganization->execute();
$resultDesignationOrganization = $stmtDesignationOrganization->get_result();

// Fetch designations and organizations
while ($data = $resultDesignationOrganization->fetch_assoc()) {
    // Add each designation to the array
    if (!empty($data['designation'])) {
        $designations[] = $data['designation'];
    }

    // Add each organization to the array
    if (!empty($data['organization'])) {
        $organizations[] = $data['organization'];
    }
}

// Remove duplicates for designations and organizations
$designations = array_unique($designations);
$organizations = array_unique($organizations);

// Join only non-empty values with commas
$designationsString = !empty($designations) ? implode(', ', $designations) : '&mdash;';
$organizationsString = !empty($organizations) ? implode(', ', $organizations) : '&mdash;';

// Display designations and organizations
echo "<td>" . $designationsString . "</td>";  // Display designations or "—" if empty
echo "<td>" . $organizationsString . "</td>";  // Display organizations or "—" if empty


        echo "<td>" . ($row["number_of_events_attended"] ? $row["number_of_events_attended"] : '') . "</td>";

// Get the logged-in user ID
$userId = $_SESSION['id'];

 // Fetch the user's role (assuming the role is stored in the "login" table)
$sql = "SELECT role FROM login WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// Fetch the organizer ID the logged-in user is affiliated with
$sql = "SELECT organizer_id FROM user_organizers WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userOrganizerId);
$userOrganizers = [];
while ($stmt->fetch()) {
    $userOrganizers[] = $userOrganizerId; // Store the organizer IDs the user is affiliated with
}
$stmt->close();

// echo "User ID: " . $userId; // Debugging line

// // Output the contents of the $userOrganizers array to see what has been fetched
// echo "<pre>";
// print_r($userOrganizers); // This will print the array of organizer IDs
// echo "</pre>";



// Get the subscriber's organizer ID from the event_subscriber_mapping table
$subscriberId = $row['subscriber_id']; // Assuming you have this from your subscriber data

// // Debug: Print the subscriberId to ensure it's correct
// echo "Subscriber ID: " . $subscriberId . "<br>";
$sql = "SELECT organizer_id FROM event_subscriber_mapping WHERE subscriber_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subscriberId);
$stmt->execute();
$stmt->bind_result($subscriberOrganizerId);
// Check if a result was fetched
if ($stmt->fetch()) {
    // If a result was fetched, print the organizer ID
    // echo "Organizer ID: " . $subscriberOrganizerId . "<br>";
} else {
    // If no result, print a message indicating no record was found
    // echo "No organizer ID found for subscriber ID: " . $subscriberId . "<br>";
}
$stmt->close();       


        // Actions
echo "<td>";
if ($role == 'user' && in_array((int)$subscriberOrganizerId, array_map('intval', $userOrganizers))) {
    // Only display the Edit button if the role is not 'user' and the user is affiliated with the same organizer
    echo "<a href='edit_subscriber.php?id=" . $row["subscriber_id"] . "' class='btn btn-outline-primary btn-sm' title='Edit'><i class='bi bi-pencil-square'></i></a>";
}elseif($role == 'admin'){
    // Only display the Edit button if the role is not 'user' and the user is affiliated with the same organizer
    echo "<a href='edit_subscriber.php?id=" . $row["subscriber_id"] . "' class='btn btn-outline-primary btn-sm' title='Edit'><i class='bi bi-pencil-square'></i></a>";
}
// echo "<a href='edit_subscriber.php?id=" . $row["subscriber_id"] . "' class='btn btn-outline-primary btn-sm' title='Edit'><i class='bi bi-pencil-square'></i></a> ";
echo "<a href='javascript:void(0);' onclick='deleteRecord(" . $row["subscriber_id"] . ")' class='btn btn-outline-danger btn-sm' title='Delete'><i class='bi bi-trash3'></i></a> ";
echo "<a href='subscribers.php?id=" . $row['subscriber_id'] . "' class='btn btn-outline-success btn-sm'><i class='bi bi-download' title='Download Vcard'></i></a>";



         // Check if the subscriber is in the matched list
if (in_array($row["subscriber_id"], $matchedSubscribers)) {
    echo " <button class='btn btn-outline-primary btn-sm'title='Merge' onclick='window.location.href=\"merge.php?subscriber_id=" . $row["subscriber_id"] . "\"'> <i class='bi bi-intersect'></i></button>";
}

         echo "</td>";

        echo "</tr>";
    }
} else {
    // Only show the "No records found" message on the first page if there are no records
    echo "<tr><td colspan='8'>No records found.</td></tr>";
}
?>
    </tbody>
</table>
        </div> 

          <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

 <!-- for deleting  -->
<script>
 function deleteRecord(id) {
    var confirmDelete = confirm('Are you sure you want to delete this record?');
    if (confirmDelete) {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // alert('Record deleted successfully.');
                location.reload();
            }
        };
        xhr.open('GET', 'subscribers.php?delete=' + id + '&confirm=yes', true);
        xhr.send();
    }
}
</script>

<script>
function toggleEmailVisibility(email) {
    if (!email || !email.includes('@')) {
        console.error("Invalid email provided: " + email);
        return;
    }

    // Create a new AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_email_visibility.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    // Log the email being sent
    console.log("Toggling visibility for email: " + email);

    // Handle the server response
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    // Parse the JSON response
                    var response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        // Toggle the button text dynamically
                        var button = document.getElementById('emailToggleBtn_' + email);
                        if (button) {
                            if (button.innerText === 'full') {
                                button.innerText = 'not';
                                button.classList.remove('btn-outline-primary');
                                button.classList.add('btn-outline-secondary');
                            } else {
                                button.innerText = 'full';
                                button.classList.remove('btn-outline-secondary');
                                button.classList.add('btn-outline-primary');
                            }
                        }
                    } else {
                        console.error("Server response indicates failure:", response.message);
                    }
                } catch (error) {
                    console.error("Error parsing server response:", error);
                    console.error("Response text:", xhr.responseText);
                }
            } else {
                console.error("AJAX request failed. Status:", xhr.status, "Response:", xhr.responseText);
            }
        }
    };

    // Send the email to the server
    xhr.send("email=" + encodeURIComponent(email));
}
</script>





<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script src="dist/js/demo.js"></script>
<!-- DataTables  & Plugins -->
<script src="plugins/datatables/jquery.dataTables.min.js"></script>

<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<!-- For responsive-->
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<!-- For responsive-->
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- For buttons of table like csv, excel, etc.-->
<script src="plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<!-- For zip files-->
<script src="plugins/jszip/jszip.min.js"></script>
<!-- For PDF Library-->
<script src="plugins/pdfmake/pdfmake.min.js"></script>
<!-- For PDF Library-->
<script src="plugins/pdfmake/vfs_fonts.js"></script>
<!-- For downloading excel, pdf, csv-->
<script src="plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<!-- Print-->
<script src="plugins/datatables-buttons/js/buttons.print.min.js"></script>
<!-- Column Visibility -->
<script src="plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- Page specific script -->
<script>
$(function () {
  // Initialize the DataTable for example1
  $("#example1").DataTable({
    "responsive": true,
    "lengthChange": false,
    "autoWidth": false,
    "buttons": [
      "copy", "csv", "excel", "pdf", "print", "colvis",
      {
        text: 'Merge',  // Custom button text
        action: function (e, dt, node, config) {
          // Collect selected checkboxes
          var selectedIds = [];
          $(".subscriber-checkbox:checked").each(function () {
            selectedIds.push($(this).val());
          });

          // Check if no checkboxes are selected
if (selectedIds.length === 0) {
  // Get the error message div
  const errorDiv = document.getElementById('error-message');
  
  // Set the error message
  errorDiv.textContent = "Please select subscribers you want to merge.";
  
  // Show the error message div
  errorDiv.style.display = 'block';
  
  // Optionally, you can hide the error message after some time
  setTimeout(() => {
    errorDiv.style.display = 'none';
  }, 3000); // Hide after 3 seconds
  return;
}

          // Sort subscriber IDs in ascending order
          selectedIds.sort(function(a, b) {
            return a - b;
          });

          // The first (smallest) subscriber ID to keep
          var firstSubscriberId = selectedIds[0];

          // Send AJAX request to merge the subscribers
          $.ajax({
            url: 'merge_subscribers.php', // Your PHP file for handling the merge
            type: 'POST',
            data: {
              subscriber_ids: selectedIds,
              first_subscriber_id: firstSubscriberId
            },
            success: function(response) {
              // If merge is successful, redirect to the edit page
              window.location.href = 'edit_subscriber.php?id=' + firstSubscriberId;
            },
            error: function(xhr, status, error) {
              alert("An error occurred: " + error);
            }
          });
        },
        className: 'btn-merge',
    init: function (dt, node, config) {
        $(node).css({
            'background-color': '#42a832',
            'color': '#fff',
            'border': 'none',
            'padding': '8px 12px',
            'border-radius': '4px',
            'cursor': 'pointer'
        });
    }
      }
    ],
    "initComplete": function () {
      // Ensure the button container stays at the top while scrolling
      var buttonsContainer = $('#example1_wrapper .col-md-6:eq(0)');
      buttonsContainer.css('position', 'sticky');
      buttonsContainer.css('top', '0');
      buttonsContainer.css('z-index', '1000');
    },
    "fixedHeader": true // Enable the fixedHeader feature
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');

  // Initialize the DataTable for example2
  $(document).ready(function () {
        $('#example2').DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": false,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
        });
    });
});
</script>
        
</body>
</html>
