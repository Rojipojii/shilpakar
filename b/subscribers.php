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


// Function to retrieve data with pagination
function fetchDataWithPagination($conn, $start, $limit)
{
    $sql = "SELECT *, COUNT(*) AS number_of_events_attended FROM subscribers GROUP BY 
            CASE
                WHEN mobile_number IS NOT NULL AND mobile_number != '' THEN mobile_number
                ELSE email
            END
            ORDER BY id DESC
            LIMIT $start, $limit"; // Use LIMIT to fetch a limited set of records
    $result = $conn->query($sql);

    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    return $result;
}

// Function to fetch additional data for infinite scrolling
function fetchAdditionalData($conn, $start, $limit) {
    // Construct and execute a SQL query to fetch more data
    $sql = "SELECT * FROM subscribers ORDER BY id DESC LIMIT $start, $limit";
    $result = $conn->query($sql);

    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    return $result;
}


// Get the page number from the URL or use 1 as the default
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$limit = 500; // Number of records to fetch per page
$start = ($page - 1) * $limit; // Calculate the starting record for the current page

// Fetch data for the current page
$result = fetchDataWithPagination($conn, $start, $limit);

// Handle AJAX requests for infinite scrolling
if (isset($_GET['action']) && $_GET['action'] === 'loadMore') {
    $result = fetchAdditionalData($conn, $start, $limit);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        // Build an array of rows to send back as JSON
        $data[] = $row;
    }

    // Return the data as JSON
    echo json_encode($data);
    exit;
}


// Initialize the serial number
$serialNumber = 1;

// // Handle search query if it's present in the URL

if (isset($_GET["search"])) {
    $searchQuery = $_GET["search"];
    $result = searchInDatabase($conn, $searchQuery);
} else {
    // If no search query, fetch data with pagination
    $result = fetchDataWithPagination($conn, $start, $limit);
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
                // Refresh the page after updating the organizer data
                 window.location.href = window.location.href;
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
            // Refresh the page after updating the organizer data
             window.location.href = window.location.href;
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
// Handle sorting request
if (isset($_GET['action']) && $_GET['action'] === 'sort') {
    $order = isset($_GET['order']) && ($_GET['order'] === 'asc' || $_GET['order'] === 'desc') ? $_GET['order'] : 'asc';
    
    // Modify your query to include the ORDER BY clause
    $sql = "SELECT *, COUNT(*) AS number_of_events_attended FROM subscribers GROUP BY 
            CASE
                WHEN mobile_number IS NOT NULL AND mobile_number != '' THEN mobile_number
                ELSE email
            END
            ORDER BY id $order
            LIMIT $start, $limit"; // Use LIMIT to fetch a limited set of records

    $result = $conn->query($sql);

    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    // Now, build the table rows and echo them as the response
    $data = '';
    $serialNumber = 1;
    while ($row = $result->fetch_assoc()) {
        // Check if this row is being edited
        $isEditing = isset($_GET["edit"]) && $_GET["edit"] == $row["id"];
        $rowClass = $isEditing ? 'highlighted' : '';

        // Construct the row
        $data .= "<tr class='$rowClass' id='row_" . $row["id"] . "'>";
        $data .= "<td>" . $serialNumber . "</td>"; // Display the serial number
        $serialNumber++; // Increment the serial number

        // The rest of the row content (similar to your existing code)...
        $data .= "<td>" . ($row["full_name"] ? $row["full_name"] : '') . "</td>";
        $data .= "<td>" . (!empty($row["mobile_number"]) ? $row["mobile_number"] : '') . "</td>";
        $data .= "<td>" . (!empty($row["email"]) ? $row["email"] : '') . "</td>";
        $data .= "<td>" . (!empty($row["designation"]) ? $row["designation"] : '') . "</td>";
        $data .= "<td>" . (!empty($row["organization"]) ? $row["organization"] : '') . "</td>";
        $data .= "<td>" . ($row["number_of_events_attended"] ? $row["number_of_events_attended"] : '') . "</td>";
        $data .= "<td>
                    <a href='subscribers.php?edit=" . $row["id"] . "'>Edit</a> | 
                    <a href='javascript:void(0);' onclick='deleteRecord(" . $row["id"] . ")'>Delete</a>
                  </td>";
        $data .= "</tr>";
    }

    echo $data;
    exit;
}

?>  

<!DOCTYPE html>
<html lang="en">  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List</title>  
    <style>
/* Style for the header table */
#header-table {
    position: sticky;
    top: 0; /* Stick to the top of the container */
    background-color: #fff; /* Background color for the header */
}

/* Style for the container to control scrolling */
#table-container {
    height: 80vh;
    overflow-y: auto;
    position: relative;
}

/* Ensure the header table doesn't interfere with the data table */
#header-table th,
#data-table td {
    min-width: 100px; /* Adjust as needed */
    max-width: 200px; /* Adjust as needed */
    padding: 8px;
    text-align: left;
}

/* Adjust the height of the header row and data cells for better alignment */
#header-table th,
#data-table td {
    height: 40px;
}

/* Additional styles and adjustments may be needed based on your design */
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

<div class="container">

<!-- Add these buttons above your table -->
<div>
    <button onclick="sortTable('asc')"><span class="material-symbols-outlined">
arrow_drop_up
</span>
Ascending Order</button>
    <button onclick="sortTable('desc')"><span class="material-symbols-outlined">
