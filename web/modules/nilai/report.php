<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

$auth = new AuthHelper();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('/web/index.php', 'Please login first.', 'danger');
}

$db = (new Database())->getConnection();
$user = $auth->getCurrentUser();

// Get student ID if viewing specific student's report
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

// If teacher/admin is viewing specific student's report, verify access
if ($siswa_id > 0) {
    if ($user['peran'] === 'guru') {
        // Check if student is in teacher's class
        $stmt = $db->prepare("
            SELECT DISTINCT s.id 
            FROM siswa s
            JOIN jadwal j ON s.kelas_id = j.kelas_id
            WHERE s.id = ? AND j.guru_id = (SELECT id FROM guru WHERE pengguna_id = ?)
        ");
        $stmt->bind_param("ii", $siswa_id, $user['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            redirect('/web/modules/nilai/index.php', 'Access denied. Student not in your class.', 'danger');
        }
    } elseif ($user['peran'] !== 'admin') {
        redirect('/web/modules/nilai/index.php', 'Access denied.', 'danger');
    }
} elseif ($user['peran'] === 'siswa') {
    // Get student's own ID
    $stmt = $db->prepare("SELECT id FROM siswa WHERE pengguna_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        redirect('/web/index.php', 'Student record not found.', 'danger');
    }
    $siswa_id = $result->fetch_assoc()['id'];
}

// Get student info
$stmt = $db->prepare("
    SELECT s.*, k.nama as kelas_nama, k.tingkat, p.nama as siswa_nama
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    JOIN pengguna p ON s.pengguna_id = p.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    redirect('/web/modules/nilai/index.php', 'Student not found.', 'danger');
}

// Get available semesters
$semesters = $db->query("
    SELECT DISTINCT semester 
    FROM nilai 
    WHERE siswa_id = $siswa_id 
    ORDER BY semester DESC
")->fetch_all(MYSQLI_ASSOC);

// Get selected semester
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : 
    (!empty($semesters) ? $semesters[0]['semester'] : '');

$page_title = "Grade Report";

// Get detailed grades
if (!empty($selected_semester)) {
    // Get grades by assessment type
    $stmt = $db->prepare("
        SELECT n.*, mp.nama as mata_pelajaran_nama, 
               jn.nama as jenis_penilaian_nama, jn.bobot,
               t.judul as tugas_judul,
               p.nama as guru_nama
        FROM nilai n
        JOIN mata_pelajaran mp ON n.mata_pelajaran_id = mp.id
        JOIN jenis_penilaian jn ON n.jenis_penilaian_id = jn.id
        JOIN pengumpulan_tugas pt ON n.pengumpulan_tugas_id = pt.id
        JOIN tugas t ON pt.tugas_id = t.id
        JOIN guru g ON n.dinilai_oleh = g.id
        JOIN pengguna p ON g.pengguna_id = p.id
        WHERE n.siswa_id = ? AND n.semester = ?
        ORDER BY mp.nama, jn.nama, n.dinilai_pada
    ");
    $stmt->bind_param("is", $siswa_id, $selected_semester);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get final grades
    $stmt = $db->prepare("
        SELECT rn.*, mp.nama as mata_pelajaran_nama
        FROM rekap_nilai rn
        JOIN mata_pelajaran mp ON rn.mata_pelajaran_id = mp.id
        WHERE rn.siswa_id = ? AND rn.semester = ?
        ORDER BY mp.nama
    ");
    $stmt->bind_param("is", $siswa_id, $selected_semester);
    $stmt->execute();
    $final_grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <!-- Student Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?php echo htmlspecialchars($student['siswa_nama']); ?></h4>
                            <p class="mb-1">
                                <strong>NIS:</strong> <?php echo htmlspecialchars($student['nis']); ?>
                            </p>
                            <p class="mb-0">
                                <strong>Class:</strong> 
                                <?php echo htmlspecialchars($student['tingkat'] . ' ' . $student['kelas_nama']); ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <!-- Semester Selection -->
                            <form method="GET" class="d-inline-block">
                                <input type="hidden" name="siswa_id" value="<?php echo $siswa_id; ?>">
                                <select name="semester" class="form-select form-select-sm d-inline-block w-auto" 
                                        onchange="this.form.submit()">
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['semester']; ?>"
                                            <?php echo $selected_semester === $semester['semester'] ? 'selected' : ''; ?>>
                                            Semester <?php echo $semester['semester']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($selected_semester)): ?>
                <!-- Final Grades -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Final Grades - Semester <?php echo $selected_semester; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th class="text-center">Final Grade</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_grade = 0;
                                    $subject_count = 0;
                                    foreach ($final_grades as $grade): 
                                        $total_grade += $grade['nilai_akhir'];
                                        $subject_count++;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($grade['mata_pelajaran_nama']); ?></td>
                                            <td class="text-center"><?php echo number_format($grade['nilai_akhir'], 2); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php 
                                                    echo $grade['nilai_akhir'] >= 75 ? 'success' : 
                                                        ($grade['nilai_akhir'] >= 60 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php 
                                                    echo $grade['nilai_akhir'] >= 75 ? 'Passed' : 
                                                        ($grade['nilai_akhir'] >= 60 ? 'Remedial' : 'Failed'); 
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($subject_count > 0): ?>
                                        <tr class="table-secondary">
                                            <td><strong>Average</strong></td>
                                            <td class="text-center">
                                                <strong><?php echo number_format($total_grade / $subject_count, 2); ?></strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detailed Grades -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Detailed Grades - Semester <?php echo $selected_semester; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="detailedGradesTable">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Assignment</th>
                                        <th>Type</th>
                                        <th>Weight</th>
                                        <th>Score</th>
                                        <th>Teacher</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $grade): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($grade['mata_pelajaran_nama']); ?></td>
                                            <td><?php echo htmlspecialchars($grade['tugas_judul']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($grade['jenis_penilaian_nama']); ?>
                                            </td>
                                            <td><?php echo $grade['bobot']; ?>%</td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $grade['skor'] >= 75 ? 'success' : 
                                                        ($grade['skor'] >= 60 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $grade['skor']; ?>
                                                </span>
                                                <?php if (!empty($grade['komentar_guru'])): ?>
                                                    <i class="fas fa-comment text-info ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       title="<?php echo htmlspecialchars($grade['komentar_guru']); ?>">
                                                    </i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($grade['guru_nama']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($grade['dinilai_pada'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No grades available for this student.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#detailedGradesTable').DataTable({
        "order": [[0, "asc"], [6, "desc"]], // Sort by subject, then date
        "pageLength": 25,
        "language": {
            "search": "Search grades:"
        }
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
