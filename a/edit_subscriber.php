<?php
session_start();

// Include the necessary files
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // If not logged in, redirect to login page
    header("Location: index.php?message=You should login first");
    exit();
}

// Fetch all events
$allEventsQuery = $conn->prepare("SELECT event_id, event_name FROM events");
$allEventsQuery->execute();
$allEventsResult = $allEventsQuery->get_result();
$allEvents = [];
while ($event = $allEventsResult->fetch_assoc()) {
    $allEvents[] = $event;
}

// Fetch all categories
$allCategoriesQuery = $conn->prepare("SELECT category_id, category_name FROM categories");
$allCategoriesQuery->execute();
$allCategoriesResult = $allCategoriesQuery->get_result();
$allCategories = [];
while ($category = $allCategoriesResult->fetch_assoc()) {
    $allCategories[] = $category;
}


// Fetch subscriber details based on the ID passed through URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $subscriberID = $_GET['id'];

    // Prepare and execute query to fetch subscriber details from the 'subscribers' table
    $stmt = $conn->prepare("SELECT * FROM subscribers WHERE subscriber_id = ?");
    if ($stmt === false) {
        die('MySQL prepare error: ' . $conn->error); // Added error handling for prepare statement
    }
    $stmt->bind_param("i", $subscriberID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Subscriber found, fetch details
        $subscriber = $result->fetch_assoc();

        // Fetch all phone numbers associated with the subscriber
        $phoneQuery = $conn->prepare("SELECT phone_number FROM phone_numbers WHERE subscriber_id = ?");
        if ($phoneQuery === false) {
            die('MySQL prepare error for phone query: ' . $conn->error); // Added error handling for phone query
        }
        $phoneQuery->bind_param("i", $subscriberID);
        $phoneQuery->execute();
        $phoneResult = $phoneQuery->get_result();
        $subscriberPhones = [];
        while ($phone = $phoneResult->fetch_assoc()) {
            $subscriberPhones[] = $phone['phone_number'];
        }
        // Remove duplicates using array_unique
        $subscriberPhones = array_unique($subscriberPhones);
        $subscriberPhone = implode(', ', $subscriberPhones); // Join all unique phone numbers with commas

        // Fetch all emails associated with the subscriber
        $emailQuery = $conn->prepare("SELECT email FROM emails WHERE subscriber_id = ?");
        if ($emailQuery === false) {
            die('MySQL prepare error for email query: ' . $conn->error); // Added error handling for email query
        }
        $emailQuery->bind_param("i", $subscriberID);
        $emailQuery->execute();
        $emailResult = $emailQuery->get_result();
        $subscriberEmails = [];
        while ($email = $emailResult->fetch_assoc()) {
            $subscriberEmails[] = $email['email'];
        }
        // Remove duplicates using array_unique
        $subscriberEmails = array_unique($subscriberEmails);
        $subscriberEmail = implode(', ', $subscriberEmails); // Join all unique emails with commas

        // Fetch unique events attended by subscribers with the same phone number or email
        $eventsQuery = $conn->prepare("SELECT DISTINCT e.event_name
                                       FROM event_subscriber_mapping esm
                                       INNER JOIN events e ON esm.event_id = e.event_id
                                       WHERE esm.subscriber_id IN (
                                           SELECT s.subscriber_id
                                           FROM subscribers s
                                           LEFT JOIN phone_numbers pn ON s.subscriber_id = pn.subscriber_id
                                           LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
                                           WHERE pn.phone_number = ? OR e.email = ?
                                       )");
        if ($eventsQuery === false) {
            die('MySQL prepare error for events query: ' . $conn->error); // Added error handling for events query
        }
        $eventsQuery->bind_param("ss", $subscriberPhone, $subscriberEmail);
        $eventsQuery->execute();
        $eventsResult = $eventsQuery->get_result();
        $eventsAttended = [];

        while ($event = $eventsResult->fetch_assoc()) {
            $eventsAttended[] = $event['event_name'];
        }

        // Prepare the string for events attended by the subscriber
        $eventsAttendedString = implode(', ', $eventsAttended);

        // Store the names of events attended by the subscriber in a string
        $subscriber['events_attended'] = $eventsAttendedString;

        // Fetch unique categories attended by subscribers with the same phone number or email
        $categoriesQuery = $conn->prepare("SELECT DISTINCT c.category_name
                                          FROM event_subscriber_mapping esm
                                          INNER JOIN categories c ON esm.category_id = c.category_id
                                          WHERE esm.subscriber_id IN (
                                              SELECT s.subscriber_id
                                              FROM subscribers s
                                              LEFT JOIN phone_numbers pn ON s.subscriber_id = pn.subscriber_id
                                              LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
                                              WHERE pn.phone_number = ? OR e.email = ?
                                          )");

        if ($categoriesQuery === false) {
            die('MySQL prepare error for categories query: ' . $conn->error); // Added error handling for categories query
        }
        $categoriesQuery->bind_param("ss", $subscriberPhone, $subscriberEmail);
        $categoriesQuery->execute();
        $categoriesResult = $categoriesQuery->get_result();
        $categoriesAttended = [];

        while ($category = $categoriesResult->fetch_assoc()) {
            $categoriesAttended[] = $category['category_name'];
        }

        // Prepare the string for categories attended by the subscriber
        $categoriesAttendedString = implode(', ', $categoriesAttended);

        // Store the names of categories attended by the subscriber in a string
        $subscriber['categories_attended'] = $categoriesAttendedString;
    } else {
        // Subscriber not found, redirect back to subscribers page
        header("Location: subscribers.php");
        exit();
    }
} else {
    // ID not provided, redirect back to subscribers page
    header("Location: subscribers.php");
    exit();
}

// Handle form submission to update subscriber details
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve edited subscriber details from the form
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $designation = $_POST['designation'];
    $organization = $_POST['organization'];
    $address = $_POST['address'];
    $selectedEvents = $_POST['events'] ?? [];
    $selectedCategories = $_POST['categories'] ?? [];

    // Update the subscriber details in the 'subscribers' table
    $updateStmt = $conn->prepare("UPDATE subscribers SET full_name = ?, designation = ?, organization = ?, address = ? WHERE subscriber_id = ?");
    $updateStmt->bind_param("ssssi", $full_name, $designation, $organization, $address, $subscriberID);

    if ($updateStmt->execute()) {
        // Update phone and email
        $updatePhoneStmt = $conn->prepare("UPDATE phone_numbers SET phone_number = ? WHERE subscriber_id = ?");
        $updatePhoneStmt->bind_param("si", $phone_number, $subscriberID);
        $updatePhoneStmt->execute();

        $updateEmailStmt = $conn->prepare("UPDATE emails SET email = ? WHERE subscriber_id = ?");
        $updateEmailStmt->bind_param("si", $email, $subscriberID);
        $updateEmailStmt->execute();

        // Update events
        $existingEventsQuery = $conn->prepare("SELECT event_id FROM event_subscriber_mapping WHERE subscriber_id = ?");
        $existingEventsQuery->bind_param("i", $subscriberID);
        $existingEventsQuery->execute();
        $existingEventsResult = $existingEventsQuery->get_result();
        $existingEvents = [];
        while ($row = $existingEventsResult->fetch_assoc()) {
            $existingEvents[] = $row['event_id'];
        }

        // Add new events
        foreach ($selectedEvents as $event) {
            if (!in_array($event, $existingEvents)) {
                $insertEventStmt = $conn->prepare("INSERT INTO event_subscriber_mapping (subscriber_id, event_id) VALUES (?, ?)");
                $insertEventStmt->bind_param("ii", $subscriberID, $event);
                $insertEventStmt->execute();
            }
        }

        // Remove unselected events
        foreach ($existingEvents as $event) {
            if (!in_array($event, $selectedEvents)) {
                $deleteEventStmt = $conn->prepare("DELETE FROM event_subscriber_mapping WHERE subscriber_id = ? AND event_id = ?");
                $deleteEventStmt->bind_param("ii", $subscriberID, $event);
                $deleteEventStmt->execute();
            }
        }

        // Update categories (similar to events)
        $existingCategoriesQuery = $conn->prepare("SELECT category_id FROM event_subscriber_mapping WHERE subscriber_id = ?");
        $existingCategoriesQuery->bind_param("i", $subscriberID);
        $existingCategoriesQuery->execute();
        $existingCategoriesResult = $existingCategoriesQuery->get_result();
        $existingCategories = [];
        while ($row = $existingCategoriesResult->fetch_assoc()) {
            $existingCategories[] = $row['category_id'];
        }

        // Add new categories
        foreach ($selectedCategories as $category) {
            if (!in_array($category, $existingCategories)) {
                $insertCategoryStmt = $conn->prepare("INSERT INTO event_subscriber_mapping (subscriber_id, category_id) VALUES (?, ?)");
                $insertCategoryStmt->bind_param("ii", $subscriberID, $category);
                $insertCategoryStmt->execute();
            }
        }

        // Remove unselected categories
        foreach ($existingCategories as $category) {
            if (!in_array($category, $selectedCategories)) {
                $deleteCategoryStmt = $conn->prepare("DELETE FROM event_subscriber_mapping WHERE subscriber_id = ? AND category_id = ?");
                $deleteCategoryStmt->bind_param("ii", $subscriberID, $category);
                $deleteCategoryStmt->execute();
            }
        }

        // Redirect back to subscribers page after updating
        header("Location: subscribers.php");
        exit();
    } else {
        // Error occurred while updating the subscriber details
        $errorMessage = "Error updating subscriber details: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscriber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MultiSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/multiselect@2.1.2/dist/css/multiselect.min.css" rel="stylesheet" />

    <!-- jQuery (needed for MultiSelect) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- MultiSelect JS -->
    <script src="https://cdn.jsdelivr.net/npm/multiselect@2.1.2/dist/js/multiselect.min.js"></script>

    <!-- Add Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Add jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

</head>


<body>
<?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
      <div class="container-fluid">
        <h1>Edit Subscriber Details</h1>
        <form method="POST">
    <div class="col-md-4 mb-3">
        <label for="full_name" class="form-label">Full Name</label>
        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($subscriber['full_name']); ?>" required>
    </div>
    <div class=" col-md-4 mb-3">
        <label for="phone_number" class="form-label">Phone Number</label>
        <input type="text" name="phone_number" id="phone_number" class="form-control" value="<?php echo htmlspecialchars($subscriberPhone); ?>" required>
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($subscriberEmail); ?>" required>
    </div>

    <!-- MultiSelect for Events -->
    <div class="mb-3">
        <label for="events" class="form-label">Select Events</label>
        <select name="events[]" id="events" class="form-control" multiple="multiple">
            <?php foreach ($allEvents as $event) { ?>
                <option value="<?php echo $event['event_id']; ?>" <?php echo in_array($event['event_name'], $eventsAttended) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($event['event_name']); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <!-- MultiSelect for Categories -->
    <div class="mb-3">
        <label for="categories" class="form-label">Select Categories</label>
        <select name="categories[]" id="categories" class="form-control" multiple="multiple">
            <?php foreach ($allCategories as $category) { ?>
                <option value="<?php echo $category['category_id']; ?>" <?php echo in_array($category['category_name'], $categoriesAttended) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
</form>

<script>
    // Initialize MultiSelect or Select2
    $(document).ready(function() {
        $('#events').multiselect(); // or use Select2 if preferred
        $('#categories').multiselect(); // or use Select2 if preferred
    });
</script>


</body>

</html>
