<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/reports_functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Set page title
$page_title = 'Reports Dashboard';

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Get date range from request if provided
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Ensure end time is end of day
$end_date_with_time = $end_date . ' 23:59:59';

// Get all the data
$user_stats = get_user_stats($conn, $start_date, $end_date_with_time);
$course_stats = get_course_stats($conn, $start_date, $end_date_with_time);
$achievement_stats = get_achievement_stats($conn, $start_date, $end_date_with_time);
$activity_data = get_activity_data($conn, $start_date, $end_date_with_time);

// Calculate enrollment count for the period
$enrollments_this_period = 0;
$query = "SELECT COUNT(*) as count FROM user_courses WHERE enrolled_at BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $start_date, $end_date_with_time);
$stmt->execute();
$result = $stmt->get_result();
$enrollments_this_period = $result->fetch_assoc()['count'];

// Calculate previous period for comparison
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -1 month'));
$prev_end_date = $start_date;

// Get previous period enrollments for comparison
$prev_enrollments = 0;
$query = "SELECT COUNT(*) as count FROM user_courses WHERE enrolled_at BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $prev_start_date, $prev_end_date);
$stmt->execute();
$result = $stmt->get_result();
$prev_enrollments = $result->fetch_assoc()['count'];

// Calculate enrollment change percentage
$enrollment_change = 0;
if ($prev_enrollments > 0) {
    $enrollment_change = (($enrollments_this_period - $prev_enrollments) / $prev_enrollments) * 100;
} elseif ($enrollments_this_period > 0) {
    $enrollment_change = 100; // 100% increase from 0
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-graph-up me-2"></i>Reports Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                <i class="bi bi-download me-1"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Date Range Picker -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date"
                       value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Apply Filter
                </button>
                <a href="index.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stats Overview -->
<div class="row mb-4">
    <!-- Users Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0">Total Users</h6>
                        <h3 class="mb-0"><?php echo number_format($user_stats['total_users']); ?></h3>
                    </div>
                </div>
                <div class="text-muted small">
                    <?php 
                    $prev_users = $user_stats['total_users'] - $user_stats['new_users'];
                    $user_change = $prev_users > 0 ? round(($user_stats['new_users'] / $prev_users) * 100, 1) : ($user_stats['new_users'] > 0 ? 100 : 0);
                    if ($user_stats['new_users'] > 0) {
                        echo '<i class="bi bi-arrow-up text-success"></i> ' . $user_change . '% from last period';
                    } else {
                        echo '<span class="text-muted">No change</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Courses Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-journal-bookmark-fill text-success fs-4"></i>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0">Total Courses</h6>
                        <h3 class="mb-0"><?php echo number_format($course_stats['total_courses']); ?></h3>
                    </div>
                </div>
                <div class="text-muted small">
                    <?php 
                    $course_change = 0; // We don't track course changes yet
                    if ($course_change > 0) {
                        echo '<i class="bi bi-arrow-up text-success"></i> ' . $course_change . '% from last period';
                    } else {
                        echo '<span class="text-muted">' . abs($course_change) . '% change</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enrollments Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-people-fill text-warning fs-4"></i>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0">Total Enrollments</h6>
                        <h3 class="mb-0"><?php echo number_format($enrollments_this_period); ?></h3>
                    </div>
                </div>
                <div class="text-muted small">
                    <?php 
                    if ($enrollment_change > 0) {
                        echo '<i class="bi bi-arrow-up text-success"></i> ' . round($enrollment_change, 1) . '% from last period';
                    } else if ($enrollment_change < 0) {
                        echo '<i class="bi bi-arrow-down text-danger"></i> ' . round(abs($enrollment_change), 1) . '% from last period';
                    } else {
                        echo '<span class="text-muted">No change</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Achievements Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-trophy-fill text-info fs-4"></i>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0">Achievements Earned</h6>
                        <h3 class="mb-0"><?php echo number_format($achievement_stats['achievements_awarded']); ?></h3>
                    </div>
                </div>
                <div class="text-muted small">
                    <?php
                    // Calculate achievement change
                    $prev_achievements = $achievement_stats['achievements_awarded'] - $achievement_stats['achievements_awarded'];
                    $achievement_change = $prev_achievements > 0 ? 
                        round((($achievement_stats['achievements_awarded'] - $prev_achievements) / $prev_achievements) * 100, 1) : 
                        ($achievement_stats['achievements_awarded'] > 0 ? 100 : 0);
                    
                    if ($achievement_change > 0) {
                        echo '<i class="bi bi-arrow-up text-success"></i> ' . $achievement_change . '% from last period';
                    } else if ($achievement_change < 0) {
                        echo '<i class="bi bi-arrow-down text-danger"></i> ' . abs($achievement_change) . '% from last period';
                    } else {
                        echo '<span class="text-muted">No change</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Initialize date range picker
document.addEventListener('DOMContentLoaded', function() {
    // Set max date to today for end date
    document.getElementById('end_date').max = new Date().toISOString().split('T')[0];
    
    // Update max start date based on end date
    document.getElementById('end_date').addEventListener('change', function() {
        document.getElementById('start_date').max = this.value;
    });
});

// Export functionality
document.getElementById('exportBtn').addEventListener('click', function() {
    // Get current date range
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // In a real implementation, this would trigger a server-side export
    alert(`Exporting data from ${startDate} to ${endDate}`);
    // window.location.href = `export.php?start_date=${startDate}&end_date=${endDate}`;
});
</script>
