<?php
require_once "db.php"; // Include the database connection file

// Function to retrieve data from the database based on search query
function searchInDatabase($conn, $searchQuery) {
    $sql = "SELECT * FROM subscribers WHERE 
            full_name LIKE ? OR 
            mobile_number LIKE ? OR 
            email LIKE ? OR 
            designation LIKE ? OR 
            organization LIKE ?";
    $searchParam = "%" . $searchQuery . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    return $stmt->get_result();
}   

// Initialize the page number
$page = 1;

// Function to retrieve data from the database with a limit
function fetchDataWithLimit($conn, $limit, $offset)
{
    $sql = "SELECT *,mobile_number, COUNT(*) AS number_of_events_attended FROM subscribers GROUP BY mobile_number ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result();
}

// Handle the initial data load
if (!isset($_GET["page"]) || $_GET["page"] == 1) {
    $result = fetchDataWithLimit($conn, 500, 0);
} else {
    $page = $_GET["page"];
    $offset = ($page - 1) * 500;
    $result = fetchDataWithLimit($conn, 500, $offset);
}

// Initialize the serial number
$serialNumber = 1;

// Handle search query if it's present in the URL
if (isset($_GET["search"])) {
    $searchQuery = $_GET["search"];
    $result = searchInDatabase($conn, $searchQuery);
} else {
    // If no search query, fetch all data
    $result = fetchDataWithLimit($conn, 500, 0); // Limit initial fetch to 10 records
}

// Handle record edit & updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_id"])) {
    $editId = $_POST["edit_id"];
    $newFullName = $_POST["new_full_name"];
    $newMobileNumber = $_POST["new_mobile_number"];
    $newEmail = $_POST["new_email"];
    $newDesignation = $_POST["new_designation"];
    $newOrganization = $_POST["new_organization"];
    $newNumberOfEventsAttended = $_POST["new_number_of_events_attended"];

    // Validate input data if needed

    // Update the record in the database
    $updateSQL = "UPDATE subscribers SET 
        full_name = ?,
        mobile_number = ?,
        email = ?,
        designation = ?,
        organization = ?,
        number_of_events_attended = ?
        WHERE id = ?";

    $updateStmt = $conn->prepare($updateSQL);

    if ($updateStmt === false) {
        die("Error preparing update statement: " . $conn->error);
    }

    $updateStmt->bind_param("ssssssi", $newFullName, $newMobileNumber, $newEmail, $newDesignation, $newOrganization, $newNumberOfEventsAttended, $editId);

    if ($updateStmt->execute()) {
        echo "<script>
                alert('Record updated successfully.');
                location.reload();
              </script>";
        exit();
    } else {
        die("Error executing update statement: " . $updateStmt->error);
    }
}


// Handle record deletion
if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {
    $deleteID = $_GET["delete"];
    
    // Check if the user confirmed the deletion
    if (isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
        $deleteSQL = "DELETE FROM subscribers WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSQL);

        if ($deleteStmt === false) {
            die("Error preparing delete statement: " . $conn->error);
        }
       
        $deleteStmt->bind_param("i", $deleteID);

        if ($deleteStmt->execute()) {
            // Use AJAX to refresh the table without a full page reload
            echo "<script>
                    alert('Record deleted successfully.');
                    location.reload();
                  </script>";
            exit();
        } else {
            die("Error executing delete statement: " . $deleteStmt->error);
        }
    } else {  
        // Prompt the user for confirmation before deleting
        echo "<script>
                var confirmDelete = confirm('Are you sure you want to delete this record?');
                if (confirmDelete) {
                    // Use AJAX to perform the delete action
                    var xhr = new XMLHttpRequest();
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            alert('Record deleted successfully.');
                            location.reload();  
                        }
                    };
                    xhr.open('GET', 'subscribers.php?delete=" . $deleteID . "&confirm=yes', true);
                    xhr.send();
                }
              </script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List</title>  
    <style>
        /* Style for edit mode */
        .edit-mode input {
            display: block;
            width: 100%;
        }

        /* Highlighted row style */
        .highlighted {
        background-color: yellow;
        }

    
    </style>
</head>
<body>

