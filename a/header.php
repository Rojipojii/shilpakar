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
  <title>Mailing List Manager</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <!-- Theme style -->
  <link rel="stylesheet" href="style.css">
      <!-- Include Bootstrap CSS -->
      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <!-- Link to Bootstrap Icons CSS -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.css">


      <style>
                body {
            font-size: 16px; /* You can adjust the value to your preferred font size */
        }

        /* Add other style rules as needed */
        </style>

</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">


  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
  
    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
  
      <!-- Search Form and Logout Button -->
      <li class="nav-item">
        <div class="row">
          <div class="col">
            <!-- Logout Button -->
            <div class="logout-btn">
            <i class="bi bi-person-fill"> </i><?php echo $_SESSION['username']; ?> <a href="logout.php" class="btn btn-danger">  <i class="bi bi-box-arrow-right"></i></a>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </nav>
  
  
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4" style="width: 250px;"> <!-- Adjust the width as needed -->
    <!-- Sidebar user (optional) -->
    <a href="dashboard.php" class="brand-link">
      <span class="brand-text font-weight-light">Mailing List Manager</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user (optional) -->

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
               <ul class="nav">
                <li class="nav-item">
                    <a href="dashboard" class="nav-link">
                    <i class="bi bi-house-fill"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="list" class="nav-link">
                        <i class="bi bi-person-fill"></i>
                        <p>Subscriber's List</p>
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="subscribers" class="nav-link">
                        <i class="bi bi-person-plus-fill"></i>
                        <p>Add Subscribers</p>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="events" class="nav-link">
                        <i class="bi bi-calendar-event"></i>
                        <p>Events</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="organizers" class="nav-link">
                        <i class="bi bi-person"></i>
                        <p>Organizers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories" class="nav-link">
                        <i class="bi bi-tags"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="phone" class="nav-link">
                        <i class="bi bi-telephone"></i>
                        <p>Phone Number's List</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mailinglist" class="nav-link">
                        <i class="bi bi-file-earmark-check"></i>
                        <p>Create Mailing-List</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="listEmails.php" class="nav-link">
                        <i class="bi bi-file-earmark-check"></i>
                        <p>Full Emails</p>
                    </a>
                </li>
                
                <?php if (isset($totalGroupsToMerge) && $totalGroupsToMerge > 0): ?>
                <li class="nav-item">
    <a href="merge" class="nav-link">
        <i class="bi bi-intersect"></i>      
        <p>Merge & Fix</p>
        
            <span>(<?= $totalGroupsToMerge ?>)</span>

    </a>
</li>
<?php endif; ?>



<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<li class="nav-item">
    <a href="createUsers.php" class="nav-link">
        <i class="bi bi-person-plus-fill"></i>      
        <p>Create User</p>
    </a>
</li>
<?php endif; ?>



            </ul>
            
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>


  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->


<!-- jQuery -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../../dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../../dist/js/demo.js"></script>
</body>
</html>
