<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: signinStudent.php");
    exit();
}
include 'databaseConnection.php';

$student_id = $_SESSION['user_id'];
// fetch courses for this student
$qc = "SELECT c.course_id, c.course_name FROM enrollments e JOIN courses c ON e.course_id=c.course_id WHERE e.student_id='$student_id'";
$rc = mysqli_query($con, $qc);

// fetch student name
$qname = "SELECT name FROM users WHERE user_id='$student_id'";
$rname = mysqli_query($con, $qname);
$rrow = mysqli_fetch_assoc($rname);
$student_name = strtolower($rrow['name']);
mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
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
    <h4 class="mb-4">Please Choose Your Course</h4>
    <div class="dropdown mb-3">
        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="dropdownMenuButton">
            Choose a Course
        </button>
        <ul class="dropdown-menu">
        <?php
        if($rc && mysqli_num_rows($rc)>0){
            while($row=mysqli_fetch_assoc($rc)){
                echo "<li><a class='dropdown-item' href='#' onclick='selectCourse(\"".$row['course_id']."\",\"".htmlspecialchars($row['course_name'])."\")'>".htmlspecialchars($row['course_name'])."</a></li>";
            }
        }else{
            echo "<li><a class='dropdown-item' href='#'>No courses found</a></li>";
        }
        ?>
        </ul>
    </div>

    <div id="absence-container" class="mt-5">
        <h5>Total Absences for Selected Course:</h5>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Total Unauthorized Absences</th>
                    <th>Total Authorized Absences</th>
                    <th>Warning</th>
                    <th>Fail</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody id="absence-table-body">
            </tbody>
        </table>
    </div>

    <!-- Excuse Form (Optional) -->
    <div class="excuses-section mt-5">
        <h3 class="text-center">Submit an Excuse</h3>
        <div class="mb-3">
            <label for="excuseReason" class="form-label">Reason</label>
            <select id="commonExcuse" class="form-select" onchange="populateRequirements()">
                <option value="" selected>-- Select Common Excuse --</option>
                <option value="medical">Medical</option>
                <option value="family">Family</option>
                <option value="traveling">Traveling</option>
                <option value="other">Other</option>
            </select>
            <textarea id="excuseReason" class="form-control mt-2" placeholder="Provide additional details"></textarea>
            <div id="requirementInfo" class="mt-2"></div>
        </div>

        <!-- File upload -->
        <div class="mb-3">
            <label for="excuseAttachment" class="form-label">Attachments (Drag/Drop or Click):</label>
            <div id="dropArea" class="border border-primary p-3 text-center">
                Drag & Drop files here or <span class="text-primary">click to upload</span>.
            </div>
            <input type="file" id="excuseAttachment" class="form-control d-none" multiple>
            <div id="fileList" class="mt-2"></div>
        </div>

        <div class="mb-3">
            <label for="excuseStartDate">Start Date</label>
            <input type="date" id="excuseStartDate" class="form-control">
        </div>
        <div class="mb-3">
            <label for="excuseEndDate">End Date</label>
            <input type="date" id="excuseEndDate" class="form-control">
        </div>

        <button class="btn btn-primary" onclick="submitExcuse()">Submit Excuse</button>
    </div>

    <!-- Pending excuses -->
    <div class="pending-excuses-section mt-5">
        <h3 class="text-center">Pending Excuses</h3>
        <div id="pendingExcusesContainer">Loading...</div>
    </div>
</div>

<footer class="footer-frame bg-light py-4 mt-5 text-center">
    <a href="index.html" class="btn btn-outline-secondary">Back</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const socket=io("http://127.0.0.1:5000");
const studentId=<?php echo $student_id; ?>;
const studentName="<?php echo addslashes($student_name); ?>".toUpperCase();
let selectedCourseId=null;
let selectedCourseName=null;

// choose course
function selectCourse(cId,cName){
    selectedCourseId=cId;
    selectedCourseName=cName;
    document.getElementById("dropdownMenuButton").innerText=cName;
    fetchAbsenceData();
}

