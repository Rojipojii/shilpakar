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

        // Add phone number and email to the VCard content
        foreach ($uniquePhoneNumbers as $phoneNumber) {
            $vcfContent .= "TEL:" . $phoneNumber . $eol;
        }

        foreach ($uniqueEmails as $email) {
            $vcfContent .= "EMAIL:" . $email . $eol;
        }

        // Include organization if it exists
        if (!empty($subscriber['organization'])) {
            $vcfContent .= "ORG:" . $subscriber['organization'] . $eol;
        }

        // Include designation if it exists
        if (!empty($subscriber['designation'])) {
            $vcfContent .= "TITLE:" . $subscriber['designation'] . $eol;
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

// Fetch all unique phone numbers and emails for the subscriber
function fetchSubscriberContacts($conn, $subscriberID) {
    global $uniquePhoneNumbers, $uniqueEmails;

    // Fetch all emails from the 'emails' table
    $sqlEmail = "SELECT email FROM emails WHERE subscriber_id = ?";
    $stmtEmail = $conn->prepare($sqlEmail);
    $stmtEmail->bind_param("i", $subscriberID);
    $stmtEmail->execute();
    $resultEmail = $stmtEmail->get_result();

    while ($emailData = $resultEmail->fetch_assoc()) {
        $uniqueEmails[] = $emailData['email'];
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
            window.location.href = window.location.href;
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

// Check for the "filter" parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Check if a category filter is applied
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Check if a category filter is applied
$organizerFilter = isset($_GET['organizer']) ? $_GET['organizer'] : '';

// Check if a category filter is applied
$eventFilter = isset($_GET['event_id']) ? $_GET['event_id'] : '';


// Modify the SQL query based on the event filter
if (!empty($eventFilter)) {
    // Modify your SQL query to filter records based on the selected event
    $sql = "SELECT subscribers.*, COUNT(*) AS number_of_events_attended 
            FROM subscribers 
            INNER JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
            WHERE event_subscriber_mapping.event_id = ? 
            GROUP BY subscribers.subscriber_id
            ORDER BY subscribers.subscriber_id DESC";           
} else if (!empty($categoryFilter)) {
    // Modify your SQL query to filter records based on the selected category
    $sql = "SELECT subscribers.*, COUNT(*) AS number_of_events_attended 
            FROM subscribers 
            INNER JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
            WHERE event_subscriber_mapping.category_id = ? 
            GROUP BY subscribers.subscriber_id
            ORDER BY subscribers.subscriber_id DESC";    
} else if (!empty($organizerFilter)) {
    // Modify your SQL query to filter records based on the selected organizer
    $sql = "SELECT subscribers.*, COUNT(*) AS number_of_events_attended 
            FROM subscribers 
            INNER JOIN event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
            WHERE event_subscriber_mapping.organizer_id = ? 
            GROUP BY subscribers.subscriber_id
            ORDER BY subscribers.subscriber_id DESC";
} else if ($filter === 'repeated') {
    // Modify the SQL query based on the filter for repeated records using phone numbers and emails
    $sql = "SELECT subscribers.subscriber_id, COALESCE(phone_numbers.phone_number, emails.email) AS contact, 
            COUNT(*) AS number_of_events_attended 
            FROM subscribers
            LEFT JOIN phone_numbers ON subscribers.subscriber_id = phone_numbers.subscriber_id
            LEFT JOIN emails ON subscribers.subscriber_id = emails.subscriber_id
            GROUP BY contact
            HAVING COUNT(*) > 1";
} else {
    $sql = "
    SELECT 
        subscribers.*, 
        COUNT(event_subscriber_mapping.subscriber_id) AS number_of_events_attended
    FROM 
        subscribers
    LEFT JOIN 
        event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
    GROUP BY 
        subscribers.subscriber_id
    ORDER BY 
        subscribers.subscriber_id DESC";

}



// Execute the query and display the results as needed
$result = $conn->query($sql);
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
      <!-- DataTables -->
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

  <style>
        .highlighted {
            background-color: #ffcccb; /* Change the color to your preference */
        }
    </style>
</head>
<body >
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">
        <div class="row mb-2">
<div class="card">
              <div class="card-header">
              </div>
              <div class="card-body">
                <!-- Table to display data or search results -->
                <table id="example1" class="table table-bordered table-striped">
                <thead>
        <tr>
            <!-- <th>Serial Number</th> -->
            <th>Full Name</th>
            <th>Mobile Number</th>
            <th>Email</th>
            <th>Designation</th>
            <th>Organization</th>
            <!-- <th>Address</th> -->
            <th>Events Attended</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Add JavaScript to scroll to the edited row
        echo "<script>document.getElementById('row_" . $row["subscriber_id"] . "').scrollIntoView();</script>";

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

        // Fetch all emails for the subscriber from the database
        $sqlEmail = "SELECT email FROM emails WHERE subscriber_id = ?";
        $stmtEmail = $conn->prepare($sqlEmail);
        $stmtEmail->bind_param("i", $row["subscriber_id"]);
        $stmtEmail->execute();
        $resultEmail = $stmtEmail->get_result();
        while ($emailData = $resultEmail->fetch_assoc()) {
            $emails[] = $emailData['email']; // Add each email to the array
        }

        // Remove duplicates by making the arrays unique
        $phoneNumbers = array_unique($phoneNumbers);
        $emails = array_unique($emails);

        // Display unique phone numbers and emails as comma-separated values
        $phoneNumbersString = implode(', ', $phoneNumbers);
        $emailsString = implode(', ', $emails);

        // Display phone numbers and emails
        echo "<td>" . ($phoneNumbersString ? $phoneNumbersString : '&mdash;') . "</td>";  // Display "—" if no phone numbers
        echo "<td>" . ($emailsString ? $emailsString : '&mdash;') . "</td>";  // Display "—" if no emails

        // Display other information
        echo "<td>" . (!empty($row["designation"]) ? $row["designation"] : '') . "</td>";
        echo "<td>" . (!empty($row["organization"]) ? $row["organization"] : '') . "</td>";
        echo "<td>" . ($row["number_of_events_attended"] ? $row["number_of_events_attended"] : '') . "</td>";

        // Edit and delete buttons
        echo "<td>
                <a href='edit_subscriber.php?id=" . $row["subscriber_id"] . "' class='btn btn-outline-warning btn-sm'><i class='bi bi-pencil-square'></i></a> |
                <a href='javascript:void(0);' onclick='deleteRecord(" . $row["subscriber_id"] . ")' class='btn btn-outline-danger btn-sm'><i class='bi bi-trash3'></i></a> |
                <a href='subscribers.php?id=" . $row['subscriber_id'] . "' class='btn btn-outline-success btn-sm'><i class='bi bi-download'></i> VCard</a>
              </td>";
        echo "</tr>";
    }
} else if ($page == 1) {
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
    $("#example1").DataTable({
      "responsive": true, "lengthChange": false, "autoWidth": false,
      "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
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
</script>


        
</body>
</html>
