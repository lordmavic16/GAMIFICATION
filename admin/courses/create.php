<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

$page_title = 'Add New Course';
$errors = [];

// Debug: Log POST data
error_log('Form submitted. POST data: ' . print_r($_POST, true));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Check if form was submitted
    error_log('Form submission detected');
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
    
    // If no errors, save to database
    if (empty($errors)) {
        error_log('No validation errors, attempting to save course');
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Insert course
            $stmt = $conn->prepare("INSERT INTO courses (title, description, category, difficulty, created_by, is_active) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssii', 
                $title, 
                $description, 
                $category, 
                $difficulty, 
                $_SESSION['user_id'], 
                $is_active
            );
            
            if ($stmt->execute()) {
                $course_id = $conn->insert_id;
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'course_created', "Course ID: $course_id");
                
                // Commit transaction
                $conn->commit();
                
                error_log("Course created successfully. ID: $course_id");
                
                // Set success message and redirect
                set_message('Course created successfully', 'success');
                error_log("Redirecting to edit.php?id=$course_id");
                
                // Make sure no output has been sent before redirect
                if (!headers_sent()) {
                    header("Location: edit.php?id=$course_id");
                    exit();
                } else {
                    error_log('Headers already sent, cannot redirect');
                    echo '<script>window.location.href = "edit.php?id=' . $course_id . '";</script>';
                    exit();
                }
            } else {
                throw new Exception('Failed to create course');
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = 'An error occurred while creating the course. Please try again.';
            error_log('Course creation error: ' . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Course</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
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
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                        <div class="form-text">A clear and concise title for your course</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="6"
                                data-required="true"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" required>
                                <div class="form-text">e.g., Web Development, Data Science</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="difficulty" class="form-label">Difficulty</label>
                                <select class="form-select" id="difficulty" name="difficulty">
                                    <option value="beginner" <?php echo ($_POST['difficulty'] ?? 'beginner') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo ($_POST['difficulty'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo ($_POST['difficulty'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                    <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive courses won't be visible to students</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Create Course
                                </button>
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

<?php include '../includes/footer.php'; ?>
