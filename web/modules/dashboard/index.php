<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

$auth = new AuthHelper();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('/web/index.php', 'Please login to access the dashboard.', 'danger');
}

$page_title = "Dashboard";
$db = (new Database())->getConnection();
$user = $auth->getCurrentUser();

// Get user-specific data
$user_data = [];
if ($user['peran'] === 'siswa') {
    // Get student data
    $stmt = $db->prepare("
        SELECT s.*, k.nama as kelas_nama, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.pengguna_id = ?
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();

    // Get upcoming assignments
    $stmt = $db->prepare("
        SELECT t.*, mp.nama as mata_pelajaran_nama, p.nama as guru_nama
        FROM tugas t
        JOIN mata_pelajaran mp ON t.mata_pelajaran_id = mp.id
        JOIN guru g ON t.guru_id = g.id
        JOIN pengguna p ON g.pengguna_id = p.id
        WHERE t.kelas_id = ? AND t.tenggat_waktu > NOW()
        ORDER BY t.tenggat_waktu ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_data['kelas_id']);
    $stmt->execute();
    $upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get recent materials
    $stmt = $db->prepare("
        SELECT m.*, mp.nama as mata_pelajaran_nama, p.nama as guru_nama
        FROM materi m
        JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
        JOIN guru g ON m.guru_id = g.id
        JOIN pengguna p ON g.pengguna_id = p.id
        WHERE m.kelas_id = ?
        ORDER BY m.dibuat_pada DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_data['kelas_id']);
    $stmt->execute();
    $recent_materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get today's schedule
    $day = date('l');
    $indo_day = str_replace(
        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
        $day
    );
    $stmt = $db->prepare("
        SELECT j.*, mp.nama as mata_pelajaran_nama, p.nama as guru_nama
        FROM jadwal j
        JOIN mata_pelajaran mp ON j.mata_pelajaran_id = mp.id
        JOIN guru g ON j.guru_id = g.id
        JOIN pengguna p ON g.pengguna_id = p.id
        WHERE j.kelas_id = ? AND j.hari = ?
        ORDER BY j.waktu_mulai ASC
    ");
    $stmt->bind_param("is", $user_data['kelas_id'], $indo_day);
    $stmt->execute();
    $today_schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($user['peran'] === 'guru') {
    // Get teacher data
    $stmt = $db->prepare("
        SELECT g.*, COUNT(DISTINCT j.kelas_id) as jumlah_kelas,
               COUNT(DISTINCT j.mata_pelajaran_id) as jumlah_mapel
        FROM guru g
        LEFT JOIN jadwal j ON g.id = j.guru_id
        WHERE g.pengguna_id = ?
        GROUP BY g.id
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();

    // Get upcoming assignments to grade
    $stmt = $db->prepare("
        SELECT t.*, k.nama as kelas_nama, mp.nama as mata_pelajaran_nama,
               COUNT(pt.id) as submission_count
        FROM tugas t
        JOIN kelas k ON t.kelas_id = k.id
        JOIN mata_pelajaran mp ON t.mata_pelajaran_id = mp.id
        LEFT JOIN pengumpulan_tugas pt ON t.id = pt.tugas_id
        WHERE t.guru_id = ? AND t.tenggat_waktu > NOW()
        GROUP BY t.id
        ORDER BY t.tenggat_waktu ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_data['id']);
    $stmt->execute();
    $upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get today's schedule
    $day = date('l');
    $indo_day = str_replace(
        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
        $day
    );
    $stmt = $db->prepare("
        SELECT j.*, k.nama as kelas_nama, mp.nama as mata_pelajaran_nama
        FROM jadwal j
        JOIN kelas k ON j.kelas_id = k.id
        JOIN mata_pelajaran mp ON j.mata_pelajaran_id = mp.id
        WHERE j.guru_id = ? AND j.hari = ?
        ORDER BY j.waktu_mulai ASC
    ");
    $stmt->bind_param("is", $user_data['id'], $indo_day);
    $stmt->execute();
    $today_schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($user['peran'] === 'admin') {
    // Get system statistics
    $stats = [];
    
    // Count users by role
    $role_counts = $db->query("
        SELECT peran, COUNT(*) as count 
        FROM pengguna 
        GROUP BY peran
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($role_counts as $count) {
        $stats[$count['peran'] . '_count'] = $count['count'];
    }

    // Count classes
    $stats['kelas_count'] = $db->query("SELECT COUNT(*) as count FROM kelas")->fetch_assoc()['count'];

    // Count subjects
    $stats['mapel_count'] = $db->query("SELECT COUNT(*) as count FROM mata_pelajaran")->fetch_assoc()['count'];

    // Get recent activities
    $recent_activities = $db->query("
        (SELECT 'material' as type, m.judul as title, m.dibuat_pada as date,
                k.nama as kelas_nama, p.nama as user_nama
         FROM materi m
         JOIN kelas k ON m.kelas_id = k.id
         JOIN guru g ON m.guru_id = g.id
         JOIN pengguna p ON g.pengguna_id = p.id
         ORDER BY m.dibuat_pada DESC
         LIMIT 5)
        UNION ALL
        (SELECT 'assignment' as type, t.judul as title, t.dibuat_pada as date,
                k.nama as kelas_nama, p.nama as user_nama
         FROM tugas t
         JOIN kelas k ON t.kelas_id = k.id
         JOIN guru g ON t.guru_id = g.id
         JOIN pengguna p ON g.pengguna_id = p.id
         ORDER BY t.dibuat_pada DESC
         LIMIT 5)
        ORDER BY date DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($user['nama']); ?>!</h2>

    <?php if ($user['peran'] === 'siswa'): ?>
        <!-- Student Dashboard -->
        <div class="row">
            <div class="col-md-8">
                <!-- Today's Schedule -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Today's Schedule</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_schedule)): ?>
                            <p class="text-muted mb-0">No classes scheduled for today.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($today_schedule as $schedule): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    echo date('H:i', strtotime($schedule['waktu_mulai'])) . ' - ' . 
                                                         date('H:i', strtotime($schedule['waktu_selesai']));
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($schedule['mata_pelajaran_nama']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['guru_nama']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Assignments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_assignments)): ?>
                            <p class="text-muted mb-0">No upcoming assignments.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($upcoming_assignments as $assignment): ?>
                                    <a href="/web/modules/tugas/submit.php?id=<?php echo $assignment['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['judul']); ?></h6>
                                            <small class="text-danger">
                                                Due: <?php echo date('d/m/Y H:i', strtotime($assignment['tenggat_waktu'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($assignment['mata_pelajaran_nama']); ?></p>
                                        <small>Teacher: <?php echo htmlspecialchars($assignment['guru_nama']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Materials -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Learning Materials</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_materials)): ?>
                            <p class="text-muted mb-0">No recent materials.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_materials as $material): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($material['judul']); ?></h6>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($material['dibuat_pada'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($material['mata_pelajaran_nama']); ?></p>
                                        <small>Teacher: <?php echo htmlspecialchars($material['guru_nama']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Student Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>NIS:</strong> <?php echo htmlspecialchars($user_data['nis']); ?></p>
                        <p class="mb-1"><strong>Class:</strong> <?php echo htmlspecialchars($user_data['tingkat'] . ' ' . $user_data['kelas_nama']); ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($user_data['telepon'] ?? '-'); ?></p>
                        <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($user_data['alamat'] ?? '-'); ?></p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="/web/modules/jadwal/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-alt me-2"></i>View Full Schedule
                            </a>
                            <a href="/web/modules/tugas/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tasks me-2"></i>View All Assignments
                            </a>
                            <a href="/web/modules/materi/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-book me-2"></i>View All Materials
                            </a>
                            <a href="/web/modules/auth/profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user me-2"></i>Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($user['peran'] === 'guru'): ?>
        <!-- Teacher Dashboard -->
        <div class="row">
            <div class="col-md-8">
                <!-- Today's Schedule -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Today's Teaching Schedule</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_schedule)): ?>
                            <p class="text-muted mb-0">No classes scheduled for today.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Class</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($today_schedule as $schedule): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    echo date('H:i', strtotime($schedule['waktu_mulai'])) . ' - ' . 
                                                         date('H:i', strtotime($schedule['waktu_selesai']));
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($schedule['mata_pelajaran_nama']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['kelas_nama']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Assignments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_assignments)): ?>
                            <p class="text-muted mb-0">No upcoming assignments.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($upcoming_assignments as $assignment): ?>
                                    <a href="/web/modules/tugas/view.php?id=<?php echo $assignment['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['judul']); ?></h6>
                                            <small class="text-danger">
                                                Due: <?php echo date('d/m/Y H:i', strtotime($assignment['tenggat_waktu'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($assignment['mata_pelajaran_nama']); ?> - 
                                            Class <?php echo htmlspecialchars($assignment['kelas_nama']); ?>
                                        </p>
                                        <small>
                                            <?php echo $assignment['submission_count']; ?> submissions
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Teacher Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Teacher Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>NIP:</strong> <?php echo htmlspecialchars($user_data['nip']); ?></p>
                        <p class="mb-1"><strong>Classes:</strong> <?php echo $user_data['jumlah_kelas']; ?> classes</p>
                        <p class="mb-1"><strong>Subjects:</strong> <?php echo $user_data['jumlah_mapel']; ?> subjects</p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($user_data['telepon'] ?? '-'); ?></p>
                        <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($user_data['alamat'] ?? '-'); ?></p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="/web/modules/jadwal/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-alt me-2"></i>View Teaching Schedule
                            </a>
                            <a href="/web/modules/tugas/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tasks me-2"></i>Manage Assignments
                            </a>
                            <a href="/web/modules/materi/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-book me-2"></i>Manage Materials
                            </a>
                            <a href="/web/modules/auth/profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user me-2"></i>Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Admin Dashboard -->
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3 mb-4">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Students</h6>
                                <h2 class="mb-0"><?php echo $stats['siswa_count'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-user-graduate fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Teachers</h6>
                                <h2 class="mb-0"><?php echo $stats['guru_count'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Classes</h6>
                                <h2 class="mb-0"><?php echo $stats['kelas_count'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-school fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Subjects</h6>
                                <h2 class="mb-0"><?php echo $stats['mapel_count'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($activity['type'] === 'material'): ?>
                                                <i class="fas fa-book text-info me-2"></i>
                                            <?php else: ?>
                                                <i class="fas fa-tasks text-warning me-2"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                        </h6>
                                        <small>
                                            <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        Class: <?php echo htmlspecialchars($activity['kelas_nama']); ?>
                                    </p>
                                    <small>
                                        By: <?php echo htmlspecialchars($activity['user_nama']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="/web/modules/user/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-users me-2"></i>Manage Users
                            </a>
                            <a href="/web/modules/kelas/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-school me-2"></i>Manage Classes
                            </a>
                            <a href="/web/modules/mapel/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-book me-2"></i>Manage Subjects
                            </a>
                            <a href="/web/modules/jadwal/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-alt me-2"></i>Manage Schedules
                            </a>
                            <a href="/web/modules/auth/profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user-cog me-2"></i>System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
