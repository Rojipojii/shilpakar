<?php
// Include the database connection
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the list of subscriber IDs and the first subscriber ID
    $subscriberIds = $_POST['subscriber_ids']; // Array of selected subscriber IDs
    $firstSubscriberId = $_POST['first_subscriber_id']; // The first (ascending order) subscriber ID

    // Start a database connection (assuming db.php handles it)
    // Assuming $conn is the connection variable in db.php

    // Begin a transaction
    mysqli_begin_transaction($conn);

    try {
        // Step 1: Delete the other subscriber records from the subscribers table
        $idsToDelete = array_diff($subscriberIds, [$firstSubscriberId]); // All IDs except the first
        if (!empty($idsToDelete)) {
            $deleteQuery = 'DELETE FROM subscribers WHERE subscriber_id IN (' . implode(',', $idsToDelete) . ')';
            mysqli_query($conn, $deleteQuery);
        }

        // Step 2: Update related tables to replace deleted subscriber_id with the first subscriber_id

        // Update emails table
        $updateEmailsQuery = 'UPDATE emails SET subscriber_id = ' . $firstSubscriberId . ' WHERE subscriber_id IN (' . implode(',', $idsToDelete) . ')';
        mysqli_query($conn, $updateEmailsQuery);

        // Update phone_numbers table
        $updatePhoneNumbersQuery = 'UPDATE phone_numbers SET subscriber_id = ' . $firstSubscriberId . ' WHERE subscriber_id IN (' . implode(',', $idsToDelete) . ')';
        mysqli_query($conn, $updatePhoneNumbersQuery);

        // Update designation_organization table
        $updateDesignationQuery = 'UPDATE designation_organization SET subscriber_id = ' . $firstSubscriberId . ' WHERE subscriber_id IN (' . implode(',', $idsToDelete) . ')';
        mysqli_query($conn, $updateDesignationQuery);

        // Update event_subscriber_mapping table
        $updateEventSubscriberQuery = 'UPDATE event_subscriber_mapping SET subscriber_id = ' . $firstSubscriberId . ' WHERE subscriber_id IN (' . implode(',', $idsToDelete) . ')';
        mysqli_query($conn, $updateEventSubscriberQuery);

        // Commit transaction
        mysqli_commit($conn);

        // Success: Redirect to edit_subscriber.php with the first subscriber's ID
        header('Location: edit_subscriber.php?id=' . $firstSubscriberId);
        exit; // Make sure no further code is executed

    } catch (Exception $e) {
        // Rollback in case of error
        mysqli_roll_back($conn);
        echo 'Failed to merge: ' . $e->getMessage();
    }
} else {
    echo 'Invalid request';
}
?>
