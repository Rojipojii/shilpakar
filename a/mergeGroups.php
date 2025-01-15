<!-- mergeGroups.php -->
<?php

// Function to merge groups based on overlapping phone numbers or emails
function getGroupsToMerge($conn) {
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

    // Filter out groups with only one subscriber
    $groups = array_filter($groups, function ($group) {
        return count($group['subscribers']) > 1;
    });

    // Return the total number of groups that need to be merged
    return count($groups);
}

// Get the total number of groups to merge
$totalGroupsToMerge = getGroupsToMerge($conn);

// Store the result in session
$_SESSION['totalGroupsToMerge'] = $totalGroupsToMerge;
?>
