<?php
// check_phone_number.php

require_once "db.php"; // Include the database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mobileNumber"])) {
    $mobileNumber = $_POST["mobileNumber"];

    $sql = "SELECT * FROM subscribers WHERE phone_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobileNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(array("success" => true, "user" => $user));
    } else {
        echo json_encode(array("success" => false));
    }
} else {
    echo json_encode(array("success" => false));
}
?>
