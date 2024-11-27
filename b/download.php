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
require  '../vendor/autoload.php'; // Include PhpSpreadsheet's autoloader

// Set the timezone to Nepal (Asia/Kathmandu)
date_default_timezone_set("Asia/Kathmandu");

// Function to retrieve data from the database
function fetchData($conn) {
    $sql = "SELECT * FROM subscribers";
    $result = $conn->query($sql);
    return $result;
}

// Handle download format selection
if (isset($_GET['format']) && ($_GET['format'] == 'csv' || $_GET['format'] == 'xlsx')) {
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
    <!-- Add your CSS and other meta tags here -->
    <style>
        /* Basic CSS for header and navigation */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 10px;
        }

        .container {
            padding: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .download-links {
            margin-bottom: 10px;
        }

        .download-links a {
            margin-right: 10px;
        }
    </style>
</head>
<body>
<?php include("header.php"); ?>
    <div class="container">
        <h2>Data from Database</h2>
        <div class="download-links">
        <span class="material-symbols-outlined">
download_for_offline
</span>
            <a href="download.php?format=csv"><span class="material-symbols-outlined"> 
csv
</span></a>
            <a href="download.php?format=xlsx"><span class="material-symbols-outlined">
table
</span> </a>
        </div>
        <table>
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
                        echo "<tr>";
                        echo "<td>" . $serialNumber . "</td>"; // Display the serial number
                        echo "<td>" . $row["full_name"] . "</td>";
                        echo "<td>" . $row["mobile_number"] . "</td>";
                        echo "<td>" . $row["email"] . "</td>";
                        echo "<td>" . $row["designation"] . "</td>";
                        echo "<td>" . $row["organization"] . "</td>";
                        echo "</tr>";

                        $serialNumber++; // Increment the serial number
                    }
                } else {
                    echo "<tr><td colspan='6'>No records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div> 
</body>
</html>
