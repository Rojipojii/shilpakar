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

// Function to search for records based on the search query
function searchInDatabase($conn, $searchQuery) {
    $sql = "SELECT * FROM subscribers WHERE
            full_name LIKE ? OR
            mobile_number LIKE ? OR
            email LIKE ? OR
            designation LIKE ? OR
            organization LIKE ?";
    $searchParam = "%" . $searchQuery . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    return $stmt->get_result();
}

// Define a function to generate and deliver VCF file for a given subscriber ID
function generateVCF($conn, $subscriberID) {
    $sql = "SELECT * FROM subscribers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriberID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $subscriber = $result->fetch_assoc();
        // Build VCF content
        $vcfContent = "BEGIN:VCARD\r\n";
        $vcfContent .= "VERSION:3.0\r\n";
        $vcfContent .= "FN:" . $subscriber['full_name'] . "\r\n";
        $vcfContent .= "TEL:" . $subscriber['mobile_number'] . "\r\n";
        $vcfContent .= "EMAIL:" . $subscriber['email'] . "\r\n";
        if (!empty($subscriber['organization'])) {
            $vcfContent .= "ORG:" . $subscriber['organization'] . "\r\n"; // Add organization if not empty
        }
        $vcfContent .= "END:VCARD\r\n";

        // Send appropriate headers for download
        header("Content-Type: text/vcard");
        header("Content-Disposition: attachment; filename=" . $subscriber['full_name'] . ".vcf");
        echo $vcfContent;
        exit;
    }
}


// Handle search query if it's present in the URL
if (isset($_GET["search"])) {
    $searchQuery = $_GET["search"];
    $result = searchInDatabase($conn, $searchQuery);
} else {
    $result = false;
}

// Handle the download action if "id" parameter is present in the URL
if (isset($_GET["id"])) {
    $subscriberID = $_GET["id"];
    generateVCF($conn, $subscriberID);
}

// Display the search form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>

    <!-- Include Bootstrap CSS from a CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/css/bootstrap.min.css">

    <style>
        .business-card {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px;
        }

        .business-card p {
            margin: 5px 0;
        }

        .download-link {
            display: block;
            text-align: right;
        }
    </style>
</head>
<body>

<?php include("header.php"); ?>

<div class="container-fluid mt-5">
    <h1 class="mb-4">Search Results</h1>

    <?php
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Display the search result in a Bootstrap card
                echo '<div class="card business-card">';
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . $row['full_name'] . '</h5>';
                echo '<p class="card-text">' . $row['mobile_number'] . '</p>';
                echo '<p class="card-text">' . $row['email'] . '</p>';
                echo '<p class="card-text">' . $row['designation'] . '</p>';

                // Display "Organization" even if the field is empty
                echo '<p class="card-text">' . (!empty($row['organization']) ? $row['organization'] : '') . '</p>';

                // Add a download link for .vcf format
                echo '<a class="download-link" href="search.php?id=' . $row['id'] . '">Download VCF</a>';
                
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p class="mt-3">No records found.</p>';
        }
    }
    ?>
</div>

<!-- Include Bootstrap JavaScript from a CDN if needed -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/js/bootstrap.min.js"></script>
</body>
</html>

