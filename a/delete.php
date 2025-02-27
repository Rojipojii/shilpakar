<?php 
// Check if the subscriber_id is provided in the URL
if (isset($_GET['subscriber_id'])) {
    // Get the subscriber_id from the URL
    $subscriberId = $_GET['subscriber_id'];

    // Include the necessary files
    require_once "db.php";
    
    // Start a transaction to ensure atomicity (either all deletes succeed or none)
    $conn->begin_transaction();

    try {
        // Check if phone number exists for the subscriber before deleting
        $checkPhoneSQL = "SELECT COUNT(*) FROM phone_numbers WHERE subscriber_id = ?";
        $phoneStmt = $conn->prepare($checkPhoneSQL);
        $phoneStmt->bind_param("i", $subscriberId);
        $phoneStmt->execute();
        $phoneStmt->bind_result($phoneCount);
        $phoneStmt->fetch();
        $phoneStmt->close();

        if ($phoneCount > 0) {
            $deletePhoneSQL = "DELETE FROM phone_numbers WHERE subscriber_id = ?";
            $phoneStmt = $conn->prepare($deletePhoneSQL);
            $phoneStmt->bind_param("i", $subscriberId);
            $phoneStmt->execute();
            $phoneStmt->close();
        }

        // Check if email exists for the subscriber before deleting
        $checkEmailSQL = "SELECT COUNT(*) FROM emails WHERE subscriber_id = ?";
        $emailStmt = $conn->prepare($checkEmailSQL);
        $emailStmt->bind_param("i", $subscriberId);
        $emailStmt->execute();
        $emailStmt->bind_result($emailCount);
        $emailStmt->fetch();
        $emailStmt->close();

        if ($emailCount > 0) {
            $deleteEmailSQL = "DELETE FROM emails WHERE subscriber_id = ?";
            $emailStmt = $conn->prepare($deleteEmailSQL);
            $emailStmt->bind_param("i", $subscriberId);
            $emailStmt->execute();
            $emailStmt->close();
        }

        // Check if designation_organization exists for the subscriber before deleting
        $checkDOSQL = "SELECT COUNT(*) FROM designation_organization WHERE subscriber_id = ?";
        $doStmt = $conn->prepare($checkDOSQL);
        $doStmt->bind_param("i", $subscriberId);
        $doStmt->execute();
        $doStmt->bind_result($doCount);
        $doStmt->fetch();
        $doStmt->close();

        if ($doCount > 0) {
            $deleteDOSQL = "DELETE FROM designation_organization WHERE subscriber_id = ?";
            $doStmt = $conn->prepare($deleteDOSQL);
            $doStmt->bind_param("i", $subscriberId);
            $doStmt->execute();
            $doStmt->close();
        }

        // Check if event_subscriber_mapping exists for the subscriber before deleting
        $checkMappingSQL = "SELECT COUNT(*) FROM event_subscriber_mapping WHERE subscriber_id = ?";
        $mappingStmt = $conn->prepare($checkMappingSQL);
        $mappingStmt->bind_param("i", $subscriberId);
        $mappingStmt->execute();
        $mappingStmt->bind_result($mappingCount);
        $mappingStmt->fetch();
        $mappingStmt->close();

        if ($mappingCount > 0) {
            $deleteMappingSQL = "DELETE FROM event_subscriber_mapping WHERE subscriber_id = ?";
            $mappingStmt = $conn->prepare($deleteMappingSQL);
            $mappingStmt->bind_param("i", $subscriberId);
            $mappingStmt->execute();
            $mappingStmt->close();
        }

        // Finally, delete from subscribers table
        $deleteSubscriberSQL = "DELETE FROM subscribers WHERE subscriber_id = ?";
        $subscriberStmt = $conn->prepare($deleteSubscriberSQL);
        $subscriberStmt->bind_param("i", $subscriberId);
        $subscriberStmt->execute();
        $subscriberStmt->close();

        // Commit the transaction to finalize the deletion
        $conn->commit();

        // Redirect back to the subscribers page after deletion
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        // If an error occurs, rollback the transaction
        $conn->rollback();
        die("Error deleting subscriber: " . $e->getMessage());
    }
} else {
    // If no subscriber_id is provided, show an error
    die("Subscriber ID is missing.");
}
?>
