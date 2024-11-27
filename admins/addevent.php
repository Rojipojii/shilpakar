<?php
session_start(); // Start a session
require_once "db.php"; // Include the database connection file

// Function to fetch categories from the database
function fetchCategories($conn)  
{
    $sql = "SELECT * FROM categories"; // Use the correct table name "categories"
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching categories: " . $conn->error);
    }
    $categories = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row["category_id"]] = $row["category_name"];
        }
    }
    return $categories;
}

// Function to fetch organizers from the database
function fetchOrganizers($conn)
{
    $sql = "SELECT * FROM organizers"; // Use the correct table name "Organizers"
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching organizers: " . $conn->error);
    }
    $organizers = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $organizers[$row["organizer_id"]] = $row["organizer_name"];
        }
    }
    return $organizers;
}

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

// Function to fetch today's event based on the current date
function getEventForDate($conn, $today) {
    $sql = "SELECT * FROM events WHERE date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null; // No event found for today
    }
}

// Handle the form submission for adding an event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addEvent"])) {
    $eventName = $_POST["eventName"];
    $eventDate = $_POST["eventDate"];
    $category = $_POST["category"];
    $organizer = $_POST["organizer"];

    // Insert event data into the database
    $sql = "INSERT INTO events (eventname, date, category_id, organizer_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }  

    // Bind parameters
    $stmt->bind_param("ssss", $eventName, $eventDate, $category, $organizer);

    if ($stmt->execute()) {
        echo "Event added successfully!";
       
        // Store the activated event and its date in a session variable
        $_SESSION["activatedEvent"] = [
            "eventname" => $eventName,
            "date" => $eventDate
        ];
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the database connection
    $stmt->close();
}

// Handle the form submission for editing event details
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit"])) {
    $eventId = $_POST["eventId"];
    $field = $_POST["field"];
    $newValue = $_POST["newValue"];

    // Define the field name in the database
    $dbField = "";
    switch ($field) {
        case "eventname":
            $dbField = "eventname";
            break;
        case "date":
            $dbField = "date";
            break;
        // Add more cases for other fields if needed
    }

    if (!empty($dbField)) {
        // Update the event data in the database
        $sql = "UPDATE events SET $dbField = ? WHERE event_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        // Bind parameters
        $stmt->bind_param("si", $newValue, $eventId);

        if ($stmt->execute()) {
            echo "Event updated successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the database connection
        $stmt->close();
    } else {
        echo "Invalid field.";
    }
}

// Fetch categories from the database
$categories = fetchCategories($conn);
// Fetch organizers from the database
$organizers = fetchOrganizers($conn);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <title>Add Event</title>
</head>
<body>
<?php include("header.php"); ?>
<!-- Your HTML form for editing and updating events -->
<table>
    <tr>
        <td class="add-event-form">
            <!-- List Of Events -->
            <h2>List of Events:</h2>  
    <table border="1">
    <thead>
    <tr>
        <th>Event Name</th>
        <th>Event Date</th>
        <th>Event Attendees</th>
        <th>Edit</th> <!-- Add a new column for editing -->
    </tr>
</thead>
<tbody>
<!-- Update your HTML structure for the list of events to include data attributes -->
<tbody>
    <?php
    $eventResult = getEventList($conn);
    if ($eventResult->num_rows > 0) {
        while ($eventRow = $eventResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td data-event-id='" . $eventRow["event_id"] . "' data-field='eventname'>" . $eventRow["eventname"] . "</td>";
            echo "<td data-event-id='" . $eventRow["event_id"] . "' data-field='date'>" . $eventRow["date"] . "</td>";
            echo "<td>" . getEventAttendees($conn, $eventRow["event_id"])["total"] . " (" . getEventAttendees($conn, $eventRow["event_id"])["repeated"] . ")</td>";
            echo "<td>";
            echo "<button class='editButton  btn btn-outline-info' data-event-id='" . $eventRow["event_id"] . "'>Edit</button>";
            echo "<button class='saveButton btn btn-outline-warning' data-event-id='" . $eventRow["event_id"] . "' style='display: none;'>Save</button>";
            echo "<button class='cancelButton btn btn-outline-secondary' data-event-id='" . $eventRow["event_id"] . "' style='display: none;'>Cancel</button>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4'>No events found.</td></tr>";
    }
    ?>
</tbody>
</table>
<script>
    // JavaScript for editing event data
document.querySelectorAll(".editButton").forEach(editButton => {
    editButton.addEventListener("click", function () {
        const eventId = this.getAttribute("data-event-id");
        const eventRow = this.closest("tr");

        // Get the event data to be edited
        const eventNameCell = eventRow.querySelector(`td[data-event-id="${eventId}"][data-field="eventname"]`);
        const dateCell = eventRow.querySelector(`td[data-event-id="${eventId}"][data-field="date"]`);

        // Create input fields for editing
        const eventNameInput = document.createElement("input");
        eventNameInput.type = "text";
        eventNameInput.value = eventNameCell.textContent;

        const dateInput = document.createElement("input");
        dateInput.type = "text";
        dateInput.value = dateCell.textContent;

        // Replace the cell content with input fields
        eventNameCell.innerHTML = "";
        eventNameCell.appendChild(eventNameInput);

        dateCell.innerHTML = "";
        dateCell.appendChild(dateInput);

        // Toggle visibility of buttons
        this.style.display = "none";
        eventRow.querySelector(`.saveButton[data-event-id="${eventId}"]`).style.display = "inline";
        eventRow.querySelector(`.cancelButton[data-event-id="${eventId}"]`).style.display = "inline";
    });
});

// JavaScript for saving and canceling edits
document.querySelectorAll(".saveButton").forEach(saveButton => {
    saveButton.addEventListener("click", function () {
        const eventId = this.getAttribute("data-event-id");
        const eventRow = this.closest("tr");
        const eventNameInput = eventRow.querySelector(`input[data-event-id="${eventId}"][data-field="eventname"]`);
        const dateInput = eventRow.querySelector(`input[data-event-id="${eventId}"][data-field="date"]`);

        const newEventName = eventNameInput.value;
        const newDate = dateInput.value;

        // Send an AJAX request to update the event data in the database
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "addevent.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const response = xhr.responseText;
                if (response === "Event updated successfully!") {
                    // Update the table cell content with the new values
                    eventNameInput.parentNode.innerHTML = newEventName;
                    dateInput.parentNode.innerHTML = newDate;
                    // Toggle visibility of buttons
                    eventRow.querySelector(`.editButton[data-event-id="${eventId}"]`).style.display = "inline";
                    this.style.display = "none";
                    eventRow.querySelector(`.cancelButton[data-event-id="${eventId}"]`).style.display = "none";
                } else {
                    alert(response); // Display an error message if the update fails
                }
            }
        };

        xhr.send(`edit=true&eventId=${eventId}&field=eventname&newValue=${newEventName}`);
    });
});

