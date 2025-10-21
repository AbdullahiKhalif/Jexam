<?php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
<div class="container-fluid p-0">
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
<i class="fa fa-users fs-4 text-primary"></i> <span class="card-title fs-4">Users</span>
<h6 class="card-subtitle text-muted">Create, edit, activate/deactivate users.</h6>
<hr style="border:1px solid #000;">
<button type="button" class="btn btn-primary my-1 float-end" id="addNew">Add New User</button>
</div>
<div class="card-body">
<div class="table-responsive">
    <table id="usersTable" class="table table-striped DataTableResponsive" style="width:100%">
<thead>
<tr>
<th>ID</th><th>Full Name</th><th>Username</th><th>Role</th><th>Active</th><th>Action</th>
</tr>
</thead>
<tbody style="font-size:12px;"></tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>


<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-hidden="true">
<div class="modal-dialog" role="document"><div class="modal-content">
<div class="modal-header">
<h5 class="modal-title fs-4">User</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body m-1">
<div class="alert alert-success p-2 d-none" role="alert">Success</div>
<div class="alert alert-danger p-2 d-none" role="alert">Error</div>
<form id="userForm">
<input type="hidden" id="id" name="id">
<div class="form-group"><label>Name</label><input id="full_name" name="full_name" class="form-control"></div>
<div class="form-group"><label>Username</label><input type="text" id="username" name="username" class="form-control"></div>
<div class="form-group"><label>Role</label>
<select id="role" name="role" class="form-control"><option value="Supper-Admin">Supper-Admin</option><option value="Admin">Admin</option></select>
</div>
<div class="form-group" id="pwWrap"><label>Password</label><input type="password" id="password" name="password" class="form-control" placeholder="min 6 chars"></div>
</form>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button type="submit" form="userForm" class="btn btn-primary">Save</button>
</div>
</div></div>
</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="../js/users.js"></script>