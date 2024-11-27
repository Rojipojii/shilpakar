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
$events = getEventList($conn);
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

        loadEventList();
       
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

// Handle form submission for editing events list
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit"])) {
    $editedEvent = $_POST["editedEvent"];
    $editedDate = $_POST["editedDate"];
    $eventId = $_POST["eventId"];

    if (!empty($editedEvent)) {
        // Update the event name and date in the database
        $sql = "UPDATE events SET eventname = ?, date=? WHERE event_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $editedEvent, $eventId);
            if ($stmt->execute()) {
                // Event updated successfully
                echo "Event updated successfully!";
            } else {
                echo "Error updating the event: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        echo "Event name cannot be empty.";
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <title>Add Event</title>
</head>
<body>

<?php include("header.php"); ?>
<!-- Your HTML form for editing and updating events -->
<div class="container" id= "eventListContainer">
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

<!-- Update your HTML structure for the list of events to include data attributes -->
<tbody>
<?php
foreach ($events as $event) {
    $eventId = $event["event_id"];
    $eventName = $event["eventname"];
    $eventDate = $event["date"];
    // ...

    echo "<tr>";
    echo "<td>";
    echo "<span class='event-name' data-event-id='$eventId'>$eventName</span>";
    echo "</td>";
    echo "<td>";
    echo "<span class='event-date' data-event-id='$eventId'>$eventDate</span>";
    // Add an input field for editing the event date
    echo "<input class='event-date-input' data-event-id='$eventId' type='text' value='$eventDate' style='display: none;'>";
    echo "</td>";
    echo "<td>" . getEventAttendees($conn, $event["event_id"])["total"] . " (" . getEventAttendees($conn, $event["event_id"])["repeated"] . ")</td>";
    echo "<td>";
    echo "<button class='edit-event yellow black-text lighten-6 waves-effect waves-light btn' data-event-id='$eventId'>Edit</button>";
    echo "<button class='save-event green black-text lighten-6 waves-effect waves-light btn' data-event-id='$eventId' style='display: none;'>Save</button>";
    echo "</td>";
    echo "</tr>";
}
?>
</tbody>





    <?php
    foreach ($events as $event) {
        $eventId = $event["event_id"];
        $eventName = $event["eventname"];
        $eventDate = $event["date"];
        // ...
    

        echo "<tr>";
        echo "<td>";
        echo "<span class='event-name' data-event-id='$eventId'>$eventName</span>";
        echo "</td>";
        echo "<td>";
        echo "<span class='event-date' data-event-id='$eventId'>$eventDate</span>";
        // Add an input field for editing the event date
        echo "<input class='event-date-input' data-event-id='$eventId' type='text' value='$eventDate' style='display: none;'>";
        echo "</td>";
        echo "<td>" . getEventAttendees($conn, $event["event_id"])["total"] . " (" . getEventAttendees($conn, $event["event_id"])["repeated"] . ")</td>";
        echo "<td>";
        echo "<button class='edit-event yellow black-text lighten-6 waves-effect waves-light btn' data-event-id='$eventId'>Edit</button>";
        echo "<button class='save-event green black-text lighten-6 waves-effect waves-light btn' data-event-id='$eventId' style='display: none;'>Save</button>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
</tbody>
</table>

<script>
    // Handle event editing and saving
    const editEventButtons = document.querySelectorAll(".edit-event");
    const saveEventButtons = document.querySelectorAll(".save-event");

    editEventButtons.forEach(button => {
        button.addEventListener("click", function () {
            const eventId = this.getAttribute("data-event-id");
            const eventElement = document.querySelector(`.event-name[data-event-id='${eventId}']`);
            const dateElement = document.querySelector(`.event-date[data-event-id='${eventId}']`);
            const dateInput = document.querySelector(`.event-date-input[data-event-id='${eventId}']`);
            const saveButton = document.querySelector(`.save-event[data-event-id='${eventId}']`);

            // Initialize a date picker for the input field
            flatpickr(dateInput, {
                dateFormat: 'Y-m-d', // Format for displaying the date
                allowInput: true, // Allow manual input
            });

            // Display the input field and hide the "Edit" button
            dateInput.style.display = "inline";
            this.style.display = "none";
            
            // Enable editing for the event name
            eventElement.contentEditable = true;
            eventElement.focus();

            // Enable editing for the event date
            dateElement.style.display = "none";
            dateInput.style.display = "inline";
            dateInput.focus();

            // Show the "Save" button and hide the "Edit" button
            saveButton.style.display = "inline";
            this.style.display = "none";
        });
    });

    saveEventButtons.forEach(button => {
        button.addEventListener("click", function () {
            console.log("Save button clicked");
            const eventId = this.getAttribute("data-event-id");
            const eventElement = document.querySelector(`.event-name[data-event-id='${eventId}']`);
            const dateElement = document.querySelector(`.event-date[data-event-id='${eventId}']`);
            const dateInput = document.querySelector(`.event-date-input[data-event-id='${eventId}']`);
            const updatedEventName = eventElement.textContent.trim();
            const updatedEventDate = dateInput.value;

            // Send an AJAX request to update the event name and date
            fetch("addevent.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `editedEvent=${updatedEventName}&editedDate=${updatedEventDate}&eventId=${eventId}&edit=1`

            })
                .then(response => response.text())
                .then(data => {
                    if (data.includes("Event updated successfully")) {
                        alert("Event updated successfully");
                        loadEventList();
                        // Refresh the page after updating the event data
                        window.location.href = window.location.href;

                        // Disable editing for the event name and date
                        eventElement.contentEditable = false;
                        dateElement.style.display = "inline";
                        dateInput.style.display = "none";

                        // Hide the "Save" button and show the "Edit" button
                        this.style.display = "none";
                        document.querySelector(`.edit-event[data-event-id='${eventId}']`).style.display = "inline";
                    } else {
                        // Restore the original name and date on error
                        eventElement.textContent = eventElement.dataset.originalName;
                        dateInput.value = dateElement.textContent;
                        // console.error("Error:", data);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                });
        });
    });

    function loadEventList() {
    fetch("addevent.php") // Replace with the correct URL
        .then(response => response.text())
        .then(data => {
            const eventListContainer = document.getElementById("eventListContainer");
            eventListContainer.innerHTML = data; // Replace the existing event list with the updated list
        })
        .catch(error => {
            console.error("Error:", error);
        });
}




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


                <input type="submit" value="Add Event" class="blue lighten-6 waves-effect waves-light btn">
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
    </div>
</body>
</html>





