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

// Function to fetch total number of people according to category
function getTotalPeopleByCategory($conn, $category) {
    if (empty($category) || in_array("all", $category)) {
        // When "All" or no category is selected, count all subscribers
        $sql = "SELECT COUNT(*) AS total FROM subscribers";
        $stmt = $conn->prepare($sql);
    } else {
         // Count subscribers for the selected categories
$categoryPlaceholders = implode(',', array_fill(0, count($category), '?'));
$sql = "SELECT COUNT(*) AS total FROM event_subscriber_mapping WHERE category_id IN ($categoryPlaceholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('s', count($category)), ...$category);

    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row["total"];
}



// Function to fetch total number of people according to organizer
function getTotalPeopleByOrganizer($conn, $organizer) {
    if (empty($organizer) || in_array("all", $organizer)) {
        // When "All" or no organizer is selected, count all subscribers
        $sql = "SELECT COUNT(*) AS total FROM subscribers";
        $stmt = $conn->prepare($sql);
    } else {
        // Count subscribers for the selected organizer
        $organizerPlaceholders = implode(',', array_fill(0, count($organizer), '?'));
        $sql = "SELECT COUNT(*) AS total FROM event_subscriber_mapping WHERE organizer_id IN ($organizerPlaceholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($organizer)), ...$organizer);
    }


    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row["total"];
}


// Fetch the list of categories from the "categories" table
function getCategoryList($conn) {
    $sql = "SELECT * FROM categories";
    $result = $conn->query($sql);
    $categories = array();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

// Initialize $category variable
$category = isset($_POST["category"]) ? $_POST["category"] : array();

// Fetch the list of categories
$categoryList = getCategoryList($conn);

// Initialize $emailLists as an empty array
$emailLists = array();


// Fetch the list of organizers from the "Organizers" table
function getOrganizerList($conn) {
    $sql = "SELECT * FROM organizers";
    $result = $conn->query($sql);
    $organizers = array();
    while ($row = $result->fetch_assoc()) {
        $organizers[] = $row;
    }
    return $organizers;
}

// Initialize $organizer variable
$organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : array();

// Fetch the list of organizers
$organizerList = getOrganizerList($conn);

// Function to fetch emails by category and organizer with a limit and offset
function getEmailsByCategoryOrganizerWithLimit($conn, $category, $organizer, $offset, $limit) {
    // Create an array to hold the category IDs
    $categoryIds = array();

    // Create placeholders for the category IDs
    $categoryPlaceholders = array();

    // Build the SQL query with placeholders for category and organizer values
    $sql = "SELECT e.email, s.full_name 
            FROM emails e
            INNER JOIN event_subscriber_mapping esm ON e.subscriber_id = esm.subscriber_id
            INNER JOIN subscribers s ON e.subscriber_id = s.subscriber_id
            WHERE e.hidden = 0 AND e.does_not_exist = 0 ";

    // Check if any categories are selected
if (!empty($category) && !in_array("all", $category)) {
    $categoryPlaceholders = array();
    foreach ($category as $categoryId) {
        $categoryIds[] = $categoryId;
        $categoryPlaceholders[] = '?';
    }
    $sql .= " AND esm.category_id IN (" . implode(',', $categoryPlaceholders) . ")";
}

// Check if any organizers are selected
if (!empty($organizer) && !in_array("all", $organizer)) {
    $organizerPlaceholders = array();
    foreach ($organizer as $organizerId) {
        $organizerIds[] = $organizerId;
        $organizerPlaceholders[] = '?';
    }
    $sql .= " AND esm.organizer_id IN (" . implode(',', $organizerPlaceholders) . ")";
}

$sql .= " ORDER BY e.email LIMIT ?, ?";

    // Initialize an array to hold the parameters
    $params = $categoryIds;

    // If organizers are selected, add them to the parameters array
    if (!empty($organizerIds)) {
        $params = array_merge($params, $organizerIds);
    }

    // Add $offset and $limit parameters to the end of the parameters array
    $params[] = $offset;
    $params[] = $limit;

    // Create a string of type specifiers for the parameters
    $paramTypes = str_repeat('i', count($params)); // Assuming all category and organizer IDs are integers

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters using bind_param
    if (!$stmt->bind_param($paramTypes, ...$params)) {
        die("Bind failed: " . $stmt->error);
    }

    // Execute the statement
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $emails = array();

    // Update the loop that fetches and formats emails
    while ($row = $result->fetch_assoc()) {
        $full_name = trim($row["full_name"]);
        $email = strtolower(trim($row["email"])); // Convert email to lowercase

        // Check if the email is non-empty and has a valid format
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Capitalize the first letter of each part of the name
            $nameParts = explode(' ', $full_name);
            $capitalizedName = '';
            foreach ($nameParts as $namePart) {
                $capitalizedName .= ucfirst(strtolower($namePart)) . ' ';
            }
            $capitalizedName = trim($capitalizedName);

            // Reconstruct and format the email with a single pair of double quotation marks
            $formattedEmail = '"' . $capitalizedName . '" <' . $email . '>';
            $emails[] = $formattedEmail;
        }
    }

    return $emails;
}




// Handle form submission for category and organizer-based email list
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["createListCategoryOrganizer"])) {
    $emailLists = array(); // Initialize $emailLists as an empty array

    // Define the limit for emails per box
    $emailsPerBox = 500;

    // Fetch emails based on selected categories and organizers
    $offset = 0;
    do {
        // Fetch a batch of emails
        $selectedEmails = getEmailsByCategoryOrganizerWithLimit($conn, $category, $organizer, $offset, $emailsPerBox);

        // Initialize an associative array to store unique emails
        $uniqueEmails = array();

        // Iterate through the selected emails and add them to the uniqueEmails array
        foreach ($selectedEmails as $email) {
            // Normalize email addresses by lowercasing and removing spaces within angle brackets
            $normalizedEmail = preg_replace('/\s+/', ' ', $email);
            $uniqueEmails[$normalizedEmail] = true;
        }

        // Merge the unique emails with the existing ones
        $emailLists = array_merge($emailLists, array_keys($uniqueEmails));

        // Increment the offset for the next batch
        $offset += $emailsPerBox;
    } while (!empty($selectedEmails));

    // Sort the email addresses alphabetically
    sort($emailLists);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email List</title>

    <!-- Include Bootstrap CSS from a CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>

    <style>
        /* Add custom styles if needed */
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
            <!-- Display the list of categories with checkboxes -->
            <form method="post" action="createmailinglist.php">

                <!-- Add a single "Create List" button at the bottom -->
    <div class="row">
        <div class="col-md-12">
            <input type="submit" name="createListCategoryOrganizer" value="Create List" class="btn btn-outline-success">
        </div>
    </div>
    
                <h2>According to Category</h2>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th></th>
                            <th>Number of Participants</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr> 
    <td>All Categories</td>
    <td>
        <input type="checkbox" name="category[]" value="all"
            <?php if (in_array('all', $category)) echo 'checked'; ?>>
    </td>
    <td><?php echo getTotalPeopleByCategory($conn, ['all']); ?></td>
</tr>

<?php
// Sort categories alphabetically by category name
usort($categoryList, function($a, $b) {
    return strcmp($a['category_name'], $b['category_name']);
});

// Loop through the sorted categories and display them
foreach ($categoryList as $cat) {
    $categoryId = $cat["category_id"];
    $categoryName = htmlspecialchars($cat["category_name"]); // Ensure proper escaping

    echo "<tr>";
    echo "<td>
            <a href='subscribers.php?category_id=$categoryId' class='category-name' data-category-id='$categoryId' 
            style='text-decoration: underline; color: inherit;' 
            onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
            onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$categoryName</a>
          </td>";
    echo "<td><input type='checkbox' name='category[]' value='$categoryId'";
    
    if (in_array($categoryId, $category)) {
        echo " checked";
    }

    echo "></td>";
    echo "<td>" . getTotalPeopleByCategory($conn, [$categoryId]) . "</td>";
    echo "</tr>";
}

?>

                    </tbody>
                </table>
        </div>

        <div class="col-md-6">
            <!-- Display the list of organizers with checkboxes -->
            <h2>According to Organizer</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Organizer</th>
                        <th></th>
                        <th>Number of Participants</th>
                    </tr>
                </thead>
                <tbody>
                <tr>
    <td>All Organizers</td>
    <td>
        <input type="checkbox" name="organizer[]" value="all"
            <?php if (in_array('all', $organizer)) echo 'checked'; ?>>
    </td>
    <td><?php echo getTotalPeopleByOrganizer($conn, ['all']); ?></td>
</tr>

<?php
// Sort organizers alphabetically by organizer name
usort($organizerList, function($a, $b) {
    return strcmp($a['organizer_name'], $b['organizer_name']);
});

// Loop through the sorted organizers and display them
foreach ($organizerList as $org) {
    $organizerId = $org["organizer_id"];
    $organizerName = htmlspecialchars($org["organizer_name"]); // Ensure proper escaping

    echo "<tr>";
    echo "<td>
            <a href='subscribers.php?organizer_id=$organizerId' class='organizer-name' data-organizer-id='$organizerId' 
            style='text-decoration: underline; color: inherit;' 
            onmouseover=\"this.style.textDecoration='none'; this.style.color='inherit';\" 
            onmouseout=\"this.style.textDecoration='underline'; this.style.color='inherit';\">$organizerName</a>
          </td>";
    echo "<td><input type='checkbox' name='organizer[]' value='$organizerId'";
    
    if (in_array($organizerId, $organizer)) {
        echo " checked";
    }

    echo "></td>";
    echo "<td>" . getTotalPeopleByOrganizer($conn, [$organizerId]) . "</td>";
    echo "</tr>";
}

?>

                </tbody>
            </table>
        </div>
    </div>
</form> <!-- Close the combined form -->


    

    <?php

    // Display the list of email addresses in boxes
if (!empty($emailLists)) {
    $emailsPerPage = 500; // Number of emails to display per page
    $emailsPerRow = 2;   // Number of boxes per row

    // Calculate the total number of pages needed
    $totalPages = ceil(count($emailLists) / $emailsPerPage);

    // Loop through each page and display emails in rows
    for ($page = 1; $page <= $totalPages; $page++) {
        // Calculate the start and end indices for the current page
        $startIdx = ($page - 1) * $emailsPerPage;
        $endIdx = $startIdx + $emailsPerPage;

        // Slice the emails for the current page
        $currentPageEmails = array_slice($emailLists, $startIdx, $emailsPerPage);

        // Filter out entries with empty email addresses
        $currentPageEmails = array_filter($currentPageEmails, function ($email) {
            return !empty(trim($email));
        });

        // Calculate the number of emails in the current box
        $numberOfEmailsInBox = count($currentPageEmails);

        // Display two boxes per row
        if ($page % $emailsPerRow == 1) {
            echo '<div class="row">';
        }

        echo '<div class="col-md-6">';
        
        echo "<h2>Email Address Set $page - $numberOfEmailsInBox Emails</h2>";

        echo '<textarea id="email-box-'.$page.'" rows="10" cols="50" class="form-control">';
        // Format and display the emails with a comma and newline
        echo implode(",\n", $currentPageEmails);
        echo '</textarea>';
        echo '<button class="btn btn-outline-success btn-copy" data-clipboard-target="#email-box-'.$page.'"><i class="bi bi-copy"></i>Copy</button>';

        
        echo '</div>';

        // Close the row if it's the last box in a row
        if ($page % $emailsPerRow == 0 || $page == $totalPages) {
            echo '</div>';
        }
    }
} else {
    echo "<p class='mt-4'>No email addresses to display.</p>";
}
    ?>
</div>

<!-- For Copying Function -->
<script>
    var clipboard = new ClipboardJS('.btn-copy');

    clipboard.on('success', function(e) {
        // Handle the success event, for example, show a tooltip or message
        e.clearSelection();
        e.trigger.textContent = 'Copied!';
    });

    clipboard.on('error', function(e) {
        // Handle any errors that may occur when copying
        console.error('Copy failed:', e.action);
    });
</script>


<!-- Include Bootstrap JavaScript from a CDN if needed -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.7.0/dist/js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>


