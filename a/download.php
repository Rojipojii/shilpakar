<?php
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

require_once "db.php";
require '../vendor/autoload.php'; // Include PhpSpreadsheet's autoloader

// Set the timezone to Nepal (Asia/Kathmandu)
date_default_timezone_set("Asia/Kathmandu");

// Function to retrieve data from the database, excluding entries with missing or invalid email addresses and phone numbers
function fetchData($conn) {
    $sql = "
        SELECT 
            s.*, 
            GROUP_CONCAT(DISTINCT do.organization ORDER BY do.organization SEPARATOR ', ') AS organizations,
            GROUP_CONCAT(DISTINCT do.designation ORDER BY do.designation SEPARATOR ', ') AS designations,
            GROUP_CONCAT(DISTINCT e.email ORDER BY e.email SEPARATOR ', ') AS emails,  -- Ensure unique emails
            GROUP_CONCAT(DISTINCT p.phone_number ORDER BY p.phone_number SEPARATOR ', ') AS phone_numbers -- Ensure unique phone numbers
        FROM 
            subscribers s
        INNER JOIN emails e ON s.subscriber_id = e.subscriber_id
        INNER JOIN phone_numbers p ON s.subscriber_id = p.subscriber_id
        LEFT JOIN designation_organization do ON s.subscriber_id = do.subscriber_id
        WHERE 
            e.email IS NOT NULL AND e.email <> '' AND
            p.phone_number IS NOT NULL AND
            p.phone_number REGEXP '^[0-9]+$'
        GROUP BY 
            s.subscriber_id
    ";
    $result = $conn->query($sql);
    return $result;
}



