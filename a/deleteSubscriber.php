<?php
// Check if subscriber_ids is provided via POST request
if (isset($_POST['subscriber_ids']) && is_array($_POST['subscriber_ids'])) {
    require_once "db.php";
    
    // Get array of subscriber IDs
    $subscriberIds = $_POST['subscriber_ids'];

    // Convert array to a comma-separated list for SQL query
    $placeholders = implode(',', array_fill(0, count($subscriberIds), '?'));

    // Start a transaction to ensure all deletions are executed properly
    $conn->begin_transaction();

    try {
        // Delete related data from child tables first
        $tables = ['phone_numbers', 'emails', 'designation_organization', 'event_subscriber_mapping'];

        foreach ($tables as $table) {
            $deleteSQL = "DELETE FROM $table WHERE subscriber_id IN ($placeholders)";
            $stmt = $conn->prepare($deleteSQL);
            $stmt->bind_param(str_repeat('i', count($subscriberIds)), ...$subscriberIds);
            $stmt->execute();
            $stmt->close();
        }

        // Delete from subscribers table
        $deleteSubscriberSQL = "DELETE FROM subscribers WHERE subscriber_id IN ($placeholders)";
        $stmt = $conn->prepare($deleteSubscriberSQL);
        $stmt->bind_param(str_repeat('i', count($subscriberIds)), ...$subscriberIds);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Success response
        echo json_encode(['success' => true, 'message' => 'Subscribers deleted successfully']);
    } catch (Exception $e) {
        // Rollback if any issue occurs
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request. No subscriber IDs provided.']);
}
?>
