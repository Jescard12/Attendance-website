<?php
session_start();
include 'databaseConnection.php';  // Include your database connection file

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = mysqli_real_escape_string($con, $_POST['password']);

    // Query to check user credentials
    $query = "SELECT user_id, role, password FROM users WHERE email = '$email'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Verify the hashed password
        if (password_verify($password, $row['password'])) {
            if ($row['role'] === 'admission') {  
                $_SESSION['user_id'] = $row['user_id'];  
                header("Location: SAO.php");  // Redirect to the SAO dashboard page
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
