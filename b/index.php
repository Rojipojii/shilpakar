<?php
session_start();

require_once "db.php"; // Include the database connection file

// echo "Today is " . date("Y/m/d") . "<br>";
// echo "Today is " . date("Y.m.d") . "<br>";
// echo "Today is " . date("Y-m-d") . "<br>";
// echo "Today is " . date("l");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Fetch user data from the database
    $sql = "SELECT id, username, password FROM login WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username);

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        echo $row["password"];
        if ($password == $row["password"]) {
            // Password is correct, create a session and log in the user
            $_SESSION['id'] = $row["id"];
            header("Location: dashboard.php"); // Redirect to a logged-in page
            exit();
        } else {
            echo $password;

            echo "Incorrect password. Please try again.";
        }
    } else {
        echo "No user found with that username.";
    }

    // Close the database connection
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">

    <!-- Compiled and minified JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
            
    <title>NMA</title>
</head>
<body>
   
    <div class="container">
      <?php 
      
      if (isset($_SESSION['id'])) {
        // If the user is not logged in, redirect to the login page with a message
        header("Location: dashboard.php");
       # echo "<script>alert('You should login first');</script>";
        exit();
    }
    ?>
    <h2>Mailing List Login System</h2>

        <!-- Page Content goes here --><div class="row">
    <form class="col s8"  action="index.php" method ="POST">
      <div class="row">
        <div class="input-field col s8">
          <input placeholder="" id="username" name="username" type="text" class="validate" required>
          <label for="username">Username</label>
        </div>
       
      </div>
      <div class="row">
        <div class="input-field col s8">
        <input id="password" type="password" name="password" class="validate" required>
          <label for="password">Password:</label>
        </div>
      </div>
      <input type="submit" class="waves-effect teal waves-light btn" value="Login">
    </form>
  </div>
      </div>
    

</body>
</html>
