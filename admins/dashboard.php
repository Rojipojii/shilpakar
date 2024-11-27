<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>

    <?php
    require_once "db.php"; // Include the database connection file

    // Function to fetch total number of people
    function getTotalPeople($conn) {
        $sql = "SELECT COUNT(*) AS total FROM subscribers";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return $row["total"];
    }

    // Function to fetch total number of events/activities
    function getTotalEvents($conn) {   
        $sql = "SELECT COUNT(*) AS total FROM events";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return $row["total"];
    }

    // Function to fetch total number of people who have come more than once
    function getTotalRepeatVisitors($conn) {
        $sql = "SELECT COUNT(*) AS total FROM (SELECT mobile_number FROM subscribers GROUP BY mobile_number HAVING COUNT(*) > 1) AS repeat_visitors";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return $row["total"];
    }

    // Function to fetch total number of people according to category
function getTotalPeopleByCategory($conn, $category) {
    if ($category === "all") {
        // When "All" is selected, count all subscribers
        $sql = "SELECT COUNT(*) AS total FROM subscribers";
        $stmt = $conn->prepare($sql);
    } else {
        // Count subscribers for the selected category
        $sql = "SELECT COUNT(*) AS total FROM subscribers WHERE category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category);
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
    $category = isset($_POST["category"]) ? $_POST["category"] : "";
    
    // Fetch the list of categories
    $categoryList = getCategoryList($conn);


    // Function to fetch total number of people according to organizer
function getTotalPeopleByOrganizer($conn, $organizer) {
    if ($organizer === "all") {
        // When "All" is selected, count all subscribers
        $sql = "SELECT COUNT(*) AS total FROM subscribers";
        $stmt = $conn->prepare($sql);
    } else {
        // Count subscribers for the selected organizer
        $sql = "SELECT COUNT(*) AS total FROM subscribers WHERE organizer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $organizer);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row["total"];
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
$organizer = isset($_POST["organizer"]) ? $_POST["organizer"] : "";

// Fetch the list of organizers
$organizerList = getOrganizerList($conn);


// Function to fetch a list of events, their IDs, dates, and attendance in descending order of event date
function getEventList($conn) {
    $sql = "SELECT event_id, eventname, date FROM events ORDER BY date DESC"; 
    $result = $conn->query($sql);
    return $result;
}


// Function to fetch the number of attendees for a specific event and the number of repeated attendees
function getEventAttendees($conn, $event_id) {
    // Fetch the total attendees
    $sqlTotalAttendees = "SELECT COUNT(*) AS total FROM subscribers WHERE event_id = ?";
    $stmtTotalAttendees = $conn->prepare($sqlTotalAttendees);
    $stmtTotalAttendees->bind_param("s", $event_id);
    $stmtTotalAttendees->execute();
    $resultTotalAttendees = $stmtTotalAttendees->get_result();
    $rowTotalAttendees = $resultTotalAttendees->fetch_assoc();
    $totalAttendees = $rowTotalAttendees["total"];

    // Fetch the repeated attendees
    $sqlRepeatedAttendees = "SELECT COUNT(*) AS total FROM (
        SELECT mobile_number FROM subscribers WHERE event_id = ? GROUP BY mobile_number HAVING COUNT(*) > 1
    ) AS repeated";
    $stmtRepeatedAttendees = $conn->prepare($sqlRepeatedAttendees);
    $stmtRepeatedAttendees->bind_param("s", $event_id);
    $stmtRepeatedAttendees->execute();
    $resultRepeatedAttendees = $stmtRepeatedAttendees->get_result();
    $rowRepeatedAttendees = $resultRepeatedAttendees->fetch_assoc();
    $repeatedAttendees = $rowRepeatedAttendees["total"];

    return array("total" => $totalAttendees, "repeated" => $repeatedAttendees);
}


?>

    <?php include("header.php"); ?>
    
    <ul>
        <li>Total Number of People: <?php echo getTotalPeople($conn); ?></li>
        <li>Total Number of Events/Activities: <?php echo getTotalEvents($conn); ?></li>
        <li>Total Number of People Who Have Come More Than Once: <?php echo getTotalRepeatVisitors($conn); ?></li>
        
        
   <!-- Dropdown menu to select category -->
<li>
    <form method="post" action="dashboard.php">
        <label for="category">Total Number of people by Category:</label>
        <select name="category" id="category">
            <option value="all" <?php if ($category === "all") echo "selected"; ?>>All</option>
            <?php
            foreach ($categoryList as $cat) {
                echo "<option value='" . $cat["category_id"] . "'";
                if ($category == $cat["category_id"]) {
                    echo " selected";
                }
                echo ">" . $cat["category_name"] . "</option>";
            }
            ?>
        </select>
        <input type="submit" value="Filter" class="btn btn-outline-success">
        <?php
        if (!empty($category)) {
            $totalPeopleByCategory = getTotalPeopleByCategory($conn, $category);
            echo "<span>: $totalPeopleByCategory</span>";
        }
        ?>
    </form>
</li>

<!-- Dropdown menu to select organizer -->
<li>
    <form method="post" action="dashboard.php">
        <label for="organizer">Total Number of people by Organizer:</label>
        <select name="organizer" id="organizer">
            <option value="all" <?php if ($organizer === "all") echo "selected"; ?>>All</option>
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
        <input type="submit" value="Filter" class="btn btn-outline-success">
        <?php
        if (!empty($organizer)) {
            $totalPeopleByOrganizer = getTotalPeopleByOrganizer($conn, $organizer);
            echo "<span>: $totalPeopleByOrganizer</span>";
        }
        ?>
    </form>
</li>        
    </ul>

    <h2>List of Events:</h2>  
    <table border="1">
    <thead>
        <tr>
            <th>Event Name</th>
            <th>Event Date</th>
            <th>Event Attendees</th> <!-- Add the new column -->
        </tr>
    </thead>
    <tbody>
        <?php
        $eventResult = getEventList($conn);
        if ($eventResult->num_rows > 0) {
            while ($eventRow = $eventResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $eventRow["eventname"] . "</td>";
                echo "<td>" . $eventRow["date"] . "</td>";
                // Display the event attendees and repeated attendees count
                echo "<td>" . getEventAttendees($conn, $eventRow["event_id"])["total"] . " (" . getEventAttendees($conn, $eventRow["event_id"])["repeated"] . ")</td>";
                echo "</tr>";
                

            }
        } else {
            echo "<tr><td colspan='3'>No events found.</td></tr>";
        }
        
        ?>
    </tbody>
</table>
</body>
</html>
