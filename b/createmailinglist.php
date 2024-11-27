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
        $sql = "SELECT COUNT(*) AS total FROM subscribers WHERE category_id IN ($categoryPlaceholders)";
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
        // Count subscribers for the selected organizers
        $organizerPlaceholders = implode(',', array_fill(0, count($organizer), '?'));
        $sql = "SELECT COUNT(*) AS total FROM subscribers WHERE organizer_id IN ($organizerPlaceholders)";
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

// Function to fetch emails by category with a limit and offset
function getEmailsByCategoryWithLimit($conn, $category, $offset, $limit) {
    // Create an array to hold the category IDs
    $categoryIds = array();

    // Create placeholders for the category IDs
    $categoryPlaceholders = array();

    // Build the SQL query with placeholders for organizer values
    $sql = "SELECT full_name, email FROM subscribers WHERE ";

    // Check if any categories are selected
    if (empty($category) || in_array("all", $category)) {
        // When "All" or no category is selected, select all subscribers
        $sql .= "1"; // This condition is always true
    } else {
        foreach ($category as $categoryId) {
            $categoryIds[] = $categoryId;
            $categoryPlaceholders[] = '?';
        }
        $sql .= "category_id IN (" . implode(',', $categoryPlaceholders) . ")";
    }

    $sql .= " ORDER BY email LIMIT ?, ?";

    // Initialize an array to hold the parameters
    $params = $categoryIds;

    // Add $offset and $limit parameters to the end of the parameters array
    $params[] = $offset;
    $params[] = $limit;

    // Create a string of type specifiers for the parameters
    $paramTypes = str_repeat('s', count($params));

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

    $stmt->execute();
    $result = $stmt->get_result();
    $emails = array();
    // Update the loop that fetches and formats emails
    while ($row = $result->fetch_assoc()) {
    // Trim spaces from the name and email
    $full_name = trim($row["full_name"]);
    $email = trim($row["email"]);

    // Reconstruct and format the email with a single pair of double quotation marks
    $formattedEmail = '"' . $full_name . '" <' . $email . '>';
    $emails[] = $formattedEmail;
}
    return $emails;
}

// Function to fetch emails by organizer with a limit and offset
function getEmailsByOrganizerWithLimit($conn, $organizer, $offset, $limit) {
    // Create an array to hold the organizer IDs
    $organizerIds = array();

    // Create placeholders for the organizer IDs
    $organizerPlaceholders = array();

    // Build the SQL query with placeholders for organizer values
    $sql = "SELECT full_name, email FROM subscribers WHERE ";

    // Check if any organizers are selected
    if (empty($organizer) || in_array("all", $organizer)) {
        // When "All" or no organizer is selected, select all subscribers
        $sql .= "1"; // This condition is always true
    } else {
        foreach ($organizer as $organizerId) {
            $organizerIds[] = $organizerId;
            $organizerPlaceholders[] = '?';
        }
        $sql .= "organizer_id IN (" . implode(',', $organizerPlaceholders) . ")";
    }

    $sql .= " ORDER BY email LIMIT ?, ?";

    // Initialize an array to hold the parameters
    $params = $organizerIds;

    // Add $offset and $limit parameters to the end of the parameters array
    $params[] = $offset;
    $params[] = $limit;

    // Create a string of type specifiers for the parameters
    $paramTypes = str_repeat('s', count($params));

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

    $stmt->execute();
    $result = $stmt->get_result();
    $emails = array();
    // Update the loop that fetches and formats emails
    while ($row = $result->fetch_assoc()) {
    // Trim spaces from the name and email
    $full_name = trim($row["full_name"]);
    $email = trim($row["email"]);

    // Reconstruct and format the email with a single pair of double quotation marks
    $formattedEmail = '"' . $full_name . '" <' . $email . '>';
    $emails[] = $formattedEmail;
}
    return $emails;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["createListCategory"])) {
    $emailLists = array(); // Initialize $emailLists as an empty array

    // Define the limit for emails per box
    $emailsPerBox = 500;

    // Fetch emails based on selected categories 
    $offset = 0;
    do {
        // Fetch a batch of emails
        $selectedEmails = getEmailsByCategoryWithLimit($conn, $category, $offset, $emailsPerBox);

        // Merge the selected emails with the existing ones and remove duplicates
        $emailLists = array_merge($emailLists, array_unique($selectedEmails));

        // Increment the offset for the next batch
        $offset += $emailsPerBox;
    } while (!empty($selectedEmails));

    // Sort the email addresses alphabetically
    sort($emailLists);
}

