<?php
session_start(); // Start a session
require_once "db.php"; // Include the database connection file

// Set the time zone to Nepal/Kathmandu
date_default_timezone_set("Asia/Kathmandu");

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

function insertData(
    $conn, $fullName, $mobileNumbers, $emails, $designation, $organization, $address,
    $number_of_events_attended, $event_id = null, $category_id = null, $organizer_id = null
) {

    // Ensure $mobileNumbers is an array
    if (!is_array($mobileNumbers)) {
        $mobileNumbers = [$mobileNumbers]; // Wrap the string into an array
    }

    // Convert emails to lowercase
    if (!is_array($emails)) {
        $emails = [$emails]; // Wrap the string into an array
    }
    $emails = array_map('strtolower', $emails);

    // Initialize subscriber ID
    $subscriberId = null;

    // Use WHERE IN clause to check if any of the provided phone numbers already exist in the database
    $placeholders = implode(',', array_fill(0, count($mobileNumbers), '?'));
    $sqlCheckPhone = "SELECT subscriber_id FROM phone_numbers WHERE phone_number IN ($placeholders)";
    $stmtCheckPhone = $conn->prepare($sqlCheckPhone);
    if (!$stmtCheckPhone) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    // Bind parameters dynamically
    $types = str_repeat('s', count($mobileNumbers));
    $stmtCheckPhone->bind_param($types, ...$mobileNumbers);
    $stmtCheckPhone->execute();
    $result = $stmtCheckPhone->get_result();

    // Check if a matching phone number exists and fetch the subscriber ID
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $subscriberId = $row['subscriber_id'];
        error_log("Matching phone number found. Using existing subscriber ID: $subscriberId");
    } else {
        error_log("No matching phone number found. Creating a new subscriber.");
    }
    $stmtCheckPhone->close();

    // If no existing subscriber ID was found, create a new one
    if ($subscriberId === null) {
        $sqlSubscriber = "INSERT INTO subscribers (full_name, designation, organization, address, number_of_events_attended) VALUES (?, ?, ?, ?, ?)";
        $stmtSubscriber = $conn->prepare($sqlSubscriber);
        if (!$stmtSubscriber) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        $stmtSubscriber->bind_param("ssssi", $fullName, $designation, $organization, $address, $number_of_events_attended);
        if (!$stmtSubscriber->execute()) {
            error_log("Execute failed: " . $stmtSubscriber->error);
            return false;
        }
        $subscriberId = $stmtSubscriber->insert_id;
        $stmtSubscriber->close();
        error_log("New subscriber created with ID: $subscriberId");
    }

    // Insert phone numbers for the found or newly created subscriber ID
    $sqlPhone = "INSERT INTO phone_numbers (subscriber_id, phone_number) VALUES (?, ?)";
    $stmtPhone = $conn->prepare($sqlPhone);
    if (!$stmtPhone) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    foreach ($mobileNumbers as $phoneNumber) {
        $stmtPhone->bind_param("is", $subscriberId, $phoneNumber);
        if (!$stmtPhone->execute()) {
            error_log("Execute failed: " . $stmtPhone->error);
            return false;
        }
    }
    $stmtPhone->close();

    // Insert emails for the found or newly created subscriber ID
    $sqlEmail = "INSERT INTO emails (subscriber_id, email) VALUES (?, ?)";

    $stmtEmail = $conn->prepare($sqlEmail);
    if (!$stmtEmail) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    foreach ($emails as $email) {
        $stmtEmail->bind_param("is", $subscriberId, $email);
        if (!$stmtEmail->execute()) {
            error_log("Execute failed: " . $stmtEmail->error);
            return false;
        }
    }
    $stmtEmail->close();

    // If event details are provided, insert into the event_subscriber_mapping table
    if ($event_id !== null && $category_id !== null && $organizer_id !== null) {
        $sqlMapping = "INSERT INTO event_subscriber_mapping (subscriber_id, event_id, organizer_id, category_id) VALUES (?, ?, ?, ?)";
        $stmtMapping = $conn->prepare($sqlMapping);
        if (!$stmtMapping) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        $stmtMapping->bind_param("iiii", $subscriberId, $event_id, $organizer_id, $category_id);
        if (!$stmtMapping->execute()) {
            error_log("Execute failed: " . $stmtMapping->error);
            return false;
        }
        $stmtMapping->close();
    }

    return true; // Return true indicating successful insertion
}


