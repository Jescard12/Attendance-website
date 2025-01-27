<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: FacultyMemberssignin.php");
    exit();
}
include 'databaseConnection.php';

// find courses for this faculty
$doctor_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT course_name FROM courses WHERE doctor_id=?");
$stmt->bind_param("i",$doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Faculty Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.0.1/socket.io.min.js"></script>
</head>
<body>
<header>
    <nav class="navbar navbar-light bg-light py-3">
        <a class="navbar-brand" href="#">
            <img class="ndulogo" src="images/NDULogo.png" width="80" height="80" alt="Logo">
        </a>
    </nav>
</header>

<div class="container my-5 std-body">
    <h4>Select a Course</h4>
    <div class="row align-items-center">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="dropdown">
                <button class="btn custom-dropdown dropdown-toggle" type="button" id="courseDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Choose a Course
                </button>
                <ul class="dropdown-menu" aria-labelledby="courseDropdown">
                    <?php
                    if($result && $result->num_rows>0){
                        while($row = $result->fetch_assoc()){
                            $cname = htmlspecialchars($row['course_name']);
                            echo "<li><a class='dropdown-item' href='#' onclick='selectCourse(\"$cname\")'>$cname</a></li>";
                        }
                    }else{
                        echo "<li><a class='dropdown-item' href='#'>No courses found</a></li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" id="startSessionBtn" onclick="runAI()">Start Session</button>
            <p class="mt-2"><strong>Note:</strong> press 'q' in the webcam feed to end session.</p>
        </div>
    </div>
</div>

<!-- FILTER SECTION -->
<div class="container my-5">
    <h4>Filter Attendance</h4>
    <form class="row g-3">
        <div class="col-md-3">
            <label for="studentName" class="form-label">Student Name</label>
            <input type="text" id="studentName" class="form-control" placeholder="Enter name">
        </div>
        <div class="col-md-2">
            <label for="minAbsences" class="form-label">Min Absences</label>
            <input type="number" id="minAbsences" class="form-control" placeholder="Min">
        </div>
        <div class="col-md-2">
            <label for="maxAbsences" class="form-label">Max Absences</label>
            <input type="number" id="maxAbsences" class="form-control" placeholder="Max">
        </div>
        <div class="col-md-5">
            <label class="form-label">Date Range</label>
            <div class="d-flex">
                <input type="date" id="startDate" class="form-control me-2">
                <input type="date" id="endDate" class="form-control">
            </div>
        </div>
        <div class="col-md-12 text-end">
            <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
        </div>
    </form>
</div>

<!-- ATTENDANCE TABLES -->
<div class="container my-5">
    <h4>Attendance Records</h4>
    <div id="attendanceContainer">Select a course to load attendance.</div>
    <button class="btn btn-danger mt-3" onclick="resetAttendance()">Reset Attendance Data</button>
    <button class="btn btn-info mt-3" onclick="exportAttendance()">Export Course Attendance</button>
    <p class="mt-2">Files will be in <strong>course_records</strong>.</p>
</div>

<footer class="footer-frame bg-light py-4 mt-5 text-center">
    <a href="index.html" class="btn btn-outline-secondary">Back</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedCourseName = null;
const socket = io("http://127.0.0.1:5000");

// We'll store raw attendance data globally to do client-side filtering.
let rawAttendanceData = null;

// socket events
socket.on('attendance_update', data => {
    if(data.course_name===selectedCourseName){
        fetchAttendanceData();
    }
});
socket.on('attendance_reset', data=>{
    alert("Attendance data has been reset.");
    fetchAttendanceData();
});

function selectCourse(cname){
    selectedCourseName = cname;
    document.getElementById("courseDropdown").innerText = cname;
    fetchAttendanceData();
}

// Start session => run AI
async function runAI(){
    if(!selectedCourseName){
        alert("Select a course first.");
        return;
    }
    if(!confirm(`Start session for ${selectedCourseName}?`)) return;
    try{
        let resp = await fetch("http://127.0.0.1:5000/run_ai", {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ course_name:selectedCourseName })
        });
        let data = await resp.json();
        if(data.success){
            alert("Session started!");
            document.getElementById("startSessionBtn").disabled=true;
            pollSessionStatus();
        } else {
            alert("Failed to start session: "+data.message);
        }
    }catch(err){
        console.error("Error starting session:",err);
        alert("Error starting session.");
    }
}

function pollSessionStatus(){
    let interval = setInterval(async ()=>{
        try{
            let r = await fetch("http://127.0.0.1:5000/is_session_running");
            let d = await r.json();
            if(!d.session_running){
                document.getElementById("startSessionBtn").disabled=false;
                clearInterval(interval);
                fetchAttendanceData();
            }
        }catch(e){
            console.error("Error checking session status",e);
            clearInterval(interval);
        }
    },1000);
}

// Fetch attendance
async function fetchAttendanceData(){
    if(!selectedCourseName) return;
    const container = document.getElementById("attendanceContainer");
    container.innerHTML="Loading...";
    try{
        let url = `http://127.0.0.1:5000/get_attendance/${selectedCourseName}`;
        let resp = await fetch(url);
        if(!resp.ok){
            throw new Error("Server returned an error.");
        }
        let data = await resp.json();
        rawAttendanceData = data;
        displayAttendance(data);
    }catch(err){
        console.error("Error fetching attendance:",err);
        container.innerHTML="Error loading attendance data.";
    }
}

