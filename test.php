<?php 

//db conenction details are stored in this file
// require "conn.inc"; 

// try {
//   //connect to database using username/ password from conn.inc file
//   $db = new PDO("mysql:host=$dbhost;port=$port;dbname=$database", $user, $password);
//   echo "<h2>SQL Connection Check</h2><ol>"; 
//   //run a sql query to fetch all rows from the table specified in conn.inc
//   foreach($db->query("SELECT content, location FROM $table") as $row) {
//     echo "<li>" . $row['content'] . " <-----> ". $row['location']  ." </li>";
//   }
//   echo "</ol>";
// } catch (PDOException $e) {
//     print "Error!: " . $e->getMessage() . "<br/>";
//     die();
// }
?>

<?php
session_start(); // Start a session
require_once "db.php"; // Include the database connection file

// Function to fetch events from the database
function fetchEvents($conn)
{
    $sql = "SELECT * FROM events"; // table name "events"
    $result = $conn->query($sql);

    if (!$result) {
        die("Error fetching events: " . $conn->error);
    }

    $events = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[$row["event_id"]] = $row;
        }
    }

    return $events;
}

function insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id = null, $category_id = null, $organizer_id = null)
{
    // Define the SQL statement with placeholders
    if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
        $sql = "INSERT INTO subscribers (full_name, mobile_number, email, designation, organization, number_of_events_attended, event_id, category_id, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else {
        $sql = "INSERT INTO subscribers (full_name, mobile_number, email, designation, organization, number_of_events_attended) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters based on whether event details are provided or not
    if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
        $stmt->bind_param("sssssiiii", $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id);
    } else {
        $stmt->bind_param("sssss", $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended);
    }

    if ($stmt->execute()) {
        return true;
    } else {
        die("Execute failed: " . $stmt->error);
    }
}



// // Function to insert data into the database
// function insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id = null, $category_id = null, $organizer_id = null)
// {
//     // Define the SQL statement with placeholders
//     if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
//         $sql = "INSERT INTO subscribers (full_name, mobile_number, email, designation, organization, number_of_events_attended, event_id, category_id, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
//     } else {
//         $sql = "INSERT INTO subscribers (full_name, mobile_number, email, designation, organization, number_of_events_attended) VALUES (?, ?, ?, ?, ?, ?)";
//     }

//     $stmt = $conn->prepare($sql);

//     if (!$stmt) {
//         die("Prepare failed: " . $conn->error);
//     }

//     // Bind parameters based on whether event details are provided or not
//     if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
//         $stmt->bind_param("sssssi", $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended);
//     } else {
//         $stmt->bind_param("sssssiiii", $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id);
//     }

//     if ($stmt->execute()) {
//         return true;
//     } else {
//         die("Execute failed: " . $stmt->error);
//     }
// }

function activateEvent($conn)
{
    // Get the current date in the same format as event dates (Y-m-d)
    $currentDate = date("Y-m-d");

    $sql = "SELECT * FROM events WHERE date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $events = array(); // Initialize an array to store multiple events
        while ($row = $result->fetch_assoc()) {
            $events[$row["event_id"]] = $row;
        }
        $_SESSION["activatedEvents"] = $events; // Store the activated events in the session
        return null; // Return null since multiple events are available
    } else {
        unset($_SESSION["activatedEvents"]); // No event found for today, remove the activated events from the session
        return null;
    }
}

// Initialize activatedEvent to avoid undefined array key error
$activatedEvent = isset($_SESSION["activatedEvent"]) ? $_SESSION["activatedEvent"] : null;

// Activate an event for the current date and get its event_id
$event_id = activateEvent($conn);

// Check if there are multiple events available
$multipleEventsAvailable = isset($_SESSION["activatedEvents"]) && count($_SESSION["activatedEvents"]) > 1;

// Fetch events for the dropdown if multiple events are available
if ($multipleEventsAvailable) {
    $activatedEvents = $_SESSION["activatedEvents"];
}

// Fetch events from the database
$events = fetchEvents($conn);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mobileNumber = $_POST["mobileNumber"];
    $fullName = $_POST["fullName"];
    $email = $_POST["email"];
    $designation = $_POST["designation"];
    $organization = $_POST["organization"];
    $number_of_events_attended = 1;

    if ($multipleEventsAvailable) {
        // If multiple events are available, check which event was selected
        $selectedEvent = $_POST["selectedEvent"];
        if (empty($selectedEvent)) {
            // No event selected, show an error message
            echo "Error: Please select an event to register for.";
        } else {
            // Fetch event details based on the selected event
            $event = $_SESSION["activatedEvents"][$selectedEvent];
            $event_id = $event["event_id"];
            $category_id = $event["category_id"];
            $organizer_id = $event["organizer_id"];

            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id)) {
                // Data inserted successfully, redirect to the subscribers page with full name and event name as parameters
                $event_name = $event["eventname"];
                header("Location: registration_success.php?name=" . urlencode($fullName) . "&event=" . urlencode($event_name));
                exit();
            } else {
                // Handle the error, if any
                echo "Error: Failed to insert data into the database.";
            }
        }
    } else {
        // If there is only one activated event, use the previous logic for a single event
        if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended, $event_id, $category_id, $organizer_id)) {
                // Data inserted successfully, redirect to the subscribers page with full name as a parameter
                $event_name = $event["eventname"];
                header("Location: registration_success.php?name=" . urlencode($fullName) . "&event=" . urlencode($event_name));
                exit();
            } else {
                // Handle the error, if any
                echo "Error: Failed to insert data into the database.";
            }
        } else {
            // If there are no active events, simply insert the user's data without specifying an event
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $number_of_events_attended)) {
                // Data inserted successfully, redirect to the subscribers page with full name as a parameter
                header("Location: registration_success.php?name=" . urlencode($fullName));
                exit();
            } else {
                // Handle the error, if any
                echo "Error: Failed to insert data into the database.";
            }
        }
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        /* Define a CSS class to highlight the input box with red border */
        .error-input {
            border: 1px solid red;
        }
    </style>
  
      <!-- Add your CSS and other meta tags here -->
  
       <!-- Compiled and minified CSS -->
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">

