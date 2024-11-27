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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once "db.php"; // Include the database connection file

// Function to fetch events from the database
function fetchEvents($conn) {
    $sql = "SELECT * FROM events"; // Use the correct table name "events"
    $result = $conn->query($sql);

    if (!$result) {
        die("Error fetching events: " . $conn->error);
    }

    $events = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[$row["event_id"]] = $row["eventname"];
        }
    }

    return $events;
}

// Function to fetch categories from the database
function fetchCategories($conn) {
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
function fetchOrganizers($conn) {
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

function insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id) {
    // Define the SQL statement with placeholders based on the provided parameters
    $sql = "INSERT INTO subscribers (full_name, mobile_number, email, designation, organization, number_of_events_attended, event_id, category_id, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssssiiii", $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id);

    if ($stmt->execute()) {
        return true;
    } else {
        die("Execute failed: " . $stmt->error);
    }
}





// Function to add an event to the database
function addEvent($conn, $eventName, $eventDate, $category_id, $organizer_id) {
    // Define the SQL statement to insert an event
    $sql = "INSERT INTO events (eventname, date, category_id, organizer_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssii", $eventName, $eventDate, $category_id, $organizer_id);

    if ($stmt->execute()) {
        return $conn->insert_id; // Return the event_id of the newly added event
    } else {
        die("Execute failed: " . $stmt->error);
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mobileNumber = $_POST["mobileNumber"]; // Retrieve mobile number first

// Handle form submission for single add
if (isset($_POST["Submit"])) {
    // Retrieve subscriber details from the form
    $fullName = $_POST["fullName"];
    $mobileNumber = $_POST["mobileNumber"];
    $email = $_POST["email"];
    $designation = $_POST["designation"];
    $organization = $_POST["organization"];
    $number_of_events_attended = 1; // You can specify the value here
    $event_id = null;
    $category_id = $_POST["category"];
    $organizer_id = null;

    // Define placeholders for null values
    $nullValue = null;

    if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $nullValue, $category_id, $nullValue)) {
        // Subscriber added successfully, set a JavaScript variable
        echo "<script>var successMessage = 'Subscriber added successfully!';</script>";
    } else {
        // Handle the error, if any
        echo "Error: Failed to insert data into the database.";
        echo "Error adding subscriber.";
    }
}


    // Fetch events from the database
    $events = fetchEvents($conn);

    // Handle form submission for Bulk Add
    if (isset($_POST["AddSubscribers"])) {
        // Retrieve event details from the form
        $eventName = isset($_POST["eventName"]) ? $_POST["eventName"] : null;
        $eventDate = isset($_POST["eventDate"]) ? $_POST["eventDate"] : null;
        $category_id = isset($_POST["category"]) ? $_POST["category"] : null;
        $organizer_id = isset($_POST["organizer"]) ? $_POST["organizer"] : null;

        // Add the event to the database
        $event_id = addEvent($conn, $eventName, $eventDate, $category_id, $organizer_id);

        // Check if the event was added successfully
        if ($event_id !== false) {
            // Store the event_id, category_id, and organizer_id in session variables
            $_SESSION["event_id"] = $event_id;
            $_SESSION["category_id"] = $category_id;
            $_SESSION["organizer_id"] = $organizer_id;

            // Update event details in the database
            $updateEventSql = "UPDATE events SET eventname = ?, date = ? WHERE event_id = ?";
            $updateEventStmt = $conn->prepare($updateEventSql);

            if (!$updateEventStmt) {
                die("Prepare failed: " . $conn->error);
            }

            $updateEventStmt->bind_param("ssi", $eventName, $eventDate, $event_id);

            if ($updateEventStmt->execute()) {
                // Event details updated successfully

                // Process the uploaded CSV file
                if (isset($_FILES["csvFile"])) {
                    $csvFile = $_FILES["csvFile"]["tmp_name"];

                    if (($handle = fopen($csvFile, "r")) !== FALSE) {
                        // Loop through the CSV file and insert records into the database
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            // Ensure the CSV file has the expected number of columns
                            if (count($data) == 5) { // Assuming 5 columns in the CSV (excluding the count)
                                $fullName = $data[0];
                                $mobileNumber = $data[1];
                                $email = $data[2];
                                $designation = $data[3];
                                $organization = $data[4];

                                // Insert the record into the database with the event_id, category_id, and organizer_id
                                if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $event_id, $category_id, $organizer_id)) {
                                    // Record added successfully
                                } else {
                                    die("Error inserting CSV data into the database.");
                                }
                            } else {
                                die("Invalid CSV format: Each row should have 5 columns.");
                            }
                        }

                        fclose($handle);

                        // Redirect to the subscribers page after processing the CSV
                        header("Location: subscribers.php");
                        exit();
                    } else {
                        die("Error opening the CSV file.");
                    }
                }
            } else {
                die("Execute failed: " . $updateEventStmt->error);
            }
        } else {
            // Handle the error, if any
            echo "Error: Failed to add the event.";
        }
    }
}