arrow_drop_down
</span>Descending Order</button>
</div>


<div id="record-container" style="height: 80vh; overflow-y: auto;">
        <!-- Table to display data or search results -->
        <table border="2" >
            <thead id="header-table">
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

                    echo "<tr class='$rowClass' id='row_" . $row["id"] . "'>";
                    echo "<td>" . $serialNumber . "</td>"; // Display the serial number
                    $serialNumber++; // Increment the serial number

                    if (isset($_GET["edit"]) && $_GET["edit"] == $row["id"]) {
                        // Display input fields for editing

                        echo "<form method='post' action='subscribers.php'>";
                        echo "<input type='hidden' name='edit_id' value='" . $row["id"] . "'>";
                        echo "<td class='edit-mode'><input type='text' name='new_full_name' value='" . $row["full_name"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_mobile_number' value='" . $row["mobile_number"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_email' value='" . $row["email"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_designation' value='" . $row["designation"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_organization' value='" . $row["organization"] . "'></td>";
                        echo "<td class='edit-mode'><input type='text' name='new_number_of_events_attended' value='" . $row["number_of_events_attended"] . "'></td>";
                        echo "<td class='edit-mode'><input type='submit' value='Update'></td>";
                        echo "</form>";
                        // Add JavaScript to scroll to the edited row
                        echo "<script>document.getElementById('row_" . $row["id"] . "').scrollIntoView();</script>";
                    } else {
                        // Display record data and edit button
                        // Display record data and edit button

                        echo "<td>" . ($row["full_name"] ? $row["full_name"] : '') . "</td>";
                        echo "<td>" . (!empty($row["mobile_number"]) ? $row["mobile_number"] : '') . "</td>";
                        echo "<td>" . (!empty($row["email"]) ? $row["email"] : '') . "</td>";
                        echo "<td>" . (!empty($row["designation"]) ? $row["designation"] : '') . "</td>";
                        echo "<td>" . (!empty($row["organization"]) ? $row["organization"] : '') . "</td>";
                        echo "<td>" . ($row["number_of_events_attended"] ? $row["number_of_events_attended"] : '') . "</td>";
                        echo "<td>
                                <a href='subscribers.php?edit=" . $row["id"] . "'>Edit</a> | 
                                <a href='javascript:void(0);' onclick='deleteRecord(" . $row["id"] . ")'>Delete</a>
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
        </div> 



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

        <script>
    // JavaScript function to toggle ascending and descending order
    function sortTable(order) {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Replace the table content with the sorted data
                var dataContainer = document.querySelector('#record-container table tbody');
                dataContainer.innerHTML = xhr.responseText;
            } else {
                console.error("Failed to sort data: " + xhr.status);
            }
        };
        
        xhr.open('GET', 'subscribers.php?action=sort&order=' + order, true);
        xhr.send();
    }
</script>

        <!-- JavaScript for infinite scrolling -->
    <script>
    var page = <?php echo $page; ?>;
    var limit = <?php echo $limit; ?>;
    var loading = false;
    var endOfData = false;

    // Function to load more data when scrolling to the bottom
       function loadMoreData() {
        if (loading || endOfData) {
        return;
        }
    var scrollHeight = document.documentElement.scrollHeight;
    var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    var clientHeight = document.documentElement.clientHeight;

    if (scrollTop + clientHeight >= scrollHeight - 50) {
        loading = true;
        page++;

        // Send an AJAX request to fetch more data
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var newData = JSON.parse(xhr.responseText); // Parse the JSON response

                if (newData.length === 0) {
                    endOfData = true;
                }

                appendDataToTable(newData); // Append the new data to the table
                loading = false;
            } else {
                console.error("Failed to load more data: " + xhr.status);
                loading = false; // Reset loading flag on error
            }
        };

        xhr.onerror = function() {
            console.error("Failed to make the XMLHttpRequest to fetch additional data.");
            loading = false; // Reset loading flag on error
        };

        xhr.open('GET', 'subscribers.php?action=loadMore&page=' + page, true);
        xhr.send();
    }
}

// Function to append new data to the table
function appendDataToTable(newData) {
    var dataContainer = document.querySelector('#record-container table tbody');
    
    var lastSerialNumber = dataContainer.querySelectorAll('tr').length + 1; // Calculate the last serial number

    newData.forEach(function(item) {
        var newRow = "<tr>";
        newRow += "<td>" + lastSerialNumber + "</td>"; // Display the serial number
        lastSerialNumber++; // Increment the serial number for the next row
        newRow += "<td>" + item.full_name + "</td>";
        newRow += "<td>" + item.mobile_number + "</td>";
        newRow += "<td>" + item.email + "</td>";
        newRow += "<td>" + item.designation + "</td>";
        newRow += "<td>" + item.organization + "</td>";
        newRow += "<td>" + item.number_of_events_attended + "</td>";
        newRow += "<td><a href='subscribers.php?edit=" + item.id + "'>Edit</a> | " +
            "<a href='javascript:void(0);' onclick='deleteRecord(" + item.id + ")'>Delete</a></td>";
        newRow += "</tr";

        dataContainer.innerHTML += newRow;
    });
}


    // Attach the scroll event listener
    window.addEventListener('scroll', loadMoreData);

    // Call the function when the page loads
    window.addEventListener('load', loadMoreData);
    </script>
</body>
</html>
