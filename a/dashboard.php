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
            HAVING COUNT(subscriber_id) > 1
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



// Function to fetch total number of people according to category
function getTotalPeopleByCategory($conn, $category) {
    if ($category === "all") {
        // When "All" is selected, count all distinct subscribers
        $sql = "SELECT COUNT(DISTINCT subscriber_id) AS with_category FROM event_subscriber_mapping WHERE subscriber_id IS NOT NULL";
    } else {
        // Count distinct subscribers for the selected category
        $sql = "SELECT COUNT(DISTINCT subscriber_id) AS total FROM event_subscriber_mapping WHERE category_id = ? AND subscriber_id IS NOT NULL";
    }

    // Prepare the SQL query
    $stmt = $conn->prepare($sql);
    
    // Bind parameter if the category is not "all"
    if ($category !== "all") {
        $stmt->bind_param("i", $category);
    }

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the result based on the category and return the appropriate value
    $row = $result->fetch_assoc();
    return $row[$category === "all" ? "with_category" : "total"];
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
$category = isset($_POST["category"]) ? $_POST["category"] : "";
    
// Fetch the list of categories
$categoryList = getCategoryList($conn);

// Function to fetch total number of people according to organizer
function getTotalPeopleByOrganizer($conn, $organizer) {
    if ($organizer === "all") {
        // When "All" is selected, count all subscribers
        $sql = "SELECT COUNT(DISTINCT subscriber_id) AS with_organizer FROM event_subscriber_mapping WHERE subscriber_id IS NOT NULL";
    } else {
        // Count subscribers for the selected organizer
        $sql = "SELECT COUNT(DISTINCT subscriber_id) AS total FROM event_subscriber_mapping WHERE organizer_id = ? AND subscriber_id IS NOT NULL";
    }

    $stmt = $conn->prepare($sql);
    if ($organizer !== "all") {
        $stmt->bind_param("s", $organizer);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row["total"];
}

// Fetch the list of organizers from the "organizers" table
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
$organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : "";

// Fetch the list of organizers
$organizerList = getOrganizerList($conn);

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
    // Base query to count total attendees for a specific event
    $sql = "SELECT COUNT(*) AS total FROM event_subscriber_mapping WHERE event_id = ?";

    // Add conditions based on provided category_id and organizer_id
    if ($category_id !== null) {
        $sql .= " AND category_id = ?";
    }
    if ($organizer_id !== null) {
        $sql .= " AND organizer_id = ?";
    }

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    // Bind parameters based on the provided IDs
    if ($category_id !== null && $organizer_id !== null) {
        $stmt->bind_param("iii", $event_id, $category_id, $organizer_id);
    } elseif ($category_id !== null) {
        $stmt->bind_param("ii", $event_id, $category_id);
    } elseif ($organizer_id !== null) {
        $stmt->bind_param("ii", $event_id, $organizer_id);
    } else {
        // Only event_id is provided
        $stmt->bind_param("i", $event_id);
    }

    // Execute the statement and fetch the result
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalAttendees = $row["total"];


   // Fetch the repeated attendees if both category ID and organizer ID are provided
/// Initialize variable to store the count of repeated attendees
$repeatedAttendees = 0;

// Check if event_id is provided
if ($event_id !== null) {
    // SQL query to find repeated attendees for the same event
    $sqlRepeatedAttendees = "
    SELECT COUNT(*) AS repeated_attendees
    FROM (
        SELECT subscriber_id
        FROM event_subscriber_mapping
        WHERE event_id = ? 
        GROUP BY subscriber_id
        HAVING COUNT(*) > 1
    ) AS repeated_attendees";

    // Prepare the SQL statement
    $stmtRepeatedAttendees = $conn->prepare($sqlRepeatedAttendees);

    // Bind parameter: event_id
    $stmtRepeatedAttendees->bind_param("i", $event_id);

    // Execute the statement
    $stmtRepeatedAttendees->execute();
    
    // Get the result
    $resultRepeatedAttendees = $stmtRepeatedAttendees->get_result();
    
    // Fetch the result and extract the count of repeated attendees
    $rowRepeatedAttendees = $resultRepeatedAttendees->fetch_assoc();
    $repeatedAttendees = $rowRepeatedAttendees["repeated_attendees"];
}

// Return the total and repeated attendees
return array("total" => $totalAttendees, "repeated" => $repeatedAttendees);
}

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
                <a href="subscribers.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
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
        <a href="addevent.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
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
        <a href="subscribers.php?filter=repeated" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
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
<!-- Dropdown menu to select category -->
<li class="list-group-item col-md-6">
    <form method="post" action="dashboard.php">
        <label for="category">Total Number of people according to Category:</label>
        <?php
        // If a category is selected, fetch and display the total number of people
        if (!empty($category)) {
            $totalPeopleByCategory = getTotalPeopleByCategory($conn, $category);
            // Output the total count and create a link to the subscribers page with the selected category filter
            echo "<a href='subscribers.php?category=$category'>$totalPeopleByCategory</a>";
        }
        ?>
        <div class="input-group">
            <select name="category" id="category" class="form-select">
                <!-- Option for "All" categories -->
                <option value="all" <?php if ($category === "all") echo "selected"; ?>>All</option>
                <?php
                // Loop through the category list and display the categories in the select dropdown
                foreach ($categoryList as $cat) {
                    echo "<option value='" . $cat["category_id"] . "'";
                    if ($category == $cat["category_id"]) {
                        echo " selected";
                    }
                    echo ">" . $cat["category_name"] . "</option>";
                }
                ?>
            </select>
            <button type="submit" class="btn btn-outline-success">Filter</button>
        </div>
    </form>
</li>


        <!-- Dropdown menu to select organizer -->
        <li class="list-group-item col-md-6">
            <form method="post" action="dashboard.php">
                <label for="organizer">Total Number of people according to Organizer:</label>
                <?php
                if (!empty($organizer)) {
                    $totalPeopleByOrganizer = getTotalPeopleByOrganizer($conn, $organizer);
                    // Output the total count and create a link to the subscribers page with the selected organizer filter
                    echo "<a href='subscribers.php?organizer=$organizer'>$totalPeopleByOrganizer</a>";
                }
                ?>
                <div class="input-group">
                    <select name="organizer" id="organizer" class="form-select">
                        <!-- <option value="all" <?php if ($organizer === "all") echo "selected"; ?>>All</option> -->
                        <?php
                        foreach ($organizerList as $org) {
                            echo "<option value='" . $org["organizer_id"] . "'";
                            if ($organizer == $org["organizer_id"]) {
                                echo " selected";
                            }
                            echo ">" . $org["organizer_name"] . "</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="btn btn-outline-success">Filter</button>
                </div>

            </form>
        </li>
    </div>
</ul>

<!-- Two-column layout for List of Events and Top 10 Attendees -->
<div class="row mt-4">
    <!-- Left column for List of Events -->
    <div class="col-lg-6">

           <!-- List of Latest Events -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Top 8 Latest Events</h3>
        <div class="card-tools">
            <span class="badge badge-success">Top 8</span>
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

                // Construct the URL with the total number of attendees as a parameter
                $eventUrl = 'subscribers.php?event_id=' . $event_id . '&total_attendees=' . $totalAttendees;

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
        <a href="addevent.php">View All Events</a>
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
                    s.subscriber_id,
                    (SELECT full_name 
                     FROM subscribers 
                     WHERE subscriber_id = s.subscriber_id
                     LIMIT 1) AS full_name,
                    COUNT(esm.event_id) AS number_of_events_attended
                FROM 
                    event_subscriber_mapping esm
                INNER JOIN subscribers s ON esm.subscriber_id = s.subscriber_id
                GROUP BY 
                    s.subscriber_id
                ORDER BY 
                    number_of_events_attended DESC
                LIMIT 10";
            
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
            <a href="subscribers.php">View All Attendees</a>
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
