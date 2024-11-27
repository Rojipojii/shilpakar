<?php
session_start(); // Start the session

// Check if the user is logged in (you may use any login check logic you have)
if (isset($_SESSION['id'])) {
    // If the user is logged in, destroy the session
    session_unset();
    session_destroy();
}

// Redirect to the login page (you should change this to the actual login page URL)
header("Location: index.php");
exit();
?>