// Fetch categories from the database (same as before)
$categories = fetchCategories($conn);

// Fetch organizers from the database (same as before)
$organizers = fetchOrganizers($conn);
?>

<!-- Rest of your HTML code -->


<!-- HTML form  -->       
  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <title>Register</title>
    <style>
        /* Style for the container table */
        table {
            border-collapse: collapse;
            width: 80%;
            margin: 0 auto; /* Center the table */
        }

        /* Style for table data cells (td) */
        td {
            padding: 20px;
            border: 1px solid #ccc;
        }
    </style>

    
</head>
<body>
    <?php include("header.php"); ?>

<div class= "container">
    <table>
        <tr>
            <td>
                <form action="addtolist.php" method="post">
                    <h2>Single Add</h2>
                    <label for="mobileNumber">Mobile Number:</label>
                    <input type="text" id="mobilenumber" name="mobileNumber" pattern="[0-9]{10}" minlength="10" maxlength="10" required><br><br>

        <label for="fullName">Full Name:</label>
        <input type="text" id="fullName" name="fullName" required><br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br><br>

        <label for="designation">Designation:</label>
        <input type="text" id="designation" name="designation" required><br><br>

        <label for="organization">Organization:</label>
        <input type="text" id="organization" name="organization" required><br><br>


                    <label for="category">Category:</label>
                    <select id="category" name="category">
                        <?php
                        foreach ($categories as $categoryId => $categoryName) {
                            echo "<option value='$categoryId'>$categoryName</option>";
                        }
                        ?>
                    </select><br><br>

                    <input type="submit" name="Submit" value="Register" class="blue lighten-6 waves-effect waves-light btn">
                </form>
                <script>
                    // Function to display the success modal
                       function showSuccessModal(message) {
                       var modal = document.getElementById("successModal");
                       var successMessage = document.getElementById("successMessage");

                    // Set the success message in the modal
                    successMessage.textContent = message;

                    // Display the modal
                    modal.style.display = "block";

                    // Close the modal when the user clicks the close button
                    var closeBtn = document.getElementsByClassName("close")[0];
                    closeBtn.onclick = function () {
                    modal.style.display = "none";
                    };

                    // Close the modal when the user clicks outside the modal
                    window.onclick = function (event) {
                    if (event.target == modal) {
                    modal.style.display = "none";
                    }
                    };
                    }

                     // Check if the successMessage variable is set, and if so, display the success modal
                    if (typeof successMessage !== "undefined") {
                    showSuccessModal(successMessage);
                    }
                    </script>

            </td>
            <td class="event-list">
                <form id="eventForm" action="addtolist.php" method="post" enctype="multipart/form-data">


                    <h2>Bulk Add</h2>

                    <!-- Button upload CSV data -->
                    <input type="file" name="csvFile" accept=".csv" required ><br><br>

                    <label for="eventName">Event Name:</label>
                    <input type="text" id="eventName" name="eventName" required><br><br>



                    <!-- Button to open the date picker -->
                    

                    <label for="eventDate">Event Date:</label>
                    <input type="text" id="eventDate" name="eventDate" placeholder="YYYY-MM-DD" required><br><br>

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

                    <label for="category2">Category:</label>
                    <select id="category2" name="category">
                    <?php
                    foreach ($categories as $categoryId => $categoryName) {
                        echo "<option value='$categoryId'>$categoryName</option>";
                    }
                    ?>
                    </select><br><br>

                    <label for="organizer2">Organizer:</label>
                    <select id="organizer2" name="organizer">
                    <?php
                    foreach ($organizers as $organizerId => $organizerName) {
                        echo "<option value='$organizerId'>$organizerName</option>";
                    }
                    ?>
                    </select><br><br>

                    <input type="submit" name="AddSubscribers" value="Add Subscribers" class="blue lighten-6 waves-effect waves-light btn"><br><br>
                </form>
                
            </td>
        </tr>
    </table>
                </div>
</body>
</html>