<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Get course ID from URL
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if (!$course_id) {
    set_message('Invalid course ID', 'danger');
    redirect('../courses/index.php');
}

// Fetch course details
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE id = ?");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course_result = $stmt->get_result();

if ($course_result->num_rows === 0) {
    set_message('Course not found', 'danger');
    redirect('../courses/index.php');
}

$course = $course_result->fetch_assoc();
$page_title = 'Manage Lessons: ' . htmlspecialchars($course['title']);

// Handle success messages
if (isset($_GET['created'])) {
    set_message('Lesson created successfully', 'success');
    redirect("index.php?course_id=$course_id");
}

if (isset($_GET['updated'])) {
    set_message('Lesson updated successfully', 'success');
    redirect("index.php?course_id=$course_id");
}

if (isset($_GET['deleted'])) {
    set_message('Lesson deleted successfully', 'success');
    redirect("index.php?course_id=$course_id");
}

// Fetch all lessons for this course
$stmt = $conn->prepare("
    SELECT l.*, 
           (SELECT COUNT(*) FROM user_progress WHERE lesson_id = l.id) as student_count
    FROM lessons l 
    WHERE l.course_id = ?
    ORDER BY l.sort_order ASC, l.title ASC
");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$lessons_result = $stmt->get_result();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <a href="../courses/edit.php?id=<?php echo $course_id; ?>" class="text-decoration-none text-muted me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        <?php echo htmlspecialchars($course['title']); ?>
        <small class="text-muted">Lessons</small>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Add New Lesson
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($lessons_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="lessonsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Lesson Title</th>
                            <th class="text-center" style="width: 100px;">Duration</th>
                            <th class="text-center" style="width: 100px;">Students</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="sortable">
                        <?php while ($lesson = $lessons_result->fetch_assoc()): ?>
                            <tr data-lesson-id="<?php echo $lesson['id']; ?>">
                                <td class="sortable-handle text-center text-muted" style="cursor: move;">
                                    <i class="bi bi-grip-vertical"></i>
                                    <span class="d-none"><?php echo $lesson['sort_order']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                    <?php if (!empty($lesson['description'])): ?>
                                        <div class="text-muted small">
                                            <?php echo mb_substr(strip_tags($lesson['description']), 0, 80); ?><?php echo strlen($lesson['description']) > 80 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($lesson['duration'])): ?>
                                        <?php echo floor($lesson['duration'] / 60) . 'm ' . ($lesson['duration'] % 60) . 's'; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo $lesson['student_count']; ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-lesson-status" 
                                               type="checkbox" 
                                               data-lesson-id="<?php echo $lesson['id']; ?>"
                                            <?php echo $lesson['is_active'] ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a href="edit.php?id=<?php echo $lesson['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-lesson" 
                                                data-lesson-id="<?php echo $lesson['id']; ?>"
                                                data-lesson-title="<?php echo htmlspecialchars($lesson['title']); ?>"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3">
                    <i class="bi bi-journal-text display-6 d-block mb-3"></i>
                    No lessons found for this course
                </div>
                <a href="create.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create Your First Lesson
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLessonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the lesson <strong id="deleteLessonTitle"></strong>?</p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> 
                    This action cannot be undone and will permanently delete the lesson and all associated data.
                </p>
                <form id="deleteLessonForm" method="post" action="delete.php">
                    <input type="hidden" name="lesson_id" id="deleteLessonId">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="submit" form="deleteLessonForm" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS for drag and drop reordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
// Initialize sortable
const sortable = new Sortable(document.querySelector('.sortable'), {
    handle: '.sortable-handle',
    animation: 150,
    onEnd: function() {
        // Get the new order
        const lessonOrder = [];
        document.querySelectorAll('#lessonsTable tbody tr').forEach((row, index) => {
            const lessonId = row.getAttribute('data-lesson-id');
            lessonOrder.push({
                id: lessonId,
                order: index + 1
            });
        });
        
        // Send the new order to the server
        fetch('reorder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                course_id: <?php echo $course_id; ?>,
                order: lessonOrder
            })
        });
    }
});

// Handle lesson status toggle
document.querySelectorAll('.toggle-lesson-status').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const lessonId = this.getAttribute('data-lesson-id');
        const isActive = this.checked ? 1 : 0;
        
        fetch('toggle-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lesson_id: lessonId,
                is_active: isActive
            })
        });
    });
});

// Handle delete button clicks
document.querySelectorAll('.delete-lesson').forEach(button => {
    button.addEventListener('click', function() {
        const lessonId = this.getAttribute('data-lesson-id');
        const lessonTitle = this.getAttribute('data-lesson-title');
        
        document.getElementById('deleteLessonId').value = lessonId;
        document.getElementById('deleteLessonTitle').textContent = lessonTitle;
        
        const modal = new bootstrap.Modal(document.getElementById('deleteLessonModal'));
        modal.show();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
