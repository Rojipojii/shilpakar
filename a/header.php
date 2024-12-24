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
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="bi bi-list"></i></a>
      </li>
    </ul>
  
    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
  
      <!-- Search Form and Logout Button -->
      <li class="nav-item">
        <div class="row">
          <!-- <div class="col"> -->
            <!-- Search Form -->
            <!-- <div class="search-form">
              <form id="search-form" class="search-form" method="get" action="subscribers.php">
                <input class="form-control" type="text" name="search" placeholder="Search..." aria-label="Search"> -->
                <!-- <button class="btn btn-outline-success" type="submit">Search</button> -->
              <!-- </form>
            </div>
          </div> -->
          <div class="col">
            <!-- Logout Button -->
            <div class="logout-btn">
              <a href="logout.php" class="btn btn-danger">Log Out <i class="bi bi-box-arrow-right"></i></a>
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
                    <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-house-fill"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="subscribers.php" class="nav-link">
                        <i class="bi bi-person-fill"></i>
                        <p>Subscriber's List</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="addtolist.php" class="nav-link">
                        <i class="bi bi-person-plus-fill"></i>
                        <p>Add Subscribers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="addevent.php" class="nav-link">
                        <i class="bi bi-calendar-event"></i>
                        <p>Events</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="addorganizer.php" class="nav-link">
                        <i class="bi bi-person"></i>
                        <p>Organizers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="addcategory.php" class="nav-link">
                        <i class="bi bi-tags"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="listnumbers.php" class="nav-link">
                        <i class="bi bi-telephone"></i>
                        <p>Phone Number's List</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="createmailinglist.php" class="nav-link">
                        <i class="bi bi-file-earmark-check"></i>
                        <p>Create Mailing-List</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="merge.php" class="nav-link">
                        <i class="bi bi-intersect"></i>
                        <p>Merge</p>
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a href="download.php" class="nav-link">
                        <i class="bi bi-cloud-download"></i>
                        <p>Download</p>
                    </a>
                </li> -->
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
