<?php

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header("Location: index.php"); // Redirect to the login page if not logged in
  exit();
}

if (isset($_SESSION['totalGroupsToMerge'])) {
    $totalGroupsToMerge = $_SESSION['totalGroupsToMerge'];
    // Use $totalGroupsToMerge as needed
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mailer Manager</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <!-- Theme style -->
  <!-- <link rel="stylesheet" href="header.css"> -->
  <link rel="stylesheet" href="header.css?v=<?php echo time(); ?>">

  <!-- Include Bootstrap CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <!-- Link to Bootstrap Icons CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

<div class="menu-header">
    <span class="menu-toggle" onclick="toggleMenu()" id="menuIcon">&#9776;</span>
</div>
    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <br><br>
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item"><a href="dashboard" class="nav-link"><i class="bi bi-house-fill"> Dashboard</i></a></li>
                <li class="nav-item"><a href="list" class="nav-link"><i class="bi bi-person-fill"> Contacts</i></a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a href="subscribers" class="nav-link"><i class="bi bi-person-plus-fill"> Create Contact</i></a></li>
                    <li class="nav-item"><a href="mailinglist" class="nav-link"><i class="bi bi-envelope"> Mailing List</i></a></li>
                    <li class="nav-item"><a href="phone" class="nav-link"><i class="bi bi-telephone"> Phone List</i></a></li>
                    <li class="nav-item"><a href="merge" class="nav-link"><i class="bi bi-intersect"> Merge & Fix</i></a></li>
                    <!-- <?php if (isset($totalGroupsToMerge) && $totalGroupsToMerge > 0): ?>
                        <li class="nav-item"><a href="merge" class="nav-link"><i class="bi bi-intersect"> Merge & Fix</i><span>(<?= $totalGroupsToMerge ?>)</span></a></li>
                    <?php endif; ?> -->
                <?php endif; ?>
                <li class="nav-item"><a href="events" class="nav-link"><i class="bi bi-calendar-event"> Events</i></a></li>
                <li class="nav-item"><a href="categories" class="nav-link"><i class="bi bi-tags"> Categories</i></a></li>
                <li class="nav-item"><a href="organizers" class="nav-link"><i class="bi bi-person"> Organizers</i></a></li>
                <li class="nav-item"><a href="listEmails.php" class="nav-link"><i class="bi bi-person-x-fill"> Invalid Contacts</i></a></li>
                <li class="nav-item"><a href="check_emails.php" class="nav-link"><i class="bi bi-person-check"> Check Contacts</i></a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a href="createUsers.php" class="nav-link"><i class="bi bi-person-plus-fill"> Create User</i></a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"> Logout</i></a></li>
            </ul>
        </nav>
    </div>
    <div class="topbar">
    <input type="text" id="searchInput" placeholder="Search..." onkeyup="liveSearch()">
    <button><i class="bi bi-search"></i></button>
    <div id="searchResults"></div> <!-- Results will be displayed here -->
</div>
</aside>
</div>

<!-- Content Wrapper. Contains page content
<div class="content-wrapper">
      <div class="container-fluid">
                </div>
                </div> -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector('.main-sidebar');
    const content = document.querySelector('.content-wrapper');
    const menuToggle = document.querySelector('.menu-toggle');
    const closeBtn = document.createElement('span');

    // Toggle menu visibility
    menuToggle.addEventListener("click", toggleMenu);
    closeBtn.addEventListener("click", toggleMenu);

    function toggleMenu() {
        sidebar.classList.toggle('active');
        if (sidebar.classList.contains('active')) {
            content.style.marginLeft = "250px";
            content.style.width = "calc(100% - 250px)";
            closeBtn.style.display = "block"; // Show close button
        } else {
            content.style.marginLeft = "0";
            content.style.width = "100%";
            closeBtn.style.display = "none"; // Hide close button
        }
    }
});

function liveSearch() {
    let query = document.getElementById("searchInput").value;
    let searchResultsDiv = document.getElementById("searchResults");
    let contentWrapper = document.querySelector(".content-wrapper");

    // Only search after typing 2+ characters
    if (query.length > 1) {
        let xhr = new XMLHttpRequest();
        xhr.open("GET", "search.php?query=" + query, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                searchResultsDiv.innerHTML = xhr.responseText;
                searchResultsDiv.style.display = "block";

                // Move the search results above the content-wrapper
                if (!searchResultsDiv.parentNode.isEqualNode(contentWrapper.parentNode)) {
                    contentWrapper.parentNode.insertBefore(searchResultsDiv, contentWrapper);
                }
            }
        };
        xhr.send();
    } else {
        searchResultsDiv.style.display = "none";
    }
}


function redirectToEdit(subscriberId) {
    // Clear the search input before redirecting
    document.getElementById("searchInput").value = '';

    // Redirect to edit_subscriber.php page
    window.location.href = "edit_subscriber.php?id=" + subscriberId;
}

</script>



</body>
</html>
