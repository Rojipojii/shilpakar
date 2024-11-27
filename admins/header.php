
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <!-- Compiled and minified CSS -->
     <!--  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"> -->

     <!-- Compiled and minified JavaScript --> 
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script> --> 
        
    <title>NMA </title>
    <!-- Add your CSS and other meta tags here -->
    <style>
        /* Basic CSS for header and navigation */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 10px;
        }

        nav {
            background-color: #444;
            text-align: center;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
        }

        nav a:hover {
            background-color: #555;
        }

        .container {
            padding: 20px;
        }
        
    </style>
</head>   
<body>
    



  <!-- <div class="navbar-fixed">
    <nav>
      <div class="nav-wrapper"> -->
        <!-- <a href="#!" class="brand-logo">Logo</a> -->
        <header>
        <h1>Shilpakar & Co. Mailing List</h1>
   
    
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="subscribers.php">Subscriber's List</a>
        <a href="addtolist.php">Add Subscribers</a>
        <a href="addevent.php">Events</a>       
        <a href="addorganizer.php">Organizers</a>        
        <a href="addcategory.php">Categories</a>
        <a href="createmailinglist.php">Create Mailing-List</a>
        <a href="download.php">Download</a> <br> <br>

        <form action="subscribers.php" method="get"> <!-- Use GET method to pass the search query -->
        <label for="search"></label>
        <input type="text" id="search" name="search">
        <input type="submit" value="Search"  class="btn btn-primary">
    </form>



    </nav>
    </header>
      <!-- </div>
    </nav>
  </div> -->
 


</body>
</html>
