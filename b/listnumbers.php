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

// Include the database connection file
require_once "db.php";

function getNumberCategory($mobile_number) {
    // Extract the prefix from the mobile number
    $prefix = substr($mobile_number, 0, 3);

    // Check the prefix to categorize the number
    if (in_array($prefix, ["984", "985", "986", "976", "974", "975"])) {
        return "NTC";
    } elseif (in_array($prefix, ["980", "981", "982", "970"])) {
        return "Ncell";
    } else {
        return "Others";
    }
}

// Initialize category arrays
$ntcNumbers = [];
$ncellNumbers = [];
$otherNumbers = [];

// Query to fetch mobile numbers
$sql = "SELECT mobile_number FROM subscribers WHERE mobile_number IS NOT NULL AND mobile_number != ''";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mobile_number = $row['mobile_number'];
        $category = getNumberCategory($mobile_number);

        // Categorize numbers into respective arrays
        if ($category === "NTC") {
            $ntcNumbers[] = $mobile_number;
        } elseif ($category === "Ncell") {
            $ncellNumbers[] = $mobile_number;
        } else {
            $otherNumbers[] = $mobile_number;
        }
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Numbers List</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<?php include("header.php"); ?>

<div class="container">
    <h1>Mobile Numbers List</h1>

    <table>
        <tr>
            <th>NTC Numbers</th>
            <th>Ncell Numbers</th>
            <th>Others</th>
        </tr>

        <?php
        for ($i = 0; $i < max(count($ntcNumbers), count($ncellNumbers), count($otherNumbers)); $i++) {
            echo "<tr><td>";
            if (isset($ntcNumbers[$i])) {
                echo $ntcNumbers[$i];
            }
            echo "</td><td>";
            if (isset($ncellNumbers[$i])) {
                echo $ncellNumbers[$i];
            }
            echo "</td><td>";
            if (isset($otherNumbers[$i])) {
                echo $otherNumbers[$i];
            }
            echo "</td></tr>";
        }
        ?>
    </table>
</div>
</body>
</html>
