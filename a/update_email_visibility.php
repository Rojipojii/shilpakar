<?php

require_once "db.php";

// Check if email is received in POST
if (isset($_POST['email'])) {
    $email = $_POST['email'];

    // Log the email to check what was received
    error_log("Received email: " . $email); // This will log to the PHP error log

    // Prepare the SQL query to toggle email visibility
    $sql = "UPDATE emails SET hidden = CASE
                WHEN hidden = 0 THEN 1
                ELSE 0
            END WHERE email = ?";  // Use the email for the update condition
    
    // Prepare the statement and execute
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);  // Bind the email parameter
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Successfully updated the email visibility
            echo json_encode(['success' => true]);
        } else {
            // No rows affected (possibly no change or wrong email)
            echo json_encode(['success' => false, 'message' => 'No changes made to the email visibility. Email: ' . $email]);
        }

        $stmt->close();
    } else {
        // SQL error
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL query. Email: ' . $email]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
}

?>