document.querySelectorAll(".cancelButton").forEach(cancelButton => {
    cancelButton.addEventListener("click", function () {
        const eventId = this.getAttribute("data-event-id");
        const eventRow = this.closest("tr");
        const eventNameInput = eventRow.querySelector(`input[data-event-id="${eventId}"][data-field="eventname"]`);
        const dateInput = eventRow.querySelector(`input[data-event-id="${eventId}"][data-field="date"]`);

        // Restore the original cell content
        eventNameInput.parentNode.innerHTML = eventNameInput.getAttribute("data-original-value");
        dateInput.parentNode.innerHTML = dateInput.getAttribute("data-original-value");

        // Toggle visibility of buttons
        eventRow.querySelector(`.editButton[data-event-id="${eventId}"]`).style.display = "inline";
        eventRow.querySelector(`.saveButton[data-event-id="${eventId}"]`).style.display = "none";
        this.style.display = "none";
    });
});


    </script>

        <!-- Event List on the Left -->
        <td class="event-list">
            <form id="eventForm" method="post" action = "addevent.php">


            <h2>Add Event </h2>
                <label for="eventName">Event Name:</label>
                <input type="text" id="eventName" name="eventName" required><br><br>


                <label for="eventDate">Event Date:</label>
                <input type="text" id="eventDate" name="eventDate" placeholder="YYYY-MM-DD" required><br> <br>


                <script>
                // Add a date picker to the input field
                document.addEventListener('DOMContentLoaded', function () {
                    const eventDateInput = document.getElementById('eventDate');
                    const selectDateButton = document.getElementById('selectDate');
                   
                    // Initialize a date picker when the page loads
                    flatpickr(eventDateInput, {
                        dateFormat: 'Y-m-d', // Format for displaying the date
                        allowInput: true, // Allow manual input
                    });


                    // Show the date picker when the "Select Date" button is clicked
                    selectDateButton.addEventListener('click', function () {
                        eventDateInput._flatpickr.open();
                    });
                });
                </script>


                <label for="category">Category:</label>
                <select id="category" name="category">
                    <?php
                    foreach ($categories as $categoryId => $categoryName) {
                        echo "<option value='$categoryId'>$categoryName</option>";
                    }
                    ?>
                </select><br><br>


                <label for="organizer">Organizer:</label>
                <select id="organizer" name="organizer">
                    <?php
                    foreach ($organizers as $organizerId => $organizerName) {
                        echo "<option value='$organizerId'>$organizerName</option>";
                    }
                    ?>
                </select><br><br>


                <input type="submit" value="Add Event" class="btn btn-outline-success">
            </form>
        </td>


        <script>
        // Add event listeners to the "Activate" buttons
        document.querySelectorAll(".activateButton").forEach(button => {
            button.addEventListener("click", function () {
                const eventId = this.getAttribute("data-event-id");
                // Redirect to the index.php page with the event ID as a parameter
                window.location.href = `../index.php?activateEvent=${eventId}`;
            });
        });

        // Check and activate events based on the current date
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Set the time to midnight for comparison

        document.querySelectorAll("tbody tr").forEach(row => {
            const eventDate = new Date(row.cells[1].textContent); // Assuming event date is in the second column
            if (eventDate >= today) {
                // If the event date is today or in the future, enable the "Activate" button
                const activateButton = row.querySelector(".activateButton");
                if (activateButton) {
                    activateButton.removeAttribute("disabled");
                }
            } else {
                // If the event date has passed, disable the "Activate" button
                const activateButton = row.querySelector(".activateButton");
                if (activateButton) {
                    activateButton.setAttribute("disabled", "true");
                }
            }
        });
        </script>

        <!-- Include the Flatpickr library script -->
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

 
    </tr>
</table>
</body>
</html>





