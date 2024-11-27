<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMA </title>
    <!-- Add your CSS and other meta tags here -->
  
       <!-- Compiled and minified CSS -->
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">

<!-- Compiled and minified JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<script type="text/javascript"> 
  document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('.sidenav');
    var instances = M.Sidenav.init(elems);
  });
</script>
   
</head>   
<style>
.nav {
  background-color: black !important;
}
</style>

<body>
<div class="navbar-fixed" >
<nav class="nav yellow-text">
    <div class="nav-wrapper">
      <a href="/b" class="brand-logo">Mailing list Manager</a>
      <a href="#" data-target="mobile-demo" class="sidenav-trigger"><i class="material-icons">menu</i></a>
      <ul class="right hide-on-med-and-down">
      <li><a href="dashboard.php" class="yellow-text">Dashboard</a></li>
        <li><a href="subscribers.php" class="yellow-text">Subscriber's List</a></li>
        <li><a href="addtolist.php" class="yellow-text">Add Subscribers</a></li>
        <li><a href="addevent.php" class="yellow-text">Events</a></li>     
        <li><a href="addorganizer.php" class="yellow-text">Organizers</a></li>    
        <li><a href="addcategory.php" class="yellow-text">Categories</a></li>
        <li><a href="listnumbers.php" class="yellow-text">Phone Number's List</a></li>
        <li><a href="createmailinglist.php" class="yellow-text">Create Mailing-List</a></li>
        <li><a href="download.php" class="yellow-text">Download</a></li>    
        <li><a href="logout.php" class="yellow-text">Logout</a></li>
      </ul>
    </div>
  </nav>
  </div>

  <ul class="sidenav" id="mobile-demo">
    <div class="yellow-text black">
        <li><a href="dashboard.php" class="yellow-text">Dashboard</a></li>
        <li><a href="subscribers.php" class="yellow-text">Subscriber's List</a></li>
        <li><a href="addtolist.php" class="yellow-text">Add Subscribers</a></li>
        <li><a href="addevent.php" class="yellow-text">Events</a></li>     
        <li><a href="addorganizer.php" class="yellow-text">Organizers</a></li>    
        <li><a href="addcategory.php" class="yellow-text">Categories</a></li>
        <li><a href="listnumbers.php" class="yellow-text">Phone Number's List</a></li>
        <li><a href="createmailinglist.php" class="yellow-text">Create Mailing-List</a></li>
        <li><a href="download.php" class="yellow-text">Download</a></li>    
        <li><a href="logout.php" class="yellow-text">Logout</a></li>
</div>
  </ul>

 
  <form  action="subscribers.php" method="get">
        <div class="input-field">
          <input id="search" type="search"  name="search" >
          <label class="label-icon" for="search"><i class="material-icons">search</i></label>
          <i class="material-icons">close</i>
        </div>
      </form> 




</body>
</html>
