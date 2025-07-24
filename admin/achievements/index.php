<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Set page title
$page_title = 'Student Achievements';

// Get search term if any
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$query = "
    SELECT 
        u.id as user_id,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        u.email,
        u.points,
        COUNT(ua.achievement_id) as achievement_count,
        MAX(ua.achieved_at) as last_achievement
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id AND r.name = 'student'
    LEFT JOIN user_achievements ua ON u.id = ua.user_id
    WHERE r.name = 'student'";

// Add search condition if search term exists
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params = [$search_term, $search_term, $search_term];
    $param_types = 'sss';
}

// Group and order
$query .= " GROUP BY u.id
           ORDER BY u.last_name, u.first_name";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($search)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-trophy-fill text-warning me-2"></i>Student Achievements
    </h1>
</div>

<!-- Search Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search students by name or email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-md-4">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear Search
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Achievements List -->
<div class="card">
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th class="text-center">Total Points</th>
                            <th class="text-center">Achievements</th>
                            <th class="text-end">Last Achievement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr style="cursor: pointer;" 
                                onclick="window.location='view.php?user_id=<?php echo $row['user_id']; ?>'"
                                class="hover-bg">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2">
                                            <i class="bi bi-person-circle fs-3"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo number_format($row['points']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success rounded-pill">
                                        <?php echo $row['achievement_count']; ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted small">
                                    <?php 
                                    if ($row['last_achievement']) {
                                        echo date('M j, Y', strtotime($row['last_achievement']));
                                    } else {
                                        echo 'No achievements yet';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3">
                    <i class="bi bi-trophy display-6 d-block mb-3"></i>
                    No student achievements found
                </div>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1"></i> Back to All Students
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.hover-bg:hover {
    background-color: rgba(0, 0, 0, 0.03);
}
</style>

<?php include '../includes/footer.php'; ?>
