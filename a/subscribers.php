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
if (isset($_GET["addedCount"]) && is_numeric($_GET["addedCount"])) {
    $addedCount = (int) $_GET["addedCount"];
    echo "<div class='alert alert-success'>Successfully added {$addedCount} contacts.</div>";
}
?>

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
<div id="error-message" class="error-box" style="display: none;"></div>


                <!-- Table to display data or search results -->
                <table id="example1" class="table table-bordered table-striped">
                <thead>
        <tr>
            <!-- <th>Serial Number</th> -->
            <th></th>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Designation</th>
            <th>Organization</th>
            <th>Events</th>
        </tr>
    </thead>
    <tbody>
    <?php

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Add JavaScript to scroll to the edited row
        echo "<script>document.getElementById('row_" . $row["subscriber_id"] . "');</script>";
          
        // Select box for merge
        echo '<td style="text-align:right; vertical-align: top; display: flex;">
        <input class="form-check-input subscriber-checkbox" type="checkbox" value="' . htmlspecialchars($row["subscriber_id"]) . '">
      </td>';

    echo "<td>";

    // Determine what text to display in priority order
    $displayText = '';

    // Fetch the correct designation, organization, and email for the specific subscriber
    $organization = '';
    $designation = '';
    $email = '';
    
    // Fetch organization and designation for this subscriber
    $sqlDesignationOrganization = "SELECT designation, organization FROM designation_organization WHERE subscriber_id = ?";
    $stmtDesignationOrganization = $conn->prepare($sqlDesignationOrganization);
    $stmtDesignationOrganization->bind_param("i", $row["subscriber_id"]);
    $stmtDesignationOrganization->execute();
    $resultDesignationOrganization = $stmtDesignationOrganization->get_result();
    
    // If there are multiple records, get the first available value
    while ($data = $resultDesignationOrganization->fetch_assoc()) {
        if (!$organization && !empty($data['organization'])) {
            $organization = $data['organization'];
        }
        if (!$designation && !empty($data['designation'])) {
            $designation = $data['designation'];
        }
    }
    
    // Fetch email for this subscriber
    $sqlEmail = "SELECT email FROM emails WHERE subscriber_id = ? LIMIT 1"; // Get only one email
    $stmtEmail = $conn->prepare($sqlEmail);
    $stmtEmail->bind_param("i", $row["subscriber_id"]);
    $stmtEmail->execute();
    $resultEmail = $stmtEmail->get_result();
    
    if ($emailData = $resultEmail->fetch_assoc()) {
        $email = strtolower(trim($emailData['email'])); // Normalize email
    }
    
    // Determine the highest-priority value to display
    if (!empty($row["full_name"])) {
        $displayText = htmlspecialchars($row["full_name"]);
    } elseif (!empty($organization)) {
        $displayText = htmlspecialchars($organization);
    } elseif (!empty($designation)) {
        $displayText = htmlspecialchars($designation);
    } elseif (!empty($email)) {
        $displayText = htmlspecialchars($email);
    } else {
        $displayText = '&mdash;'; // If nothing exists, display a placeholder
    }
    
    // Make the selected text clickable (link to the edit page)
    echo "<a href='edit_subscriber.php?id=" . $row["subscriber_id"] . "' 
             style='text-decoration: underline; color: inherit;' 
             onmouseover='this.style.textDecoration=\"none\"; this.style.color=\"inherit\";' 
             onmouseout='this.style.textDecoration=\"underline\"; this.style.color=\"inherit\";'>
             " . $displayText . "
          </a>";
    
    echo "</td>";
    
    
    
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


// Fetch all emails and their visibility (hidden) and existence (nonexistent) status for the subscriber
$sqlEmail = "SELECT email, hidden, does_not_exist FROM emails WHERE subscriber_id = ?";
$stmtEmail = $conn->prepare($sqlEmail);
$stmtEmail->bind_param("i", $row["subscriber_id"]);  // Bind the subscriber_id parameter
$stmtEmail->execute();
$resultEmail = $stmtEmail->get_result();

