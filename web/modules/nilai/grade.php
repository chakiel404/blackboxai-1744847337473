<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';

$auth = new AuthHelper();
$validation = new ValidationHelper();

// Check if user is logged in and has appropriate role
if (!$auth->isLoggedIn() || !$auth->hasRole(['admin', 'guru'])) {
    redirect('/web/index.php', 'Access denied. Insufficient privileges.', 'danger');
}

$db = (new Database())->getConnection();
$user = $auth->getCurrentUser();

// Get submission ID from URL
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
if ($submission_id <= 0) {
    redirect('/web/modules/tugas/index.php', 'Invalid submission ID.', 'danger');
}

// Get submission data
$stmt = $db->prepare("
    SELECT pt.*, t.judul as tugas_judul, t.bobot_nilai, t.jadwal_id,
           s.id as siswa_id, p.nama as siswa_nama, s.nis,
           mp.id as mata_pelajaran_id, mp.nama as mata_pelajaran_nama,
           j.semester
    FROM pengumpulan_tugas pt
    JOIN tugas t ON pt.tugas_id = t.id
    JOIN siswa s ON pt.siswa_id = s.id
    JOIN pengguna p ON s.pengguna_id = p.id
    JOIN jadwal j ON t.jadwal_id = j.id
    JOIN mata_pelajaran mp ON j.mata_pelajaran_id = mp.id
    WHERE pt.id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    redirect('/web/modules/tugas/index.php', 'Submission not found.', 'danger');
}

