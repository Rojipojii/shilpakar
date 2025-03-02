<?php

require_once "db.php";

// Check if email and toggle type are received in POST
if (isset($_POST['email']) && isset($_POST['toggle'])) {
    $email = $_POST['email'];
    $toggleType = $_POST['toggle']; // Either 'visibility' or 'nonexistent'

    // Log the received parameters
    error_log("Received email: " . $email . " | Toggle type: " . $toggleType);

    if ($toggleType === 'visibility') {
        // Toggle 'hidden' column and reset 'does_not_exist' to 0 if 'hidden' is set to 1
        $sql = "UPDATE emails 
                SET hidden = CASE WHEN hidden = 0 THEN 1 ELSE 0 END, 
                    does_not_exist = CASE WHEN hidden = 0 THEN does_not_exist ELSE 0 END 
                WHERE email = ?";
    } elseif ($toggleType === 'nonexistent') {
        // Toggle 'does_not_exist' column and reset 'hidden' to 0 if 'does_not_exist' is set to 1
        $sql = "UPDATE emails 
                SET does_not_exist = CASE WHEN does_not_exist = 0 THEN 1 ELSE 0 END, 
                    hidden = CASE WHEN does_not_exist = 0 THEN hidden ELSE 0 END 
                WHERE email = ?";
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid toggle type.']);
        exit;
    }

    // Prepare and execute the SQL statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made. Email: ' . $email]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL query.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
}

?>