function getEmails($conn, $subscriberId) {
    $sql = "SELECT email FROM emails WHERE subscriber_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getPhoneNumbers($conn, $subscriberId) {
    $sql = "SELECT phone_number FROM phone_numbers WHERE subscriber_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get designations for a subscriber
function getDesignations($conn, $subscriberId) {
    $sql = "SELECT designation FROM designation_organization WHERE subscriber_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get organizations for a subscriber
function getOrganizations($conn, $subscriberId) {
    $sql = "SELECT organization FROM designation_organization WHERE subscriber_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


function generateVCard($row, $emails, $phoneNumbers, $organizations, $designations) {
    $vcard = "BEGIN:VCARD\r\n";
    $vcard .= "VERSION:3.0\r\n";
    $vcard .= "FN:" . $row["full_name"] . "\r\n";

    // Remove duplicate phone numbers and add them to the vCard
    $uniquePhoneNumbers = array_unique(array_map(function($phone) {
        return $phone['phone_number']; // Assuming the result is an associative array with 'phone_number' as the key
    }, $phoneNumbers));
    
    foreach ($uniquePhoneNumbers as $phone) {
        $vcard .= "TEL:" . $phone . "\r\n";
    }

    // Remove duplicate emails and add them to the vCard
    $uniqueEmails = array_unique(array_map(function($email) {
        return $email['email']; // Assuming the result is an associative array with 'email' as the key
    }, $emails));

    foreach ($uniqueEmails as $email) {
        $vcard .= "EMAIL:" . $email . "\r\n";
    }

    // Add all organizations to the vCard
    if (!empty($organizations)) {
        foreach ($organizations as $organization) {
            $vcard .= "ORG:" . $organization['organization'] . "\r\n"; // Assuming the result is an associative array with 'organization' as the key
        }
    } else {
        $vcard .= "ORG:;\r\n"; // Set "ORG" to null if no organizations
    }

    // Add all designations to the vCard
    if (!empty($designations)) {
        foreach ($designations as $designation) {
            $vcard .= "TITLE:" . $designation['designation'] . "\r\n"; // Assuming the result is an associative array with 'designation' as the key
        }
    } else {
        $vcard .= "TITLE:;\r\n"; // Set "TITLE" to null if no designations
    }

    $vcard .= "END:VCARD\r\n";
    return $vcard;
}


// Handle download format selection
if (isset($_GET['format']) && ($_GET['format'] == 'csv' || $_GET['format'] == 'xlsx' || $_GET['format'] == 'vcf')) {
    $format = $_GET['format'];

    // Generate the current date and time in the desired format
    $currentDateTime = date("Y-m-d-H-i");

    if ($format == 'csv') {
        // Output CSV data
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename={$currentDateTime}.csv");
    
        // Fetch data from the database
        $result = fetchData($conn);
    
        // Output CSV header
        $output = fopen('php://output', 'w');
        fputcsv($output, ["Full Name", "Phone Numbers", "Emails", "Designations", "Organizations"]);
    
        // Add data from the database to the CSV
        while ($row = $result->fetch_assoc()) {
            // Fetch emails, phone numbers, designations, and organizations for this subscriber
            $emails = array_column(getEmails($conn, $row['subscriber_id']), 'email');
            $phoneNumbers = array_column(getPhoneNumbers($conn, $row['subscriber_id']), 'phone_number');
            $designations = array_column(getDesignations($conn, $row['subscriber_id']), 'designation');
            $organizations = array_column(getOrganizations($conn, $row['subscriber_id']), 'organization');
    
            // Use array_unique to remove duplicates
            $emails = array_unique($emails);
            $phoneNumbers = array_unique($phoneNumbers);
            $designations = array_unique($designations);
            $organizations = array_unique($organizations);
    
            // Create comma-separated strings, avoiding commas before empty values
        $emailString = implode(", ", array_filter(array_map('trim', $emails)));
        $phoneNumberString = implode(", ", array_filter(array_map('trim', $phoneNumbers)));
        $designationString = implode(", ", array_filter(array_map('trim', $designations)));
        $organizationString = implode(", ", array_filter(array_map('trim', $organizations)));
    
            // Write data to the CSV
            fputcsv($output, [
                $row["full_name"],
                $phoneNumberString,
                $emailString,
                $designationString,
                $organizationString
            ]);
        }
    
        fclose($output);
        exit;
    } elseif ($format == 'xlsx') {
        // Create a new PhpSpreadsheet instance
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        // Set headers
        $headers = ["ID", "Full Name", "Phone Numbers", "Emails", "Designations", "Organizations"];
        $sheet->fromArray([$headers], null, 'A1');
    
        // Fetch data from the database
        $result = fetchData($conn);
    
        // Add data from the database to the XLSX file
        $rowIndex = 2;
        while ($row = $result->fetch_assoc()) {
            // Fetch emails, phone numbers, designations, and organizations for this subscriber
            $emails = array_column(getEmails($conn, $row['subscriber_id']), 'email');
            $phoneNumbers = array_column(getPhoneNumbers($conn, $row['subscriber_id']), 'phone_number');
            $designations = array_column(getDesignations($conn, $row['subscriber_id']), 'designation');
            $organizations = array_column(getOrganizations($conn, $row['subscriber_id']), 'organization');
    
            // Use array_unique to remove duplicates
            $emails = array_unique($emails);
            $phoneNumbers = array_unique($phoneNumbers);
            $designations = array_unique($designations);
            $organizations = array_unique($organizations);
    
            // Create comma-separated strings, avoiding commas before empty values
        $emailString = implode(", ", array_filter(array_map('trim', $emails)));
        $phoneNumberString = implode(", ", array_filter(array_map('trim', $phoneNumbers)));
        $designationString = implode(", ", array_filter(array_map('trim', $designations)));
        $organizationString = implode(", ", array_filter(array_map('trim', $organizations)));
    
            // Prepare row data
            $data = [
                $row["subscriber_id"],
                $row["full_name"],
                $phoneNumberString,
                $emailString,
                $designationString,
                $organizationString
            ];
    
            // Write data to the sheet
            $sheet->fromArray([$data], null, "A$rowIndex");
            $rowIndex++;
        }
    
        // Create a Writer
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    
        // Output the file to the browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename={$currentDateTime}.xlsx");
        $writer->save('php://output');
    
        exit;    
    } elseif ($format == 'vcf') {
        // Output a single vCard file for all contacts
        header("Content-Type: text/vcard");
        header("Content-Disposition: attachment; filename={$currentDateTime}.vcf");

        // Start with an empty vCard string
        $allVCards = "";

        // Fetch data from the database
        $result = fetchData($conn);

        // Generate vCard for each row and append to the $allVCards string
        while ($row = $result->fetch_assoc()) {
            // Fetch emails, phone numbers, designations, and organizations for this subscriber
            $emails = array_column(getEmails($conn, $row['subscriber_id']), 'email');
            $phoneNumbers = array_column(getPhoneNumbers($conn, $row['subscriber_id']), 'phone_number');
            $designations = array_column(getDesignations($conn, $row['subscriber_id']), 'designation');
            $organizations = array_column(getOrganizations($conn, $row['subscriber_id']), 'organization');


             // Remove duplicates from emails and phone numbers
        $emails = array_unique($emails);
        $phoneNumbers = array_unique($phoneNumbers);
        $designations = array_unique($designations);
        $organizations = array_unique($organizations);

            // Generate vCard data
            $vcard = "BEGIN:VCARD\r\n";
            $vcard .= "VERSION:3.0\r\n";
            $vcard .= "FN:" . $row["full_name"] . "\r\n";

            // Add phone numbers (only if available and not empty or whitespace)
        if (!empty($phoneNumbers)) {
            foreach ($phoneNumbers as $phone) {
                $phone = trim($phone); // Remove leading and trailing whitespace
                if (!empty($phone)) { // Check if phone is not empty after trimming
                    $vcard .= "TEL:" . $phone . "\r\n";
                }
            }
        }

        // Add emails (only if available and not empty or whitespace)
        if (!empty($emails)) {
            foreach ($emails as $email) {
                $email = trim($email); // Remove leading and trailing whitespace
                if (!empty($email)) { // Check if email is not empty after trimming
                    $vcard .= "EMAIL:" . $email . "\r\n";
                }
            }
        }

        // Add organizations (only if available and not empty or whitespace)
        if (!empty($organizations)) {
            foreach ($organizations as $organization) {
                $organization = trim($organization); // Remove leading and trailing whitespace
                if (!empty($organization)) { // Check if organization is not empty after trimming
                    $vcard .= "ORG:" . $organization . "\r\n";
                }
            }
        }

        // Add designations (only if available and not empty or whitespace)
        if (!empty($designations)) {
            foreach ($designations as $designation) {
                $designation = trim($designation); // Remove leading and trailing whitespace
                if (!empty($designation)) { // Check if designation is not empty after trimming
                    $vcard .= "TITLE:" . $designation . "\r\n";
                }
            }
        }

            $vcard .= "END:VCARD\r\n";

            $allVCards .= $vcard;
        }

        // Output the combined vCard data
        echo $allVCards;

        exit;
    }
}

// Retrieve data from the database
$result = fetchData($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Data</title>

    <!-- Add Bootstrap CSS from a CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/css/bootstrap.min.css">

    <!-- Add custom CSS styles -->
    <style>
        /* Style for entries with missing fields */
        .highlight-missing {
            background-color: #FFCCCC; /* Light red background */
            color: #FF0000; /* Red text color */
        }

        .container-fluid {
            padding: 20px;
        }
    </style>
</head>
<body>
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
    <h2>Data from Database</h2>
    <div class="download-links">
        <a href="download.php?format=csv" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv"></i>Download CSV</a>
        <a href="download.php?format=xlsx" class="btn btn-outline-success btn-sm"><i class="bi bi-file-excel"></i>Download Excel</a>
        <a href="download.php?format=vcf"  class="btn btn-outline-success btn-sm"><i class="bi bi-person-vcard-fill"></i>Download Vcard</a>
    </div>
    <!-- <table class="table">
        <thead>
            <tr>
                <th>Serial Number</th>
                <th>Full Name</th>
                <th>Mobile Number</th>
                <th>Email</th>
                <th>Designation</th>
                <th>Organization</th>
            </tr>
        </thead>
        <tbody>
        <?php
            // Retrieve data from the database
            $result = fetchData($conn);
            
            // Initialize a counter for the serial number
            $serialNumber = 1;

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Check for missing fields and add the "highlight-missing" class if any are missing
                    $missingFields = array();
                    if (empty($row["full_name"])) {
                        $missingFields[] = 'phone_name';
                    }
                    if (empty($row["mobile_number"])) {
                        $missingFields[] = 'phone_number';
                    }
                    if (empty($row["email"])) {
                        $missingFields[] = 'email';
                    }
                    if (empty($row["designation"])) {
                        $missingFields[] = 'designation';
                    }
                    if (empty($row["organization"])) {
                        $missingFields[] = 'organization';
                    }

                    // Check if any fields are missing
                    if (!empty($missingFields)) {
                        // If any fields are missing, apply the "highlight-missing" class to the row
                        echo "<tr class='highlight-missing'>";
                    } else {
                        // If all fields are present, display the row without the class
                        echo "<tr>";
                    }

                    echo "<td>" . $serialNumber . "</td>"; // Display the serial number

                    // Display empty cells for missing fields
                    if (in_array('full_name', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["full_name"] . "</td>";
                    }

                    if (in_array('phone_number', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["phone_number"] . "</td>";
                    }

                    if (in_array('email', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["email"] . "</td>";
                    }

                    if (in_array('designation', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["designation"] . "</td>";
                    }

                    if (in_array('organization', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["organization"] . "</td>";
                    }

                    echo "</tr>";

                    $serialNumber++; // Increment the serial number
                }
            } else {
                echo "<tr><td colspan='6'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div> -->

<!-- Add Bootstrap JavaScript from a CDN if needed -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
