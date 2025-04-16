<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/auth_helper.php';

$auth = new AuthHelper();

// If user is already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    redirect('/web/modules/dashboard/index.php');
}

$page_title = "Welcome to School Management System";

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center py-5" style="background-color: #f8f9fa;">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Welcome Text -->
            <div class="col-lg-7">
                <h1 class="display-4 fw-bold mb-4">School Management System</h1>
                <p class="lead mb-4">
                    A comprehensive platform for managing educational activities, assignments, and communications between 
                    teachers, students, and administrators.
                </p>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary text-white rounded-circle p-3 me-3">
                                <i class="fas fa-book"></i>
                            </div>
                            <div>
                                <h5>Learning Materials</h5>
                                <p class="text-muted">Access and manage educational resources easily.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-success text-white rounded-circle p-3 me-3">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div>
                                <h5>Assignments</h5>
                                <p class="text-muted">Submit and grade assignments efficiently.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-info text-white rounded-circle p-3 me-3">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h5>Class Schedule</h5>
                                <p class="text-muted">Organize and view class schedules.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-warning text-white rounded-circle p-3 me-3">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h5>Progress Tracking</h5>
                                <p class="text-muted">Monitor academic progress in real-time.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Form -->
            <div class="col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Login</h2>

                        <?php echo display_flash_message(); ?>

                        <form action="/web/modules/auth/login.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- System Requirements -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">System Requirements</h5>
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-check text-success me-2"></i>Modern web browser (Chrome, Firefox, Safari)</li>
                            <li><i class="fas fa-check text-success me-2"></i>JavaScript enabled</li>
                            <li><i class="fas fa-check text-success me-2"></i>Stable internet connection</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
