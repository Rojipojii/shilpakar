<?php
// Database connection parameters
$servername = "localhost";
$username = "shilpakar";
$password = "RC7sHwW39KsVUDPz";
$database = "shilpakar";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve mobile number from the form
$mobileNumber = $_POST['mobileNumber'];

// Check if the mobile number exists in the database
$sql = "SELECT * FROM form_data WHERE mobileNumber = '$mobileNumber'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Mobile number found, pre-fill the form fields
    $row = $result->fetch_assoc();
    $fullName = $row['fullName'];
    $email = $row['email'];
    $designation = $row['designation'];
    $organization = $row['organization'];
} else {
    // Mobile number not found, insert new data into the database
    $fullName = $_POST['fullName'];
    $email = $_POST['email'];
    $designation = $_POST['designation'];
    $organization = $_POST['organization'];

    $insert_sql = "INSERT INTO form_data (mobileNumber, fullName, email, designation, organization) VALUES ('$mobileNumber', '$fullName', '$email', '$designation', '$organization')";
    $conn->query($insert_sql);
}

// Close the database connection
$conn->close();
?>

<!-- Display the pre-filled form -->
<form>
    Mobile Number: <input type="text" name="mobileNumber" value="<?php echo $mobileNumber; ?>" required><br>
    Full Name: <input type="text" name="fullName" value="<?php echo $fullName; ?>"><br>
    Email: <input type="email" name="email" value="<?php echo $email; ?>"><br>
    Designation: <input type="text" name="designation" value="<?php echo $designation; ?>"><br>
    Organization: <input type="text" name="organization" value="<?php echo $organization; ?>"><br>
</form>