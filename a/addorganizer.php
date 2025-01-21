<?php
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

require_once "db.php"; // Include the database connection file

// Function to fetch organizers and their total number of attendees from the database
function fetchOrganizersWithAttendees($conn)
{
    $sql = "SELECT 
    organizers.organizer_id, 
    organizers.organizer_name, 
    COUNT(DISTINCT events.event_id) AS total_events,
    (
        SELECT COUNT(*)
        FROM event_subscriber_mapping 
        WHERE organizers.organizer_id = event_subscriber_mapping.organizer_id
    ) AS total_attendees
FROM 
    organizers
LEFT JOIN 
    events ON organizers.organizer_id = events.organizer_id
GROUP BY
    organizers.organizer_id, organizers.organizer_name;
";

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
        // Check if the organizer already exists
        $sqlCheck = "SELECT organizer_id FROM organizers WHERE organizer_name = ?";
        $stmtCheck = $conn->prepare($sqlCheck);

        if ($stmtCheck) {
            $stmtCheck->bind_param("s", $newOrganizer);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows == 0) {
                // The organizer doesn't exist, so insert it
                $stmtCheck->close();

                $sqlInsert = "INSERT INTO organizers (organizer_name) VALUES (?)";
                $stmtInsert = $conn->prepare($sqlInsert);

                if ($stmtInsert) {
                    $stmtInsert->bind_param("s", $newOrganizer);
                    if ($stmtInsert->execute()) {
                        // Organizer added successfully
                        $organizerMessage = "Organizer added successfully!";
                    } else {
                        $organizerMessage = "Error adding the organizer: " . $stmtInsert->error;
                    }

                    $stmtInsert->close();
                } else {
                    $organizerMessage = "Error preparing the statement: " . $conn->error;
                }
            } else {
                $organizerMessage = "Organizer with the same name already exists.";
            }
        } else {
            $organizerMessage = "Error preparing the statement for organizer name check: " . $conn->error;
        }
    } else {
        $organizerMessage = "Organizer name cannot be empty.";
    }
}

// Handle form submission for editing an organizer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editOrganizer"])) {
    $editedOrganizer = $_POST["editedOrganizer"];
    $organizerId = $_POST["organizerId"];

    if (!empty($editedOrganizer)) {
        // Update the organizer name in the database
        $sql = "UPDATE organizers SET organizer_name = ? WHERE organizer_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $editedOrganizer, $organizerId);
            if ($stmt->execute()) {
                // Organizer updated successfully
                $organizerMessage = "Organizer updated successfully ";
            } else {
                $organizerMessage = "Error updating the organizer " . $stmt->error;
            }

            $stmt->close();
        } else {
            $organizerMessage = "Error preparing the statement " . $conn->error;
        }
    } else {
        $organizerMessage = "Organizer name cannot be empty. ";
    }
}

// Fetch organizers with total attendees from the database
$organizers = fetchOrganizersWithAttendees($conn);
$totalOrganizers = count($organizers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Add Organizer</title>
    <style>
    #organizerNameError {
        color: red;
        font-size: 14px;
    }
    </style>
</head>
<body>
    <?php include("header.php"); ?>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="mt-4">Add Organizer</h1>
                        <div id="organizerResponseMessage">
                            <?php
                            if (!empty($organizerMessage)) {
                                echo '<div class="alert alert-info">' . $organizerMessage . '</div>';
                            }
                            ?>
                        </div>
                        <form id="addOrganizerForm" method="POST" action="addorganizer.php" class="mt-3">
                            <div class="form-group">
                                <label for="newOrganizer">Organizer Name:</label>
                                <input type="text" id="newOrganizer" name="newOrganizer" required class="form-control">
                            </div>
                            <button type="submit" name="addOrganizer" class="btn btn-outline-success">Add Organizer</button>
                        </form>
                        <!-- Display existing organizers with total attendees -->
                        <h2 class="mt-4">Organizers: <?php echo $totalOrganizers; ?></h2>
                        <table class="table">
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
    // Sort organizers alphabetically by organizer_name
    usort($organizers, function($a, $b) {
        return strcmp($a["organizer_name"], $b["organizer_name"]);
    });

    foreach ($organizers as $organizer) {
        $organizerId = $organizer["organizer_id"];
        $organizerName = $organizer["organizer_name"];
        $fetchOrganizersWithAttendees = $organizer["total_attendees"];
        $totalEvents = $organizer["total_events"];

        echo "<tr>";
        echo "<td>";
        // Make the organizer name a hyperlink
        echo '<a href="subscribers.php?organizer_id=' . $organizerId . '" class="organizer-name" data-organizer-id="' . $organizerId . '" style="text-decoration: underline; color: inherit;" 
    onmouseover="this.style.textDecoration=\'none\'; this.style.color=\'inherit\';" 
    onmouseout="this.style.textDecoration=\'underline\'; this.style.color=\'inherit\';">' . $organizerName . '</a>';
        echo "</td>";
        echo "<td>";
        // Display attendees count without a hyperlink
        echo "$fetchOrganizersWithAttendees";
        echo "</td>";
        echo "<td>$totalEvents</td>";
        echo "<td>";
        echo "<button class='edit-organizer btn btn-outline-primary' data-organizer-id='$organizerId'><i class='bi bi-pencil-square'></i></button>";
        echo "<button class='save-organizer btn btn-outline-success' data-organizer-id='$organizerId' style='display: none;'><i class='bi bi-check2-square'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
?>

</tbody>

                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Handle organizer editing and saving
        const editOrganizerButtons = document.querySelectorAll(".edit-organizer");
        const saveOrganizerButtons = document.querySelectorAll(".save-organizer");

        editOrganizerButtons.forEach(button => {
            button.addEventListener("click", function () {
                const organizerId = this.getAttribute("data-organizer-id");
                const organizerElement = document.querySelector(`.organizer-name[data-organizer-id='${organizerId}']`);
                const saveButton = document.querySelector(`.save-organizer[data-organizer-id='${organizerId}']`);

                // Enable editing for the organizer name
                organizerElement.contentEditable = "true";
                organizerElement.focus();

                // Show the "Save" button and hide the "Edit" button
                saveButton.style.display = "inline";
                this.style.display = "none";
            });
        });


        saveOrganizerButtons.forEach(button => {
    button.addEventListener("click", function () {
        const organizerId = this.getAttribute("data-organizer-id");
        const organizerElement = document.querySelector(`.organizer-name[data-organizer-id='${organizerId}']`);
        let updatedOrganizerName = organizerElement.textContent.trim();

        // Encode the organizer name
        updatedOrganizerName = encodeURIComponent(updatedOrganizerName);

        fetch("addorganizer.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            // Use the encoded organizer name in the request body
            body: `editedOrganizer=${updatedOrganizerName}&organizerId=${organizerId}&editOrganizer=1`
        })
            .then(response => response.text())
            .then(data => {
                if (data.includes("Organizer updated successfully")) {
                    // Update the organizer element (optional)
                    organizerElement.textContent = decodeURIComponent(updatedOrganizerName);

                    // Display the success message
                    document.getElementById("organizerResponseMessage").innerHTML = '<div class="alert alert-success">Organizer updated successfully</div>';

                    // Hide the "Save" button and show the "Edit" button
                    button.style.display = "none";
                    document.querySelector(`.edit-organizer[data-organizer-id='${organizerId}']`).style.display = "inline";
                } else {
                    console.error("Error:", data);
                }
            })
            .catch(error => {
                console.log("Error:", error);
            });
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