// Initialize arrays to store emails and their visibility
$emails = [];
while ($emailData = $resultEmail->fetch_assoc()) {
    // Normalize email to lowercase and store visibility + non-existence status
    $normalizedEmail = strtolower(trim($emailData['email']));
    $emails[] = [
        'email' => $normalizedEmail, 
        'hidden' => $emailData['hidden'], 
        'nonexistent' => $emailData['does_not_exist']
    ];
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


        
        echo "<td>";
        if (!empty($emails)) {
            foreach ($emails as $emailData) {
                // Display the email address
                $email = htmlspecialchars($emailData['email']);  // Safely handle special characters
                $emailHidden = $emailData['hidden'];
                $emailNonExistent = $emailData['nonexistent']; // Assuming this is stored in the DB
        
                // Determine styles for hidden/nonexistent emails
                $emailStyle = "";
                if ($emailHidden == 1) {
                    $emailStyle = 'style="color: red; font-style: italic;"';
                } elseif ($emailNonExistent == 1) {
                    $emailStyle = 'style="color: blue; font-style: italic;"';
                }
        
                // echo "<span $emailStyle>$email</span> ";
                echo "<span $emailStyle data-email=\"$email\" class='me-2'>$email</span>";


                 // Display buttons only if an email exists
        if (!empty($email)) {
        
                // Toggle Visibility Button
                $visibilityIcon = '<i class="bi bi-envelope-exclamation" title="Mailbox Full"></i>';
                $visibilityClass = $emailHidden == 1 ? 'btn-outline-danger' : 'btn-outline-secondary';
        
                echo "<button id='emailToggleBtn_" . $email . "' 
          class='btn $visibilityClass btn-sm me-2' 
          onclick='toggleEmailVisibility(\"$email\")'>$visibilityIcon</button>";
        
                // Toggle Email Doesn't Exist Button
                $nonExistIcon = '<i class="bi bi-envelope-slash" title="Email Doesn’t Exist/Unsubscribed"></i>';
                $nonExistClass = $emailNonExistent == 1 ? 'btn-outline-primary' : 'btn-outline-secondary';
        
                echo "<button id='emailDoesNotExistBtn_" . $email . "' 
                          class='btn $nonExistClass btn-sm' 
                          onclick='toggleEmailNonExistence(\"$email\")'>$nonExistIcon</button>";
                        }
                echo "<br>"; // New line for each email entry
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
<script>
function sanitizeEmailForSelector(email) {
    return email.replace(/@/g, '-').replace(/\./g, '-');
}

function toggleEmailVisibility(email) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_email_visibility.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var sanitizedEmail = sanitizeEmailForSelector(email);
                    var visibilityButton = document.querySelector(`#emailToggleBtn_${sanitizedEmail}`);
                    var nonexistentButton = document.querySelector(`#emailDoesNotExistBtn_${sanitizedEmail}`);
                    var emailText = document.querySelector(`span[data-email="${email}"]`);

                    if (!visibilityButton || !nonexistentButton || !emailText) {
                        console.error("Elements not found:", { visibilityButton, nonexistentButton, emailText });
                        return;
                    }

                    var isHidden = emailText.style.color === "red";

                    // Toggle visibility state
                    emailText.style.color = isHidden ? "" : "red";
                    emailText.style.fontStyle = isHidden ? "" : "italic";

                    if (isHidden) {
                        visibilityButton.classList.replace("btn-outline-danger", "btn-outline-secondary");
                    } else {
                        visibilityButton.classList.replace("btn-outline-secondary", "btn-outline-danger");
                        nonexistentButton.classList.replace("btn-outline-primary", "btn-outline-secondary"); // Reset nonexistent
                    }
                } else {
                    console.error("Server response failure:", response.message);
                }
            } catch (error) {
                console.error("Error parsing response:", error);
            }
        }
    };

    xhr.send("email=" + encodeURIComponent(email) + "&toggle=visibility");
}

function toggleEmailNonExistence(email) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_email_visibility.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var sanitizedEmail = sanitizeEmailForSelector(email);
                    var nonexistentButton = document.querySelector(`#emailDoesNotExistBtn_${sanitizedEmail}`);
                    var visibilityButton = document.querySelector(`#emailToggleBtn_${sanitizedEmail}`);
                    var emailText = document.querySelector(`span[data-email="${email}"]`);

                    if (!nonexistentButton || !visibilityButton || !emailText) {
                        console.error("Elements not found:", { nonexistentButton, visibilityButton, emailText });
                        return;
                    }

                    var isNonExistent = emailText.style.color === "blue";

                    // Toggle nonexistent state
                    emailText.style.color = isNonExistent ? "" : "blue";
                    emailText.style.fontStyle = isNonExistent ? "" : "italic";

                    if (isNonExistent) {
                        nonexistentButton.classList.replace("btn-outline-primary", "btn-outline-secondary");
                    } else {
                        nonexistentButton.classList.replace("btn-outline-secondary", "btn-outline-primary");
                        visibilityButton.classList.replace("btn-outline-danger", "btn-outline-secondary"); // Reset visibility
                    }
                } else {
                    console.error("Server response failure:", response.message);
                }
            } catch (error) {
                console.error("Error parsing response:", error);
            }
        }
    };

    xhr.send("email=" + encodeURIComponent(email) + "&toggle=nonexistent");
}

</script>


