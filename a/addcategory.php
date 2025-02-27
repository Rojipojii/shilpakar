<?php

// Start the session
session_start();

// Check if a user is logged in
if (!isset($_SESSION['id'])) {
    // If the user is not logged in, redirect to the login page with a message
    header("Location: index.php?message=You should login first");
    exit();
}

require_once "db.php"; // Include the database connection file

// function fetchCategoriesWithAttendees($conn)
// {
//     $sql = "SELECT 
//         categories.category_id, 
//         categories.category_name, 
//         (
//             SELECT COUNT(*) 
//             FROM event_subscriber_mapping 
//             WHERE event_subscriber_mapping.category_id = categories.category_id
//         ) AS total_attendees
//     FROM 
//         categories
//     GROUP BY 
//         categories.category_id, categories.category_name;
//     ";

//     $result = $conn->query($sql);

//     $categories = array();
//     if ($result->num_rows > 0) {
//         while ($row = $result->fetch_assoc()) {
//             $categories[] = $row;
//         }
//     }

//     return $categories;
// }

function fetchCategoriesWithAttendees($conn)
{
    $sql = "SELECT 
        c.category_id, 
        c.category_name, 
        COUNT(es.category_id) AS total_attendees
    FROM 
        categories c
    LEFT JOIN 
        event_subscriber_mapping es ON c.category_id = es.category_id
    GROUP BY 
        c.category_id, c.category_name";

    $result = $conn->query($sql);
    $categories = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}



// Handle form submission for adding a category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addCategory"])) {
    $newCategory = $_POST["newCategory"];

    if (!empty($newCategory)) {
        // Check if the category already exists
        $sqlCheck = "SELECT category_id FROM categories WHERE category_name = ?";
        $stmtCheck = $conn->prepare($sqlCheck);

        if ($stmtCheck) {
            $stmtCheck->bind_param("s", $newCategory);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows == 0) {
                // The category doesn't exist, so insert it
                $stmtCheck->close();

                $sqlInsert = "INSERT INTO categories (category_name) VALUES (?)";
                $stmtInsert = $conn->prepare($sqlInsert);

                if ($stmtInsert) {
                    $stmtInsert->bind_param("s", $newCategory);
                    if ($stmtInsert->execute()) {
                        // Category added successfully
                        $categoryMessage = "Category added successfully!";
                    } else {
                        $categoryMessage = "Error adding the category: " . $stmtInsert->error;
                    }

                    $stmtInsert->close();
                } else {
                    $categoryMessage = "Error preparing the statement: " . $conn->error;
                }
            } else {
                $categoryMessage = "Category with the same name already exists.";
            }
        } else {
            $categoryMessage = "Error preparing the statement for category name check: " . $conn->error;
        }
    } else {
        $categoryMessage = "Category name cannot be empty.";
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
                $categoryMessage = "Category updated successfully ";
            } else {
                $categoryMessage = "Error updating the category " . $stmt->error;
                // echo "Error updating the category: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $categoryMessage = "Error preparing the statement " . $conn->error;
            // echo "Error preparing the statement: " . $conn->error;
        }
    } else {
        $categoryMessage = "Category name cannot be empty. ";
        // echo "Category name cannot be empty.";
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
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <title>Categories</title>
</head>
<body>
    <?php include("header.php"); ?>
          <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
        <h1 class="mt-4">Add Category</h1>

        <!-- Display the category message -->
<div id="categoryResponseMessage">
    <?php
    if (!empty($categoryMessage)) {
        echo '<div class="alert alert-info">' . $categoryMessage . '</div>';
    }
    ?>
</div>

        <!-- Form to add a new category -->
        <form id="addCategoryForm" method="POST" action="addcategory.php" class="mt-3">
            <div class="form-group">
                <label for="newCategory">Category Name:</label>
                <input type="text" id="newCategory" name="newCategory" required class="form-control">
            </div>
            <button type="submit" name="addCategory" class="btn btn-outline-success">Add Category</button>
        </form>

        <!-- Display existing categories with total attendees -->
        <h2 class="mt-4">Categories: <?= count($categories); ?></h2>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Total Attendees</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
    // Sort categories alphabetically by category_name
    usort($categories, function($a, $b) {
        return strcmp($a["category_name"], $b["category_name"]);
    });

    foreach ($categories as $category) {
        $categoryId = $category["category_id"];
        $categoryName = $category["category_name"];
        $fetchCategoriesWithAttendees = $category["total_attendees"];

        echo "<tr>";
        echo "<td>";
        // Make the category name a hyperlink
        echo '<a href="subscribers.php?category_id=' . $categoryId . '" class="category-name" data-category-id="' . $categoryId . '" style="text-decoration: underline; color: inherit;" 
    onmouseover="this.style.textDecoration=\'none\'; this.style.color=\'inherit\';" 
    onmouseout="this.style.textDecoration=\'underline\'; this.style.color=\'inherit\';">' . $categoryName . '</a>';

        echo "</td>";
        echo "<td>";
        // Display attendees count without a hyperlink
        echo "$fetchCategoriesWithAttendees";
        echo "</td>";
        echo "<td>";
        echo "<button class='edit-category btn btn-outline-primary' data-category-id='$categoryId'><i class='bi bi-pencil-square'></i></button>";
        echo "<button class='save-category btn btn-outline-success' data-category-id='$categoryId' style='display: none;'><i class='bi bi-check2-square'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
?>

</tbody>

        </table>
        <div id="successMessage" class="alert alert-success" style="display: none;">Category updated successfully</div>

    </div>

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

        
// Handle category editing and saving
saveCategoryButtons.forEach(button => {
    button.addEventListener("click", function () {
        const categoryId = this.getAttribute("data-category-id");
        const categoryElement = document.querySelector(`.category-name[data-category-id='${categoryId}']`);
        const updatedCategoryName = categoryElement.textContent.trim();

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
                    // Update the category element
                    categoryElement.textContent = updatedCategoryName;
                    
                    // Display the success message
                    document.getElementById("categoryResponseMessage").innerHTML = '<div class="alert alert-success">Category updated successfully</div>';

                    // Hide the "Save" button and show the "Edit" button
                    button.style.display = "none";
                    document.querySelector(`.edit-category[data-category-id='${categoryId}']`).style.display = "inline";
                } else {
                    // Restore the original name on error
                    categoryElement.textContent = categoryElement.dataset.originalName;
                    console.error("Error:", data);
                }
            })
            .catch(error => {
                console.log("Error:", error);
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