// fetch absence data for the selected course
async function fetchAbsenceData(){
    if(!selectedCourseId) return;
    try{
        // fetch all enrollments for this student
        let enrResp = await fetch(`http://127.0.0.1:5000/list_enrollments/${studentId}`);
        let enrData = await enrResp.json();
        let enrollmentId = null;
        for(let e of enrData){
            if(e.course_name.toUpperCase()===selectedCourseName.toUpperCase()){
                enrollmentId = e.enrollments_id;
                break;
            }
        }
        if(!enrollmentId){
            alert("Enrollment not found for this course.");
            return;
        }

        // fetch the full attendance across all courses
        let attResp = await fetch(`http://127.0.0.1:5000/get_student_attendance/${studentId}`);
        let data = await attResp.json();

        let tableBody = document.getElementById("absence-table-body");
        tableBody.innerHTML = "";

        // If the student has data for the selectedCourseName
        if(data[selectedCourseName] && data[selectedCourseName].length > 0) {
            // pick the LAST row for summary info
            let recs = data[selectedCourseName];
            let lastIndex = recs.length - 1;
            let rowData = recs[lastIndex];

            let html=`
              <tr>
                <td>${selectedCourseName}</td>
                <td>${rowData.totalAbsence}</td>
                <td>${rowData.authorizedAbsences}</td>
                <td>${rowData.warning}</td>
                <td>${rowData.fail}</td>
                <td>${rowData.last_updated || "N/A"}</td>
              </tr>
            `;
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = "<tr><td colspan='6'>No data found for this course.</td></tr>";
        }
    } catch(err){
        console.error("Error fetchAbsenceData:", err);
    }
}

// excuse form logic
let uploadedFiles=[];
document.getElementById("dropArea").addEventListener("click",()=>{
    document.getElementById("excuseAttachment").click();
});
document.getElementById("excuseAttachment").addEventListener("change",(evt)=>{
    handleFiles(evt.target.files);
});
document.getElementById("dropArea").addEventListener("dragover",(evt)=>{
    evt.preventDefault();
    evt.stopPropagation();
    evt.currentTarget.classList.add("border-success");
});
document.getElementById("dropArea").addEventListener("drop",(evt)=>{
    evt.preventDefault();
    evt.stopPropagation();
    evt.currentTarget.classList.remove("border-success");
    handleFiles(evt.dataTransfer.files);
});

function handleFiles(files){
    for(const f of files){
        uploadedFiles.push(f);
        let item=document.createElement("div");
        item.textContent=f.name;
        document.getElementById("fileList").appendChild(item);
    }
}

function populateRequirements(){
    let val=document.getElementById("commonExcuse").value;
    let info=document.getElementById("requirementInfo");
    info.innerHTML="";
    if(val==="medical"){
        info.innerHTML="Requirement: attach a medical certificate.";
    } else if(val==="family"){
        info.innerHTML="Requirement: provide family emergency details.";
    } else if(val==="traveling"){
        info.innerHTML="Requirement: attach ticket or itinerary.";
    } else if(val==="other"){
        info.innerHTML="Provide details.";
    }
}

async function submitExcuse(){
    let reason=document.getElementById("excuseReason").value.trim();
    let startDate=document.getElementById("excuseStartDate").value;
    let endDate=document.getElementById("excuseEndDate").value;
    if(!reason || !startDate || !endDate){
        alert("Fill all fields.");
        return;
    }
    const formData=new FormData();
    formData.append("student_id",studentId);
    formData.append("reason",reason);
    formData.append("start_date",startDate);
    formData.append("end_date",endDate);
    let input=document.getElementById("excuseAttachment");
    if(input.files){
        for(let f of input.files){
            formData.append("attachments",f);
        }
    }
    try{
        let resp=await fetch("http://127.0.0.1:5000/submit_excuse",{
            method:"POST",
            body:formData
        });
        let data=await resp.json();
        if(data.success){
            alert("Excuse submitted successfully!");
        }else{
            alert("Error: "+data.message);
        }
    }catch(err){
        console.error("submitExcuse error:",err);
    }
}

// pending excuses
async function fetchPendingExcuses(){
    let container=document.getElementById("pendingExcusesContainer");
    container.innerHTML="Loading...";
    try{
        let r=await fetch(`http://127.0.0.1:5000/get_pending_excuses/${studentId}`);
        let data=await r.json();
        if(data.length===0){
            container.innerHTML="<p>No pending excuses.</p>";
            return;
        }
        let html=`
          <table class="table table-bordered mt-3">
            <thead>
              <tr>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Reason</th>
                <th>Attachments</th>
              </tr>
            </thead>
            <tbody>
        `;
        data.forEach(ex=>{
            let fileName=ex.attachments?ex.attachments.split("\\").pop():"";
            html+=`
              <tr>
                <td>${ex.start_date}</td>
                <td>${ex.end_date}</td>
                <td>${ex.reason}</td>
                <td>${fileName?`<a href="http://127.0.0.1:5000/view_attachment/${encodeURIComponent(fileName)}" target="_blank">View</a>`:"None"}</td>
              </tr>
            `;
        });
        html+="</tbody></table>";
        container.innerHTML=html;
    }catch(err){
        console.error("fetchPendingExcuses error:",err);
        container.innerHTML="Error loading pending excuses.";
    }
}
// socket events if needed
socket.on("attendance_update", data=>{
    // if it pertains to this student
    // you can decide to re-fetch or not
});
</script>
</body>
</html>