// Check if user has permission to grade this submission
if ($user['peran'] === 'guru') {
    $stmt = $db->prepare("
        SELECT j.guru_id 
        FROM jadwal j 
        WHERE j.id = ? AND j.guru_id = (SELECT id FROM guru WHERE pengguna_id = ?)");
    $stmt->bind_param("ii", $submission['jadwal_id'], $user['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        redirect('/web/modules/tugas/index.php', 'You can only grade submissions for your classes.', 'danger');
    }
}

// Get existing grade if any
$stmt = $db->prepare("
    SELECT n.* 
    FROM nilai n 
    WHERE n.pengumpulan_tugas_id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$existing_grade = $stmt->get_result()->fetch_assoc();

// Get grading types
$jenis_penilaian = $db->query("
    SELECT * FROM jenis_penilaian 
    ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$page_title = "Grade Assignment";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skor = (float)$_POST['skor'];
    $komentar = $validation->sanitizeInput($_POST['komentar'] ?? '');
    $jenis_penilaian_id = (int)$_POST['jenis_penilaian_id'];

    $errors = [];

    // Validate input
    if ($skor < 0 || $skor > 100) {
        $errors[] = "Score must be between 0 and 100";
    }

    if ($jenis_penilaian_id <= 0) {
        $errors[] = "Assessment type is required";
    }

    if (empty($errors)) {
        try {
            $db->begin_transaction();

            // Get teacher ID
            $stmt = $db->prepare("SELECT id FROM guru WHERE pengguna_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $guru = $stmt->get_result()->fetch_assoc();
            $guru_id = $guru['id'];

            if ($existing_grade) {
                // Update existing grade
                $stmt = $db->prepare("
                    UPDATE nilai 
                    SET skor = ?, komentar_guru = ?, jenis_penilaian_id = ?,
                        dinilai_oleh = ?, dinilai_pada = NOW(), diperbarui_pada = NOW()
                    WHERE id = ?");
                $stmt->bind_param("dsiii", $skor, $komentar, $jenis_penilaian_id, 
                                $guru_id, $existing_grade['id']);
            } else {
                // Create new grade
                $stmt = $db->prepare("
                    INSERT INTO nilai (pengumpulan_tugas_id, siswa_id, mata_pelajaran_id,
                                     skor, komentar_guru, jenis_penilaian_id, semester,
                                     dinilai_oleh, dinilai_pada, dibuat_pada)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("iiidsis", $submission_id, $submission['siswa_id'],
                                $submission['mata_pelajaran_id'], $skor, $komentar,
                                $jenis_penilaian_id, $submission['semester'], $guru_id);
            }
            $stmt->execute();

            // Update submission status
            $stmt = $db->prepare("
                UPDATE pengumpulan_tugas 
                SET status = 'sudah_dinilai', diperbarui_pada = NOW()
                WHERE id = ?");
            $stmt->bind_param("i", $submission_id);
            $stmt->execute();

            // Calculate and update final grade
            $stmt = $db->prepare("
                SELECT AVG(n.skor * (jn.bobot / 100)) as nilai_akhir
                FROM nilai n
                JOIN jenis_penilaian jn ON n.jenis_penilaian_id = jn.id
                WHERE n.siswa_id = ? AND n.mata_pelajaran_id = ? AND n.semester = ?
                GROUP BY n.siswa_id, n.mata_pelajaran_id, n.semester");
            $stmt->bind_param("iis", $submission['siswa_id'], 
                            $submission['mata_pelajaran_id'], $submission['semester']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $nilai_akhir = $result['nilai_akhir'];

            // Update or insert final grade
            $stmt = $db->prepare("
                INSERT INTO rekap_nilai (siswa_id, mata_pelajaran_id, semester, 
                                       nilai_akhir, dibuat_pada)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    nilai_akhir = VALUES(nilai_akhir),
                    diperbarui_pada = NOW()");
            $stmt->bind_param("iisd", $submission['siswa_id'], 
                            $submission['mata_pelajaran_id'], 
                            $submission['semester'], $nilai_akhir);
            $stmt->execute();

            $db->commit();
            redirect('/web/modules/tugas/view.php?id=' . $submission['tugas_id'], 
                    'Assignment graded successfully.', 'success');
        } catch (Exception $e) {
            $db->rollback();
            $error = "Failed to grade assignment: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Grade Assignment</h4>
                </div>
                <div class="card-body">
                    <!-- Submission Details -->
                    <div class="mb-4">
                        <h5><?php echo htmlspecialchars($submission['tugas_judul']); ?></h5>
                        <p class="mb-2">
                            <strong>Student:</strong> 
                            <?php echo htmlspecialchars($submission['siswa_nama'] . 
                                  ' (' . $submission['nis'] . ')'); ?>
                        </p>
                        <p class="mb-2">
                            <strong>Subject:</strong> 
                            <?php echo htmlspecialchars($submission['mata_pelajaran_nama']); ?>
                        </p>
                        <p class="mb-2">
                            <strong>Submitted:</strong> 
                            <?php echo date('d/m/Y H:i', strtotime($submission['dikumpulkan_pada'])); ?>
                        </p>
                        <?php if (!empty($submission['komentar_siswa'])): ?>
                            <p class="mb-2">
                                <strong>Student's Notes:</strong><br>
                                <?php echo nl2br(htmlspecialchars($submission['komentar_siswa'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($submission['jalur_file'])): ?>
                            <p class="mb-0">
                                <strong>Submission File:</strong>
                                <a href="/web/assets/uploads/<?php echo htmlspecialchars($submission['jalur_file']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="jenis_penilaian_id" class="form-label">Assessment Type</label>
                            <select class="form-select" id="jenis_penilaian_id" name="jenis_penilaian_id" required>
                                <option value="">Select Assessment Type</option>
                                <?php foreach ($jenis_penilaian as $jenis): ?>
                                    <option value="<?php echo $jenis['id']; ?>"
                                            data-bobot="<?php echo $jenis['bobot']; ?>"
                                        <?php echo ($existing_grade && 
                                                   $existing_grade['jenis_penilaian_id'] == $jenis['id']) ? 
                                                   'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($jenis['nama'] . 
                                              ' (Weight: ' . $jenis['bobot'] . '%)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                The weight will be used to calculate the final grade.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="skor" class="form-label">Score (0-100)</label>
                            <input type="number" class="form-control" id="skor" name="skor" 
                                   min="0" max="100" step="0.01" required
                                   value="<?php echo $existing_grade ? $existing_grade['skor'] : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="komentar" class="form-label">Comments</label>
                            <textarea class="form-control" id="komentar" name="komentar" rows="3"
                                    ><?php echo $existing_grade ? 
                                          htmlspecialchars($existing_grade['komentar_guru']) : ''; ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/web/modules/tugas/view.php?id=<?php echo $submission['tugas_id']; ?>" 
                               class="btn btn-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $existing_grade ? 'Update Grade' : 'Save Grade'; ?>
                            </button>
                        </div>
                    </form>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
