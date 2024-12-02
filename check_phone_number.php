<?php
// check_phone_number.php

require_once "db.php"; // Include the database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mobileNumber"])) {
    $mobileNumber = $_POST["mobileNumber"];

    // Query to fetch subscriber details and their email
    $sql = "
        SELECT 
            subscribers.*, 
            emails.email 
        FROM 
            phone_numbers 
        INNER JOIN 
            subscribers 
        ON 
            phone_numbers.subscriber_id = subscribers.subscriber_id 
        LEFT JOIN 
            emails 
        ON 
            subscribers.subscriber_id = emails.subscriber_id 
        WHERE 
            phone_numbers.phone_number = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobileNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Fetch the subscriber details and email
        $user = $result->fetch_assoc();
        echo json_encode(array("success" => true, "user" => $user));
    } else {
        // No matching phone number found
        echo json_encode(array("success" => false, "message" => "No subscriber found for this phone number."));
    }
} else {
    // Invalid request or missing parameters
    echo json_encode(array("success" => false, "message" => "Invalid request."));
}
?>
