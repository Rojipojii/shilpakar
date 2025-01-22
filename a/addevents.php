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

// Set the timezone to Nepal (Asia/Kathmandu)
date_default_timezone_set("Asia/Kathmandu");

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
    $sql = "SELECT * FROM organizers"; // Use the correct table name "organizers"
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
$events = getEventList($conn);
function getEventList($conn) {
    $sql = "SELECT e.event_id, e.event_name, e.event_date, o.organizer_name 
        FROM events e
        JOIN organizers o ON e.organizer_id = o.organizer_id
        ORDER BY e.event_date DESC";
    $result = $conn->query($sql);
    return $result;
}

// Function to fetch the number of attendees for a specific event and the number of repeated attendees
function getEventAttendees($conn, $event_id) {
    // Initialize return values
    $totalAttendees = 0;
    $repeatedAttendees = 0;

    // Fetch the total unique attendees (distinct subscriber_id for the event)
    $sqlTotalAttendees = "SELECT COUNT(DISTINCT subscriber_id) AS total FROM event_subscriber_mapping WHERE event_id = ?";
    $stmtTotalAttendees = $conn->prepare($sqlTotalAttendees);
    $stmtTotalAttendees->bind_param("i", $event_id);
    $stmtTotalAttendees->execute();
    $resultTotalAttendees = $stmtTotalAttendees->get_result();
    $rowTotalAttendees = $resultTotalAttendees->fetch_assoc();
    $totalAttendees = $rowTotalAttendees ? $rowTotalAttendees["total"] : 0;

    // Fetch the repeated attendees (those who have attended more than once to the event)
    $sqlRepeatedAttendees = "SELECT subscriber_id, COUNT(*) AS total 
    FROM event_subscriber_mapping
    WHERE subscriber_id IN (
    SELECT DISTINCT subscriber_id
    FROM event_subscriber_mapping
    WHERE event_id = ?)
   GROUP BY subscriber_id HAVING COUNT(*) > 1";
    $stmtRepeatedAttendees = $conn->prepare($sqlRepeatedAttendees);
    $stmtRepeatedAttendees->bind_param("i", $event_id);
    $stmtRepeatedAttendees->execute();
    $resultRepeatedAttendees = $stmtRepeatedAttendees->get_result();
    $rowRepeatedAttendees = $resultRepeatedAttendees->fetch_assoc();
    $repeatedAttendees = $rowRepeatedAttendees ? $rowRepeatedAttendees["total"] : 0;

    return array("total" => $totalAttendees, "repeated" => $repeatedAttendees);
}


