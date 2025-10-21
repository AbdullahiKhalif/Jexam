<?php
// apps/views/assign_subjects.php
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

    <div class="header print-hide">
      <div class="container">
        <h1 class="mb-0 text-white">Assign Subject to Classes (No File)</h1>
        <div class="text-white-50">Pick Academic Year / Semester / Term / Department, select classes, then enter Subject + Date + Campus + Shift.</div>
      </div>
    </div>

    <div class="upload-container print-hide">
      <div class="row g-3 exam-details-form">

        <div class="col-md-4">
          <label class="form-label">Academic Year + Semester (active)</label>
          <select id="yearsem" class="form-select"></select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Term</label>
          <select id="term" class="form-select"></select>
        </div>

        <div class="col-md-5">
        <label class="form-label">Department</label>
        <select id="department" class="form-select"></select>
        </div>

        <div class="col-12"><hr></div>

        <div class="col-md-6">
        <label class="form-label">Classes in Department</label>
        <div id="classList" class="border rounded p-2" style="max-height: 280px; overflow:auto;"></div>
        <div class="form-text">Tick the classes to assign the subject to.</div>
        </div>

        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Subject name</label>
            <input type="text" id="subject_name" class="form-control" placeholder="e.g., Mathematics II">
          </div>

          <div class="mb-3">
            <label class="form-label">Assigned date (optional)</label>
            <input type="date" id="assigned_date" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">Campus</label>
            <select id="campus" class="form-select"></select>
          </div>

          <div class="mb-3">
            <label class="form-label">Shift</label>
            <select id="shift" class="form-select"></select>
            <div class="form-text">Shifts are filtered by campus policy.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" id="duration" class="form-control" value="120" min="1">
          </div>

          <div class="mb-3">
            <label class="form-label">Level (visual)</label>
            <select id="level" class="form-select">
              <option value="Specific" selected>Specific</option>
              <option value="General">General</option>
            </select>
          </div>

          <div class="text-end">
            <button id="btnAssign" class="btn btn-primary">
              <i class="fa fa-check me-1"></i> Assign
            </button>
          </div>
        </div>

      </div>
    </div>

    <div class="card print-hide">
      <div class="card-body">
        <pre id="result" class="small mb-0" style="white-space:pre-wrap"></pre>
      </div>
    </div>

  </div>
</main>
<?php include '../layouts/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/assign_subjects.js?v=1"></script>
