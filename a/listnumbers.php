<?php 
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

// Include the database connection file
require_once "db.php";

// Function to validate a mobile number
function isValidMobileNumber($mobile_number) {
    // Check if the mobile number contains only numeric characters and is 10 digits long
    $mobile_number = preg_replace('/\D/', '', trim($mobile_number)); // Remove spaces at the start and end
    return ctype_digit($mobile_number) && strlen($mobile_number) === 10;
}

// Function to categorize a number based on its prefix
function getNumberCategory($mobile_number) {
    $prefix = substr($mobile_number, 0, 3); // Extract the first 3 digits

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
$invalidNumbers = [];

// Query to fetch phone_number and subscriber_id
$sql = "SELECT phone_number, subscriber_id FROM phone_numbers 
        WHERE phone_number IS NOT NULL AND phone_number != ''";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $phone_number = $row['phone_number'];
        $subscriber_id = $row['subscriber_id'];

        // Check if the mobile number is valid
        if (isValidMobileNumber($phone_number)) {
            $category = getNumberCategory($phone_number);

            // Categorize numbers into respective arrays
            switch ($category) {
                case "NTC":
                    if (!in_array($phone_number, $ntcNumbers)) {
                        $ntcNumbers[] = $phone_number;
                    }
                    break;
                case "Ncell":
                    if (!in_array($phone_number, $ncellNumbers)) {
                        $ncellNumbers[] = $phone_number;
                    }
                    break;
                case "Smart":
                    if (!in_array($phone_number, $smartNumbers)) {
                        $smartNumbers[] = $phone_number;
                    }
                    break;
                case "UTL":
                    if (!in_array($phone_number, $utlNumbers)) {
                        $utlNumbers[] = $phone_number;
                    }
                    break;
                default:
                    if (!in_array($phone_number, $otherNumbers)) {
                        $otherNumbers[] =[
                'phone_number' => $phone_number,
                'subscriber_id' => $subscriber_id ?: "Missing ID"
                        ];
                    }
                    break;
            }
        } else {
            // Add invalid numbers to the array if not already present
            $invalidNumbers[] = [
                'phone_number' => $phone_number,
                'subscriber_id' => $subscriber_id ?: "Missing ID"
            ];
        }
    }
}

// Ensure unique invalid numbers only (in case of duplicates)
$invalidNumbers = array_map("unserialize", array_unique(array_map("serialize", $invalidNumbers)));

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

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <!-- Categories -->
                <?php 
                $categories = [
                    "NTC Numbers" => $ntcNumbers,
                    "Ncell Numbers" => $ncellNumbers,
                    "Smart Numbers" => $smartNumbers,
                    "UTL Numbers" => $utlNumbers,
                    "Other Numbers" => $otherNumbers,
                    "Invalid Numbers" => $invalidNumbers
                ];

                foreach ($categories as $categoryName => $numbers): 
                    // Sort the numbers in ascending order
                    if (is_array($numbers)) {
                        usort($numbers, function($a, $b) {
                            $phoneA = is_array($a) ? $a['phone_number'] : $a;
                            $phoneB = is_array($b) ? $b['phone_number'] : $b;
                            return strcmp($phoneA, $phoneB);
                        });
                    }
                ?>
                    <div class="col-md-2">
                        
                        <div class="category">
                            <h2><?php echo $categoryName; ?> (<?php echo count($numbers); ?>)</h2>
                            <button class="btn btn-outline-<?php echo $categoryName === "Invalid Numbers" ? "danger" : "success"; ?> copy-button" 
                                    data-clipboard-target="#<?php echo strtolower(str_replace(' ', '-', $categoryName)); ?>-list">
                                Copy
                            </button>
                            <ul id="<?php echo strtolower(str_replace(' ', '-', $categoryName)); ?>-list">
                            <?php foreach ($numbers as $number): ?>
    <li>
    <?php 
    if ($categoryName === "Other Numbers" && is_array($number) && isset($number['subscriber_id'])): ?>
        
        <a href="edit_subscriber.php?id=<?php echo urlencode($number['subscriber_id']); ?>" 
           style="text-decoration: underline; color: inherit;" 
           onmouseover="this.style.textDecoration='none'; this.style.color='inherit';" 
           onmouseout="this.style.textDecoration='underline'; this.style.color='inherit';">
            <?php echo htmlspecialchars($number['phone_number']); ?>
        </a>
    <?php elseif ($categoryName === "Invalid Numbers" && is_array($number) && isset($number['subscriber_id'])): ?>
        <a href="edit_subscriber.php?id=<?php echo urlencode($number['subscriber_id']); ?>" 
           style="text-decoration: underline; color: inherit;" 
           onmouseover="this.style.textDecoration='none'; this.style.color='inherit';" 
           onmouseout="this.style.textDecoration='underline'; this.style.color='inherit';">
            <?php echo htmlspecialchars($number['phone_number']); ?>
        </a>
    <?php else: ?>
        <?php echo htmlspecialchars(is_array($number) ? $number['phone_number'] : $number); ?>
    <?php endif; ?>
    </li>
<?php endforeach; ?>

                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
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
