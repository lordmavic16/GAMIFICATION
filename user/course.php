<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = 'Please log in to view this course';
    redirect('login.php');
}

// Check if course ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid course specified';
    redirect('courses.php');
}

$user_id = $_SESSION['user_id'];
$course_id = (int)$_GET['id'];

// Get course details
$course_query = "SELECT c.*, u.username as instructor_name 
                FROM courses c 
                LEFT JOIN users u ON c.created_by = u.id 
                WHERE c.id = ? AND c.is_active = 1";
$stmt = $conn->prepare($course_query);
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = 'Course not found or not available';
    redirect('courses.php');
}

// Check if user is enrolled in the course
$enrollment_query = "SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param('ii', $user_id, $course_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$is_enrolled = (bool)$enrollment;

// If not enrolled, redirect to enrollment
if (!$is_enrolled) {
    $_SESSION['error'] = 'You are not enrolled in this course';
    redirect('courses.php');
}

// Get course lessons
$lessons_query = "SELECT * FROM lessons 
                 WHERE course_id = ? AND is_active = 1 
                 ORDER BY sort_order ASC, created_at ASC";
$stmt = $conn->prepare($lessons_query);
$stmt->bind_param('i', $course_id);
$stmt->execute();
$lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user progress for this course
$progress_query = "SELECT lesson_id, completed, score, last_accessed 
                  FROM user_progress 
                  WHERE user_id = ? AND course_id = ?";
$stmt = $conn->prepare($progress_query);
$stmt->bind_param('ii', $user_id, $course_id);
$stmt->execute();
$progress_result = $stmt->get_result();

$progress = [];
$completed_lessons = 0;
$total_score = 0;

while ($row = $progress_result->fetch_assoc()) {
    $progress[$row['lesson_id']] = $row;
    if ($row['completed']) {
        $completed_lessons++;
        $total_score += $row['score'] ?? 0;
    }
}

// Calculate course progress
$total_lessons = count($lessons);
$course_progress = $total_lessons > 0 ? ($completed_lessons / $total_lessons) * 100 : 0;
$average_score = $completed_lessons > 0 ? $total_score / $completed_lessons : 0;

// Set page title
$page_title = $course['title'] . ' - Course';

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Course Header -->
    <div class="card mb-4">
        <div class="card-body">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($course['title']); ?></li>
                </ol>
            </nav>
            
            <h1 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h1>
            <p class="text-muted">
                Instructor: <?php echo htmlspecialchars($course['instructor_name']); ?> | 
                Difficulty: <span class="badge bg-<?php 
                    echo $course['difficulty'] === 'beginner' ? 'success' : 
                        ($course['difficulty'] === 'intermediate' ? 'warning' : 'danger'); 
                ?>">
                    <?php echo ucfirst($course['difficulty']); ?>
                </span>
            </p>
            
            <!-- Progress Bar -->
            <div class="progress mb-3" style="height: 20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: <?php echo $course_progress; ?>%" 
                     aria-valuenow="<?php echo $course_progress; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    <?php echo round($course_progress); ?>%
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <small class="text-muted">
                    <?php echo $completed_lessons; ?> of <?php echo $total_lessons; ?> lessons completed
                </small>
                <?php if ($completed_lessons > 0): ?>
                <small class="text-muted">
                    Average Score: <?php echo round($average_score); ?>%
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Lessons List -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Course Content</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($lessons)): ?>
                        <div class="list-group-item">
                            <p class="mb-0 text-muted">No lessons available yet. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lessons as $index => $lesson): 
                            $lesson_progress = $progress[$lesson['id']] ?? null;
                            $is_completed = $lesson_progress && $lesson_progress['completed'];
                            $is_available = $index === 0 || ($index > 0 && isset($progress[$lessons[$index-1]['id']]) && $progress[$lessons[$index-1]['id']]['completed']);
                        ?>
                            <a href="lesson.php?id=<?php echo $lesson['id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo !$is_available ? 'disabled' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if ($is_completed): ?>
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php else: ?>
                                            <i class="far fa-circle me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($lesson['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo $lesson['duration'] ? $lesson['duration'] . ' min' : ''; ?>
                                    </small>
                                </div>
                                <?php if ($lesson['description']): ?>
                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($lesson['description']); ?></p>
                                <?php endif; ?>
                                <?php if ($is_completed && isset($lesson_progress['score'])): ?>
                                    <small class="text-success">
                                        <i class="fas fa-check"></i> Completed (Score: <?php echo $lesson_progress['score']; ?>%)
                                    </small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Course Sidebar -->
        <div class="col-lg-4">
            <!-- Course Actions -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if ($course_progress < 100): ?>
                        <?php 
                        $next_lesson_id = null;
                        foreach ($lessons as $lesson) {
                            if (!isset($progress[$lesson['id']]) || !$progress[$lesson['id']]['completed']) {
                                $next_lesson_id = $lesson['id'];
                                break;
                            }
                        }
                        ?>
                        <a href="lesson.php?id=<?php echo $next_lesson_id; ?>" 
                           class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-play me-2"></i>
                            <?php echo $completed_lessons === 0 ? 'Start Course' : 'Continue Learning'; ?>
                        </a>
                    <?php else: ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-trophy fa-2x mb-2"></i>
                            <h5>Course Completed!</h5>
                            <p class="mb-0">Congratulations on completing this course!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Course Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Course Statistics</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-book me-2"></i>Total Lessons</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $total_lessons; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-check-circle me-2"></i>Completed</span>
                            <span class="badge bg-success rounded-pill"><?php echo $completed_lessons; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-clock me-2"></i>Total Duration</span>
                            <span class="text-muted">
                                <?php 
                                    $total_minutes = array_sum(array_column($lessons, 'duration'));
                                    $hours = floor($total_minutes / 60);
                                    $minutes = $total_minutes % 60;
                                    echo $hours > 0 ? "$hours h $minutes m" : "$minutes m";
                                ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
