<?php 
// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php?message=You should login first");
    exit();
}

require_once "db.php"; // Include the database connection file

// Get subscriber_id from URL
$subscriberIdFilter = isset($_GET['subscriber_id']) ? $_GET['subscriber_id'] : null;

$query = "
    SELECT
        s.subscriber_id,
        s.full_name,
        p.phone_number,
        e.email,
        d.designation,
        d.organization
    FROM
        subscribers s
    LEFT JOIN phone_numbers p ON s.subscriber_id = p.subscriber_id
    LEFT JOIN emails e ON s.subscriber_id = e.subscriber_id
    LEFT JOIN designation_organization d ON s.subscriber_id = d.subscriber_id
";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$groups = [];

// Fetch all rows and group by overlapping phone numbers or emails
while ($row = $result->fetch_assoc()) {
    $subscriberId = $row['subscriber_id'];
    $fullName = $row['full_name'];
    $designation = $row['designation'];
    $organization = $row['organization'];
    $phone = $row['phone_number'];
    $email = $row['email'];
    $matched = false;

    foreach ($groups as &$group) {
        if (
            ($phone && in_array($phone, $group['phone_numbers'])) ||
            ($email && in_array($email, $group['emails']))
        ) {
            // Merge subscriber into the group
            if (!isset($group['subscribers'][$subscriberId])) {
                $group['subscribers'][$subscriberId] = [
                    'full_name' => $fullName,
                    'designation' => [],
                    'organization' => [],
                    'phone_numbers' => [],
                    'emails' => [],
                ];
            }
            if ($phone) {
                $group['subscribers'][$subscriberId]['phone_numbers'][] = $phone;
                $group['phone_numbers'][] = $phone;
            }
            if ($email) {
                $group['subscribers'][$subscriberId]['emails'][] = $email;
                $group['emails'][] = $email;
            }
            if ($designation && !in_array($designation, $group['subscribers'][$subscriberId]['designation'])) {
                $group['subscribers'][$subscriberId]['designation'][] = $designation;
            }
            if ($organization && !in_array($organization, $group['subscribers'][$subscriberId]['organization'])) {
                $group['subscribers'][$subscriberId]['organization'][] = $organization;
            }
            $matched = true;
            break;
        }
    }

    // If no match is found, create a new group
    if (!$matched) {
        $groups[] = [
            'subscribers' => [
                $subscriberId => [
                    'full_name' => $fullName,
                    'designation' => $designation ? [$designation] : [],
                    'organization' => $organization ? [$organization] : [],
                    'phone_numbers' => $phone ? [$phone] : [],
                    'emails' => $email ? [$email] : [],
                ],
            ],
            'phone_numbers' => $phone ? [$phone] : [],
            'emails' => $email ? [$email] : [],
        ];
    }
}

    $groups = array_filter($groups, function ($group) {
        return count($group['subscribers']) > 1;
    });

    // Function to find the group containing the given subscriber_id
