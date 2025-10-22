<?php
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

    <!-- Header -->
    <div class="header print-hide">
      <div class="container">
        <h1 class="mb-0 text-white"><i class="fa-solid fa-chart-line me-2"></i>Dashboard Overview</h1>
        <div class="text-white-50">Live insight into halls and today’s exams</div>
      </div>
    </div>

    <!-- Filters -->
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
          <label class="form-label">Date</label>
          <input type="date" id="date" class="form-control">
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <button id="btnGenerate" class="btn btn-info">
            <i class="fa fa-rotate me-1"></i> Generate
          </button>
          
        </div>
      </div>

      <div id="errorBox" class="error-details"></div>
    </div>

    <!-- Loading -->
    <div class="loading" id="loading">
      <div class="spinner"></div>
      <div>Loading Dashboard...</div>
    </div>

    <!-- Results -->
    <div class="results-section" id="resultsSection">
      <div id="summaryLine" class="mb-4 text-center fw-semibold fs-5"></div>

      <!-- KPI Cards -->
      <div class="row g-3 mb-4" id="kpiRow"></div>

      <!-- Tables (no charts) -->
      <div class="row g-4 mb-4">
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Over-Capacity Halls</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead><tr><th>Hall</th><th>Capacity</th><th>Allocated</th></tr></thead>
                  <tbody id="tblOverCap"><tr><td colspan="3" class="text-muted">No data</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header fw-bold text-success"><i class="fa-solid fa-circle-check me-2"></i>Empty Halls (Free)</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead><tr><th>Hall</th><th>Capacity</th></tr></thead>
                  <tbody id="tblEmpty"><tr><td colspan="2" class="text-muted">No data</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Classes having exam today -->
      <div class="row">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-header fw-bold"><i class="fa-solid fa-calendar-day me-2"></i>Classes Having Exam Today</div>
            <div class="card-body" id="printArea">
              <div class="table-responsive">
                <table id="tblToday" class="table table-bordered table-hover" style="width:100%">
                  <thead><tr><th>Department</th><th>Cohort</th><th>Campus</th><th>Subject (Today)</th></tr></thead>
                  <tbody id="tblClassesToday"><tr><td colspan="3" class="text-muted">No data</td></tr></tbody>
                </table>
              </div>
            </div>
            <div class="card-footer print-hide d-flex justify-content-center gap-2">
              <button id="btnExportExcel" class="btn btn-primary"><i class="fa-solid fa-file-excel me-2"></i>Export</button>
            
            </div>
          </div>
        </div>
      </div>

    </div>

  </div>
</main>

<?php include '../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="../js/dashboard.js?v=3"></script>>
