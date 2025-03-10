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
    $conn, $fullName, $mobileNumbers, $emails, $designations, $organizations, $address,
    $number_of_events_attended, $event_id = null, $category_id = null, $organizer_id = null
) {
    // Ensure arrays for inputs
    $mobileNumbers = is_array($mobileNumbers) ? $mobileNumbers : [$mobileNumbers];
    $emails = is_array($emails) ? $emails : [$emails];
    $designations = is_array($designations) ? $designations : [$designations];
    $organizations = is_array($organizations) ? $organizations : [$organizations];

    // Sanitize emails: trim spaces and convert to lowercase
    $emails = array_map(function($email) {
        return strtolower(trim($email));
    }, $emails);

    // Initialize subscriber ID
    $subscriberId = null;

    // Create new subscriber without checking for existing phone or email
    $sqlSubscriber = "INSERT INTO subscribers (full_name, address, number_of_events_attended) VALUES (?, ?, ?)";
    $stmtSubscriber = $conn->prepare($sqlSubscriber);
    if (!$stmtSubscriber) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmtSubscriber->bind_param("ssi", $fullName, $address, $number_of_events_attended);
    if (!$stmtSubscriber->execute()) {
        error_log("Execute failed: " . $stmtSubscriber->error);
        return false;
    }
    $subscriberId = $stmtSubscriber->insert_id;
    $stmtSubscriber->close();
    error_log("New subscriber created with ID: $subscriberId");

    // Insert phone numbers
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

    // Insert sanitized emails
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

    // Insert designations and organizations into designation_organization table
$sqlDesignationOrganization = "INSERT INTO designation_organization (subscriber_id, designation, organization) VALUES (?, ?, ?)";
$stmtDesignationOrganization = $conn->prepare($sqlDesignationOrganization);
if (!$stmtDesignationOrganization) {
    error_log("Prepare failed: " . $conn->error);
    return false;
}

// Loop through designations and organizations arrays
for ($i = 0; $i < max(count($designations), count($organizations)); $i++) {
    $designation = $designations[$i] ?? null; // Preserve original case for designation
    $organization = $organizations[$i] ?? null; // Preserve original case for organization

    $stmtDesignationOrganization->bind_param("iss", $subscriberId, $designation, $organization);
    if (!$stmtDesignationOrganization->execute()) {
        error_log("Execute failed: " . $stmtDesignationOrganization->error);
        return false;
    }
}
$stmtDesignationOrganization->close();


    // Insert into event_subscriber_mapping
    // Debugging logs before insertion
error_log("Inserting mapping: Subscriber ID: $subscriberId, Event ID: " . ($event_id ?? 'NULL') . ", Organizer ID: " . ($organizer_id ?? 'NULL'));

 // Check if event_id and organizer_id are provided; if not, fetch organizer_id from the events table
 if ($event_id !== null) {
    // Fetch organizer_id from the events table
    $sqlEvent = "SELECT organizer_id FROM events WHERE event_id = ?";
    $stmtEvent = $conn->prepare($sqlEvent);
    if (!$stmtEvent) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmtEvent->bind_param("i", $event_id);
    $stmtEvent->execute();
    $stmtEvent->bind_result($fetchedOrganizerId);
    if ($stmtEvent->fetch()) {
        $organizer_id = $fetchedOrganizerId; // Set the fetched organizer_id
    }
    $stmtEvent->close();
}
if ($event_id !== null && $organizer_id !== null) {
    $sqlMapping = "INSERT INTO event_subscriber_mapping (subscriber_id, event_id, organizer_id) VALUES (?, ?, ?)";
    $stmtMapping = $conn->prepare($sqlMapping);
    if (!$stmtMapping) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmtMapping->bind_param("iii", $subscriberId, $event_id, $organizer_id);
    if (!$stmtMapping->execute()) {
        error_log("Execute failed: " . $stmtMapping->error);
        return false;
    }
    $stmtMapping->close();
} else {
    error_log("Error: event_id or organizer_id is NULL. Default category assigned.");
    $defaultCategoryId = 6;
    $sqlMapping = "INSERT INTO event_subscriber_mapping (subscriber_id, category_id) VALUES (?, ?)";
    $stmtMapping = $conn->prepare($sqlMapping);
    if (!$stmtMapping) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmtMapping->bind_param("ii", $subscriberId, $defaultCategoryId);
    if (!$stmtMapping->execute()) {
        error_log("Execute failed: " . $stmtMapping->error);
        return false;
    }
    $stmtMapping->close();
}


    return true;
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
    $designation = trim($_POST["designation"]);
    $organization = trim($_POST["organization"]);
    $number_of_events_attended = 1;
    $address = null; // Address set to NULL

    // Default popup message
    $popupMessage = " ";

    // Check if email exists in the database
    $sqlCheckEmail = "SELECT hidden, unsubscribed FROM emails WHERE email = ?";
    $stmtCheckEmail = $conn->prepare($sqlCheckEmail);
    $stmtCheckEmail->bind_param("s", $email);
    $stmtCheckEmail->execute();
    $stmtCheckEmail->store_result();
    
    if ($stmtCheckEmail->num_rows > 0) {
        $stmtCheckEmail->bind_result($hidden, $unsubscribed);
        $stmtCheckEmail->fetch();
        
        if ($hidden == 1) {
            $_SESSION["popup_message"] = "Your email is full! Please empty your email.";
            $popupMessage = "Your email is full! Please empty your email.";
        } elseif ($unsubscribed == 1) {
            $_SESSION["popup_message"] = "You have unsubscribed from our mailing list! Do you want to subscribe to it again?";
            $popupMessage = "You have unsubscribed from our mailing list! Do you want to subscribe to it again?";
        }
    }
    $stmtCheckEmail->close();

    // Proceed with registration if email is not hidden or unsubscribed
    if ($multipleEventsAvailable) {
        $selectedEvent = $_POST["selectedEvent"];
        if (empty($selectedEvent)) {
            echo "Error: Please select an event to register for.";
        } else {
            $event = $_SESSION["activatedEvents"][$selectedEvent];
            $event_id = $event["event_id"];
            $organizer_id = $event["organizer_id"];
    
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended, $event_id, $organizer_id)) {
                $event_name = $event["eventname"];
                $redirectUrl = "registration_success.php?name=" . urlencode($fullName) 
                               . "&event=" . urlencode($event_name)
                               . "&message=" . urlencode($popupMessage)
                               . "&email=" . urlencode($email);  // Add email parameter here                
                header("Location: $redirectUrl");
                exit();
            } else {
                echo "Error: Failed to insert data into the database.";
            }
        }
    } else {
        if ($activatedEvents !== null && count($activatedEvents) === 1) {
            $activatedEvent = reset($activatedEvents);
            $event_id = $activatedEvent["event_id"];
            $organizer_id = $activatedEvent["organizer_id"];
        
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended, $event_id, $organizer_id)) {
                $event_name = $activatedEvent["eventname"];
                $redirectUrl = "registration_success.php?name=" . urlencode($fullName) 
                               . "&event=" . urlencode($event_name)
                               . "&message=" . urlencode($popupMessage)
                               . "&email=" . urlencode($email);  // Add email parameter here
                
                header("Location: $redirectUrl");
                exit();
            } else {
                echo "Error: Failed to insert data into the database.";
            }
        }
         else {
            if (insertData($conn, $fullName, $mobileNumber, $email, $designation, $organization, $address, $number_of_events_attended)) {
                $redirectUrl = "registration_success.php?name=" . urlencode($fullName) 
                               . "&event=" . urlencode($event_name)
                               . "&message=" . urlencode($popupMessage)
                               . "&email=" . urlencode($email);  // Add email parameter here
                
                header("Location: $redirectUrl");
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
        <?php foreach ($activatedEvents as $event): ?>
            <h2 class="text-center"><?php echo htmlspecialchars($event['event_name']); ?></h2>
            <h4 class="text-center">
                <?php 
                    $date = new DateTime($event['event_date']); 
                    echo $date->format('j F Y'); // Output: "26 February 2025"

                    // Fetch organizer name based on organizer_id using MySQLi
                    $organizerId = $event['organizer_id'];
                    $query = "SELECT organizer_name FROM organizers WHERE organizer_id = '$organizerId'";
                    $result = $conn->query($query);

                    if ($result && $result->num_rows > 0) {
                        $organizer = $result->fetch_assoc();
                        echo  " | " .  "Organized by " . htmlspecialchars($organizer['organizer_name']);
                    } else {
                        echo " / Organizer not found";
                    }
                ?>
            </h4>
        <?php endforeach; ?>
    </div>
<?php elseif (empty($activatedEvent)): ?>



    
<?php endif; ?>


    <?php if ($multipleEventsAvailable): ?>
        <label for="selectedEvent" style="font-size: 18px;">Select Today's Event:</label>
        <select id="selectedEvent" name="selectedEvent" class="form-control" required>
            <option value="">Select an event</option>
            <?php foreach ($activatedEvents as $event): ?>
                <option value="<?php echo $event['event_id']; ?>">
                    <?php echo $event['event_name']; ?>
                </option>
            <?php endforeach; ?>
        </select>
            </div><br>
    <?php endif; ?>


     
    <div class="form-group">
    <label for="mobileNumber" style="font-size: 18px;">Mobile Number</label>

    <!-- Add oninput attribute to trigger the AJAX request -->
    <input type="text" id="mobileNumber" name="mobileNumber" pattern="[0-9]{10}" minlength="10" maxlength="10" 
           class="form-control" required oninput="checkPhoneNumber()">
    <small id="mobileError" class="form-text text-danger"></small><br>
</div>

<div id="additionalFields">
    <div class="form-group">
        <label for="fullName" style="font-size: 18px;">Full Name</label>
        <input type="text" id="fullName" name="fullName" class="form-control" value="" disabled><br>
    </div>
    <div class="form-group">
        <label for="email" style="font-size: 18px;">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="" disabled><br>
    </div>
    <div class="form-group">
        <label for="designation" style="font-size: 18px;">Designation</label>
        <input type="text" id="designation" name="designation" class="form-control" value="" disabled><br>
    </div>
    <div class="form-group">
        <label for="organization" style="font-size: 18px;">Organization</label>
        <input type="text" id="organization" name="organization" class="form-control" value="" disabled><br>
    </div>
</div>

<div class="text-center">
    <button type="submit" class="btn btn-outline-success">Submit</button>
</div>

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
            <label for="fullName" style="font-size: 18px;">Full Name</label>
            <input type="text" id="fullName" name="fullName" class="form-control" required value="${user.full_name || ''}"><br>
        </div>
        <div class="form-group">
            <label for="email" style="font-size: 18px;">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="${user.email || ''}"><br>
        </div>
        <div class="form-group">
            <label for="designation" style="font-size: 18px;">Designation</label>
            <input type="text" id="designation" name="designation" class="form-control" value="${user.designation || ''}"><br>
        </div>
        <div class="form-group">
            <label for="organization" style="font-size: 18px;">Organization</label>
            <input type="text" id="organization" name="organization" class="form-control" value="${user.organization || ''}"><br>
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
            <input type="email" id="email" name="email" class="form-control" value=""><br><br>
        </div>
        <div class="form-group">
            <label for="designation">Designation:</label>
            <input type="text" id="designation" name="designation" class="form-control" value=""><br><br>
        </div>
        <div class="form-group">
            <label for="organization">Organization:</label>
            <input type="text" id="organization" name="organization" class="form-control" value=""><br><br>
        </div>
    `;

    // Show the additional fields container
    document.getElementById("additionalFields");
}

</script>


</body>
</html>