function findGroupForSubscriber($subscriberId, $groups) {
    foreach ($groups as $group) {
        if (isset($group['subscribers'][$subscriberId])) {
            return $group;
        }
    }
    return null; // No group found for this subscriber
}

    if($subscriberIdFilter){
        // Find and display the group for the specified subscriber_id
    $groupForSubscriberId = findGroupForSubscriber($subscriberIdFilter, $groups);
    // Assign the group to display
    $groupsToDisplay = [$groupForSubscriberId];
    if ($groupForSubscriberId) {
        // echo "<pre>";
        // print_r($groupForSubscriberId); // Display the final group for the subscriber
        // echo "</pre>";
        
    } else {
        echo "<p>No group found for Subscriber ID: " . htmlspecialchars($subscriberIdFilter) . "</p>";
    }

    }else{

    // Count the total number of groups that need to be merged
    $totalGroupsToMerge = count($groups);

    // Pagination logic
    $groupsPerPage = 5; // Number of groups per page
    $totalGroups = count($groups); // Total number of groups
    $totalPages = ceil($totalGroups / $groupsPerPage); // Total number of pages

    // Get the current page number
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $currentPage = max(1, min($currentPage, $totalPages)); // Ensure page is within range

    // Calculate the start and end index for the groups to display
    $startIndex = ($currentPage - 1) * $groupsPerPage;

    // Apply pagination
    $groupsToDisplay = array_slice($groups, $startIndex, $groupsPerPage);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['merge'])) {

        $mergeIds = $_POST['merge']; // Array of selected subscriber IDs
        // // Check if at least two checkboxes are selected
        // if (count($mergeIds) < 2) {
        //     echo "Please select at least two subscribers to merge.";
        //     exit(); // Stop further execution
        // }

        // Set a session variable to indicate the merge page origin
        $_SESSION['from_merge_page'] = true;



        // Get the first subscriber ID in the group
        $firstSubscriberId = $mergeIds[0];

        // Start a transaction to ensure all actions are done atomically
        $conn->begin_transaction();

        try {
            // Step 1: Delete all subscribers except the first subscriber_id of the matched group
            // Remove subscriber_ids that are in the merge group but not the first subscriber
            $deleteQuery = "DELETE FROM subscribers WHERE subscriber_id != ? AND subscriber_id IN (" . implode(",", $mergeIds) . ")";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $firstSubscriberId);
            $stmt->execute();

            // Step 2: Update emails, phone_numbers, designation_organization, and event_subscriber_mapping tables
            // Update emails table
            $updateEmailQuery = "UPDATE emails SET subscriber_id = ? WHERE subscriber_id != ? AND subscriber_id IN (" . implode(",", $mergeIds) . ")";
            $stmt = $conn->prepare($updateEmailQuery);
            $stmt->bind_param("ii", $firstSubscriberId, $firstSubscriberId);
            $stmt->execute();

            // Update phone_numbers table
            $updatePhoneQuery = "UPDATE phone_numbers SET subscriber_id = ? WHERE subscriber_id != ? AND subscriber_id IN (" . implode(",", $mergeIds) . ")";
            $stmt = $conn->prepare($updatePhoneQuery);
            $stmt->bind_param("ii", $firstSubscriberId, $firstSubscriberId);
            $stmt->execute();

            // Update designation_organization table
            $updateDesignationQuery = "UPDATE designation_organization SET subscriber_id = ? WHERE subscriber_id != ? AND subscriber_id IN (" . implode(",", $mergeIds) . ")";
            $stmt = $conn->prepare($updateDesignationQuery);
            $stmt->bind_param("ii", $firstSubscriberId, $firstSubscriberId);
            $stmt->execute();

            // Update event_subscriber_mapping table
            $updateEventSubscriberMappingQuery = "UPDATE event_subscriber_mapping SET subscriber_id = ? WHERE subscriber_id != ? AND subscriber_id IN (" . implode(",", $mergeIds) . ")";
            $stmt = $conn->prepare($updateEventSubscriberMappingQuery);
            $stmt->bind_param("ii", $firstSubscriberId, $firstSubscriberId);
            $stmt->execute();

            // Commit the transaction
            $conn->commit();

            // Redirect to the edit_subscriber page for the first subscriber_id in the group
            header("Location: edit_subscriber.php?id=" . $firstSubscriberId);
            exit(); // Ensure no further code is executed after redirection
        } catch (Exception $e) {
            // If there is an error, roll back the transaction
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Records</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>


   
<?php 
// // Calculate the total number of groups to merge
// $totalGroupsToMerge = count($groups);

include("header.php"); ?>
<div class="content-wrapper">
    <div class="container-fluid">
        <div class="container mt-5">
        <h3 class="text-center mb-4">
                Merge & Fix
                <?php if ($subscriberIdFilter): ?>
                    <!-- (Displaying Group for Subscriber ID: <?= $subscriberIdFilter ?>) -->
                <?php else: ?>
                    (<?= $totalGroupsToMerge ?> Groups to Merge)
                <?php endif; ?>
            </h3>
            <?php if (!empty($groupsToDisplay)): ?>
                <?php foreach ($groupsToDisplay as $index => $group): ?>
                    <form action="merge.php" method="POST" class="mb-5 merge-form">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Select</th>
                                        <th>Full Name</th>
                                        <th>Phone Numbers</th>
                                        <th>Emails</th>
                                        <th>Designation</th>
                                        <th>Organization</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['subscribers'] as $subscriberId => $details): ?>
                                        <tr>
                                        <td style="text-align: center; vertical-align: top;">
  <input class="form-check-input" type="checkbox" name="merge[]" value="<?= $subscriberId ?>">
</td>

                                        <td>    
                                            <a href="edit_subscriber.php?id=<?= $subscriberId ?>" 
   style="text-decoration: underline; color: inherit;" 
   onmouseover="this.style.textDecoration='none'; this.style.color='inherit';" 
   onmouseout="this.style.textDecoration='underline'; this.style.color='inherit';">
    <?= $details['full_name'] ?>
</a>
                                            </td>
                                            <td><?= implode(', ', array_unique($details['phone_numbers'])) ?></td>
                                            <td><?= implode(', ', array_unique($details['emails'])) ?></td>
                                            <td><?= implode(', ', $details['designation']) ?></td>
                                            <td><?= implode(', ', $details['organization']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Hidden input to identify the group -->
                        <input type="hidden" name="group_id" value="<?= $startIndex + $index + 1 ?>">
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" class="btn btn-outline-success">Merge</button>
                        </div>
                        <!-- Error message -->
                        <p class="text-danger text-center mt-2 error-message" style="display: none;">
        Please select at least 2 checkbox.
    </p>
                    </form>
                <?php endforeach; ?>

                <!-- Pagination controls -->
                <?php if (!$subscriberIdFilter): ?>
    <div class="pagination justify-content-center">
        <?php if ($currentPage > 1): ?>
            <a class="btn btn-secondary" href="?page=<?= $currentPage - 1 ?>">Previous</a>
        <?php endif; ?>
        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
            <a class="btn btn-secondary <?= $page == $currentPage ? 'active' : '' ?>" href="?page=<?= $page ?>"><?= $page ?></a>
        <?php endfor; ?>
        <?php if ($currentPage < $totalPages): ?>
            <a class="btn btn-secondary" href="?page=<?= $currentPage + 1 ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>


            <?php else: ?>
                <p class="text-center">No duplicate records found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
    // Select all forms with the 'merge-form' class
    document.querySelectorAll('.merge-form').forEach(form => {
        form.addEventListener('submit', (e) => {
            // Get all checkboxes in the current form
            const checkboxes = form.querySelectorAll('input[name="merge[]"]');
            const errorMessage = form.querySelector('.error-message');

            // Check if at least two checkboxes are selected
            const checkedCount = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;

if (checkedCount < 2) {
    e.preventDefault(); // Prevent form submission
    errorMessage.style.display = 'block'; // Show error message
} else {
    errorMessage.style.display = 'none'; // Hide error message
}
        });
    });
});
</script>


</body>
</html>

