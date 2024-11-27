<?php


require_once "db.php"; // Include the database connection file

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMA</title>
</head>
<body>
    <h1>Mailing List Login System</h1>
    <form action="index.php" method ="POST">
    
    <label for="username">Username:</label>
    <input type="username" id="username" name="username" required><br><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>

        <input type="submit" value="Login" class="btn btn-primary">
    </form>
</body>
</html>
