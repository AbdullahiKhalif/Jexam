<?php
// apps/views/reports_allocation.php
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

    <!-- header bar -->
    <div class="header bg-secondary m-2 p-3 print-hide" style="border-radius:8px;">
      <div class="container">
        <h1 class="mb-0 text-white">Exam Allocation Report</h1>
        <div class="text-white-50">Select Year/Semester/Term, Campus, Shift and Date, then generate printable cards.</div>
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
        <div class="col-md-3">
          <label class="form-label">Campus</label>
          <select id="campus" class="form-select"></select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Shift</label>
          <select id="shift" class="form-select"></select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Date (required)</label>
          <input type="date" id="date" class="form-control">
        </div>
        <div class="row m-2">
        <div class="col-md-6 text-end">
          <button id="btnGenerate" class="btn btn-info p-2 rouned">
            <i class="fa fa-refresh text-white"></i> Generate
          </button>
          <button id="btnPrint" class="btn btn-success p-2 rouned">
            <i class="fa fa-print text-white"></i> Print
          </button>
        </div>
      </div>
      </div>
      
    </div>

    <center>
      <div id="resultsSection" class="results-section">
        <div class="row" id="hallCards"></div>
      </div>

      <div id="loading" class="loading print-hide">
        <div class="spinner"></div><div>Loading…</div>
      </div>
      <div id="errorBox" class="error-details print-hide"></div>
    </center>

  </div>
</main>
<?php include '../layouts/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/report_allocation_template.js?v=2"></script>