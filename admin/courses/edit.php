<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

$page_title = 'Edit Course';
$errors = [];

// Get course ID from URL
$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$course_id) {
    set_message('Invalid course ID', 'danger');
    redirect('index.php');
}

// Fetch course data
$course = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_message('Course not found', 'danger');
    redirect('index.php');
}

$course = $result->fetch_assoc();

// Debug: Log POST data
error_log('Edit form submitted. POST data: ' . print_r($_POST, true));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'beginner';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate input
    if (empty($title)) {
        $errors[] = 'Course title is required';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Title must be less than 255 characters';
    }
    
    if (empty($description)) {
        $errors[] = 'Course description is required';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required';
    } elseif (strlen($category) > 100) {
        $errors[] = 'Category must be less than 100 characters';
    }
    
    // If no errors, update the course
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // First, check if the course exists
            $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
            $check->bind_param('i', $course_id);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows === 0) {
                throw new Exception('Course not found');
            }
            
            // Update course
            $update_sql = "UPDATE courses 
                          SET title = ?, 
                              description = ?, 
                              category = ?, 
                              difficulty = ?, 
                              is_active = ?, 
                              updated_at = NOW() 
                          WHERE id = ?";
                          
            error_log("SQL Query: $update_sql");
            error_log("Params: title=$title, category=$category, difficulty=$difficulty, is_active=$is_active, id=$course_id");
            
            $stmt = $conn->prepare($update_sql);
            if ($stmt === false) {
                error_log('Prepare failed: ' . $conn->error);
                throw new Exception('Failed to prepare the update statement');
            }
            
            $bind_result = $stmt->bind_param('ssssii', 
                $title, 
                $description, 
                $category, 
                $difficulty, 
                $is_active,
                $course_id
            );
            
            if ($bind_result === false) {
                error_log('Bind param failed: ' . $stmt->error);
                throw new Exception('Failed to bind parameters');
            }
            
            if ($stmt->execute()) {
                // Check if any rows were affected
                if ($stmt->affected_rows > 0) {
                    // Log the activity
                    log_activity($_SESSION['user_id'], 'course_updated', "Course ID: $course_id");
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message and refresh the page to show updated data
                    set_message('Course updated successfully', 'success');
                    error_log("Course #$course_id updated successfully");
                    redirect("edit.php?id=$course_id");
                } else {
                    error_log("No rows affected when updating course #$course_id");
                    throw new Exception('No changes were made to the course.');
                }
            } else {
                error_log('MySQL Error: ' . $stmt->error);
                throw new Exception('Database error occurred while updating the course');
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = 'An error occurred while updating the course. Please try again.';
            error_log('Course update error: ' . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <a href="index.php" class="text-decoration-none text-muted me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        Edit Course
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../lessons/index.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-primary me-2">
            <i class="bi bi-journal-text me-1"></i> Manage Lessons
        </a>
        <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Courses
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5 class="alert-heading">Please fix the following errors:</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="" id="courseForm" onsubmit="return validateForm()">
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="title" class="form-label">Course Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($course['title']); ?>" required>
                        <div class="form-text">A clear and concise title for your course</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="6"
                                data-required="true"><?php echo htmlspecialchars($course['description']); ?></textarea>
                        <div class="invalid-feedback">Please provide a course description</div>
                        <div class="form-text">Provide a detailed description of your course</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            Course Details
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?php echo htmlspecialchars($course['category']); ?>" required>
                                <div class="form-text">e.g., Web Development, Data Science</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="difficulty" class="form-label">Difficulty</label>
                                <select class="form-select" id="difficulty" name="difficulty">
                                    <option value="beginner" <?php echo $course['difficulty'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo $course['difficulty'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo $course['difficulty'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                    <?php echo $course['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive courses won't be visible to students</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Update Course
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCourseModal">
                                    <i class="bi bi-trash me-1"></i> Delete Course
                                </button>
                            </div>
                            
                            <div class="mt-3 pt-3 border-top">
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-info-circle me-1"></i> Course Details
                                </div>
                                <ul class="list-unstyled small">
                                    <li class="mb-1">
                                        <i class="bi bi-calendar me-1"></i> 
                                        Created: <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                                    </li>
                                    <?php if (!empty($course['updated_at'])): ?>
                                    <li class="mb-1">
                                        <i class="bi bi-arrow-repeat me-1"></i> 
                                        Last updated: <?php echo date('M d, Y', strtotime($course['updated_at'])); ?>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SimpleMDE Editor for Description -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
    // Form validation function
    function validateForm() {
        let isValid = true;
        
        // Validate title
        const title = document.getElementById('title').value.trim();
        if (!title) {
            document.getElementById('title').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('title').classList.remove('is-invalid');
        }
        
        // Validate category
        const category = document.getElementById('category').value.trim();
        if (!category) {
            document.getElementById('category').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('category').classList.remove('is-invalid');
        }
        
        // Validate description (from SimpleMDE)
        const description = easyMDE.value().trim();
        const descriptionField = document.getElementById('description');
        if (!description) {
            descriptionField.classList.add('is-invalid');
            isValid = false;
        } else {
            descriptionField.classList.remove('is-invalid');
        }
        
        // If validation fails, scroll to first error
        if (!isValid) {
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return isValid;
    }
    
    // Initialize SimpleMDE editor
    const easyMDE = new EasyMDE({
        element: document.getElementById('description'),
        spellChecker: false,
        status: false,
        placeholder: 'Enter course description...',
        toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', 'preview'],
        forceSync: true, // Ensure the textarea is always in sync
        autoDownloadFontAwesome: false
    });
    
    // Update the hidden textarea before form submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('courseForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Update the hidden textarea with the current editor content
                const descriptionTextarea = document.getElementById('description');
                if (descriptionTextarea) {
                    descriptionTextarea.value = easyMDE.value();
                }
                
                // Validate form
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // If validation passes, the form will submit normally
                return true;
            });
        }
        
        // Remove invalid class when user starts typing
        document.getElementById('title').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
        
        document.getElementById('category').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
        
        // For description (SimpleMDE)
        easyMDE.codemirror.on('change', function() {
            const descriptionField = document.getElementById('description');
            if (easyMDE.value().trim()) {
                descriptionField.classList.remove('is-invalid');
            }
        });
    });
</script>

<!-- Delete Course Confirmation Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-labelledby="deleteCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCourseModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the course <strong><?php echo htmlspecialchars($course['title']); ?></strong>?</p>
                <p class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i> This action cannot be undone and will permanently delete the course and all associated data.</p>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                    <label class="form-check-label" for="confirmDelete">
                        I understand that this action cannot be undone
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <form id="deleteCourseForm" action="delete.php" method="post" class="d-inline">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash me-1"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Enable/disable delete button based on confirmation checkbox
const confirmDelete = document.getElementById('confirmDelete');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const deleteForm = document.getElementById('deleteCourseForm');

if (confirmDelete && confirmDeleteBtn) {
    confirmDelete.addEventListener('change', function() {
        confirmDeleteBtn.disabled = !this.checked;
    });
    
    // Reset the form when modal is closed
    const deleteModal = document.getElementById('deleteCourseModal');
    if (deleteModal) {
        deleteModal.addEventListener('hidden.bs.modal', function () {
            confirmDelete.checked = false;
            confirmDeleteBtn.disabled = true;
        });
    }
    
    // Handle form submission with AJAX
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successAlert = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(deleteModal);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Redirect to courses list
                    window.location.href = data.redirect;
                } else {
                    throw new Error(data.message || 'Failed to delete course');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
