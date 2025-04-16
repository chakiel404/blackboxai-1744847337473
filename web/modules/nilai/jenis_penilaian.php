<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';

$auth = new AuthHelper();
$validation = new ValidationHelper();

// Check if user is logged in and has admin role
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    redirect('/web/index.php', 'Access denied. Admin privileges required.', 'danger');
}

$db = (new Database())->getConnection();
$page_title = "Assessment Types Management";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama = $validation->sanitizeInput($_POST['nama']);
        $bobot = (float)$_POST['bobot'];
        $deskripsi = $validation->sanitizeInput($_POST['deskripsi'] ?? '');

        $errors = [];

        // Validate input
        if (empty($nama)) {
            $errors[] = "Name is required";
        }
        if ($bobot <= 0 || $bobot > 100) {
            $errors[] = "Weight must be between 0 and 100";
        }

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    // Update existing assessment type
                    $stmt = $db->prepare("
                        UPDATE jenis_penilaian 
                        SET nama = ?, bobot = ?, deskripsi = ?, diperbarui_pada = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("sdsi", $nama, $bobot, $deskripsi, $id);
                    $stmt->execute();
                    $message = "Assessment type updated successfully.";
                } else {
                    // Create new assessment type
                    $stmt = $db->prepare("
                        INSERT INTO jenis_penilaian (nama, bobot, deskripsi, dibuat_pada)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("sds", $nama, $bobot, $deskripsi);
                    $stmt->execute();
                    $message = "Assessment type created successfully.";
                }
                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_message_type'] = 'success';
            } catch (Exception $e) {
                $error = "Failed to save assessment type: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        try {
            // Check if assessment type is in use
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM nilai WHERE jenis_penilaian_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $error = "Cannot delete assessment type that is already in use.";
            } else {
                $stmt = $db->prepare("DELETE FROM jenis_penilaian WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $_SESSION['flash_message'] = "Assessment type deleted successfully.";
                $_SESSION['flash_message_type'] = 'success';
            }
        } catch (Exception $e) {
            $error = "Failed to delete assessment type: " . $e->getMessage();
        }
    }
}

// Get all assessment types
$assessment_types = $db->query("
    SELECT jp.*, 
           (SELECT COUNT(*) FROM nilai WHERE jenis_penilaian_id = jp.id) as usage_count
    FROM jenis_penilaian jp
    ORDER BY jp.nama
")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Assessment Types</h4>
                    <button type="button" class="btn btn-primary" onclick="showModal()">
                        <i class="fas fa-plus"></i> Add Assessment Type
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo $err; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover" id="assessmentTypesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Weight</th>
                                    <th>Description</th>
                                    <th>Usage Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assessment_types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['nama']); ?></td>
                                        <td><?php echo $type['bobot']; ?>%</td>
                                        <td><?php echo htmlspecialchars($type['deskripsi'] ?? '-'); ?></td>
                                        <td><?php echo $type['usage_count']; ?></td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    onclick="showModal(<?php 
                                                        echo htmlspecialchars(json_encode([
                                                            'id' => $type['id'],
                                                            'nama' => $type['nama'],
                                                            'bobot' => $type['bobot'],
                                                            'deskripsi' => $type['deskripsi']
                                                        ])); 
                                                    ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($type['usage_count'] === '0'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="confirmDelete(<?php echo $type['id']; ?>, 
                                                                             '<?php echo htmlspecialchars($type['nama']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assessment Type Modal -->
<div class="modal fade" id="assessmentTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assessment Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="id" id="typeId">
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">Name</label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>

                    <div class="mb-3">
                        <label for="bobot" class="form-label">Weight (%)</label>
                        <input type="number" class="form-control" id="bobot" name="bobot" 
                               min="0" max="100" step="0.01" required>
                        <div class="form-text">
                            Weight will be used to calculate final grades.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Description</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add" id="addButton" class="btn btn-primary">Add</button>
                    <button type="submit" name="edit" id="editButton" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete assessment type: <strong id="deleteTypeName"></strong>?
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="id" id="deleteTypeId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showModal(data = null) {
    const modal = new bootstrap.Modal(document.getElementById('assessmentTypeModal'));
    const form = modal._element.querySelector('form');
    const addButton = document.getElementById('addButton');
    const editButton = document.getElementById('editButton');

    // Reset form
    form.reset();
    
    if (data) {
        // Edit mode
        document.getElementById('typeId').value = data.id;
        document.getElementById('nama').value = data.nama;
        document.getElementById('bobot').value = data.bobot;
        document.getElementById('deskripsi').value = data.deskripsi || '';
        addButton.style.display = 'none';
        editButton.style.display = 'block';
    } else {
        // Add mode
        document.getElementById('typeId').value = '';
        addButton.style.display = 'block';
        editButton.style.display = 'none';
    }

    modal.show();
}

function confirmDelete(id, name) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('deleteTypeId').value = id;
    document.getElementById('deleteTypeName').textContent = name;
    modal.show();
}

// Initialize DataTable
$(document).ready(function() {
    $('#assessmentTypesTable').DataTable({
        "order": [[0, "asc"]], // Sort by name by default
        "pageLength": 25,
        "language": {
            "search": "Search assessment types:"
        }
    });
});

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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
