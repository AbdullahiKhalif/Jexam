<?php
// layouts/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Role helpers */
$role         = strtoupper(trim($_SESSION['role'] ?? ''));
$isSuperAdmin = ($role === 'SUPPER-ADMIN');                 // only this role sees Setup & Uploads
$isOfficer    = in_array($role, ['SUPPER-ADMIN','ADMIN'], true);
$fullName     = $_SESSION['full_name'] ?? 'User';

/* Active helpers */
$path    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = strtolower(basename($path ?: ''));

function is_active(string $file, string $current): string {
  return strtolower($file) === $current ? 'active' : '';
}
function any_active(array $files, string $current): bool {
  $current = strtolower($current);
  foreach ($files as $f) if (strtolower($f) === $current) return true;
  return false;
}

/* 🔒 Hard redirect for non–SUPPER-ADMIN if they type restricted URLs */
$setupFiles = ['users.php','academic.php','semesters.php','terms.php','campuses.php','shifts.php','departments.php'];
$upFiles    = ['upload.php','allocations_upload.php','upload_periodic.php','subject_upload.php'];
$restricted = array_map('strtolower', array_merge($setupFiles, $upFiles));

if (!$isSuperAdmin && in_array($current, $restricted, true)) {
  header("Location: ../views/dashboard.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Exam Allocation</title>

    <link rel="shortcut icon" href="../../assets/img/favicon.ico"/>
    <link rel="preconnect" href="https://fonts.googleapis.com/"/>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link class="js-stylesheet" href="../../assets/css/light.css" rel="stylesheet"/>
  </head>

  <body data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-behavior="sticky">
    <div class="wrapper">
      <!-- Sidebar -->
      <nav id="sidebar" class="sidebar">
        <div class="sidebar-content js-simplebar">

          <!-- Brand -->
          <div class="d-flex align-items-center justify-content-center py-3">
            <img src="../../assets/img/logo.png" alt="Logo" style="height:40px"/>
            <div class="ms-2 lh-sm text-center">
              <div class="fw-bold text-white" style="font-size:14px;">JAMHURIYA UNIVERSITY</div>
              <div class="fw-bold" style="font-size:12px; color:#4BBF73;">OF Science &amp; Technology</div>
            </div>
          </div>

          <ul class="sidebar-nav">

            <!-- Dashboard (everyone) -->
            <li class="sidebar-item <?= is_active('dashboard.php', $current) ?>">
              <a class="sidebar-link" href="../views/dashboard.php">
                <i class="fa-solid fa-gauge-high me-2"></i> <span>Dashboard</span>
              </a>
            </li>

            <?php if ($isSuperAdmin): ?>
              <!-- Academic Setup (SUPPER-ADMIN only) -->
              <?php
              $setupFiles = ['users.php','academic.php','semesters.php','terms.php','campuses.php','shifts.php','departments.php'];
              $setupOpen  = any_active($setupFiles, $current) ? 'show' : '';
              ?>
              <li class="sidebar-item">
                <a class="sidebar-link <?= $setupOpen ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#menu-setup">
                  <i class="fa-solid fa-sitemap me-2"></i> <span>Academic Setup</span>
                  <span class="badge badge-sidebar-primary">Core</span>
                </a>
                <ul id="menu-setup" class="sidebar-dropdown list-unstyled collapse <?= $setupOpen ? 'show' : '' ?>" data-bs-parent="#sidebar" style="margin-left: -15px;">
                  <li class="sidebar-item <?= is_active('academic.php',$current) ?>">
                    <a class="sidebar-link" href="../views/academic.php">
                      <i class="fa-solid fa-calendar-days me-2"></i> Academic Years + Terms
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('semesters.php',$current) ?>">
                    <a class="sidebar-link" href="../views/semesters.php">
                      <i class="fa-solid fa-layer-group me-2"></i> Semesters
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('terms.php',$current) ?>">
                    <a class="sidebar-link" href="../views/terms.php">
                      <i class="fa-solid fa-bookmark me-2"></i> Terms
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('campuses.php',$current) ?>">
                    <a class="sidebar-link" href="../views/campuses.php">
                      <i class="fa-solid fa-building-columns me-2"></i> Campuses
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('shifts.php',$current) ?>">
                    <a class="sidebar-link" href="../views/shifts.php">
                      <i class="fa-solid fa-clock me-2"></i> Shifts
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('departments.php',$current) ?>">
                    <a class="sidebar-link" href="../views/departments.php">
                      <i class="fa-solid fa-sitemap me-2"></i> Departments &amp; Rules
                    </a>
                  </li>
                  <li class="sidebar-item <?= is_active('users.php',$current) ?>">
                    <a class="sidebar-link" href="../views/users.php">
                      <i class="fa-solid fa-user-gear me-2"></i> User Management
                    </a>
                  </li>
                </ul>
              </li>

              <!-- Uploads (SUPPER-ADMIN only) -->
              <?php
              $upFiles = ['upload.php','allocations_upload.php','upload_periodic.php','subject_upload.php'];
              $upOpen  = any_active($upFiles, $current) ? 'show' : '';
              ?>
              <li class="sidebar-item">
                <a class="sidebar-link <?= $upOpen ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#menu-upload">
                  <i class="fa-solid fa-file-arrow-up me-2"></i> <span>Uploads</span>
                </a>
                <ul id="menu-upload" class="sidebar-dropdown list-unstyled collapse <?= $upOpen ? 'show' : '' ?>" data-bs-parent="#sidebar" style="margin-left: -15px;">
                  <li class="sidebar-item <?= is_active('allocations_upload.php',$current) ?>">
                  <a class="sidebar-link" href="../views/allocations_upload.php">
                    <i class="fa-solid fa-file-arrow-up me-2"></i> Upload Allocations
                  </a>
                </li>
                <li class="sidebar-item <?= is_active('subject_upload.php',$current) ?>">
                  <a class="sidebar-link" href="../views/subject_upload.php">
                    <i class="fa-solid fa-upload me-2"></i> Upload Subjects
                  </a>
                </li>
                </ul>
              </li>
            <?php endif; ?>

            <!-- Halls & Availability (everyone) -->
            <?php
            $csFiles = ['halls_periodic.php'];
            $csOpen  = any_active($csFiles, $current) ? 'show' : '';
            ?>
            <li class="sidebar-item">
              <a class="sidebar-link <?= $csOpen ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#menu-cs">
                 <i class="fa-solid fa-chair me-2"></i> <span>Halls &amp; Availability</span>
              </a>
              <ul id="menu-cs" class="sidebar-dropdown list-unstyled collapse <?= $csOpen ? 'show' : '' ?>" data-bs-parent="#sidebar" style="margin-left: -10px;">
                <li class="sidebar-item <?= is_active('halls_periodic.php',$current) ?>">
                <a class="sidebar-link" href="../views/halls_periodic.php">
                  <i class="fa-solid fa-chair me-2"></i> Halls
                </a>
              </li>
              </ul>
            </li>

            <!-- Reports (everyone) -->
            <?php
            $repFiles = ['reports_allocation.php','report_subjects_assigned.php','report_halls_open.php','report_subject_halls.php'];
            $repOpen  = any_active($repFiles, $current) ? 'show' : '';
            ?>
            <li class="sidebar-item">
              <a class="sidebar-link <?= $repOpen ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#menu-reports">
                <i class="fa-solid fa-chart-pie me-2"></i> <span>Reports</span>
              </a>
              <ul id="menu-reports" class="sidebar-dropdown list-unstyled collapse <?= $repOpen ? 'show' : '' ?>" data-bs-parent="#sidebar" style="margin-left: -25px;">
                <li class="sidebar-item <?= is_active('reports_allocation.php',$current) ?>">
                  <a class="sidebar-link" href="../views/reports_allocation.php">
                    <i class="fa-solid fa-clipboard me-2"></i> <span>Exam Allocation Report</span>
                  </a>
                </li>
                <li class="sidebar-item <?= is_active('report_subjects_assigned.php',$current) ?>">
                  <a class="sidebar-link" href="../views/report_subjects_assigned.php">
                    <i class="fa-solid fa-book me-2"></i> <span>Exam Subject Assigned</span>
                  </a>
                </li>
                <li class="sidebar-item <?= is_active('report_subject_halls.php',$current) ?>">
                  <a class="sidebar-link" href="../views/report_subject_halls.php">
                    <i class="fa-solid fa-door-open me-2"></i> <span>Subject Assigned by Hall</span>
                  </a>
                </li>
                <li class="sidebar-item <?= is_active('report_halls_open.php',$current) ?>">
                  <a class="sidebar-link" href="../views/report_halls_open.php">
                    <i class="fa-solid fa-door-closed me-2"></i> <span>Report Halls Open</span>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Administration (officers only - keep your original rule) -->
            <?php if ($isOfficer): ?>
              <?php
              $adminFiles = ['users.php'];
              $adminOpen  = any_active($adminFiles, $current) ? 'show' : '';
              ?>
              <!-- (Add items here if you later want officer-only submenus) -->
            <?php endif; ?>

          </ul>
        </div>
      </nav>
      <!-- /Sidebar -->
