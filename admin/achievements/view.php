<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Get user ID from URL
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$user_id) {
    set_message('Invalid student ID', 'danger');
    redirect('index.php');
}

// Get student details
$stmt = $conn->prepare("
    SELECT 
        id,
        CONCAT(first_name, ' ', last_name) as student_name,
        email,
        points,
        profile_picture
    FROM users 
    WHERE id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    set_message('Student not found', 'danger');
    redirect('index.php');
}

// Get student's achievements
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.name,
        a.description,
        a.icon,
        a.points_required,
        ua.achieved_at
    FROM user_achievements ua
    JOIN achievements a ON ua.achievement_id = a.id
    WHERE ua.user_id = ?
    ORDER BY ua.achieved_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$achievements = $stmt->get_result();

// Set page title
$page_title = $student['student_name'] . '\'s Achievements';

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <a href="index.php" class="text-decoration-none text-muted me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        <i class="bi bi-trophy-fill text-warning me-2"></i>
        <?php echo htmlspecialchars($student['student_name']); ?>'s Achievements
    </h1>
</div>

<div class="row">
    <!-- Student Info Card -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($student['profile_picture']) && $student['profile_picture'] !== 'default.jpg'): ?>
                        <img src="/uploads/profiles/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                             class="rounded-circle" width="120" height="120" alt="Profile Picture">
                    <?php else: ?>
                        <i class="bi bi-person-circle" style="font-size: 6rem;"></i>
                    <?php endif; ?>
                </div>
                <h4><?php echo htmlspecialchars($student['student_name']); ?></h4>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($student['email']); ?></p>
                
                <div class="d-flex justify-content-around mb-3">
                    <div class="text-center">
                        <div class="h4 mb-0"><?php echo number_format($student['points']); ?></div>
                        <small class="text-muted">Points</small>
                    </div>
                    <div class="text-center">
                        <div class="h4 mb-0"><?php echo $achievements->num_rows; ?></div>
                        <small class="text-muted">Achievements</small>
                    </div>
                </div>
                
                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" 
                   class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-envelope me-1"></i> Send Message
                </a>
            </div>
        </div>
    </div>
    
    <!-- Achievements List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?php if ($achievements->num_rows > 0): ?>
                    <div class="row g-3">
                        <?php while ($achievement = $achievements->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <div class="me-3 text-warning" style="font-size: 2rem;">
                                                <i class="bi bi-trophy-fill"></i>
                                            </div>
                                            <div>
                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($achievement['name']); ?></h5>
                                                <p class="card-text text-muted small mb-2">
                                                    <?php echo htmlspecialchars($achievement['description']); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-primary">
                                                        <?php echo number_format($achievement['points_required']); ?> points
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($achievement['achieved_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3">
                            <i class="bi bi-emoji-frown display-6 d-block mb-3"></i>
                            No achievements earned yet
                        </div>
                        <p class="text-muted">This student hasn't earned any achievements yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>