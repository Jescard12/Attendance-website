<?php
session_start();
include 'databaseConnection.php';  // Include your database connection file

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = mysqli_real_escape_string($con, $_POST['password']);

    // Query to get user details by email
    $query = "SELECT user_id, password, role FROM users WHERE email = '$email'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $hashed_password = $row['password'];

        // Verify the password against the hash
        if (password_verify($password, $hashed_password)) {
            if ($row['role'] === 'student') {  // Check if the user's role is 'student'
                $_SESSION['user_id'] = $row['user_id'];  // Store user ID in session
                header("Location: Student.php");  // Redirect to Student.php
                exit();
            } else {
                echo "<script>alert('Access Denied: You are not authorized to enter this page.'); window.history.back();</script>";
            }
        } else {
            echo "<script>alert('Invalid Email or Password'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Invalid Email or Password'); window.history.back();</script>";
    }

    mysqli_close($con);  // Close the database connection
}
?>
