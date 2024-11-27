<?php
require_once "db.php"; // Include the database connection file

// Function to fetch categories and their total number of attendees from the database
function fetchcategoriesWithAttendees($conn)
{
    $sql = "SELECT categories.category_id, categories.category_name, 
               COUNT(subscribers.id) AS total_attendees,
               (SELECT COUNT(*) FROM events WHERE category_id = categories.category_id) AS total_events
        FROM categories
        LEFT JOIN subscribers ON categories.category_id = subscribers.category_id
        GROUP BY categories.category_id, categories.category_name";

    $result = $conn->query($sql);

    $categories = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}


// Handle form submission for adding an category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addCategory"])) {
    $newCategory = $_POST["newCategory"];

    if (!empty($newCategory)) {
        // Insert the new category into the database
        $sql = "INSERT INTO categories (category_name) VALUES (?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $newCategory);
            if ($stmt->execute()) {
                // Category added successfully
            } else {
                echo "Error adding the category: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        echo "Category name cannot be empty.";
    }
}

// Handle form submission for editing an category
else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editCategory"])) {
    $editedCategory = $_POST["editedCategory"];
    $categoryId = $_POST["categoryId"];

    if (!empty($editedCategory)) {
        // Update the category name in the database
        $sql = "UPDATE categories SET category_name = ? WHERE category_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $editedCategory, $categoryId);
            if ($stmt->execute()) {
                // Category updated successfully
            } else {
                echo "Error updating the category: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        echo "Category name cannot be empty.";
    }
}

// Fetch categories with total attendees from the database
$categories = fetchcategoriesWithAttendees($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category</title>
</head>
<body>
    <?php include("header.php"); ?>
    <h1>Add Category</h1>
    <div id="responseMessage"></div>

     <!-- Form to add a new category -->
     <form id="addCategoryForm" method="POST" action="addcategory.php">
        <label for="newCategory">Category Name:</label>
        <input type="text" id="newCategory" name="newCategory" required>
        <button type="submit" name="addCategory" class="btn btn-outline-success">Add Category</button>
    </form>

    <!-- Display existing categories with total attendees -->
    <h2>Existing categories:</h2>
    <form id="editCategoryForm" method="POST" action="addcategory.php">
    <table border="1">
    <thead>
    <tr>
        <th>Category Name</th>
        <th>Total Attendees</th>
        <th>Total Events</th>
        <th>Action</th>
    </tr>
        </thead>

        <tbody>
    <?php
    foreach ($categories as $category) {
        $categoryId = $category["category_id"];
        $categoryName = $category["category_name"];
        $totalAttendees = $category["total_attendees"];
        $totalEvents = $category["total_events"];

        echo "<tr>";
        echo "<td><span contenteditable='true' class='edit-category' data-category-id='$categoryId'>$categoryName</span></td>";
        echo "<td>$totalAttendees</td>";
        echo "<td>$totalEvents</td>";
        echo "<td><button class='save-category btn btn-outline-warning' data-category-id='$categoryId'>Save</button></td>";
        echo "</tr>";
    }
    ?>
</tbody>
</table>
</form>

<script>
        // Handle inline editing of existing category
        const editCategoryElements = document.querySelectorAll(".edit-category");
        editCategoryElements.forEach(element => {
            element.addEventListener("focus", function () {
                // Store the original category name
                this.dataset.originalName = this.textContent.trim();
            });

            element.addEventListener("blur", function () {
                const editedCategory = this.textContent.trim();
                const categoryId = this.getAttribute("data-category-id");

                // Send an AJAX request to update the category name
                fetch("addcategory.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `editedCategory=${editedCategory}&categoryId=${categoryId}&editCategory=1`
                })
                    .then(response => response.text())
                    .then(data => {
                        if (data.includes("Category updated successfully")) {
                            // Category updated successfully
                            document.getElementById("responseMessage").textContent = "Category updated successfully";

                            // Refresh the category list
                            refreshCategoryList();
                        } else {
                            // Restore the original name on error
                            this.textContent = this.dataset.originalName;
                            console.error("Error:", data);
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                    });
            });
        });

        // Function to refresh the category list
        function refreshCategoryList() {
            fetch("addcategory.php")
                .then(response => response.text())
                .then(data => {
                    // Replace the existing category list with the updated list
                    const categoryList = document.querySelector("tbody");
                    categoryList.innerHTML = data;
                })
                .catch(error => {
                    console.error("Error:", error);
                });
        }
    </script>
</body>
</html>
