<?php
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

// Include the database connection file
require_once "db.php"; // Ensure this file initializes the $conn variable for database connection

// Fetch the list of organizers from the "organizers" table
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get the form inputs
    $username = $_POST['username'];
    $password = $_POST['password'];
    $organizerId = $_POST['organizer'];

    // Validate inputs
    if (empty($username) || empty($password) || empty($organizerId)) {
        echo "All fields are required.";
        exit;
    }

    try {
        // Hash the password for security
        // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Start a transaction to ensure atomicity
        $conn->begin_transaction();

        // Insert into login table
        $stmt = $conn->prepare("INSERT INTO login (username, password, role) VALUES (?, ?, 'user')");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $userId = $conn->insert_id; // Get the inserted user's ID

        // Insert into user_organizers table
        if (is_array($organizerId)) {
            foreach ($organizerId as $orgId) {
                $stmt = $conn->prepare("INSERT INTO user_organizers (user_id, organizer_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $orgId);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO user_organizers (user_id, organizer_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $organizerId);
            $stmt->execute();
        }

        // Commit the transaction
        $conn->commit();

        echo "User and organizers successfully created.";
    } catch (Exception $e) {
        // Rollback the transaction in case of an error
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <style>
  /* Change the background color of selected options */
  .select2-selection__choice {
    background-color: #f9e79f !important;  /* Example: Green background */
    color: black !important;  /* White text */
}

/* Optionally, change the hover color of selected options */
.select2-selection__choice:hover {
    background-color: #f9e79f !important;  /* Darker green on hover */
}

/* Change the border color for the selected items */
.select2-selection__choice {
    border: 1px solid #f9e79f !important;  /* Green border */
}

/* Change the background color of the selected option in the dropdown */
.select2-results__option[aria-selected="true"] {
    background-color: #f9e79f !important;  /* Example: Green background */
    color: black !important;  /* White text */
}

/* Change the background color of the hovered option */
.select2-results__option {
    background-color:rgb(243, 243, 243) !important;  /* Darker green on hover */
    color: black !important;  /* White text on hover */
}

/* Change the background color of the hovered option */
.select2-results__option:hover {
    background-color: #f9e79f !important;  /* Darker green on hover */
    color: black !important;  /* White text on hover */
}
/* Style for the clear icon in Select2 */
.select2-selection__clear {
    color: black !important; /* Change to black or any visible color */
    font-size: 18px;         /* Adjust size if needed */
    cursor: pointer;         /* Ensure it appears clickable */
}
/* Target the cross (close) button inside the selected options */
.select2-selection__choice__remove {
    color: black !important;  /* Set cross button to black */
    font-weight: bold;        /* Make it stand out */
    cursor: pointer;          /* Ensure it looks clickable */
    margin-right: 5px;        /* Adjust spacing for better alignment */
}

/* Add hover effect for the cross button */
.select2-selection__choice__remove:hover {
    color: red !important;    /* Optional: Cross turns red on hover */
}

.multi-select-container {
        position: relative;
        width: 100%;
    }

    .multi-select-container select {
        width: 100%;
        padding: 8px;
        font-size: 14px;
        border-radius: 8px;
        border: 1px solid #ccc;
        height: 150px;
        display: block;
    }

    .multi-select-container .option-wrapper {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
        padding: 5px;
        background-color: #f9e79f;
        border-radius: 8px;
    }

    .multi-select-container .option-wrapper button {
        margin-left: 10px;
    }

    .form-container {
        width: 50%;
        margin: 0 auto;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }


label {
    font-weight: bold;
    margin-bottom: 5px;
}

.btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
}

.btn:hover {
    background-color: #0056b3;
}

    </style>
</head>
<body>
<?php include("header.php"); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">

        <form class="mt-3" action="createUsers.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
        <label for="organizer" class="form-label">Allow access for (Select multiple):</label>
        <div id="organizer-container" class="multi-select-container">
            <select id="organizer" name="organizer[]" class="form-control" multiple="multiple">
                <?php
                // Loop through the organizer list and display options
                foreach ($organizerList as $org) {
                    echo "<option value='" . $org["organizer_id"] . "'>" . $org["organizer_name"] . "</option>";
                }
                ?>
            </select>
            <div id="organizer-actions">
                <!-- Action buttons will be displayed here dynamically -->
            </div>
        </div>
    </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
</div>
</div>
</div>

<!-- Link to jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Link to Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
    // Add selected organizer options with buttons beside them
    document.getElementById('organizer').addEventListener('change', function() {
        const selectedOptions = Array.from(this.selectedOptions);
        const organizerActionsContainer = document.getElementById('organizer-actions');
        organizerActionsContainer.innerHTML = ''; // Clear previous buttons

        selectedOptions.forEach(function(option) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('option-wrapper');

            const organizerName = option.textContent;
            const editableBtn = document.createElement('button');
            editableBtn.textContent = 'Editable';
            editableBtn.classList.add('btn', 'btn-info');
            editableBtn.setAttribute('data-id', option.value);
            editableBtn.addEventListener('click', function() {
                option.disabled = false; // Enable editing
                option.style.backgroundColor = "#ffffff"; // Restore default background color
            });

            const readOnlyBtn = document.createElement('button');
            readOnlyBtn.textContent = 'Read-Only';
            readOnlyBtn.classList.add('btn', 'btn-warning');
            readOnlyBtn.setAttribute('data-id', option.value);
            readOnlyBtn.addEventListener('click', function() {
                option.disabled = true; // Make it read-only
                option.style.backgroundColor = "#f0f0f0"; // Change background color for read-only
            });

            // Append buttons to wrapper
            wrapper.appendChild(document.createTextNode(organizerName));
            wrapper.appendChild(editableBtn);
            wrapper.appendChild(readOnlyBtn);

            // Append wrapper to the container
            organizerActionsContainer.appendChild(wrapper);
        });
    });
</script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for the organizer select input
        $('#organizer').select2({
            placeholder: "Select Organizer",   // Placeholder text for categories
            allowClear: true,           // Option to clear selection
            tags: true,                 // Enable tagging
            width: '100%'               // Ensure it fits the container
        });
    });
</script>


    
</body>
</html>