<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$page_title = 'Dashboard';

// Get user statistics
$user_id = $_SESSION['user_id'];
$stats = [
    'enrolled_courses' => 0,
    'completed_courses' => 0,
    'in_progress_courses' => 0,
    'points' => 0,
    'level' => 1
];

// Get enrolled courses count
$stats['enrolled_courses'] = 0; // Default value

try {
    $sql = "SELECT COUNT(*) as count FROM user_courses WHERE user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['enrolled_courses'] = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    // Table might not exist yet, we'll use the default value of 0
    error_log("Error getting enrolled courses: " . $e->getMessage());
}

// Get completed courses count
$stats['completed_courses'] = 0; // Default value
$stats['in_progress_courses'] = 0; // Default value

try {
    $sql = "SELECT COUNT(DISTINCT course_id) as count FROM user_progress 
            WHERE user_id = ? AND completed = 1";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['completed_courses'] = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
        
        // Calculate in-progress courses
        $stats['in_progress_courses'] = $stats['enrolled_courses'] - $stats['completed_courses'];
    }
} catch (Exception $e) {
    // Table might not exist yet, we'll use the default values
    error_log("Error getting completed courses: " . $e->getMessage());
}

// Get user points and level with error handling for missing columns
$stats['points'] = 0;
$stats['level'] = 1;

try {
    // First check if the columns exist
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'points'");
    if (mysqli_num_rows($check_columns) > 0) {
        $sql = "SELECT points, level, experience, next_level_exp FROM users WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($user_data = mysqli_fetch_assoc($result)) {
                $stats['points'] = $user_data['points'] ?? 0;
                $stats['level'] = $user_data['level'] ?? 1;
                $stats['experience'] = $user_data['experience'] ?? 0;
                $stats['next_level_exp'] = $user_data['next_level_exp'] ?? 100;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Add the missing columns if they don't exist
        $alter_sql = [
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS points INT DEFAULT 0",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS level INT DEFAULT 1",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS experience INT DEFAULT 0",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS next_level_exp INT DEFAULT 100"
        ];
        
        foreach ($alter_sql as $query) {
            mysqli_query($conn, $query);
        }
        
        // Set default values
        $stats['points'] = 0;
        $stats['level'] = 1;
        $stats['experience'] = 0;
        $stats['next_level_exp'] = 100;
    }
} catch (Exception $e) {
    // Log the error and use default values
    error_log("Error getting user stats: " . $e->getMessage());
    $stats['points'] = 0;
    $stats['level'] = 1;
    $stats['experience'] = 0;
    $stats['next_level_exp'] = 100;
}

// Get recent activities
$activities = [];
$sql = "SELECT description, created_at FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $activities[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get recommended courses (simple implementation - you can enhance this)
$recommended_courses = [];
try {
    // First check if courses table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'courses'");
    if (mysqli_num_rows($table_check) > 0) {
        $sql = "SELECT id, title, description, difficulty FROM courses ";
        
        // Only add the subquery if user_courses table exists
        $user_courses_check = mysqli_query($conn, "SHOW TABLES LIKE 'user_courses'");
        if (mysqli_num_rows($user_courses_check) > 0) {
            $sql .= "WHERE id NOT IN (SELECT course_id FROM user_courses WHERE user_id = ?) ";
        }
        
        $sql .= "ORDER BY RAND() LIMIT 3";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Only bind parameter if we're using it in the query
            if (mysqli_num_rows($user_courses_check) > 0) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $recommended_courses[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }
} catch (Exception $e) {
    // Handle any errors gracefully
    error_log("Error getting recommended courses: " . $e->getMessage());
}

include '../includes/header.php';
?>

<!-- Welcome Card -->
<div class="welcome-card p-4 mb-4 text-white">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p class="mb-0">Keep up the good work on your learning journey.</p>
            
            <!-- Experience Progress Bar -->
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-1">
                    <span>Level <?php echo $stats['level']; ?></span>
                    <span><?php echo $stats['experience']; ?>/<?php echo $stats['next_level_exp']; ?> XP</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <?php 
                    $progress = ($stats['experience'] / $stats['next_level_exp']) * 100;
                    $progress = min(100, max(0, $progress)); // Ensure between 0-100
                    ?>
                    <div class="progress-bar bg-white" role="progressbar" 
                         style="width: <?php echo $progress; ?>%;" 
                         aria-valuenow="<?php echo $progress; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center ms-4">
            <div class="display-4 fw-bold">Level <?php echo $stats['level']; ?></div>
            <div class="small"><?php echo $stats['points']; ?> Points</div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Enrolled Courses</h6>
                        <h3 class="mb-0"><?php echo $stats['enrolled_courses']; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-journal-bookmark fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">In Progress</h6>
                        <h3 class="mb-0"><?php echo $stats['in_progress_courses']; ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Completed</h6>
                        <h3 class="mb-0"><?php echo $stats['completed_courses']; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">XP Points</h6>
                        <h3 class="mb-0"><?php echo $stats['points']; ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-star-fill fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activities -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activities</h5>
                <a href="activities.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($activities)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activities as $activity): ?>
                            <li class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 bg-light rounded-circle p-2 me-3">
                                        <i class="bi bi-activity text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center p-4">
                        <p class="text-muted mb-0">No recent activities found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recommended Courses -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Recommended for You</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recommended_courses)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recommended_courses as $course): ?>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <small class="text-muted"><?php echo ucfirst($course['difficulty']); ?></small>
                                </div>
                                <p class="mb-1 small text-muted">
                                    <?php echo mb_strimwidth(htmlspecialchars($course['description']), 0, 70, '...'); ?>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <p class="text-muted mb-0">No recommended courses at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="courses.php" class="btn btn-sm btn-outline-primary w-100">Browse All Courses</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
