<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = 'Please log in to view this lesson';
    redirect('login.php');
}

// Check if lesson ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid lesson specified';
    redirect('courses.php');
}

$user_id = $_SESSION['user_id'];
$lesson_id = (int)$_GET['id'];

// Get lesson details with course info
$lesson_query = "SELECT l.*, c.id as course_id, c.title as course_title, 
                c.created_by as course_instructor, c.difficulty as course_difficulty
                FROM lessons l
                JOIN courses c ON l.course_id = c.id
                WHERE l.id = ? AND l.is_active = 1 AND c.is_active = 1";
$stmt = $conn->prepare($lesson_query);
$stmt->bind_param('i', $lesson_id);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();

if (!$lesson) {
    $_SESSION['error'] = 'Lesson not found or not available';
    redirect('courses.php');
}

$course_id = $lesson['course_id'];

// Check if user is enrolled in the course
$enrollment_query = "SELECT * FROM user_courses 
                    WHERE user_id = ? AND course_id = ?";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param('ii', $user_id, $course_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();

if (!$enrollment) {
    $_SESSION['error'] = 'You are not enrolled in this course';
    redirect('course.php?id=' . $course_id);
}

// Get all lessons in the course for navigation
$lessons_query = "SELECT id, title, sort_order 
                 FROM lessons 
                 WHERE course_id = ? AND is_active = 1 
                 ORDER BY sort_order ASC, created_at ASC";
$stmt = $conn->prepare($lessons_query);
$stmt->bind_param('i', $course_id);
$stmt->execute();
$all_lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Find current lesson index and get next/previous lessons
$current_lesson_index = null;
$next_lesson = null;
$prev_lesson = null;

foreach ($all_lessons as $index => $l) {
    if ($l['id'] == $lesson_id) {
        $current_lesson_index = $index;
        $prev_lesson = $index > 0 ? $all_lessons[$index - 1] : null;
        $next_lesson = $index < count($all_lessons) - 1 ? $all_lessons[$index + 1] : null;
        break;
    }
}

// Check if this lesson is locked (previous lesson not completed)
$is_locked = false;
if ($current_lesson_index > 0) {
    $prev_lesson_id = $all_lessons[$current_lesson_index - 1]['id'];
    $check_completion = "SELECT completed FROM user_progress 
                        WHERE user_id = ? AND lesson_id = ? AND completed = 1";
    $stmt = $conn->prepare($check_completion);
    $stmt->bind_param('ii', $user_id, $prev_lesson_id);
    $stmt->execute();
    $is_locked = $stmt->get_result()->num_rows === 0;
}

// Mark lesson as started/update last accessed
$now = date('Y-m-d H:i:s');
$update_progress = "INSERT INTO user_progress 
                  (user_id, course_id, lesson_id, last_accessed, completed) 
                  VALUES (?, ?, ?, ?, 0)
                  ON DUPLICATE KEY UPDATE last_accessed = ?";
$stmt = $conn->prepare($update_progress);
$stmt->bind_param('iiiss', $user_id, $course_id, $lesson_id, $now, $now);
$stmt->execute();

// Handle lesson completion
$is_completed = false;
if (isset($_POST['mark_complete']) && !$is_locked) {
    $complete_query = "INSERT INTO user_progress 
                      (user_id, course_id, lesson_id, completed, last_accessed) 
                      VALUES (?, ?, ?, 1, NOW())
                      ON DUPLICATE KEY UPDATE completed = 1, last_accessed = NOW()";
    $stmt = $conn->prepare($complete_query);
    $stmt->bind_param('iii', $user_id, $course_id, $lesson_id);
    if ($stmt->execute()) {
        $is_completed = true;
        
        // Reset statement variable to avoid duplicate closure
        $stmt = null;
        
        // Get course difficulty for points calculation
        $points = 50; // Default points for beginner
        if (!empty($lesson['course_difficulty'])) {
            if ($lesson['course_difficulty'] === 'intermediate') {
                $points = 100;
            } elseif ($lesson['course_difficulty'] === 'advanced') {
                $points = 150;
            }
        }
        
        // Add points to user
        $update_points = "UPDATE users SET 
            points = COALESCE(points, 0) + ?, 
            experience = COALESCE(experience, 0) + ?,
            level = FLOOR(1 + SQRT(COALESCE(experience, 0) + ?) / 10)
            WHERE id = ?";
        $update_stmt = $conn->prepare($update_points);
        $update_stmt->bind_param('iiii', $points, $points, $points, $user_id);
        if ($update_stmt->execute()) {
            // Check for new achievements
            check_and_award_achievements($user_id);
        }
        $update_stmt->close();
        
        // Log the completion
        $action = 'lesson_completed';
        $description = "Completed lesson: " . $lesson['title'] . " (ID: $lesson_id)";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs 
                                  (user_id, action, description, created_at) 
                                  VALUES (?, ?, ?, NOW())");
        $log_stmt->bind_param('iss', $user_id, $action, $description);
        $log_stmt->execute();
        
        $_SESSION['success'] = 'Lesson marked as completed!';
    }
}

// Reset update statement variable
$update_stmt = null;

// Check if lesson is already completed
$completion_check = "SELECT completed FROM user_progress 
                    WHERE user_id = ? AND lesson_id = ? AND completed = 1";