<!-- Compiled and minified JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<script type="text/javascript"> 
  document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('.sidenav');
    var instances = M.Sidenav.init(elems);
  });
</script>
</head>
<body>
<h2>Register</h2>

<div id="eventDetails">
<?php
if ($multipleEventsAvailable) {
    // Display the activated event name and date
    if (isset($selectedEvent) && isset($selectedEvent["eventname"]) && isset($selectedEvent["date"])) {
        echo "<p>" . $selectedEvent["eventname"] . "</p>";
        echo "<p>" . $selectedEvent["date"] . "</p>";
    }
}
?>
</div>

    <?php
if ($activatedEvent && !$multipleEventsAvailable) {
    // Display the activated event name and date for a single activated event
    echo "<p>" . $activatedEvent["eventname"] . "</p>";
    echo "<p>" . $activatedEvent["date"] . "</p>";


    } else {
        // Display a message if no event is activated for today show nothing
        
    }
    ?>
     <div class="container">
    <form action="index.php" method="post">

    <?php if ($multipleEventsAvailable) : ?>
        <label for="selectedEvent">Select Today's Event:</label>
        <select id="selectedEvent" name="selectedEvent" required>
            <option value="">Select an event</option>
            <?php foreach ($activatedEvents as $event) : ?>
                <option value="<?php echo $event['event_id']; ?>">
                    <?php echo $event['eventname']; ?> - <?php echo $event['date']; ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>
    <?php endif; ?>


    <label for="mobileNumber">Mobile Number:</label>
    <input type="text" id="mobilenumber" name="mobileNumber" pattern="[0-9]{10}" minlength="10" maxlength="10" required  class="validate">
    <!-- Add a span element for the error message -->
    <span id="mobileError" style="color: red;"></span><br><br>

    <label for="fullName">Full Name:</label>
    <input type="text" id="fullName" name="fullName" required class="validate"><br><br>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required  class="validate"><br><br>

    <label for="designation">Designation:</label>
    <input type="text" id="designation" name="designation" required class="validate"><br><br>

    <label for="organization">Organization:</label>
    <input type="text" id="organization" name="organization" required class="validate"><br><br>



    <input type="submit" value="Register" class="waves-effect blue waves-light btn">
</form>

<script>
    function validateForm() {
        // Get the mobile number input and error message span
        var mobileNumberInput = document.getElementById("mobilenumber");
        var mobileError = document.getElementById("mobileError");

        // Get the entered mobile number
        var mobileNumber = mobileNumberInput.value;

        // Check if the mobile number has exactly 10 digits
        if (mobileNumber.length !== 10 || isNaN(mobileNumber)) {
            mobileError.textContent = "Mobile number must be 10 digits";
            mobileNumberInput.classList.add("error-input"); // Add the error class to highlight the input
            return false; // Prevent form submission
        } else {
            // Reset error message and input style
            mobileError.textContent = "";
            mobileNumberInput.classList.remove("error-input"); // Remove the error class
            return true; // Allow form submission
        }
    }
</script>

</div>

<!-- ... Rest of your HTML ... -->

<script>
    // Function to update event details based on the selected event
    function updateEventDetails() {
        var selectedEventId = document.getElementById("selectedEvent").value;
        var eventDetailsDiv = document.getElementById("eventDetails");

        if (selectedEventId && selectedEventId in <?php echo json_encode($activatedEvents); ?>) {
            // Event found in the activatedEvents array
            var selectedEvent = <?php echo json_encode($activatedEvents); ?>[selectedEventId];
            eventDetailsDiv.innerHTML = "<p>" + selectedEvent["eventname"] + "</p><p>" + selectedEvent["date"] + "</p>";
        } else {
            // Event not found, display a message or clear the details
            eventDetailsDiv.innerHTML = "";
        }
    }

    // Add an event listener to the select dropdown
    var selectedEventDropdown = document.getElementById("selectedEvent");
    selectedEventDropdown.addEventListener("change", updateEventDetails);

    // Initial call to set event details when the page loads
    updateEventDetails();
</script>
  </div>
</body>
</html>
