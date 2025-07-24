<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = 'Please log in to view your courses';
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// First, get the count of total lessons per course
$query = "SELECT 
    c.*,
    u.first_name, 
    u.last_name,
    (
        SELECT COUNT(*) 
        FROM lessons l 
        WHERE l.course_id = c.id AND l.is_active = 1
    ) as total_lessons,
    (
        SELECT COUNT(DISTINCT up.lesson_id)
        FROM user_progress up
        JOIN lessons l ON up.lesson_id = l.id
        WHERE up.user_id = ? 
        AND up.completed = 1 
        AND l.course_id = c.id
    ) as completed_lessons,
    uc.enrolled_at
FROM user_courses uc
JOIN courses c ON uc.course_id = c.id
JOIN users u ON c.created_by = u.id
WHERE uc.user_id = ? AND c.is_active = 1";

$params = [$user_id, $user_id];
$types = 'ii';

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Add category filter
if (!empty($category)) {
    $query .= " AND c.category = ?";
    $params[] = $category;
    $types .= 's';
}

// Add difficulty filter
if (!empty($difficulty)) {
    $query .= " AND c.difficulty = ?";
    $params[] = $difficulty;
    $types .= 's';
}

// No need for GROUP BY with subqueries

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique categories for filter
$categories_query = "SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = 'My Courses';

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Courses</h1>
        <a href="courses.php" class="btn btn-outline-primary">
            <i class="fas fa-plus me-1"></i> Browse All Courses
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search courses..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="difficulty" class="form-select">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Courses Grid -->
    <?php if (count($courses) > 0): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $course): 
                $progress = $course['total_lessons'] > 0 
                    ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) 
                    : 0;
                $difficulty_badge = [
                    'beginner' => ['class' => 'bg-success', 'text' => 'Beginner'],
                    'intermediate' => ['class' => 'bg-warning', 'text' => 'Intermediate'],
                    'advanced' => ['class' => 'bg-danger', 'text' => 'Advanced']
                ][$course['difficulty'] ?? 'beginner'];
            ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-img-top bg-light" style="height: 150px; background-color: #f8f9fa;">
                            <?php if (!empty($course['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($course['image_url']); ?>" 
                                     class="img-fluid h-100 w-100" 
                                     style="object-fit: cover;" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    <i class="fas fa-book fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?php echo $difficulty_badge['class']; ?> mb-2">
                                    <?php echo $difficulty_badge['text']; ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo $course['total_lessons']; ?> 
                                    <?php echo $course['total_lessons'] == 1 ? 'Lesson' : 'Lessons'; ?>
                                </small>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="card-text text-muted small flex-grow-1">
                                <?php echo mb_strimwidth(strip_tags($course['description']), 0, 100, '...'); ?>
                            </p>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Progress</small>
                                    <small><?php echo $progress; ?>%</small>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $progress; ?>%;" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="course.php?id=<?php echo $course['id']; ?>" 
                               class="btn btn-sm btn-outline-primary w-100">
                                <?php echo $progress > 0 ? 'Continue' : 'Start Learning'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                <h3>No courses found</h3>
                <p class="text-muted">You haven't enrolled in any courses yet.</p>
                <a href="courses.php" class="btn btn-primary mt-3">
                    <i class="fas fa-search me-1"></i> Browse Courses
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
