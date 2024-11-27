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
    $sql = "SELECT * FROM subscribers WHERE 
            email IS NOT NULL AND email <> '' AND
            mobile_number IS NOT NULL AND
            mobile_number REGEXP '^[0-9]+$'";
    $result = $conn->query($sql);
    return $result;
}

// Function to generate vCard data
function generateVCard($row) {
    $vcard = "BEGIN:VCARD\r\n";
    $vcard .= "VERSION:3.0\r\n";
    $vcard .= "FN:" . $row["full_name"] . "\r\n";
    $vcard .= "TEL:" . $row["mobile_number"] . "\r\n";
    $vcard .= "EMAIL:" . $row["email"] . "\r\n";

    // Check if the "Organization" field is not empty before adding it to vCard
    if (!empty($row["organization"])) {
        $vcard .= "ORG:" . $row["organization"] . "\r\n";
    }

    $vcard .= "TITLE:" . $row["designation"] . "\r\n";
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
        fputcsv($output, ["ID", "Full Name", "Mobile Number", "Email", "Designation", "Organization"]);

        // Add data from the database to the CSV
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row["id"], $row["full_name"], $row["mobile_number"], $row["email"], $row["designation"], $row["organization"]]);
        }

        fclose($output);
        exit;
    } elseif ($format == 'xlsx') {
        // Create a new PhpSpreadsheet instance
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = ["ID", "Full Name", "Mobile Number", "Email", "Designation", "Organization"];
        $sheet->fromArray([$headers], null, 'A1');

        // Fetch data from the database
        $result = fetchData($conn);

        // Add data from the database to the XLSX file
        $rowIndex = 2;
        while ($row = $result->fetch_assoc()) {
            $data = [$row["id"], $row["full_name"], $row["mobile_number"], $row["email"], $row["designation"], $row["organization"]];
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
            $allVCards .= generateVCard($row);
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
<div class="container-fluid mt-5">
    <h2>Data from Database</h2>
    <div class="download-links">
        <a href="download.php?format=csv" class="btn btn-primary">Download CSV</a>
        <a href="download.php?format=xlsx" class="btn btn-primary">Download Excel</a>
        <a href="download.php?format=vcf" class="btn btn-primary">Download vCard</a>
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
                        $missingFields[] = 'full_name';
                    }
                    if (empty($row["mobile_number"])) {
                        $missingFields[] = 'mobile_number';
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

                    if (in_array('mobile_number', $missingFields)) {
                        echo "<td></td>";
                    } else {
                        echo "<td>" . $row["mobile_number"] . "</td>";
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