function activateEvent($conn)
{
    // Get the current date in the same format as event dates (Y-m-d)
    $currentDate = date("Y-m-d");
    
    $sql = "SELECT * FROM events WHERE event_date = ?";
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

// Fetch events from the database
$events = fetchEvents($conn);

// Initialize activatedEvent to avoid undefined array key error
$activatedEvent = null;

// Activate an event for the current date and get its event_id
$event_id = activateEvent($conn);

// Check if there are multiple events available
$multipleEventsAvailable = isset($_SESSION["activatedEvents"]) && count($_SESSION["activatedEvents"]) > 1;

// Fetch events for the dropdown if multiple events are available
if ($multipleEventsAvailable) {
    $activatedEvents = $_SESSION["activatedEvents"];
    $activatedEvent = null; // Initialize activatedEvent variable
} else {
    // If there's only one activated event or none, set the activatedEvent variable
    $activatedEvents = isset($_SESSION["activatedEvents"]) ? $_SESSION["activatedEvents"] : null;

    if ($event_id !== null) {
        $activatedEvent = $events[$event_id]; // Set $activatedEvent when a single event is activated

        // You need to determine $category_id and $organizer_id based on your application logic.
        $category_id = null; // Replace with the actual category ID
        $organizer_id = null; // Replace with the actual organizer ID
    } else {
        // Handle the case when there's no activated event
        $activatedEvent = null; // Define $activatedEvent as null or any default value
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mobileNumber = $_POST["mobileNumber"];
    $fullName = ucwords(strtolower(trim($_POST["fullName"])));
    $email = strtolower(trim($_POST["email"]));
    $designation = ucwords(strtolower(trim($_POST["designation"])));
    $organization = ucwords(strtolower(trim($_POST["organization"])));
    $number_of_events_attended = 1;

    // Use trim to remove leading and trailing spaces from the full name
    $fullName = trim($fullName);
    $address = null; // Set the address to NULL

    if ($multipleEventsAvailable) {
        $selectedEvent = $_POST["selectedEvent"];
        if (empty($selectedEvent)) {
            echo "Error: Please select an event to register for.";
        } else {
            $event = $_SESSION["activatedEvents"][$selectedEvent];
            $event_id = $event["event_id"];
            $category_id = $event["category_id"];
            $organizer_id = $event["organizer_id"];

            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended, $event_id, $category_id, $organizer_id)) {
                $event_name = $event["eventname"];
                header("Location: registration_success.php?name=" . urlencode($fullName) . "&event=" . urlencode($event_name));
                exit();
            } else {
                echo "Error: Failed to insert data into the database.";
            }
        }
    } else {
        if ($activatedEvents !== null && count($activatedEvents) === 1) {
            $activatedEvent = reset($activatedEvents);
            $event_id = $activatedEvent["event_id"];
            $category_id = $activatedEvent["category_id"];
            $organizer_id = $activatedEvent["organizer_id"];

            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended, $event_id, $category_id, $organizer_id)) {
                $event_name = $activatedEvent["eventname"];
                header("Location: registration_success.php?name=" . urlencode($fullName) . "&event=" . urlencode($event_name));
                exit();
            } else {
                echo "Error: Failed to insert data into the database.";
            }
        } else {
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended)) {
                header("Location: registration_success.php?name=" . urlencode($fullName));
                exit();
            } else {
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

    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-size: 13px; /* You can adjust the value to your preferred font size */
        }

        /* Add other style rules as needed */
    </style>
</head>
<body>


<div class="container mt-5">
    <div class="row justify-content-center align-items-center">
        <div class="col-md-6">
        <?php
                // Dynamically set the heading based on conditions
                $headingText = (empty($activatedEvents)) ? 'Subscribe to our mailing list' : 'Register';
                echo '<h2 class="text-center">' . $headingText . '</h2>';
                ?>


<form action="index.php" method="post" onsubmit="return validateForm()" id="registrationForm">
<div class="form-group">

<?php if ($activatedEvents !== null && count($activatedEvents) === 1): ?>
    <div class="alert alert-info" role="alert">
<?php
        // Display the activated event names and dates for multiple activated events
        foreach ($activatedEvents as $event) {
            echo "Today's Event: " . $event['event_name'] . "<br>";
            echo  " Date: " . $event['event_date'] . "<br>";
        }
        ?>
    </div>
<?php elseif (empty($activatedEvent)): ?>
    
