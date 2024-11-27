<?php
require_once "db.php"; // Include the database connection file

// Function to fetch organizers and their total number of attendees from the database
function fetchOrganizersWithAttendees($conn)
{
    $sql = "    SELECT organizers.organizer_id, organizers.organizer_name, 
    COUNT(subscribers.id) AS total_attendees,
    (SELECT COUNT(*) FROM events WHERE organizer_id = organizers.organizer_id) AS total_events
    FROM organizers
    LEFT JOIN subscribers ON organizers.organizer_id = subscribers.organizer_id
    GROUP BY organizers.organizer_id, organizers.organizer_name";

    $result = $conn->query($sql);

    $organizers = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $organizers[] = $row;
        }
    }

    return $organizers;
}


// Handle form submission for adding an organizer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addOrganizer"])) {
    $newOrganizer = $_POST["newOrganizer"];

    if (!empty($newOrganizer)) {
        // Insert the new organizer into the database
        $sql = "INSERT INTO Organizers (organizer_name) VALUES (?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $newOrganizer);
            if ($stmt->execute()) {
                // Organizer added successfully
                echo "Organizer added successfully!";
            } else {
                echo "Error adding the organizer: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        echo "Organizer name cannot be empty.";
    }
}

// Handle form submission for editing an organizer
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editOrganizer"])) {
    $editedOrganizer = $_POST["editedOrganizer"];
    $organizerId = $_POST["organizerId"];

    if (!empty($editedOrganizer)) {
        // Update the organizer name in the database
        $sql = "UPDATE Organizers SET organizer_name = ? WHERE organizer_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $editedOrganizer, $organizerId);
            if ($stmt->execute()) {
                // Organizer updated successfully
                echo "Organizer updated successfully!";
            } else {
                echo "Error updating the organizer: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        echo "Organizer name cannot be empty.";
    }
}

// Fetch organizers with total attendees from the database
$organizers = fetchOrganizersWithAttendees($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Organizer</title>
</head>
<body>
    <?php include("header.php"); ?>
    <h1>Add Organizer</h1>
    <div id="responseMessage"></div>

    <!-- Form to add a new organizer -->
    <form id="addOrganizerForm" method="POST" action="addorganizer.php">
        <label for="newOrganizer">Organizer Name:</label>
        <input type="text" id="newOrganizer" name="newOrganizer" required>
        <button type="submit" name="addOrganizer" class="btn btn-outline-success">Add Organizer</button>
    </form>

      <!-- Display existing organizers with total attendees -->
      <h2>Existing Organizers:</h2>
    <table border="1">
    <thead>
    <tr>
        <th>Organizer Name</th>
        <th>Total Attendees</th>
        <th>Total Events</th>
        <th>Action</th>
    </tr>
    </thead>

    <tbody>
    <?php
    foreach ($organizers as $organizer) {
        $organizerId = $organizer["organizer_id"];
        $organizerName = $organizer["organizer_name"];
        $totalAttendees = $organizer["total_attendees"];
        $totalEvents = $organizer["total_events"];

        echo "<tr>";
        echo "<td><span contenteditable='true' class='edit-organizer' data-organizer-id='$organizerId'>$organizerName</span></td>";
        echo "<td>$totalAttendees</td>";
        echo "<td>$totalEvents</td>";
        echo "<td><button class='edit-organizer btn btn-outline-info' data-organizer-id='$organizerId'>Edit</button></td>";
        echo "<td><button class='save-organizer btn btn-outline-warning' data-organizer-id='$organizerId'>Save</button></td>";
        echo "</tr>";
    }
    ?>
</tbody>
</table>
        <script>
        // Handle inline editing of existing organizers
const editOrganizerElements = document.querySelectorAll(".edit-organizer");
editOrganizerElements.forEach(element => {
    element.addEventListener("click", function () {
        // Store the original organizer name
        this.dataset.originalName = this.textContent.trim();

        // Enable content editing
        this.contentEditable = true;
        this.focus();
    });

    element.addEventListener("blur", function () {
        const editedOrganizer = this.textContent.trim();
        const organizerId = this.getAttribute("data-organizer-id");

        // Send an AJAX request to update the organizer name
        fetch("addorganizer.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `editedOrganizer=${editedOrganizer}&organizerId=${organizerId}&editOrganizer=1`
        })
            .then(response => response.text())
            .then(data => {
                if (data.includes("Organizer updated successfully")) {
                    // Organizer updated successfully
                    document.getElementById("responseMessage").textContent = "Organizer updated successfully";

                    // Refresh the organizer list
                    refreshOrganizerList();
                } else {
                    // Restore the original name on error
                    this.textContent = this.dataset.originalName;
                    console.error("Error:", data);
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });

        // Disable content editing
        this.contentEditable = false;
    });
});

// Function to refresh the organizer list
function refreshOrganizerList() {
    fetch("addorganizer.php")
        .then(response => response.text())
        .then(data => {
            // Replace the existing organizer list with the updated list
            const organizerList = document.querySelector("tbody");
            organizerList.innerHTML = data;
        })
        .catch(error => {
            console.error("Error:", error);
        });
}


    </script>
</body>
</html>
