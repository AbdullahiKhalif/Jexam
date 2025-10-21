<?php
// apps/views/departments.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
<div class="container-fluid p-0">

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="deptTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-depts" data-bs-toggle="tab" data-bs-target="#pane-depts" type="button" role="tab" aria-controls="pane-depts" aria-selected="true">
        Departments
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-rules" data-bs-toggle="tab" data-bs-target="#pane-rules" type="button" role="tab" aria-controls="pane-rules" aria-selected="false">
        Department Rules
      </button>
    </li>
  </ul>

  <div class="tab-content" id="deptTabsContent">

    <!-- TAB 1: Departments -->
    <div class="tab-pane fade show active" id="pane-depts" role="tabpanel" aria-labelledby="tab-depts" tabindex="0">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <i class="fa fa-building fs-4 text-primary"></i>
            <span class="fs-5 ms-1">Departments</span>
            <div class="text-muted small">Create, rename, delete departments.</div>
          </div>
          <button class="btn btn-primary" id="btnAddDept">Add Department</button>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tblDept" class="table table-striped table-bordered">
              <thead>
                <tr><th>#</th><th>Name</th><th>Actions</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: Rules -->
    <div class="tab-pane fade" id="pane-rules" role="tabpanel" aria-labelledby="tab-rules" tabindex="0">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <i class="fa fa-link fs-4 text-primary"></i>
            <span class="fs-5 ms-1">Department Rules (Class-code prefixes → Department)</span>
            <div class="text-muted small">Lower priority numbers are applied first.</div>
          </div>
          <div class="d-flex gap-2">
            <select id="filterDept" class="form-select">
              <option value="">All Departments</option>
            </select>
            <button class="btn btn-primary" id="btnAddRule">Add Rule</button>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tblRule" class="table table-striped table-bordered">
              <thead>
                <tr>
                  <th>#</th><th>Prefix</th><th>Department</th><th>Priority</th><th>Order</th><th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->

</div>

<!-- Dept Modal -->
<div class="modal fade" id="mdlDept" tabindex="-1">
  <div class="modal-dialog">
    <form id="frmDept" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="dept_id">
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" id="dept_name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Rule Modal -->
<div class="modal fade" id="mdlRule" tabindex="-1">
  <div class="modal-dialog">
    <form id="frmRule" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Department Rule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rule_id">
        <div class="mb-2">
          <label class="form-label">Prefix (e.g., CA, CNS)</label>
          <input type="text" id="rule_prefix" class="form-control" maxlength="16" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Department</label>
          <select id="rule_department_id" class="form-select" required>
            <option value="">Select Department</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Priority</label>
          <input type="number" id="rule_priority" class="form-control" placeholder="(auto if empty)">
          <div class="form-text">Smaller number = higher priority. Leave blank for auto.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

</main>
<?php include '../layouts/footer.php'; ?>
<!-- Make sure Bootstrap 5 bundle is loaded in header/footer -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/departments.js"></script>
