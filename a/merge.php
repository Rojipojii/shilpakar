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

// Get the primary subscriber ID to keep from URL
$primary_subscriber_id = isset($_GET['subscriber_id']) ? $_GET['subscriber_id'] : null;

function fetchMatchingContacts($conn, $primary_subscriber_id = null) {
    $sql = "
        SELECT DISTINCT
            s1.subscriber_id AS subscriber1_id, 
            s2.subscriber_id AS subscriber2_id,
            s1.full_name, 
            pn.phone_number AS phone_number,
            e.email AS email,
            do.designation AS designation,
            do.organization AS organization
        FROM subscribers s1
        LEFT JOIN phone_numbers pn ON s1.subscriber_id = pn.subscriber_id
        LEFT JOIN emails e ON s1.subscriber_id = e.subscriber_id
        LEFT JOIN designation_organization do ON s1.subscriber_id = do.subscriber_id
        JOIN subscribers s2 ON (s1.subscriber_id != s2.subscriber_id)
        LEFT JOIN phone_numbers pn2 ON s2.subscriber_id = pn2.subscriber_id
        LEFT JOIN emails e2 ON s2.subscriber_id = e2.subscriber_id
        WHERE (pn.phone_number = pn2.phone_number OR e.email = e2.email)
    ";

    // If a primary subscriber ID is provided, filter results by the subscriber_id
    if ($primary_subscriber_id !== null) {
        $sql .= " AND (s1.subscriber_id = ? OR s2.subscriber_id = ?)";
    }

    $stmt = $conn->prepare($sql);

    if ($primary_subscriber_id !== null) {
        $stmt->bind_param("ii", $primary_subscriber_id, $primary_subscriber_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $matches = [];

    while ($row = $result->fetch_assoc()) {
        // Group matches by phone_number or email
        $key = $row['phone_number'] ?: $row['email'];
        $matches[$key][] = $row;
    }

    return ['matches' => $matches, 'group_count' => count($matches)];
}


// Get the result from the fetchMatchingContacts function
$result = fetchMatchingContacts($conn, $primary_subscriber_id);

// Extract the matches and group count
$subscribers = $result['matches'];
$group_count = $result['group_count'];


// Initialize error message variables
$error_message = "";
$clicked_group = isset($_POST['merge']) ? $_POST['merge'] : null;

// Handle the form submission
if (isset($_POST['merge'])) {
    $group_index = $_POST['merge']; // Get the group index

    // Check if at least one checkbox is selected
    if (isset($_POST['merge_ids']) && is_array($_POST['merge_ids']) && count($_POST['merge_ids']) > 0) {
        // Continue with the merging process
    } else {
        // Set error message for the specific group
        $error_message[$group_index] = "Please select Subscribers to merge.";
    }
}


// Handle the form submission
if (isset($_POST['merge'])) {
    // Check if at least one checkbox is selected
    if (isset($_POST['merge_ids']) && is_array($_POST['merge_ids']) && count($_POST['merge_ids']) > 0) {
        $merge_ids = $_POST['merge_ids'];

        // Sort the merge IDs to ensure the first ID is considered the primary subscriber ID
        sort($merge_ids);

        // The first ID in the sorted list will be the primary subscriber ID
        $primary_subscriber_id = $merge_ids[0];

        // Ensure the primary subscriber is not deleted or merged with itself
        if (in_array($primary_subscriber_id, $merge_ids)) {
            $key = array_search($primary_subscriber_id, $merge_ids);
            unset($merge_ids[$key]);
        }

        // Perform the merge logic using both the primary and merged IDs
        $tablesToUpdate = ['phone_numbers', 'emails', 'event_subscriber_mapping'];
        
        foreach ($tablesToUpdate as $table) {
            if (!empty($merge_ids)) {
                $placeholders = implode(",", array_fill(0, count($merge_ids), "?"));
                $sql = "UPDATE $table SET subscriber_id = ? WHERE subscriber_id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $types = str_repeat("i", count($merge_ids) + 1);
                $params = array_merge([$primary_subscriber_id], $merge_ids);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }            
        }

        // Merging designations and organizations
        // First, get all the designations and organizations for the merged subscribers
        $merge_ids_imploded = implode(",", $merge_ids);
        $sql = "SELECT subscriber_id, designation, organization FROM designation_organization WHERE subscriber_id IN ($merge_ids_imploded)";
        $result = $conn->query($sql);
        
        $designations = [];
        $organizations = [];
        
        while ($row = $result->fetch_assoc()) {
            // Store designations and organizations
            $designations[] = $row['designation'];
            $organizations[] = $row['organization'];
        }
        
        // Merge designations and organizations - for simplicity, we assume we take the first value from the list.
        // You could enhance this logic depending on your requirements (e.g., concatenating or choosing based on priority).
        $merged_designation = !empty($designations) ? $designations[0] : null;
        $merged_organization = !empty($organizations) ? $organizations[0] : null;

        // Update the primary subscriber's designation and organization
        if ($merged_designation && $merged_organization) {
            $sql = "UPDATE designation_organization SET designation = ?, organization = ? WHERE subscriber_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $merged_designation, $merged_organization, $primary_subscriber_id);
            $stmt->execute();
        }

        // Delete the merged subscribers from the `designation_organization` table
        if (!empty($merge_ids)) {
            $merge_ids_imploded = implode(",", $merge_ids);
            $sql = "DELETE FROM designation_organization WHERE subscriber_id IN ($merge_ids_imploded)";
            if (!$conn->query($sql)) {
                die('Error deleting merged designations/organizations: ' . $conn->error);
            }
        }
        if (!empty($merge_ids)) {
            $merge_ids_imploded = implode(",", $merge_ids);
            $sql = "DELETE FROM subscribers WHERE subscriber_id IN ($merge_ids_imploded)";
            if (!$conn->query($sql)) {
                die('Error deleting merged subscribers: ' . $conn->error);
            }
        }
        

        // Redirect to the edit_subscriber page of the primary subscriber
        header("Location: edit_subscriber.php?id=$primary_subscriber_id&merge_success=1");
        exit();
    } else {
        // Set error message if no checkboxes are selected
        $error_message = "Please select Subscribers to merge.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Merge Subscribers</title>
    <style>
.table-container {
    margin-left: 20px; /* Adds a small space on the left side */
    margin-bottom: 30px; /* Space between groups */
    max-width: 80%; /* Adjusts the width of the container */
    text-align: left; /* Aligns text to the left */
}

table {
    width: 100%; /* Full width within the container */
    border-collapse: collapse;
}

th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left; /* Aligns content to the left in cells */
}

th {
    background-color: #f2f2f2;
}

.merge-button {
    margin-top: 10px;
    padding: 5px 10px;
    background-color: #4CAF50;
    color: white;
    border: none;
    cursor: pointer;
}

.merge-button:hover {
    background-color: #45a049;
}

    </style>
</head>
<body>
<?php include("header.php"); ?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <div class="container-fluid">
    <h1>Merge Subscribers (<?php echo $group_count; ?> groups to merge)</h1>

<form action="merge.php" method="post">
    <input type="hidden" name="primary_subscriber_id" value="<?php echo $primary_subscriber_id; ?>">

    <?php if ($primary_subscriber_id === null): ?>
        <?php foreach ($subscribers as $key => $group): ?>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Organization</th>
                        <th>Designation</th>
                    </tr>
                    <?php foreach ($group as $subscriber): ?>
                        <tr>
                            <td>
                                <label>
                                    <input type="checkbox" name="merge_ids[]" value="<?php echo $subscriber['subscriber1_id']; ?>" 
                                    <?php echo $subscriber['subscriber1_id'] == $primary_subscriber_id ? 'checked disabled' : ''; ?>>
                                    <?php echo $subscriber['full_name']; ?>
                                </label>
                            </td>
                            <td><?php echo $subscriber['email']; ?></td>
                            <td><?php echo $subscriber['phone_number']; ?></td>
                            <td><?php echo $subscriber['organization']; ?></td>
                            <td><?php echo $subscriber['designation']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (isset($error_message[$key])): ?>
            <div class="alert alert-info" style="color: red; font-size: 14px;">
                <?php echo htmlspecialchars($error_message[$key]); ?>
            </div>
        <?php endif; ?>
                <button type="submit" name="merge" class="merge-button" value="<?php echo $key; ?>">Merge this group</button>


            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($subscribers as $key => $group): ?>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Organization</th>
                        <th>Designation</th>
                    </tr>
                    <?php foreach ($group as $subscriber): ?>
                        <?php if ($subscriber['subscriber1_id'] == $primary_subscriber_id || $subscriber['subscriber2_id'] == $primary_subscriber_id): ?>
                            <tr>
                                <td>
                                    <label>
                                        <input type="checkbox" name="merge_ids[]" value="<?php echo $subscriber['subscriber1_id']; ?>" 
                                        <?php echo $subscriber['subscriber1_id'] == $primary_subscriber_id ? 'checked disabled' : ''; ?>>
                                        <?php echo $subscriber['full_name']; ?>
                                    </label>
                                </td>
                                <td><?php echo $subscriber['email']; ?></td>
                                <td><?php echo $subscriber['phone_number']; ?></td>
                                <td><?php echo $subscriber['organization']; ?></td>
                                <td><?php echo $subscriber['designation']; ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                <?php if (isset($error_message[$key])): ?>
            <div class="alert alert-info" style="color: red; font-size: 14px;">
                <?php echo htmlspecialchars($error_message[$key]); ?>
            </div>
        <?php endif; ?>
                <button type="submit" name="merge" class="merge-button" value="<?php echo $key; ?>">Merge this group</button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</form>

    </div>
</div>
</body>
</html>
