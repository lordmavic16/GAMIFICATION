<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and redirect if not
if (!is_logged_in()) {
    set_message('You must be logged in to view your achievements.', 'danger');
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch user's current points
$stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_points = $stmt->get_result()->fetch_assoc()['points'] ?? 0;
$stmt->close();

// Fetch all achievements and the user's earned achievements
$sql = "SELECT 
            a.id, a.name, a.description, a.icon, a.points_required,
            ua.achieved_at
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
        ORDER BY a.points_required ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Achievements';

// Now, include the header
require_once '../includes/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4"><i class="fas fa-trophy me-2"></i>My Achievements</h1>
    <p class="lead">Track your progress and see all the badges you can earn by collecting points!</p>
    <hr>

    <div class="row g-4">
        <?php foreach ($achievements as $ach) : ?>
            <?php 
                $is_unlocked = !is_null($ach['achieved_at']);
                $progress_percent = 0;
                if (!$is_unlocked) {
                    $progress_percent = ($user_points / $ach['points_required']) * 100;
                    if ($progress_percent > 100) $progress_percent = 100;
                }
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 text-center <?php echo $is_unlocked ? 'border-success' : 'bg-light'; ?>">
                    <div class="card-body">
                        <div class="achievement-icon mb-3 fs-1 <?php echo $is_unlocked ? 'text-warning' : 'text-muted'; ?>">
                            <i class="<?php echo htmlspecialchars($ach['icon']); ?>"></i>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($ach['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($ach['description']); ?></p>
                        
                        <?php if ($is_unlocked) : ?>
                            <p class="text-success mb-0">
                                <i class="fas fa-check-circle me-1"></i> Unlocked on <?php echo date('M j, Y', strtotime($ach['achieved_at'])); ?>
                            </p>
                        <?php else : ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress_percent; ?>%;" aria-valuenow="<?php echo $user_points; ?>" aria-valuemin="0" aria-valuemax="<?php echo $ach['points_required']; ?>">
                                    <?php echo number_format($user_points) . ' / ' . number_format($ach['points_required']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
