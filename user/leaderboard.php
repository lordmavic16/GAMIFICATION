<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    set_message('You must be logged in to view the leaderboard.', 'danger');
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch top 20 users for the leaderboard
$leaderboard_sql = "SELECT 
                        u.id, u.username, u.first_name, u.last_name, u.points, u.level, u.profile_picture,
                        RANK() OVER (ORDER BY u.points DESC) as user_rank
                    FROM users u
                    JOIN user_roles ur ON u.id = ur.user_id
                    JOIN roles r ON ur.role_id = r.id
                    WHERE u.is_active = 1 AND r.name = 'student'
                    ORDER BY u.points DESC, u.username ASC
                    LIMIT 20";
$leaderboard_result = $conn->query($leaderboard_sql);
$top_users = $leaderboard_result->fetch_all(MYSQLI_ASSOC);

// Fetch the current user's rank among students
$user_rank_sql = "SELECT user_rank, points FROM (
                    SELECT u.id, u.points, RANK() OVER (ORDER BY u.points DESC) as user_rank
                    FROM users u
                    JOIN user_roles ur ON u.id = ur.user_id
                    JOIN roles r ON ur.role_id = r.id
                    WHERE u.is_active = 1 AND r.name = 'student'
                 ) as ranked_users WHERE id = ?";
$stmt = $conn->prepare($user_rank_sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$current_user_rank_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Leaderboard';
require_once '../includes/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4"><i class="fas fa-trophy me-2"></i>Leaderboard</h1>
    <p class="lead">See how you stack up against other learners. Keep earning points to climb the ranks!</p>

    <!-- Current User's Rank -->
    <?php if ($current_user_rank_data): ?>
    <div class="card bg-light p-3 mb-4 text-center">
        <h5 class="mb-0">Your Rank: <span class="badge bg-primary fs-5">#<?php echo $current_user_rank_data['user_rank']; ?></span> with <?php echo number_format($current_user_rank_data['points']); ?> points</h5>
    </div>
    <?php endif; ?>

    <!-- Leaderboard Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" class="text-center">Rank</th>
                            <th scope="col">Player</th>
                            <th scope="col" class="text-center">Level</th>
                            <th scope="col" class="text-end">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user): ?>
                            <tr class="<?php echo ($user['id'] == $user_id) ? 'table-info' : ''; ?>">
                                <td class="text-center fw-bold fs-5 align-middle">
                                    <?php 
                                        $rank_icon = '';
                                        if ($user['user_rank'] == 1) $rank_icon = '<i class="fas fa-crown text-warning"></i>';
                                        elseif ($user['user_rank'] == 2) $rank_icon = '<i class="fas fa-medal text-secondary"></i>';
                                        elseif ($user['user_rank'] == 3) $rank_icon = '<i class="fas fa-award text-danger"></i>';
                                        echo $rank_icon . ' ' . $user['user_rank']; 
                                    ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../assets/img/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Avatar" class="rounded-circle me-3" width="40" height="40" onerror="this.onerror=null; this.src='../assets/img/default.jpg';">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center align-middle"><span class="badge bg-info"><?php echo $user['level']; ?></span></td>
                                <td class="text-end fw-bold align-middle"><?php echo number_format($user['points']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
