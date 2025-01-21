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
require_once "mergeGroups.php"; // Include the function file


// Get the total number of groups to merge
$totalGroupsToMerge = getGroupsToMerge($conn);


// Store the result in session
$_SESSION['totalGroupsToMerge'] = $totalGroupsToMerge;

// Function to fetch total number of people from all data
function getTotalPeopleFromAllData($conn) {
    // Adjust the SQL query to count the total number of subscriber_ids in the subscribers table
    $sql = "SELECT COUNT(DISTINCT subscriber_id) AS total FROM subscribers";

    // Execute the query
    $result = $conn->query($sql);

    // Check if query execution was successful
    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    // Fetch the result row and return the 'total' column
    $row = $result->fetch_assoc();
    return $row["total"];
}

// Call the function to get the total count
$totalPeopleFromAllData = getTotalPeopleFromAllData($conn);

// Function to fetch total number of events/activities
function getTotalEvents($conn) {   
    $sql = "SELECT COUNT(*) AS total FROM events";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row["total"];
}

// Function to fetch the number of subscriber_id counts that have been repeated (appear more than once)
function getTotalRepeatVisitors($conn) {
    // SQL query to count the number of subscriber_ids that have been repeated (appear more than once)
    $sql = "
        SELECT COUNT(*) AS total
FROM (
    SELECT subscriber_id
    FROM event_subscriber_mapping
    GROUP BY subscriber_id
    HAVING COUNT(CASE WHEN event_id IS NOT NULL THEN 1 END) > 1
) AS repeated_subscribers

    ";

    // Execute the query
    $result = $conn->query($sql);

    // Check if query execution was successful
    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    // Fetch the result row and return the 'total' column
    $row = $result->fetch_assoc();
    return $row["total"];
}


// Function to fetch a list of events, their IDs, names, and dates in descending order of event date
function getEventList($conn) {
    $sql = "SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC"; 
    $result = $conn->query($sql);
    return $result;
}

// Fetch the latest events and get the total count
$pageSize = 8; // Number of events per page
$currentPage = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($currentPage - 1) * $pageSize;

$sqlLatestEvents = "SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC LIMIT $offset, $pageSize";
$resultLatestEvents = $conn->query($sqlLatestEvents);

$totalEvents = $resultLatestEvents->num_rows;

// Calculate the total number of pages
$sqlTotalEvents = "SELECT COUNT(*) AS total FROM events";
$resultTotalEvents = $conn->query($sqlTotalEvents);
$rowTotalEvents = $resultTotalEvents->fetch_assoc();
$totalEvents = $rowTotalEvents["total"];
$totalPages = ceil($totalEvents / $pageSize);

function getEventAttendees($conn, $event_id = null, $category_id = null, $organizer_id = null) {
    // Base query to count total attendees
    $sql = "SELECT COUNT(*) AS total FROM event_subscriber_mapping WHERE event_id = ?";
    $types = "i"; // Start with event_id type
    $params = [$event_id];

    if ($category_id !== null) {
        $sql .= " AND category_id = ?";
        $types .= "i";
        $params[] = $category_id;
    }
    if ($organizer_id !== null) {
        $sql .= " AND organizer_id = ?";
        $types .= "i";
        $params[] = $organizer_id;
    }

    // Prepare and bind parameters
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    // Execute and fetch the result
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalAttendees = $row["total"];

    // Repeated attendees query
    $repeatedAttendees = 0;
    $sqlRepeatedAttendees = "
        SELECT COUNT(DISTINCT esm1.subscriber_id) AS repeated_attendees
        FROM event_subscriber_mapping esm1
        INNER JOIN event_subscriber_mapping esm2
        ON esm1.subscriber_id = esm2.subscriber_id
        WHERE esm1.event_id = ?
        AND esm2.event_id != esm1.event_id;

    ";

    $stmtRepeatedAttendees = $conn->prepare($sqlRepeatedAttendees);
    $stmtRepeatedAttendees->bind_param("i", $event_id);
    $stmtRepeatedAttendees->execute();
    $resultRepeatedAttendees = $stmtRepeatedAttendees->get_result();
    $rowRepeatedAttendees = $resultRepeatedAttendees->fetch_assoc();
    $repeatedAttendees = $rowRepeatedAttendees["repeated_attendees"];

    return array("total" => $totalAttendees, "repeated" => $repeatedAttendees);
}