<?php include("header.php"); ?>

    <div id="record-container">
        <!-- Table to display data or search results -->
        <table border="2">
            <thead>
                <tr>
                    <th>Serial Number</th>
                    <th>Full Name</th>
                    <th>Mobile Number</th>
                    <th>Email</th>
                    <th>Designation</th>
                    <th>Organization</th>
                    <th>Number of Events Attended</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>  
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Check if this row is being edited
                    $isEditing = isset($_GET["edit"]) && $_GET["edit"] == $row["id"];

                    // Add the "highlighted" class if editing
                    $rowClass = $isEditing ? 'highlighted' : '';

                    echo "<tr class='$rowClass'>";
                    echo "<td>" . $serialNumber . "</td>"; // Display the serial number
                    $serialNumber++; // Increment the serial number

                    if (isset($_GET["edit"]) && $_GET["edit"] == $row["id"]) {
                        // Display input fields for editing
                        echo "<form method='post'>";
                        echo "<input type='hidden' name='edit_id' value='" . $row["id"] . "'>";
                        echo "<td class='edit-mode'><input type='text' name='new_full_name' value='" . $row["full_name"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_mobile_number' value='" . $row["mobile_number"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_email' value='" . $row["email"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_designation' value='" . $row["designation"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_organization' value='" . $row["organization"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_number_of_events_attended' value='" . $row["number_of_events_attended"] . "'></td>";
                        echo "<td class='edit-mode'><input type='submit' value='Update'></td>";
                        echo "</form>";
                    } else {
                        // Display record data and edit button
                        echo "<td>" . $row["full_name"] . "</td>";
                        echo "<td>" . $row["mobile_number"] . "</td>";
                        echo "<td>" . $row["email"] . "</td>";
                        echo "<td>" . $row["designation"] . "</td>";
                        echo "<td>" . $row["organization"] . "</td>";
                        echo "<td>" . $row["number_of_events_attended"] . "</td>";
                        echo "<td>
                                <a href='subscribers.php?edit=" . $row["id"] . "'class='btn btn-outline-info btn-sm'><i class='bi bi-pencil-square'></i></a> | 
                                <a href='javascript:void(0);' onclick='deleteRecord(" . $row["id"] . ")' class='btn btn-outline-danger btn-sm'><i class='bi bi-trash3'></i></a>
                              </td>";
                    }

                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No records found.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
<!-- for infinite scroll -->

<script>
        var isLoading = false;
var isEndOfData = false;

function loadMoreRecords() {
    if (!isLoading && !isEndOfData) {
        isLoading = true;
        var container = document.getElementById("record-container");
        var xhr = new XMLHttpRequest();
        var nextPage = <?php echo $page + 1; ?>; // Get the next page number

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var records = JSON.parse(xhr.responseText);
                if (records.length > 0) {
                    var table = container.querySelector("table tbody");

                    records.forEach(function(record) {
                        // Create and append rows for the new records
                        var newRow = document.createElement("tr");
                        newRow.innerHTML = `
                            <td>${record.serialNumber}</td>
                            <td>${record.full_name}</td>
                            <td>${record.mobile_number}</td>
                            <td>${record.email}</td>
                            <td>${record.designation}</td>
                            <td>${record.organization}</td>
                            <td>${record.number_of_events_attended}</td>
                            <td>
                                <a href='subscribers.php?edit=${record.id}'>Edit</a> | 
                                <a href='javascript:void(0);' onclick='deleteRecord(${record.id})'>Delete</a>
                            </td>
                        `;
                        table.appendChild(newRow);
                    });

                    page = nextPage; // Update the current page
                    isLoading = false;
                } else {
                    // No more data to load
                    isEndOfData = true;
                }
            }
        };

        xhr.open('GET', 'subscribers.php?page=' + nextPage, true);
        xhr.send();
    }
}

// Attach the scroll event listener to load more records when reaching the bottom
window.addEventListener("scroll", function() {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight) {
        loadMoreRecords();
    }
});

// Initial load of records
loadMoreRecords();
    </script>


 <!-- for deleting  -->
    <script>
        function deleteRecord(id) {
            var confirmDelete = confirm('Are you sure you want to delete this record?');
            if (confirmDelete) {
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        // Do not show any alert message here
                        // alert('Record deleted successfully.');
                        location.reload();  
                    }  
                };
                xhr.open('GET', 'subscribers.php?delete=' + id + '&confirm=yes', true);
                xhr.send();
            }
        }
    </script>

</body>
</html>
