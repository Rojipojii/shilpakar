<?php
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

// Function to fetch name and emails according to category

function getEmailsByCategory($conn, $category) {
    if (empty($category) || in_array("all", $category)) {
        // When "All" or no category is selected, fetch all full names and email addresses
        $sql = "SELECT CONCAT(full_name, ', ', email) AS full_name_email FROM subscribers";
    } else {
        // Fetch full names and email addresses for the selected categories
        $categoryPlaceholders = implode(',', array_fill(0, count($category), '?'));
        $sql = "SELECT CONCAT(full_name, ', ', email) AS full_name_email FROM subscribers WHERE category_id IN ($categoryPlaceholders)";
    }

    $stmt = $conn->prepare($sql);

    if (!empty($category) && !in_array("all", $category)) {
        $stmt->bind_param(str_repeat('s', count($category)), ...$category);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $emails = array();
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row["full_name_email"];
    }
    return $emails;
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

// Initialize variable to store email list
$emailList = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["createList"])) {
    // Fetch emails based on selected categories
    $selectedEmails = getEmailsByCategory($conn, $category);

    // Create a list of email addresses
    $emailList = implode("\n", $selectedEmails);
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

// Function to fetch name and emails according to organizer
function getEmailsByOrganizer($conn, $organizer) {
    if (empty($organizer) || in_array("all", $organizer)) {
        // When "All" or no organizer is selected, fetch all full names and email addresses
        $sql = "SELECT CONCAT(full_name, ', ', email) AS full_name_email FROM subscribers";
    } else {
        // Fetch full names and email addresses for the selected organizers
        $organizerPlaceholders = implode(',', array_fill(0, count($organizer), '?'));
        $sql = "SELECT CONCAT(full_name, ', ', email) AS full_name_email FROM subscribers WHERE organizer_id IN ($organizerPlaceholders)";
    }

    $stmt = $conn->prepare($sql);

    if (!empty($organizer) && !in_array("all", $organizer)) {
        $stmt->bind_param(str_repeat('s', count($organizer)), ...$organizer);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $emails = array();
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row["full_name_email"];
    }
    return $emails;
}


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

// Initialize variable to store email lists as an array
$emailLists = array(); // Initialize $emailLists as an empty array

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["createList"])) {
    // Define the limit for each box (e.g., 500 emails per box)
    $limit = 500;
    $offset = 0;

    do {
        // Fetch emails based on selected categories
        $selectedEmails = getEmailsByCategory($conn, $category, $offset, $limit);

        // Create a list of email addresses
        $emailLists[] = $selectedEmails;

        // Update the offset for the next iteration
        $offset += $limit;
    } while (!empty($selectedEmails));
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

<table>
    <tr>
        <td>
<!-- Display the list of categories with checkboxes -->
<form method="post">
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
    <input type="submit" name="createList" value="Create List" class="btn btn-outline-success">
</form>
    </td>

<td>
            <!-- Display the list of email addresses -->
            <?php foreach ($emailLists as $index => $emailList) { ?>
                <h2>Email Addresses - Box <?php echo $index + 1; ?></h2>
                <textarea rows="10" cols="50"><?php echo implode("\n", $emailList); ?></textarea>
            <?php } ?>
        </td>

<td>
<!-- Display the list of organizers with checkboxes -->
<form method="post">
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
    <input type="submit" name="createList" value="Create List" class="btn btn-outline-success">
</form>
    </td>
    </tr>
    </table>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
