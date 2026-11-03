<?php
// apps/views/exam_events.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>

<main class="content">
<div class="container-fluid p-0">
  <div class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label">Year + Sem</label>
        <select id="yearsem" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Term</label>
        <select id="term" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Campus</label>
        <select id="campus" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Shift</label>
        <select id="shift" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Class</label>
        <select id="class" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Subject</label>
        <select id="subject" class="form-select"></select>
      </div>
      <div class="col-12 d-flex justify-content-between mt-3">
        <div>
           <button class="btn btn-primary" id="btnBulkEdit"><i class="fa fa-edit text-white"></i> Bulk Edit Selected</button>
           <button class="btn btn-danger" id="btnBulkDelete"><i class="fa fa-trash text-white"></i> Bulk Delete Selected</button>
        </div>
        <button class="btn btn-info" id="btnRefresh"><i class="fa fa-refresh text-white"></i> Refresh</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Exam Events Management</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="tblExamEvents" class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th style="width: 40px;"><input type="checkbox" id="chkSelectAll" class="form-check-input"></th>
              <th>#</th>
              <th>Class</th>
              <th>Subject</th>
              <th>Exam Date</th>
              <th>Shift</th>
              <th>Campus</th>
              <th>Duration (Min)</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody><!-- filled by JS --></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Exam Event Modal -->
  <div class="modal fade" id="mdlExamEvent" tabindex="-1">
    <div class="modal-dialog">
      <form id="frmExamEvent" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Exam Event</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="event_id">
          
          <div class="mb-2">
            <label class="form-label">Class</label>
            <input type="text" id="event_class" class="form-control" readonly>
          </div>
          
          <div class="mb-2">
            <label class="form-label">Subject</label>
            <input type="text" id="event_subject" class="form-control" readonly>
          </div>
          
          <div class="mb-2">
            <label class="form-label">Exam Date</label>
            <input type="date" id="event_date" class="form-control" required>
          </div>
          
          <div class="mb-2">
            <label class="form-label">Shift</label>
            <select id="event_shift_id" class="form-select"></select>
          </div>
          
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bulk Edit Modal -->
  <div class="modal fade" id="mdlBulkEdit" tabindex="-1">
    <div class="modal-dialog">
      <form id="frmBulkEdit" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Bulk Edit Exam Events</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>You are editing <strong id="bulkCount">0</strong> selected events.</p>
          <div class="mb-2">
            <label class="form-label">New Exam Date</label>
            <input type="date" id="bulk_date" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">New Shift</label>
            <select id="bulk_shift_id" class="form-select"></select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Apply to All</button>
        </div>
      </form>
    </div>
  </div>

</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/exam_events.js"></script>
