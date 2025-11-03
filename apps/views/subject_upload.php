<?php
// apps/views/subject_upload.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">

    <div class="card mb-3">
      <div class="card-body row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Academic Year + Semester (active)</label>
          <select id="yearsem" class="form-select"></select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Term (active)</label>
          <select id="term" class="form-select"></select>
          
        </div>
        <div class="col-md-4">
  <label class="form-label d-none">Default duration (min)</label>
          <input type="number" id="duration" class="form-control d-none" value="120" min="1">
        </div>
        <div class="col-md-4"></div>
        <div class="col-md-4 col-sm-12" style="margin-top: -10px;">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="allTerms">
            <label class="form-check-label" for="allTerms">Apply to <b>all active Terms</b> in this Semester</label>
          </div>
        </div>
        <div class="col-md-4"></div>
  

      
      </div>
    </div>

    <div class="card">
      <div class="card-body row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label">Excel file (.xlsx)</label>
          <input type="file" id="file" class="form-control" accept=".xlsx">
          <div class="form-text">
            Required headers on row 1 (case-insensitive):<br>
            <b>Department</b>, <b>Class</b>, <b>Course Code</b>, <b>Course Name</b>, <b>Shift</b>, <b>Date</b>, <b>Campus</b>.<br>
            Date accepted as <code>YYYY-MM-DD</code>, <code>dd/mm/yyyy</code>, or Excel serial. Campus &amp; Shift must match your master tables.
          </div>
        </div>
        <div class="col-md-4 text-end">
          <button id="btnUpload" class="btn btn-primary">Upload</button>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body">
        <pre id="result" class="small mb-0" style="white-space:pre-wrap"></pre>
      </div>
    </div>

  </div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/subject_upload.js?v=2"></script>
