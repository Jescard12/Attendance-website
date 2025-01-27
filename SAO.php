<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: signinSAO.php");
    exit();
}
include 'databaseConnection.php';

// fetch all students
$q = "SELECT user_id, name FROM users WHERE role='student'";
$res = mysqli_query($con, $q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>SAO Dashboard</title>
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
    <div class="mb-3">
        <label class="form-label"><strong>Choose a Student</strong></label>
        <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="studentDropdown">
                Select Student
            </button>
            <ul class="dropdown-menu">
            <?php
            if($res && mysqli_num_rows($res)>0){
                while($row=mysqli_fetch_assoc($res)){
                    echo "<li><a class='dropdown-item' href='#' onclick='selectStudent(\"".$row['user_id']."\",\"".htmlspecialchars($row['name'])."\")'>".htmlspecialchars($row['name'])."</a></li>";
                }
            }else{
                echo "<li><a class='dropdown-item' href='#'>No Students Found</a></li>";
            }
            mysqli_close($con);
            ?>
            </ul>
        </div>
    </div>

    <!-- FILTERS for SAO to filter a single student's attendance across all courses -->
    <div class="my-4">
        <h5>Filter Attendance</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="saoCourseName" class="form-label">Course Name</label>
                <input type="text" id="saoCourseName" class="form-control" placeholder="Enter partial course name">
            </div>
            <div class="col-md-2">
                <label for="saoMinAbsences" class="form-label">Min Absences</label>
                <input type="number" id="saoMinAbsences" class="form-control" placeholder="Min">
            </div>
            <div class="col-md-2">
                <label for="saoMaxAbsences" class="form-label">Max Absences</label>
                <input type="number" id="saoMaxAbsences" class="form-control" placeholder="Max">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date Range</label>
                <div class="d-flex">
                    <input type="date" id="saoStartDate" class="form-control me-2">
                    <input type="date" id="saoEndDate" class="form-control">
                </div>
            </div>
            <div class="col-md-12 text-end">
                <button class="btn btn-primary me-2" onclick="applySAOFilters()">Apply Filters</button>
                <button class="btn btn-secondary" onclick="resetSAOFilters()">Reset</button>
            </div>
        </div>
    </div>

    <h5>Attendance</h5>
    <div id="attendanceContainer">Select a student</div>
    <button class="btn btn-info mt-3" onclick="exportStudentData()">Export Student Excel</button>

    <h5 class="mt-5">Pending Excuses</h5>
    <div id="excuseContainer">No student selected yet.</div>
</div>

<footer class="footer-frame bg-light py-4 mt-5 text-center">
    <a href="index.html" class="btn btn-outline-secondary">Back</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const socket = io("http://127.0.0.1:5000");
let selectedStudentId=null;
let selectedStudentName=null;
let rawSAOData=null; // store all attendance for the student

socket.on("attendance_update", data=>{
    if(data.student_id==selectedStudentId){
        fetchStudentAttendance(); // reload
    }
});

document.addEventListener("DOMContentLoaded", ()=>{
    fetchPendingExcuses(); // or wait until a student is selected
});

function selectStudent(stuId, stuName){
    selectedStudentId=stuId;
    selectedStudentName=stuName;
    document.getElementById("studentDropdown").innerText=stuName;
    fetchStudentAttendance();
    fetchPendingExcuses();
}

async function fetchStudentAttendance(){
    if(!selectedStudentId) return;
    let container=document.getElementById("attendanceContainer");
    container.innerHTML="Loading...";
    let url=`http://127.0.0.1:5000/get_student_attendance/${selectedStudentId}`;
    try{
        let r=await fetch(url);
        if(!r.ok){
            throw new Error("Error fetching student attendance");
        }
        let data=await r.json();
        rawSAOData=data;
        displayStudentAttendance(data);
    }catch(err){
        console.error("fetchStudentAttendance error:",err);
        container.innerHTML="Error loading attendance.";
    }
}

// data is { "CourseName": [ {date, time, status, ...}, ... ], "OtherCourse": [...], ... }
function displayStudentAttendance(data){
    let container=document.getElementById("attendanceContainer");
    container.innerHTML="";
    if(Object.keys(data).length===0){
        container.innerHTML="<p>No attendance found for this student.</p>";
        return;
    }
    let html="";
    for(let courseName in data){
        let recs = data[courseName];
        html+=`
          <h6>${courseName}</h6>
          <table class="table table-striped my-4">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Unauthorized Abs.</th>
                <th>Authorized Abs.</th>
                <th>Warning</th>
                <th>Fail</th>
                <th>Note</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
        `;
        recs.forEach(r=>{
            html+=`
              <tr>
                <td>${r.date}</td>
                <td>${r.time}</td>
                <td>${r.status}</td>
                <td>${r.totalAbsence}</td>
                <td>${r.authorizedAbsences}</td>
                <td>${r.warning}</td>
                <td>${r.fail}</td>
                <td>${r.note||""}</td>
                <td>${r.last_updated||""}</td>
              </tr>
            `;
        });
        html+="</tbody></table>";
    }
    container.innerHTML=html;
}

// SAO filters
async function applySAOFilters(){
    if(!rawSAOData) return;
    const cName = document.getElementById("saoCourseName").value.trim().toLowerCase();
    const minA  = parseInt(document.getElementById("saoMinAbsences").value)||null;
    const maxA  = parseInt(document.getElementById("saoMaxAbsences").value)||null;
    const sDate = document.getElementById("saoStartDate").value;
    const eDate = document.getElementById("saoEndDate").value;

    let filtered={};

    // rawSAOData: { "CourseName": [ {...}, {...} ], "Course2": [...], ... }
    for(let course of Object.keys(rawSAOData)){
        // if cName is set, check if course name includes cName
        if(cName && !course.toLowerCase().includes(cName)) continue;
        let recs = rawSAOData[course].filter(r=>{
            let pass=true;
            if(minA!==null && r.totalAbsence<minA) pass=false;
            if(maxA!==null && r.totalAbsence>maxA) pass=false;
            // date range
            if(sDate && r.date<sDate) pass=false;
            if(eDate && r.date>eDate) pass=false;
            return pass;
        });
        if(recs.length>0){
            filtered[course]=recs;
        }
    }
    displayStudentAttendance(filtered);
}

function resetSAOFilters(){
    document.getElementById("saoCourseName").value="";
    document.getElementById("saoMinAbsences").value="";
    document.getElementById("saoMaxAbsences").value="";
    document.getElementById("saoStartDate").value="";
    document.getElementById("saoEndDate").value="";
    if(rawSAOData) displayStudentAttendance(rawSAOData);
}

async function fetchPendingExcuses(){
    // optional if you want to show all pending or only for selected student
    // for now let's show all
    const container=document.getElementById("excuseContainer");
    container.innerHTML="Loading pending excuses...";
    try{
        let r=await fetch("http://127.0.0.1:5000/list_all_pending_excuses");
        if(r.ok){
            let data=await r.json();
            displayExcuses(data);
        }else{
            container.innerHTML="Error fetching pending excuses.";
        }
    }catch(e){
        console.error("fetchPendingExcuses error:",e);
        container.innerHTML="Error loading excuses.";
    }
}

function displayExcuses(excuses){
    const container=document.getElementById("excuseContainer");
    container.innerHTML="";
    if(!excuses || excuses.length===0){
        container.innerHTML="<p>No pending excuses.</p>";
        return;
    }
    let html=`
      <table class="table table-striped">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Reason</th>
          <th>Attachments</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
    `;
    excuses.forEach(ex=>{
        const file = ex.attachments ? ex.attachments.split("\\").pop() : "None";
        html+=`
          <tr>
            <td>${ex.student_name||"?"}</td>
            <td>${ex.start_date}</td>
            <td>${ex.end_date}</td>
            <td>${ex.reason}</td>
            <td>${ex.attachments ? `<a href="http://127.0.0.1:5000/view_attachment/${encodeURIComponent(file)}" target="_blank">View</a>` : "None"}</td>
            <td>
              <button class="btn btn-success btn-sm" onclick="reviewExcuse(${ex.excecuse_id}, 'Approved')">Approve</button>
              <button class="btn btn-danger btn-sm" onclick="reviewExcuse(${ex.excecuse_id}, 'Denied')">Deny</button>
            </td>
          </tr>
        `;
    });
    html+="</tbody></table>";
    container.innerHTML=html;
}

async function reviewExcuse(excuseId, status){
    if(!confirm(`Are you sure you want to ${status} this excuse?`)) return;
    try{
        let resp=await fetch("http://127.0.0.1:5000/review_excuse",{
            method:'PATCH',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                excuse_id:excuseId,
                status:status,
                reviewed_by: <?php echo $_SESSION['user_id']; ?>
            })
        });
        let data=await resp.json();
        if(data.success){
            alert(`Excuse ${status} successfully!`);
            fetchPendingExcuses();
        } else {
            alert("Failed to review excuse: "+data.message);
        }
    }catch(e){
        console.error("reviewExcuse error:",e);
        alert("Error reviewing excuse.");
    }
}

async function exportStudentData(){
    if(!selectedStudentId){
        alert("Select a student first.");
        return;
    }
    try{
        let r=await fetch(`http://127.0.0.1:5000/export_attendance/${selectedStudentId}`);
        if(r.ok){
            let blob=await r.blob();
            let link=document.createElement('a');
            link.href=window.URL.createObjectURL(blob);
            link.download=`student_${selectedStudentId}_attendance.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert("No attendance data found for this student.");
        }
    }catch(e){
        console.error("exportStudentData error:",e);
    }
}
</script>
</body>
</html>
