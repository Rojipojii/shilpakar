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

function isValidMobileNumber($mobile_number) {
    // Check if the mobile number contains only numeric characters
    return ctype_digit($mobile_number);
}

function getNumberCategory($mobile_number) {
    // Extract the prefix from the mobile number
    $prefix = substr($mobile_number, 0, 3);

    // Check the prefix to categorize the number
    if (in_array($prefix, ["984", "985", "986", "976", "974", "975"])) {
        return "NTC";
    } elseif (in_array($prefix, ["980", "981", "982", "970"])) {
        return "Ncell";
    } elseif (in_array($prefix, ["961", "962", "988"])) {
        return "Smart";
    } elseif ($prefix === "972") {
        return "UTL";
    } else {
        return "Others";
    }
}

// Initialize category arrays
$ntcNumbers = [];
$ncellNumbers = [];
$smartNumbers = [];
$utlNumbers = [];
$otherNumbers = [];

// Query to fetch unique mobile numbers
$sql = "SELECT DISTINCT phone_number FROM subscribers WHERE phone_number IS NOT NULL AND phone_number != ''";


$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $phone_number = $row['phone_number'];

        // Check if the mobile number is valid (contains only numeric characters)
        if (isValidMobileNumber($phone_number)) {
            $category = getNumberCategory($phone_number);

            // Categorize numbers into respective arrays
            switch ($category) {
                case "NTC":
                    $ntcNumbers[] = $phone_number;
                    break;
                case "Ncell":
                    $ncellNumbers[] = $phone_number;
                    break;
                case "Smart":
                    $smartNumbers[] = $phone_number;
                    break;
                case "UTL":
                    $utlNumbers[] = $phone_number;
                    break;
                default:
                    $otherNumbers[] = $phone_number;
                    break;
            }
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

    <!-- Include Bootstrap CSS from CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>

    <style>
        .category {
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px;
        }

        .category h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .category ul {
            list-style: none;
            padding: 0;
        }

        .category ul li {
            font-size: 16px;
            margin: 5px 0;
        }

        .container {
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

          <div class="col-md-2">
            <div class="category">
                <h2>NTC Numbers (<?php echo count($ntcNumbers); ?>)</h2>
                <button class="btn btn-outline-success copy-button" data-clipboard-target="#ntc-list"><i class="bi bi-copy"></i>Copy</button>
                <ul id="ntc-list">
                    <?php foreach ($ntcNumbers as $number) : ?>
                        <li><?php echo $number; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-2">
            <div class="category">
                <h2>Ncell Numbers (<?php echo count($ncellNumbers); ?>)</h2>
                <button class="btn btn-outline-success copy-button" data-clipboard-target="#ncell-list"><i class="bi bi-copy"></i>Copy</button>
                <ul id="ncell-list">
                    <?php foreach ($ncellNumbers as $number) : ?>
                        <li><?php echo $number; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-2">
            <div class ="category">
                <h2>Smart Numbers (<?php echo count($smartNumbers); ?>)</h2>
                <button class="btn btn-outline-success copy-button" data-clipboard-target="#smart-list"><i class="bi bi-copy"></i>Copy</button>
                <ul id="smart-list">
                    <?php foreach ($smartNumbers as $number) : ?>
                        <li><?php echo $number; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-2">
            <div class="category">
                <h2>UTL Numbers (<?php echo count($utlNumbers); ?>)</h2>
                <button class="btn btn-outline-success copy-button" data-clipboard-target="#utl-list"><i class="bi bi-copy"></i>Copy</button>
                <ul id="utl-list">
                    <?php foreach ($utlNumbers as $number) : ?>
                        <li><?php echo $number; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-2">
            <div class="category">
                <h2>Other Numbers (<?php echo count($otherNumbers); ?>)</h2>
                <button class="btn btn-outline-success copy-button" data-clipboard-target="#other-list"><i class="bi bi-copy"></i>Copy</button>
                <ul id="other-list">
                    <?php foreach ($otherNumbers as $number) : ?>
                        <li><?php echo $number; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
   </div>
 </div>
</div>


<script>
    // Initialize Clipboard.js
    var clipboard = new ClipboardJS('.copy-button');

    clipboard.on('success', function (e) {
        e.clearSelection(); // Clear the selection
        e.trigger.textContent = 'Copied!';
    });

    clipboard.on('error', function (e) {
        alert("Failed to copy numbers. Please try again.");
    });
</script>

</body>
</html>