// Function to fetch today's event based on the current date
function getEventForDate($conn, $today) {
    $sql = "SELECT * FROM events WHERE event_date = ?";
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

// Function to count the total number of events
function countTotalEvents($conn) {
    $sql = "SELECT COUNT(*) AS total_events FROM events";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error counting events: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    return $row['total_events'];
}

// Get the total number of events
$totalEvents = countTotalEvents($conn);


// Handle the form submission for editing events
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit"])) {
    // Retrieve form inputs
    $editedEvent = $_POST["editedEventName"];
    $editedDate = $_POST["editedDate"];
    $editedOrganizer = $_POST['editedOrganizerId'];
    $eventId = $_POST["eventId"];

    // Validate required fields
    if (!empty($editedEvent) && !empty($editedDate) && !empty($editedOrganizer)) {
        // Update the event in the database
        $sql = "UPDATE events 
                SET event_name = ?, event_date = ?, organizer_id = ?
                WHERE event_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters to the SQL statement
            $stmt->bind_param("ssii", $editedEvent, $editedDate, $editedOrganizer, $eventId);

            // Execute the statement
            if ($stmt->execute()) {
                echo "Event updated successfully!";
            } else {
                echo "Error updating the event: " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        // Handle validation errors
        echo "All fields (event name, date, organizer) are required.";
    }

    // Exit after handling the update to prevent page reload
    exit;
}


// Handle the form submission for adding an event
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["eventName"]) && isset($_POST["eventDate"]) && isset($_POST["organizer"])) {
    $eventName = $_POST["eventName"];
    $eventDate = $_POST["eventDate"];
    $organizer = $_POST["organizer"];

    // Validate the inputs
    if (empty($eventName) || empty($eventDate) || empty($organizer)) {
        $eventMessage = "All fields are required, including the event date!";
        // echo "<script>alert('$eventMessage');</script>";
    } else {
        // Insert event data into the database
        $sql = "INSERT INTO events (event_name, event_date, organizer_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        // Bind parameters
        $stmt->bind_param("ssi", $eventName, $eventDate, $organizer);
        if ($stmt->execute()) {
            // Event added successfully
            $eventMessage = "Event added successfully!";
            // Redirect to refresh the page and display the updated list
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $eventMessage = "Error: " . $stmt->error;
        }

        // Close the database connection
        $stmt->close();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap/dist/js/bootstrap.min.js"></script>
    <title>Events</title>
</head>
<body>

<?php include("header.php"); ?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid" id="eventListContainer">
            <div class="row mb-2">
                <div class="col-md-8">
                    <div id="eventResponseMessage">
                        <?php
                        if (!empty($eventMessage)) {
                            echo '<div class="alert alert-info">' . $eventMessage . '</div>';
                        }
                        ?>
                    </div>
                    <!-- Left Column: List of Events -->
                    <h2>Events: <?php echo $totalEvents; ?></h2>
                    <table class="table table-bordered">
    <thead>
        <tr>
            <th>Event Name</th>
            <th>Event Date</th>
            <th>Organizer</th>
            <th>Event Attendees</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php
    while ($event = $events->fetch_assoc()) {
        $eventId = $event["event_id"];
        $eventName = $event["event_name"];
        $eventDate = $event["event_date"];
        $organizerName = $event["organizer_name"];

        // Calculate the total attendees for the current event
        $attendees = getEventAttendees($conn, $eventId);
        $totalAttendees = $attendees["total"];
        $repeatedAttendees = $attendees["repeated"];

        echo "<tr>";
        echo "<td>";
        // Make the event name a hyperlink
        echo '<a href="subscribers.php?event_id=' . $eventId . '" class="event-name" data-event-id="' . $eventId . '" style="text-decoration: underline; color: inherit;" 
        onmouseover="this.style.textDecoration=\'none\'; this.style.color=\'inherit\';" 
        onmouseout="this.style.textDecoration=\'underline\'; this.style.color=\'inherit\';">' . $eventName . '</a>';
        echo "</td>";
        echo "<td>";
        $eventDateFormatted = date("d F Y", strtotime($eventDate));
        echo "<span class='event-date' data-event-id='$eventId'>$eventDateFormatted</span>";
        // Add an input field for editing the event date
        echo "<input class='form-control event-date-input' data-event-id='$eventId' type='text' value='$eventDate' style='display: none;'>";
        echo "</td>";
        
        // Organizer dropdown - Initially hidden
        echo "<td>";
        // Display the current organizer
        echo "<span class='organizer-name' data-event-id='$eventId'>$organizerName</span>";
        
        // Check if event has attendees
        if ($totalAttendees > 0) {
            // Disable the organizer dropdown if event has attendees
            echo "<select class='form-control organizer-dropdown' data-event-id='$eventId' style='display: none;' disabled>";
            echo "<option value='$organizerId' selected>$organizerName (Not Editable)</option>";
        } else {
            // If no attendees, allow editing
            echo "<select class='form-control organizer-dropdown' data-event-id='$eventId' style='display: none;'>";
            echo "<option value='' disabled selected>Select Organizer</option>";

            // Sort organizers alphabetically by organizer name
            asort($organizers);

            foreach ($organizers as $organizerId => $organizerName) {
                $selected = (isset($event['organizer_id']) && $organizerId == $event['organizer_id']) ? 'selected' : '';
                echo "<option value='$organizerId' $selected>$organizerName</option>";
            }
        }

        echo "</select>";
        echo "</td>";

        echo "<td>";
        echo "$totalAttendees ($repeatedAttendees)";
        echo "</td>";
        echo "<td>";
        echo "<button class='btn btn-outline-primary edit-event' data-event-id='$eventId'><i class='bi bi-pencil-square'></i></button>";
        echo "<button class='btn btn-outline-success save-event' data-event-id='$eventId' style='display: none;'><i class='bi bi-check2-square'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
</tbody>


</table>

                </div>
                <div class="col-md-4">
                    <!-- Add Event Form -->
                    <h2>Add Event</h2>
                    <form id="eventForm" class="add-event-form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"  onsubmit="return validateForm()" class="form-horizontal">
                        <div class="form-group">
                            <label for="eventName">Event Name:</label>
                            <input type="text" id="eventName" name="eventName" class="form-control" required>
                        </div>
                        <div class="form-group">
        <label for="eventDate">Event Date:</label>
        <input type="text" id="eventDate" name="eventDate" class="form-control" placeholder="YYYY-MM-DD" required>
        <div id="eventDateError" class="invalid-feedback" style="display:none;">
        Please select a date.
    </div>
    </div>
    <script>
        // Initialize Flatpickr datepicker
        flatpickr('#eventDate', {
            dateFormat: 'Y-m-d', // Set the date format
            enableTime: false, // Disable time selection
            altInput: true, // Show the date in a readable format (optional)
            altFormat: 'F j, Y', // Date display format (optional)
        });

        // Validate form before submission
        function validateForm() {
            const eventDateField = document.getElementById('eventDate');
            const eventDateError = document.getElementById('eventDateError');
            const eventDateValue = eventDateField.value;

            // Check if the date is empty
            if (!eventDateValue) {
                eventDateField.value = ''; // Clear any existing value
                eventDateField.placeholder = "Please select a date!"; // Update placeholder with error message
                eventDateField.classList.add('is-invalid'); // Add Bootstrap invalid class for styling
                eventDateError.style.display = 'block'; // Show error message
                return false; // Prevent form submission
            }

            eventDateField.classList.remove('is-invalid'); // Remove invalid class if valid
            eventDateError.style.display = 'none'; 
            return true; // Allow form submission
        }
    </script>

<div class="form-group">
    <label for="organizer">Organizer:</label>
    <select id="organizer" name="organizer" class="form-control" required>
        <option value="" disabled selected>Select Organizer</option>
        <?php
        // Sort the organizers array by their values (organizer names) in alphabetical order
        asort($organizers);

        // Loop through the sorted organizers and display each as an option
        foreach ($organizers as $organizerId => $organizerName) {
            echo "<option value='$organizerId'>$organizerName</option>";
        }
        ?>
    </select>
</div>

                        <div class="form-group">
                            <input type="submit" value="Add Event" id="addEventButton" class="btn btn-outline-success">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Handle event editing and saving -->
<script>
const editEventButtons = document.querySelectorAll(".edit-event");
const saveEventButtons = document.querySelectorAll(".save-event");

editEventButtons.forEach(button => {
    button.addEventListener("click", function (event) {
        event.preventDefault();

        const eventId = this.getAttribute("data-event-id");

        // Define all required elements
        const eventElement = document.querySelector(`.event-name[data-event-id='${eventId}']`);
        const dateElement = document.querySelector(`.event-date[data-event-id='${eventId}']`);
        const dateInput = document.querySelector(`.event-date-input[data-event-id='${eventId}']`);
        const organizerElement = document.querySelector(`.organizer-name[data-event-id='${eventId}']`);
        const organizerDropdown = document.querySelector(`.organizer-dropdown[data-event-id='${eventId}']`);
        const saveButton = document.querySelector(`.save-event[data-event-id='${eventId}']`);

        // Apply Flatpickr to the date input
        flatpickr(dateInput, {
            dateFormat: 'Y-m-d',
            allowInput: true,
        });

        // Enable editing
        eventElement.contentEditable = true;
        eventElement.focus();
        dateElement.style.display = "none";
        dateInput.style.display = "inline";
        dateInput.focus();
        organizerElement.style.display = "none";
        organizerDropdown.style.display = "inline";

        // Toggle buttons
        saveButton.style.display = "inline";
        this.style.display = "none";
    });
});


saveEventButtons.forEach(button => {
    button.addEventListener("click", function (event) {
        event.preventDefault(); // Prevent default behavior of button click

        const eventId = this.getAttribute("data-event-id");

        const eventElement = document.querySelector(`.event-name[data-event-id='${eventId}']`);
        const dateElement = document.querySelector(`.event-date[data-event-id='${eventId}']`);
        const dateInput = document.querySelector(`.event-date-input[data-event-id='${eventId}']`);
        const organizerDropdown = document.querySelector(`.organizer-dropdown[data-event-id='${eventId}']`);
        const organizerElement = document.querySelector(`.organizer-name[data-event-id='${eventId}']`);

        const updatedEventName = eventElement.textContent.trim();
        const updatedEventDate = dateInput.value;
        const updatedOrganizerId = organizerDropdown.value;

        // Prepare data for AJAX request
        const formData = new FormData();
        formData.append("editedEventName", updatedEventName);
        formData.append("editedDate", updatedEventDate);
        formData.append("eventId", eventId);
        formData.append("editedOrganizerId", updatedOrganizerId);
        formData.append("edit", 1);

        fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes("Event updated successfully")) {
                // Update the event details in the DOM
                eventElement.textContent = updatedEventName;
                dateElement.textContent = updatedEventDate;
                organizerElement.textContent = organizerDropdown.options[organizerDropdown.selectedIndex].text;

                // Hide the input fields and show the text elements
                organizerDropdown.style.display = "none";
                organizerElement.style.display = "inline";
                dateInput.style.display = "none";
                dateElement.style.display = "inline";

                // Store success message in sessionStorage
                sessionStorage.setItem('eventUpdateSuccess', 'Event updated successfully');

                // Reload the page
                location.reload();

                // Toggle buttons
                this.style.display = "none";
                document.querySelector(`.edit-event[data-event-id='${eventId}']`).style.display = "inline";
            } else {
                document.getElementById("eventResponseMessage").innerHTML = `<div class="alert alert-warning">Failed to update event: ${data} </div>`;
            }
        })
        .catch(error => {
            console.error("Error:", error);
        });
    });
});

// After the page reloads, check if there's a success message in sessionStorage
window.onload = function() {
    var successMessage = sessionStorage.getItem('eventUpdateSuccess');
    if (successMessage) {
        // Display the success message
        document.getElementById("eventResponseMessage").innerHTML = '<div class="alert alert-success">' + successMessage + '</div>';

        // Clear the message from sessionStorage after showing it
        sessionStorage.removeItem('eventUpdateSuccess');
    }
};


</script>

<!-- Activate Events based on current date -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
    });
</script>

</body>
</html>