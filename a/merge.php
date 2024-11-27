<?php
include 'db.php'; // Your database connection file

// Function to fetch duplicate subscribers based on phone number or email
function getDuplicateSubscribers($conn) {
    // SQL to find duplicate subscribers based on phone_number or email
    $sql = "SELECT phone_number, email, COUNT(*) as count 
            FROM subscribers
            GROUP BY phone_number, email
            HAVING count > 1";

    $result = $conn->query($sql);

    // Check if the query was successful
    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }

    return $duplicates;
}

// Function to merge duplicates
function mergeDuplicates($conn, $phoneNumber, $email) {
    // Start a transaction to ensure the merge is atomic
    $conn->begin_transaction();

    try {
        // Find the duplicate entries based on phone number or email
        $sql = "SELECT * FROM subscribers WHERE phone_number = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $phoneNumber, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $subscribers = [];
        while ($row = $result->fetch_assoc()) {
            $subscribers[] = $row;
        }

        // Perform merging logic here
        // For example, keeping the most complete record and deleting duplicates

        if (count($subscribers) > 1) {
            // Example: Merge and keep the first subscriber's info
            $keepSubscriber = $subscribers[0];
            
            // Delete the duplicate records
            for ($i = 1; $i < count($subscribers); $i++) {
                $duplicateId = $subscribers[$i]['id'];
                $deleteSql = "DELETE FROM subscribers WHERE id = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param("i", $duplicateId);
                $deleteStmt->execute();
            }

            // Optionally, update the kept subscriber's information if necessary

            $conn->commit(); // Commit the transaction
            return "Duplicates merged successfully!";
        } else {
            return "No duplicates found.";
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback in case of an error
        return "Error merging duplicates: " . $e->getMessage();
    }
}

// Handling the merge request
if (isset($_POST['merge'])) {
    $phoneNumber = $_POST['phone_number'];
    $email = $_POST['email'];

    $mergeMessage = mergeDuplicates($conn, $phoneNumber, $email);
}

$duplicates = getDuplicateSubscribers($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merge Duplicates</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include("header.php"); ?>
<div class="content-wrapper">
    <h1>Merge Duplicate Subscribers</h1>

    <!-- Show the merge result message -->
    <?php if (isset($mergeMessage)): ?>
        <div class="alert"><?php echo $mergeMessage; ?></div>
    <?php endif; ?>

    <h2>Duplicates Found</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Number of Duplicates</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($duplicates as $duplicate): ?>
                <tr>
                    <td><?php echo htmlspecialchars($duplicate['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($duplicate['email']); ?></td>
                    <td><?php echo $duplicate['count']; ?></td>
                    <td>
                        <!-- Merge form for each duplicate -->
                        <form method="POST" action="merge.php">
                            <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($duplicate['phone_number']); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($duplicate['email']); ?>">
                            <button type="submit" name="merge">Merge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
            </div>
</body>
</html>
