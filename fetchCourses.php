<?php
// Include database connection
include 'databaseConnection.php';  

// Assuming the user ID is stored in the session
$faculty_id = $_SESSION['userid']; 

// Fetch courses for this faculty member
$query = "SELECT course_id, course_name FROM courses WHERE faculty_id = '$doctor_id'";
$result = mysqli_query($con, $query);

// Display courses in a dropdown
if ($result && mysqli_num_rows($result) > 0) {
    echo '<select id="courseSelect">';
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<option value="' . $row['course_id'] . '">' . $row['course_name'] . '</option>';
    }
    echo '</select>';
} else {
    echo 'No courses found for this faculty member.';
}

mysqli_close($con);
?>