$completion_stmt = $conn->prepare($completion_check);
$completion_stmt->bind_param('ii', $user_id, $lesson_id);
$completion_stmt->execute();
$is_completed = $is_completed || $completion_stmt->get_result()->num_rows > 0;
$completion_stmt->close();

// Set page title
$page_title = $lesson['title'] . ' - ' . $lesson['course_title'];

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $course_id; ?>">
                <?php echo htmlspecialchars($lesson['course_title']); ?>
            </a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($lesson['title']); ?>
            </li>
        </ol>
    </nav>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php echo htmlspecialchars($lesson['title']); ?></h2>
                    <?php if ($is_locked): ?>
                        <div class="alert alert-warning mt-2 mb-0">
                            <i class="fas fa-lock me-2"></i>
                            Complete the previous lesson to unlock this content.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <?php if ($lesson['video_url']): 
                        // Convert YouTube URL to embed format
                        $video_url = $lesson['video_url'];
                        $embed_url = '';
                        
                        // Handle youtu.be short URLs
                        if (str_contains($video_url, 'youtu.be/')) {
                            $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
                            $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                        } 
                        // Handle regular youtube.com URLs
                        else if (str_contains($video_url, 'youtube.com')) {
                            parse_str(parse_url($video_url, PHP_URL_QUERY), $params);
                            $video_id = $params['v'] ?? '';
                            if ($video_id) {
                                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                            }
                        }
                        // Already an embed URL or other video source
                        else {
                            $embed_url = $video_url;
                        }
                        
                        if ($embed_url): ?>
                        <div class="ratio ratio-16x9 mb-4">
                            <iframe src="<?php echo htmlspecialchars($embed_url); ?>" 
                                    title="<?php echo htmlspecialchars($lesson['title']); ?>" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen></iframe>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>

                    <div class="lesson-content">
                        <?php echo $lesson['content'] ? nl2br(htmlspecialchars($lesson['content'])) : '<p>No content available for this lesson.</p>'; ?>
                    </div>
                    
                    <?php if (!$is_locked): ?>
                        <hr>
                        <form method="POST" class="text-end mt-4">
                            <?php if ($is_completed): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    You've completed this lesson!
                                </div>
                            <?php else: ?>
                                <button type="submit" name="mark_complete" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Mark as Complete
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
                
                <!-- Lesson Navigation -->
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <?php if ($prev_lesson): ?>
                            <a href="lesson.php?id=<?php echo $prev_lesson['id']; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Previous Lesson
                            </a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        
                        <?php if ($next_lesson): ?>
                            <?php if (!$is_locked): ?>
                                <a href="lesson.php?id=<?php echo $next_lesson['id']; ?>" 
                                   class="btn btn-primary">
                                    Next Lesson
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    Complete This Lesson to Continue
                                    <i class="fas fa-lock ms-2"></i>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="course.php?id=<?php echo $course_id; ?>" 
                               class="btn btn-primary">
                                Back to Course
                                <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Course Progress -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Course Progress</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get course progress
                    $progress_query = "SELECT 
                        (SELECT COUNT(*) FROM lessons WHERE course_id = ? AND is_active = 1) as total_lessons,
                        (SELECT COUNT(DISTINCT lesson_id) FROM user_progress 
                         WHERE user_id = ? AND course_id = ? AND completed = 1) as completed_lessons";
                    $progress_stmt = $conn->prepare($progress_query);
                    $progress_stmt->bind_param('iii', $course_id, $user_id, $course_id);
                    $progress_stmt->execute();
                    $progress_result = $progress_stmt->get_result();
                    $progress = $progress_result->fetch_assoc();
                    $progress_stmt->close();
                    
                    $total_lessons = $progress['total_lessons'];
                    $completed_lessons = $progress['completed_lessons'];
                    $progress_percent = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
                    ?>
                    
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $progress_percent; ?>%" 
                             aria-valuenow="<?php echo $progress_percent; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                    <p class="mb-0 text-center">
                        <?php echo $completed_lessons; ?> of <?php echo $total_lessons; ?> lessons completed
                    </p>
                    
                    <a href="course.php?id=<?php echo $course_id; ?>" class="btn btn-outline-primary w-100 mt-3">
                        <i class="fas fa-book me-2"></i>Back to Course
                    </a>
                </div>
            </div>
            
            <!-- Lesson List -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Lessons</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($all_lessons as $index => $l): 
                        $is_current = $l['id'] == $lesson_id;
                        $is_available = $index === 0 || 
                            (isset($all_lessons[$index - 1]) && 
                             $conn->query("SELECT 1 FROM user_progress 
                                         WHERE user_id = $user_id 
                                         AND lesson_id = {$all_lessons[$index - 1]['id']} 
                                         AND completed = 1")->num_rows > 0);
                    ?>
                        <a href="lesson.php?id=<?php echo $l['id']; ?>" 
                           class="list-group-item list-group-item-action <?php 
                               echo $is_current ? 'active' : ''; 
                               echo !$is_available ? ' disabled' : '';
                           ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <span class="me-2">
                                    <?php if ($conn->query("SELECT 1 FROM user_progress 
                                                         WHERE user_id = $user_id 
                                                         AND lesson_id = {$l['id']} 
                                                         AND completed = 1")->num_rows > 0): ?>
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                    <?php else: ?>
                                        <i class="far fa-circle me-2"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($l['title']); ?>
                                </span>
                                <?php if ($is_current): ?>
                                    <span class="badge bg-primary">Now Playing</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