<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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
    {
        extend: "copy",  // Copy button
        exportOptions: {
            columns: ':not(:eq(0)):not(:last-child)',  // Exclude both the first and last columns
            modifier: { selected: null }
        }
    },
    {
    extend: "csv",  // CSV button
    exportOptions: {
        columns: ':not(:eq(0)):not(:last-child)',  // Exclude first and last columns
        modifier: { selected: null }
    },
    filename: function() {
        // Get the current date in mm-dd-yyyy format
        var currentDate = new Date();
        var mm = currentDate.getMonth() + 1;  // Months are zero-indexed
        var dd = currentDate.getDate();
        var yyyy = currentDate.getFullYear();

        // Format date as mm-dd-yyyy
        var formattedDate = mm + '-' + dd + '-' + yyyy;

        // Return the desired filename for the CSV file
        return 'list-' + formattedDate;
    }
},
    {
        extend: "pdf",  // PDF button
        exportOptions: {
            columns: ':not(:eq(0)):not(:last-child)',  // Exclude both the first and last columns
            modifier: { selected: null }
        }
    },
    {
        extend: "print",  // Print button
        exportOptions: {
            columns: ':not(:eq(0)):not(:last-child)',  // Exclude both the first and last columns
            modifier: { selected: null }
        }
    },
    "colvis",
    {
    text: '<i class="bi bi-trash" title="Delete"></i>',  // Custom Delete button
  action: function (e, dt, node, config) {
    // Array to hold selected subscriber IDs
    var selectedIds = [];

    // Collect the IDs of the selected checkboxes
    $(".subscriber-checkbox:checked").each(function () {
      selectedIds.push($(this).val());
    });

    // If no subscribers are selected, show an error message
    if (selectedIds.length === 0) {
      const errorDiv = document.getElementById('error-message');
      errorDiv.textContent = "Please select subscribers to delete.";
      errorDiv.style.display = 'block';
      setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
      return;
    }

    // Ask the user to confirm the deletion
    if (!confirm("Are you sure you want to delete the selected subscriber?")) {
      return;
    }

    // Send an AJAX request to delete the selected subscribers
    $.ajax({
      url: 'deleteSubscriber.php', // Adjust with your actual delete endpoint
      type: 'POST',
      data: { subscriber_ids: selectedIds }, // Send an array of selected subscriber IDs
      success: function(response) {
        const successDiv = document.getElementById('error-message');
        successDiv.style.display = 'block';
        setTimeout(() => { successDiv.style.display = 'none'; }, 3000);
        location.reload(); 
        successDiv.textContent = "Selected subscriber have been deleted."; // Refresh the page to update the table
      },
      error: function(xhr, status, error) {
        const errorDiv = document.getElementById('error-message');
        errorDiv.textContent = "An error occurred: " + error;
        errorDiv.style.display = 'block';
        setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
      }
    });
  },
  className: 'btn-delete',
  init: function (dt, node, config) {
    $(node).css({
      'background-color': '#d9534f',
      'color': '#fff',
      "deferRender": true,
      'border': 'none',
      'padding': '8px 12px',
      'border-radius': '4px',
      'cursor': 'pointer'
    });
  }
}, {
    text: '<i class="bi bi-download" title="Download VCard"></i>',   // Custom Download VCF button
  action: function (e, dt, node, config) {
    var selectedIds = [];

    // Collect the IDs of the selected checkboxes
    $(".subscriber-checkbox:checked").each(function () {
      selectedIds.push($(this).val());
    });

    // If no subscribers are selected, show an error message
    if (selectedIds.length === 0) {
      const errorDiv = document.getElementById('error-message');
      errorDiv.textContent = "Please select a subscriber to download the VCF.";
      errorDiv.style.display = 'block';
      setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
      return;
    }

    // Send an AJAX request to download the VCF for the selected subscriber
    $.ajax({
      url: 'subscribers.php',  // Same page to handle the request
      type: 'POST',
      data: { action: 'download_vcf', subscriber_ids: selectedIds },  // Send selected subscriber IDs
      success: function(response) {
        // Assuming the server sends a downloadable VCF response
        const successDiv = document.getElementById('error-message');
        successDiv.textContent = "VCF has been downloaded.";
        successDiv.style.display = 'block';
        setTimeout(() => { successDiv.style.display = 'none'; }, 3000);
      },
      error: function(xhr, status, error) {
        const errorDiv = document.getElementById('error-message');
        errorDiv.textContent = "An error occurred: " + error;
        errorDiv.style.display = 'block';
        setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
      }
    });
  },
  className: 'btn-download-vcf',
  init: function (dt, node, config) {
    $(node).css({
      'background-color': '#5bc0de',
      'color': '#fff',
      'border': 'none',
      'padding': '8px 12px',
      'border-radius': '4px',
      'cursor': 'pointer'
    });
  }
},
    {
      text: 'Merge',
      action: function (e, dt, node, config) {
        var selectedIds = [];
        $(".subscriber-checkbox:checked").each(function () {
          selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
          const errorDiv = document.getElementById('error-message');
          errorDiv.textContent = "Please select subscribers you want to merge.";
          errorDiv.style.display = 'block';
          setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
          return;
        }

        selectedIds.sort(function(a, b) { return a - b; });
        var firstSubscriberId = selectedIds[0];

        $.ajax({
          url: 'merge_subscribers.php',
          type: 'POST',
          data: { subscriber_ids: selectedIds, first_subscriber_id: firstSubscriberId },
          success: function(response) {
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
    var buttonsContainer = $('#example1_wrapper .col-md-6:eq(0)');
    buttonsContainer.css('position', 'sticky');
    buttonsContainer.css('top', '0');
    buttonsContainer.css('z-index', '1000');
  },
  "fixedHeader": true
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