// Display function: data is like { "Session 1": [...], "Session 2": [...], ...}
function displayAttendance(data){
    const container = document.getElementById("attendanceContainer");
    container.innerHTML="";

    const sessionKeys = Object.keys(data);
    if(sessionKeys.length===0){
        container.innerHTML="<p>No attendance found for this course.</p>";
        return;
    }

    sessionKeys.forEach(sessionKey=>{
        let recs = data[sessionKey];
        let html=`
          <h5>${sessionKey}</h5>
          <table class="table table-striped my-4">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Total Absences</th>
                <th>Authorized</th>
                <th>Unauthorized</th>
                <th>Note</th>
                <th>Last Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
        `;
        recs.forEach(r=>{
            let recId = r.record_id;
            let statusId = `status-${recId}`;
            let noteId   = `note-${recId}`;
            html+=`
              <tr>
                <td>${r.student_name}</td>
                <td>${r.date}</td>
                <td>${r.time}</td>
                <td>
                  <select id="${statusId}" class="form-control">
                    <option value="Present"  ${r.status==="Present"?"selected":""}>Present</option>
                    <option value="Absent"   ${r.status==="Absent"?"selected":""}>Absent</option>
                  </select>
                </td>
                <td>${r.totalAbsences}</td>
                <td>${r.authorizedAbsences}</td>
                <td>${r.unauthorizedAbsences}</td>
                <td>
                  <input type="text" id="${noteId}" class="form-control" value="${r.note||''}" />
                </td>
                <td>${r.last_updated}</td>
                <td>
                  <button class="btn btn-success btn-sm" onclick="saveAttendance('${recId}')">Save</button>
                </td>
              </tr>
            `;
        });
        html+="</tbody></table>";
        container.innerHTML+=html;
    });
}

// Filter logic
function applyFilters(){
    if(!rawAttendanceData) return;

    const studentName = document.getElementById("studentName").value.trim().toLowerCase();
    const minAbs = parseInt(document.getElementById("minAbsences").value) || null;
    const maxAbs = parseInt(document.getElementById("maxAbsences").value) || null;
    const startDate = document.getElementById("startDate").value;
    const endDate   = document.getElementById("endDate").value;

    let filtered = {};
    // rawAttendanceData is { "Session 1": [...], "Session 2": [...], ... }
    for(let sessionKey of Object.keys(rawAttendanceData)){
        let records = rawAttendanceData[sessionKey];
        let newRecs = records.filter(r=>{
            let pass = true;

            // filter by student name
            if(studentName && !r.student_name.toLowerCase().includes(studentName)){
                pass=false;
            }
            // filter by minAbs
            if(minAbs!==null && r.totalAbsences < minAbs){
                pass=false;
            }
            // filter by maxAbs
            if(maxAbs!==null && r.totalAbsences > maxAbs){
                pass=false;
            }
            // filter by date range
            if(startDate && r.date < startDate){
                pass=false;
            }
            if(endDate && r.date > endDate){
                pass=false;
            }
            return pass;
        });
        if(newRecs.length>0){
            filtered[sessionKey] = newRecs;
        }
    }
    displayAttendance(filtered);
}

function resetFilters(){
    document.getElementById("studentName").value="";
    document.getElementById("minAbsences").value="";
    document.getElementById("maxAbsences").value="";
    document.getElementById("startDate").value="";
    document.getElementById("endDate").value="";
    if(rawAttendanceData){
        displayAttendance(rawAttendanceData);
    }
}

// Save attendance row
async function saveAttendance(recordId){
    let statusSelect = document.getElementById(`status-${recordId}`);
    let noteInput    = document.getElementById(`note-${recordId}`);
    if(!statusSelect || !noteInput){
        alert("Error: missing row fields. Refresh and try again.");
        return;
    }
    let newStatus = statusSelect.value;
    let note = noteInput.value;

    if(!confirm("Save changes for this row?")) return;

    try{
        let resp = await fetch("http://127.0.0.1:5000/update_attendance", {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                record_id: recordId,
                status:newStatus,
                note: note
            })
        });
        let data = await resp.json();
        if(data.success){
            alert("Row updated successfully!");
            fetchAttendanceData();
        } else {
            alert("Error: "+data.message);
        }
    }catch(err){
        console.error("Update error:",err);
        alert("Failed to update attendance.");
    }
}

// Reset attendance
async function resetAttendance(){
    if(!confirm("Reset all attendance data for all students? This deletes Excel files.")) return;
    try{
        let resp = await fetch("http://127.0.0.1:5000/reset_attendance", {
            method:'POST',
            headers:{'Content-Type':'application/json'}
        });
        if(resp.ok){
            alert("Reset successful.");
            fetchAttendanceData();
        } else {
            alert("Error during reset.");
        }
    }catch(e){
        console.error("Reset error:",e);
    }
}

// Export
async function exportAttendance(){
    if(!selectedCourseName){
        alert("Select a course first.");
        return;
    }
    try{
        let url=`http://127.0.0.1:5000/export_course_attendance/${selectedCourseName}`;
        let r=await fetch(url);
        if(r.ok){
            let blob=await r.blob();
            let link=document.createElement('a');
            link.href=window.URL.createObjectURL(blob);
            link.download=`${selectedCourseName}_attendance.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert("Failed to export course data.");
        }
    }catch(err){
        console.error("Export error:",err);
    }
}
</script>
</body>
</html>
