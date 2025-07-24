<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

$page_title = 'Manage Courses';

// Pagination settings
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

// Search term
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$query = "SELECT c.*, u.username as creator 
          FROM courses c 
          LEFT JOIN users u ON c.created_by = u.id 
          WHERE 1=1";

// Add search condition if search term exists
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM courses c WHERE 1=1";
if (!empty($search)) {
    $count_query .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)";
}

$stmt = $conn->prepare($count_query);
if (!empty($search)) {
    $stmt->bind_param('sss', $search_term, $search_term, $search_term);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_courses = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_courses / $per_page);

// Add sorting and pagination to main query
$query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";

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

// Show success message if course was deleted
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            Course has been deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Courses</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Add New Course
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn btn-outline-danger">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Courses List -->
<div class="card">
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Difficulty</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($course = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                    <div class="text-muted small">
                                        <?php echo mb_substr(strip_tags($course['description']), 0, 50); ?><?php echo strlen($course['description']) > 50 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($course['category'] ?? 'Uncategorized'); ?></td>
                                <td>
                                    <?php 
                                    $difficulty_class = [
                                        'beginner' => 'success',
                                        'intermediate' => 'warning',
                                        'advanced' => 'danger'
                                    ][$course['difficulty']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $difficulty_class; ?>">
                                        <?php echo ucfirst($course['difficulty']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($course['creator'] ?? 'System'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $course['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-<?php echo $course['is_active'] ? 'danger' : 'success'; ?>"
                                                onclick="toggleCourseStatus(<?php echo $course['id']; ?>, <?php echo $course['is_active'] ? '0' : '1'; ?>)"
                                                title="<?php echo $course['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="bi bi-<?php echo $course['is_active'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Course pagination" class="mt-4">
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_courses); ?> of <?php echo $total_courses; ?> courses
                    </div>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <div class="text-muted">
                    <i class="bi bi-book display-6 d-block mb-3"></i>
                    No courses found
                    <?php if (!empty($search)): ?>
                        <p class="mt-2">
                            <small>Try adjusting your search or create a new course.</small>
                        </p>
                    <?php else: ?>
                        <p class="mt-2">
                            <small>Get started by creating your first course.</small>
                        </p>
                        <a href="create.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-lg"></i> Create Course
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Change Confirmation Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to <span id="statusAction">deactivate</span> this course?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChange">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle course status
function toggleCourseStatus(courseId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    document.getElementById('statusAction').textContent = action;
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
    
    document.getElementById('confirmStatusChange').onclick = function() {
        fetch(`toggle-status.php?id=${courseId}&status=${newStatus}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to update course status'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the course status.');
        })
        .finally(() => {
            modal.hide();
        });
    };
}
</script>

<?php include '../includes/footer.php'; ?>
