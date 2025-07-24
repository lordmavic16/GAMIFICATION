<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

$page_title = 'Manage Users';

// Pagination settings
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

// Search term
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$query = "SELECT u.*, GROUP_CONCAT(r.name) as roles 
          FROM users u 
          LEFT JOIN user_roles ur ON u.id = ur.user_id 
          LEFT JOIN roles r ON ur.role_id = r.id ";

// Add search condition if search term exists
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " WHERE u.username LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? ";
}

// Group by user to handle multiple roles
$query .= " GROUP BY u.id ";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u ";
if (!empty($search)) {
    $count_query .= " WHERE u.username LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? ";
}

$stmt = $conn->prepare($count_query);
if (!empty($search)) {
    $stmt->bind_param('sss', $search_term, $search_term, $search_term);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_users = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Add pagination to main query
$query .= " LIMIT ? OFFSET ?";

// Prepare and execute the main query
$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param('sssii', $search_term, $search_term, $search_term, $per_page, $offset);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Users</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Add New User
        </a>
    </div>
</div>

            <!-- User List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Users</h5>
                    <form method="GET" action="" class="input-group" style="max-width: 300px;">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="?" class="btn btn-sm btn-outline-danger ms-1">
                                <i class="bi bi-x"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($user = $result->fetch_assoc()): 
                                        $roles = !empty($user['roles']) ? explode(',', $user['roles']) : ['No Role'];
                                    ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo get_avatar_url($user['profile_picture']); ?>" 
                                                     class="rounded-circle me-2" 
                                                     width="32" 
                                                     height="32" 
                                                     alt="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                     style="object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php foreach ($roles as $role): ?>
                                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars(ucfirst($role)); ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Edit User">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'danger' : 'success'; ?>"
                                                            onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? '0' : '1'; ?>)"
                                                            title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="bi bi-<?php echo $user['is_active'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-people display-6 d-block mb-2"></i>
                                                No users found
                                                <?php if (!empty($search)): ?>
                                                    <p class="mt-2">
                                                        <small>Try adjusting your search or filter to find what you're looking for.</small>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="User pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" <?php echo $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                            </li>
                            
                            <?php
                            // Show first page
                            if ($page > 3): ?>
                                <li class="page-item"><a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">1</a></li>
                                <?php if ($page > 4): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages - 2): ?>
                                <?php if ($page < $total_pages - 3): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $total_pages; ?></a></li>
                            <?php endif; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" <?php echo $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                            </li>
                        </ul>
                        <div class="text-center text-muted small">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_users); ?> of <?php echo $total_users; ?> users
                        </div>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>


<!-- Delete Confirmation Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to <span id="statusAction">deactivate</span> this user?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChange">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle user status
function toggleUserStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    document.getElementById('statusAction').textContent = action;
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    
    document.getElementById('confirmStatusChange').onclick = function() {
        // Send AJAX request to update user status
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&is_active=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to update user status'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating user status');
        });
        
        modal.hide();
    };
    
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
