<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$page_title = 'Course Catalog';
$user_id = $_SESSION['user_id'];

// Initialize filters
$category = $_GET['category'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build the base query
$query = "SELECT c.*, u.username as instructor_name, 
          (SELECT COUNT(*) FROM user_courses uc WHERE uc.course_id = c.id) as enrolled_students,
          (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count
          FROM courses c 
          LEFT JOIN users u ON c.created_by = u.id 
          WHERE c.is_active = 1";

// Define default points based on difficulty
$default_points = [
    'beginner' => 500,
    'intermediate' => 750,
    'advanced' => 1000
];

$params = [];
$types = '';

// Add search condition
if (!empty($search)) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Add category filter
if (!empty($category)) {
    $query .= " AND c.category = ?";
    $params[] = $category;
    $types .= 's';
}

// Add difficulty filter
if (!empty($difficulty) && in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) {
    $query .= " AND c.difficulty = ?";
    $params[] = $difficulty;
    $types .= 's';
}

// Get unique categories for filter dropdown
$categories_query = "SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['category'];
}

// Execute the main query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$courses = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Check if user is enrolled in each course
foreach ($courses as &$course) {
    $course_id = $course['id'];
    $enrollment_query = "SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?";
    $stmt = mysqli_prepare($conn, $enrollment_query);
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $course_id);
    mysqli_stmt_execute($stmt);
    $enrollment_result = mysqli_stmt_get_result($stmt);
    $course['is_enrolled'] = mysqli_num_rows($enrollment_result) > 0;
}
unset($course); // Break the reference

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Course Catalog</h1>
        <form class="d-flex" method="GET" action="">
            <input class="form-control me-2" type="search" name="search" placeholder="Search courses..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($cat)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="difficulty" class="form-label">Difficulty</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="courses.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Course Grid -->
    <?php if (count($courses) > 0): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $course): ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <span class="badge bg-<?php 
                                echo $course['difficulty'] === 'beginner' ? 'success' : 
                                    ($course['difficulty'] === 'intermediate' ? 'warning' : 'danger'); 
                            ?> float-end">
                                <?php echo ucfirst($course['difficulty']); ?>
                            </span>
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($course['title']); ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 150)) . '...'; ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-user-graduate me-1"></i> 
                                    <?php echo $course['enrolled_students']; ?> students
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-book me-1"></i> 
                                    <?php echo $course['lesson_count']; ?> lessons
                                </small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-user-tie me-1"></i> 
                                    <?php echo htmlspecialchars($course['instructor_name']); ?>
                                </small>
                                <span class="badge bg-info">
                                    <i class="fas fa-star me-1"></i> 
                                    <!-- <?php 
                                        $points = $default_points[strtolower($course['difficulty'])] ?? 500;
                                        echo $points; 
                                    ?> XP -->
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <?php if ($course['is_enrolled']): ?>
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-success w-100">
                                    <i class="fas fa-play-circle me-1"></i> Continue Learning
                                </a>
                            <?php else: ?>
                                <form method="POST" action="enroll.php" class="d-inline w-100">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus-circle me-1"></i> Enroll Now
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No courses found matching your criteria. Try adjusting your filters.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
