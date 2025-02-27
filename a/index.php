<?php
require_once "db.php"; // Include the database connection file

// Set session cookie lifetime to 2 hours before starting the session
$sessionLifetime = 3600; // 1 hours in seconds
session_set_cookie_params($sessionLifetime);
session_start();

// Function to check if the session has expired
function checkSessionExpiration() {
    global $sessionLifetime; // Declare $sessionLifetime as a global variable
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionLifetime) {
        // Session has expired, log the user out
        session_unset();
        session_destroy();
        header("Location: index.php"); // Redirect to the login page
        exit();
    } else {
        // Update the last activity time
        $_SESSION['last_activity'] = time();
    }
}

// Check session expiration
checkSessionExpiration();

// Initialize alert variables
$alertMessage = "";
$alertType = "d-none"; // Initially, hide the alert

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Fetch user data from the database with a case-sensitive comparison
    $sql = "SELECT id, username, password, role FROM login WHERE BINARY username = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind the parameters for username and password (only username here for the query)
    $stmt->bind_param("s", $username);  // Only bind username since it's the only parameter in the query

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();  // Fetch the result row
        if ($password == $row["password"]) {  // Compare entered password with the database password
            // Password is correct, create a session and log in the user
            $_SESSION['id'] = $row["id"];
            $_SESSION['username'] = $row["username"];
            
            $_SESSION['role'] = $row["role"]; // Store the role for later use (e.g., access control)
            header("Location: dashboard.php"); // Redirect to a logged-in page
            exit();
        } else {
            // Show an alert for incorrect password
            $alertMessage = "Incorrect password. Please try again.";
            $alertType = "alert-danger";
        }
    } else {
        // Show an alert for no user found
        $alertMessage = "No user found with that username.";
        $alertType = "alert-danger";
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML</title>

    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Mailing List Login System</h1>
        <!-- Alert Message -->
        <div class="alert <?php echo $alertType; ?>">
            <?php echo $alertMessage; ?>
        </div>
        <form class="mt-3" action="index.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
</html>