// Fetch the count of subscribers without email content
$subscribersWithoutEmailQuery = "
    SELECT COUNT(*) AS count
    FROM subscribers s
    WHERE NOT EXISTS (
        SELECT 1 FROM emails e WHERE e.subscriber_id = s.subscriber_id
    )";
$subscribersWithoutEmailResult = $conn->query($subscribersWithoutEmailQuery);
$subscribersWithoutEmailCount = $subscribersWithoutEmailResult->fetch_assoc()['count'];

// Fetch the count of subscribers without phone content
$subscribersWithoutPhoneQuery = "
    SELECT COUNT(*) AS count
    FROM subscribers s
    WHERE NOT EXISTS (
        SELECT 1 FROM phone_numbers p WHERE p.subscriber_id = s.subscriber_id
    )";
$subscribersWithoutPhoneResult = $conn->query($subscribersWithoutPhoneQuery);
$subscribersWithoutPhoneCount = $subscribersWithoutPhoneResult->fetch_assoc()['count'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
      <!-- Theme style -->
      <!-- Include Bootstrap CSS -->
      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <!-- Link to Bootstrap Icons CSS -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include("header.php"); ?>
      <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php echo getTotalPeopleFromAllData($conn); ?></h3>
                    <p>Total Number of People</p>
                </div>
                <div class="icon"> <!-- Adjust the font-size as needed -->
                <i class="bi bi-person-fill"></i>
                </div>
                <a href="list" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>


<div class="col-lg-3 col-6">
    <!-- small box -->
    <div class="small-box bg-danger">
        <div class="inner">
            <h3><?php echo getTotalEvents($conn); ?></h3>
            <p>Total Number of Events/Activities</p>
        </div>
        <div class="icon">
        <i class="bi bi-calendar-event"></i>
        </div>
        <a href="events" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
</div>

<div class="col-lg-3 col-6">
    <!-- small box -->
    <div class="small-box bg-warning">
        <div class="inner">
            <h3><?php echo getTotalRepeatVisitors($conn); ?></h3>
            <p>Repeated Visitors:</p>
        </div>
        <div class="icon">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <a href="list?filter=repeated" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
</div>


<div class="col-lg-3 col-6">
    <!-- small box -->
    <div class="small-box bg-success">
        <div class="inner">
            <h1>
                <a href="download.php?format=csv" class="btn btn-success"><i class="bi bi-filetype-csv"></i> Download CSV</a><br>
                <a href="download.php?format=xlsx" class="btn btn-success"><i class="bi bi-file-excel"></i> Download Excel</a><br>
                <a href="download.php?format=vcf" class="btn btn-success"><i class="bi bi-person-vcard-fill"></i> Download VCard</a><br>
            </h1>
        </div>
        <div class="icon">
            <i class="bi bi-file-earmark-arrow-down-fill"></i>
        </div>
    </div>
</div>
</div>

<ul class="list-group">
            <div class="row">
                <!-- Display count for people without email -->
                <li class="list-group-item col-md-6">
                    <label>Total Number of People Without Email:</label>       
                    <a href="listEmails.php" style="text-decoration: underline; color: inherit;" 
   onmouseover="this.style.textDecoration='none'; this.style.color='inherit';" 
   onmouseout="this.style.textDecoration='underline'; this.style.color='inherit';" ><?= $subscribersWithoutEmailCount; ?></a>
                </li>

                <!-- Display count for people without phone -->
                <li class="list-group-item col-md-6">
                    <label>Total Number of People Without Phone Number:</label>
                    <a href="listnumbers.php" style="text-decoration: underline; color: inherit;" 
   onmouseover="this.style.textDecoration='none'; this.style.color='inherit';" 
   onmouseout="this.style.textDecoration='underline'; this.style.color='inherit';"><?= $subscribersWithoutPhoneCount; ?></a>
                </li>
            </div>
        </ul>

<br>

<!-- Two-column layout for List of Events and Top 10 Attendees -->
<div class="row mt-4">
    <!-- Left column for List of Events -->
    <div class="col-lg-6">

           <!-- List of Latest Events -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Latest Events</h3>
        <div class="card-tools">
            <?php
            $prevPage = max(1, $currentPage - 1);
            $nextPage = $currentPage + 1;
            ?>
            <?php if ($currentPage > 1) : ?>
                <a href="?page=<?php echo $prevPage; ?>" class="btn btn-tool">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            <?php if ($totalEvents > 0 && $nextPage <= $totalPages) : ?>
                <a href="?page=<?php echo $nextPage; ?>" class="btn btn-tool">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <ul class="users-list clearfix">
        <?php
        if ($totalEvents > 0) {
            while ($rowLatestEvent = $resultLatestEvents->fetch_assoc()) {
                $event_id = $rowLatestEvent["event_id"];

                // Get total and repeated attendees directly using the event_id
                $eventAttendees = getEventAttendees($conn, $event_id);
                $totalAttendees = $eventAttendees["total"];
                $repeatedAttendees = $eventAttendees["repeated"];

                // URL encode the event name to ensure safe inclusion in the URL
            //    $encodedEventName = urlencode($event_name);

                // Construct the URL with the event name as a parameter
                $eventUrl = 'list?event_id=' . $event_id ;

                echo '<li>';
                echo '<a class="users-list-name" href="' . $eventUrl . '">' . $rowLatestEvent["event_name"] . '</a>';
                echo '<span class="users-list-date">' . date("d F Y", strtotime($rowLatestEvent["event_date"])) . '</span>';
                echo '<span class="users-list-date">Total Attendees: ' . $totalAttendees .  '</span>';
                echo '<span class="users-list-date">Repeated Attendees: ' . $repeatedAttendees . '</span>';
                echo '</li>';
            }
        } else {
            echo "<li>No latest events found.</li>";
        }
        ?>
        </ul>
    </div>
    <div class="card-footer text-center">
        <a href="events">View All Events</a>
    </div>
</div>


    </div>

<!-- Right column for Top 10 Attendees -->
<div class="col-lg-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top 10 Attendees</h3>
            <div class="card-tools">
                <span class="badge badge-success">Top 10</span>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
                <button type="button" class="btn btn-tool" data-card-widget="remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <ul class="users-list clearfix">
            <?php
            // SQL query to retrieve the top 10 attendees based on the number of events attended
            $sqlTopAttendees = "
                SELECT 
    subscribers.*, 
    COUNT(CASE WHEN event_subscriber_mapping.event_id IS NOT NULL THEN 1 END) AS number_of_events_attended
FROM 
    subscribers
LEFT JOIN 
    event_subscriber_mapping ON subscribers.subscriber_id = event_subscriber_mapping.subscriber_id
GROUP BY 
    subscribers.subscriber_id
ORDER BY 
    number_of_events_attended DESC
LIMIT 10
";
            
            $resultTopAttendees = $conn->query($sqlTopAttendees);

            if ($resultTopAttendees->num_rows > 0) {
                while ($rowTopAttendees = $resultTopAttendees->fetch_assoc()) {
                    echo '<li>';
                    echo '<a class="users-list-name" href="edit_subscriber.php?id='. $rowTopAttendees["subscriber_id"] . '">' . $rowTopAttendees["full_name"] . '</a>';
                    echo '<span class="users-list-date">Total Events Attended: ' . $rowTopAttendees["number_of_events_attended"] . '</span>';
                    echo '</li>';
                }
            } else {
                echo "<li>No top attendees found.</li>";
            }
            ?>
            </ul>
        </div>
        <div class="card-footer text-center">
            <a href="list">View All Attendees</a>
        </div>
    </div>
</div>


            </ul>
        </div>
    </div>
</div>
</div>
</div>

</body>
</html>
