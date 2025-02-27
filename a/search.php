<?php
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    echo "<script>alert('You should login first');</script>";
    exit();
}

require_once "db.php"; // Include the database connection file

if (isset($_GET['query'])) {
    $searchQuery = trim($_GET['query']); 
    $searchQuery = "%$searchQuery%"; // For SQL LIKE

    // Prepare the SQL query
    $sql = "SELECT s.subscriber_id, s.full_name, d.designation, d.organization, 
                   GROUP_CONCAT(DISTINCT e.email SEPARATOR ', ') AS emails, 
                   GROUP_CONCAT(DISTINCT p.phone_number SEPARATOR ', ') AS phone_numbers
            FROM subscribers s
            LEFT JOIN designation_organization d ON s.subscriber_id = d.subscriber_id
            LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
            LEFT JOIN phone_numbers p ON s.subscriber_id = p.subscriber_id
            WHERE s.full_name LIKE ? 
               OR d.designation LIKE ? 
               OR d.organization LIKE ? 
               OR e.email LIKE ? 
               OR p.phone_number LIKE ? 
            GROUP BY s.subscriber_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery); // Bind parameters

    if ($stmt->execute()) {         
        $result = $stmt->get_result(); // Fetch results         
        if ($result->num_rows > 0) {             
            echo "<p><strong>" . $result->num_rows . " result(s) found</strong></p>";             
            echo "<ul class='search-results'>";             
            while ($row = $result->fetch_assoc()) {                 
                echo "<li onclick=\"redirectToEdit({$row['subscriber_id']})\">";                 
                echo "<strong>{$row['full_name']}</strong><br>";
    
                // Check if designation or organization are not empty and only show them if they exist
                if (!empty($row['designation']) || !empty($row['organization'])) {
                    echo "<small>";
                    if (!empty($row['designation'])) {
                        echo "{$row['designation']}";
                    }
                    if (!empty($row['organization'])) {
                        if (!empty($row['designation'])) {
                            echo " at "; // Add "at" only if designation exists
                        }
                        echo "{$row['organization']}";
                    }
                    echo "</small><br>";
                }
    
                // Display the emails and phone numbers as usual
                echo "<small>{$row['emails']}</small><br>";                 
                echo "<small>{$row['phone_numbers']}</small><br>";                 
                echo "</li>";             
            }             
            echo "</ul>";         
        } else {
            echo "<p>No results found.</p>";
        }
    }
     else {
        echo "<p>Error executing search</p>";
    }
}
?>