<?php endif; ?>


    <?php if ($multipleEventsAvailable): ?>
        <label for="selectedEvent">Select Today's Event:</label>
        <select id="selectedEvent" name="selectedEvent" class="form-control" required>
            <option value="">Select an event</option>
            <?php foreach ($activatedEvents as $event): ?>
                <option value="<?php echo $event['event_id']; ?>">
                    <?php echo $event['event_name']; ?>
                </option>
            <?php endforeach; ?>
        </select>
            </div>
    <?php endif; ?>

    <br><br>

     
    <div class="form-group">
    <label for="mobileNumber">Mobile Number:</label>
    <!-- Add oninput attribute to trigger the AJAX request -->
    <input type="text" id="mobileNumber" name="mobileNumber" pattern="[0-9]{10}" minlength="10" maxlength="10" 
           class="form-control" required oninput="checkPhoneNumber()">
    <small id="mobileError" class="form-text text-danger"></small><br><br>
</div>

<div id="additionalFields">
    <div class="form-group">
        <label for="fullName">Full Name:</label>
        <input type="text" id="fullName" name="fullName" class="form-control" value="" disabled><br><br>
    </div>
    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" class="form-control" value="" disabled><br><br>
    </div>
    <div class="form-group">
        <label for="designation">Designation:</label>
        <input type="text" id="designation" name="designation" class="form-control" value="" disabled><br><br>
    </div>
    <div class="form-group">
        <label for="organization">Organization:</label>
        <input type="text" id="organization" name="organization" class="form-control" value="" disabled><br><br>
    </div>
</div>

<button type="submit" class="btn btn-outline-success" value="Register">Submit</button>
<script>
    function validateForm() {
        // Get the mobile number input and error message span
        var mobileNumberInput = document.getElementById("mobileNumber");
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



<script>
function checkPhoneNumber() {
    // Get the mobile number input
    var mobileNumberInput = document.getElementById("mobileNumber");
    var mobileNumber = mobileNumberInput.value;

    // Check if the mobile number has exactly 10 digits
    if (mobileNumber.length === 10 && !isNaN(mobileNumber)) {
        // Enable the additional form fields
        enableAdditionalFields();

        // Perform AJAX request
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);

                if (response.success) {
                    // User found, populate the form
                    populateForm(response.user);
                } else {
                    // User not found, clear the form
                    clearForm();
                }
            }
        };
        xhr.open('POST', 'check_phone_number.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('mobileNumber=' + mobileNumber);
    } else {
        // Clear the form if the entered mobile number is not valid
        clearForm();

        // Disable the additional form fields
        disableAdditionalFields();
    }
}

function enableAdditionalFields() {
    // Enable the additional form fields
    document.getElementById("fullName").disabled = false;
    document.getElementById("email").disabled = false;
    document.getElementById("designation").disabled = false;
    document.getElementById("organization").disabled = false;
}

function disableAdditionalFields() {
    // Disable the additional form fields
    document.getElementById("fullName").disabled = true;
    document.getElementById("email").disabled = true;
    document.getElementById("designation").disabled = true;
    document.getElementById("organization").disabled = true;
}

function populateForm(user) {
    // Show the additional form fields
    document.getElementById("additionalFields").innerHTML = `
        <div class="form-group">
            <label for="fullName">Full Name:</label>
            <input type="text" id="fullName" name="fullName" class="form-control" required value="${user.full_name || ''}"><br><br>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" class="form-control" required value="${user.email || ''}"><br><br>
        </div>
        <div class="form-group">
            <label for="designation">Designation:</label>
            <input type="text" id="designation" name="designation" class="form-control" required value="${user.designation || ''}"><br><br>
        </div>
        <div class="form-group">
            <label for="organization">Organization:</label>
            <input type="text" id="organization" name="organization" class="form-control" required value="${user.organization || ''}"><br><br>
        </div>
    `;

    // Show the additional fields container
    document.getElementById("additionalFields");
}

function clearForm() {
    // Clear the additional form fields with default values
    document.getElementById("additionalFields").innerHTML = `
        <div class="form-group">
            <label for="fullName">Full Name:</label>
            <input type="text" id="fullName" name="fullName" class="form-control" required value=""><br><br>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" class="form-control" required value=""><br><br>
        </div>
        <div class="form-group">
            <label for="designation">Designation:</label>
            <input type="text" id="designation" name="designation" class="form-control" required value=""><br><br>
        </div>
        <div class="form-group">
            <label for="organization">Organization:</label>
            <input type="text" id="organization" name="organization" class="form-control" required value=""><br><br>
        </div>
    `;

    // Show the additional fields container
    document.getElementById("additionalFields");
}

</script>


</body>
</html>