// Handle form submission for organizer-based email list
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["createListOrganizer"])) {
    $emailLists = array(); // Initialize $emailLists as an empty array

    // Define the limit for emails per box
    $emailsPerBox = 500;

    // Fetch emails based on selected organizers
    $offset = 0;
    do {
        // Fetch a batch of emails
        $selectedEmails = getEmailsByOrganizerWithLimit($conn, $organizer, $offset, $emailsPerBox);

        // Merge the selected emails with the existing ones and remove duplicates
        $emailLists = array_merge($emailLists, array_unique($selectedEmails));

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
</head>
<body>
<?php include("header.php"); ?>
<div class="container">

<table>
    <tr>
        <td>
<!-- Display the list of categories with checkboxes -->
<form method="post" action= "createmailinglist.php">
    <h2>According to Category</h2>
    <table>
        <tr>
            <th>Category</th>
            <th></th>
            <th>Number of Participants</th>
        </tr>
        <tr>
            <td>All Categories</td>
            <td><input type='checkbox' name='category[]' value='all' <?php if (in_array('all', $category)) echo 'checked'; ?>></td>
            <td><?php echo getTotalPeopleByCategory($conn, ['all']); ?></td>
        </tr>
        <?php
        foreach ($categoryList as $cat) {
            echo "<tr>";
            echo "<td>" . $cat["category_name"] . "</td>";
            echo "<td><input type='checkbox' name='category[]' value='" . $cat["category_id"] . "'";
            if (in_array($cat["category_id"], $category)) {
                echo " checked";
            }
            echo "></td>";
            echo "<td>" . getTotalPeopleByCategory($conn, [$cat["category_id"]]) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    <input type="submit" name="createListCategory" value="Create List "  class="right blue waves-effect waves-light btn">
</form>
    </td>

<td>
<!-- Display the list of organizers with checkboxes -->
<form method="post" action= "createmailinglist.php">
    <h2>According to Organizer</h2>
    <table>
        <tr>
            <th>Organizer</th>
            <th></th>
            <th>Number of Participants</th>
        </tr>
        <tr>
            <td>All Organizers</td>
            <td><input type='checkbox' name='organizer[]' value='all' <?php if (in_array('all', $organizer)) echo 'checked'; ?>></td>
            <td><?php echo getTotalPeopleByOrganizer($conn, ['all']); ?></td>
        </tr>
        <?php
        foreach ($organizerList as $org) {
            echo "<tr>";
            echo "<td>" . $org["organizer_name"] . "</td>";
            echo "<td><input type='checkbox' name='organizer[]' value='" . $org["organizer_id"] . "'";
            if (in_array($org["organizer_id"], $organizer)) {
                echo " checked";
            }
            echo "></td>";
            echo "<td>" . getTotalPeopleByOrganizer($conn, [$org["organizer_id"]]) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    <input type="submit" name="createListOrganizer" value="Create List"  class="right blue waves-effect waves-light btn">
</form>
    </td>
    </tr>
    </table> 
    <?php
// Display the list of email addresses in boxes
if (!empty($emailLists)) {
    $emailsPerPage = 500; // Number of emails to display per page

    // Calculate the total number of pages needed
    $totalPages = ceil(count($emailLists) / $emailsPerPage);

    // Loop through each page and display emails
for ($page = 1; $page <= $totalPages; $page++) {
    echo "<h2>Email Addresses - Page $page</h2>";
    echo "<textarea rows='10' cols='50'>";

    // Calculate the start and end indices for the current page
    $startIdx = ($page - 1) * $emailsPerPage;
    $endIdx = $startIdx + $emailsPerPage;

    // Slice the emails for the current page
    $currentPageEmails = array_slice($emailLists, $startIdx, $emailsPerPage);

    // Format and display the emails with a comma and newline
    echo implode(",\n", $currentPageEmails);
   }

        echo implode("\n", $currentPageEmails);
        echo "</textarea>";
    }
else {
    echo "<p>No email addresses to display.</p>";
}
?>

</div>


</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
