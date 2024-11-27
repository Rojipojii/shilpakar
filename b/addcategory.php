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

// Function to fetch Categories and their total number of attendees from the database
function fetchCategoriesWithAttendees($conn)
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
$categories = fetchCategoriesWithAttendees($conn);
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
    <div class="container">
    <h1>Add Category</h1>

     <!-- Form to add a new category -->
     <form id="addCategoryForm" method="POST" action="addcategory.php">
        <label for="newCategory">Category Name:</label>
        <input type="text" id="newCategory" name="newCategory" required>
        <button type="submit" name="addCategory" class=" blue lighten-6 waves-effect waves-light btn">Add Category</button>
    </form>

    <!-- Display existing categories with total attendees -->
    <h2>Existing Categories:</h2>
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
        echo "<td>";
        echo "<span class='category-name' data-category-id='$categoryId'>$categoryName</span>";

        echo "</td>";
        echo "<td>$totalAttendees</td>";
        echo "<td>$totalEvents</td>";
        echo "<td>";
        echo "<button class='edit-category yellow black-text lighten-6 waves-effect waves-light btn' data-category-id='$categoryId'>Edit</button>";
        echo "<button class='save-category  green black-text lighten-6 waves-effect waves-light btn' data-category-id='$categoryId' style='display: none;'>Save</button>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<script>
// Handle category editing and saving
const editCategoryButtons = document.querySelectorAll(".edit-category");
const saveCategoryButtons = document.querySelectorAll(".save-category");

editCategoryButtons.forEach(button => {
    button.addEventListener("click", function () {
        const categoryId = this.getAttribute("data-category-id");
        const categoryElement = document.querySelector(`.category-name[data-category-id='${categoryId}']`);
        const saveButton = document.querySelector(`.save-category[data-category-id='${categoryId}']`);

        // Enable editing for the category name
        categoryElement.contentEditable = true;
        categoryElement.focus();

        // Show the "Save" button and hide the "Edit" button
        saveButton.style.display = "inline";
        this.style.display = "none";
    });
});

saveCategoryButtons.forEach(button => {
    button.addEventListener("click", function () {
        const categoryId = this.getAttribute("data-category-id");
        const categoryElement = document.querySelector(`.category-name[data-category-id='${categoryId}']`);
        const updatedCategoryName = categoryElement.textContent.trim();

        

        // Send an AJAX request to update the category name
        fetch("addcategory.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `editedCategory=${updatedCategoryName}&categoryId=${categoryId}&editCategory=1`
        })
            .then(response => response.text())
            .then(data => {
                if (data.includes("Category updated successfully")) {
                    alert("Category updated successfully");
                    // Refresh the page after updating the organizer data
                    window.location.href = window.location.href;

                    // Category updated successfully
                    document.getElementById("responseMessage").textContent = "Category updated successfully";

                    // Disable editing for the category name
                    categoryElement.contentEditable = false;

                    // Hide the "Save" button and show the "Edit" button
                    saveButton.style.display = "none";
                    document.querySelector(`.edit-category[data-category-id='${categoryId}']`).style.display = "inline";

                    
                } else {
                    // Restore the original name on error
                    categoryElement.textContent = categoryElement.dataset.originalName;
                    console.error("Error:", data);
                }
            })
            .catch(error => {
                // console.error("Error:", error);
                console.log("asdasd");
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
</div>

</body>
</html>
