<?php
include 'databaseConnection.php';  // Include your database connection file

// Query to select all users
$query = "SELECT user_id, password FROM users";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $user_id = $row['user_id'];
        $password = $row['password'];

        // Check if the password is already hashed (typically hashed passwords are longer than plain text)
        if (strlen($password) < 60) {  // Password hash length is typically around 60 characters for bcrypt
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update the password in the database
            $updateQuery = "UPDATE users SET password = '$hashedPassword' WHERE user_id = '$user_id'";
            if (mysqli_query($con, $updateQuery)) {
                echo "Password for user ID $user_id has been hashed successfully.<br>";
            } else {
                echo "Error updating password for user ID $user_id: " . mysqli_error($con) . "<br>";
            }
        } else {
            echo "Password for user ID $user_id is already hashed.<br>";
        }
    }
} else {
    echo "No users found in the database.<br>";
}

mysqli_close($con);  // Close the database connection
?>
