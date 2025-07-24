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
$page_title = 'Add New Lesson: ' . htmlspecialchars($course['title']);

// Initialize variables
$errors = [];
$lesson = [
    'title' => '',
    'description' => '',
    'content' => '',
    'video_url' => '',
    'duration' => '',
    'is_active' => 1
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $lesson['title'] = trim($_POST['title'] ?? '');
    $lesson['description'] = trim($_POST['description'] ?? '');
    $lesson['content'] = trim($_POST['content'] ?? '');
    $lesson['video_url'] = trim($_POST['video_url'] ?? '');
    $lesson['duration'] = (int)($_POST['duration'] ?? 0);
    $lesson['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate input
    if (empty($lesson['title'])) {
        $errors[] = 'Lesson title is required';
    }
    
    if (empty($lesson['content'])) {
        $errors[] = 'Lesson content is required';
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get the next sort order
            $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM lessons WHERE course_id = ?");
            $stmt->bind_param('i', $course_id);
            $stmt->execute();
            $sort_order = $stmt->get_result()->fetch_assoc()['next_order'];
            
            // Insert lesson
            $stmt = $conn->prepare("
                INSERT INTO lessons (
                    course_id, title, description, content, video_url, 
                    duration, sort_order, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('isssssii',
                $course_id,
                $lesson['title'],
                $lesson['description'],
                $lesson['content'],
                $lesson['video_url'],
                $lesson['duration'],
                $sort_order,
                $lesson['is_active']
            );
            
            if ($stmt->execute()) {
                $lesson_id = $conn->insert_id;
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'lesson_created', "Lesson ID: $lesson_id - " . $lesson['title']);
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to lessons list with success message
                set_message('Lesson created successfully', 'success');
                redirect("index.php?course_id=$course_id&created=1");
            } else {
                throw new Exception('Failed to create lesson');
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = 'An error occurred while creating the lesson. Please try again.';
            error_log('Lesson creation error: ' . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <a href="index.php?course_id=<?php echo $course_id; ?>" class="text-decoration-none text-muted me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        Add New Lesson
        <small class="text-muted"><?php echo htmlspecialchars($course['title']); ?></small>
    </h1>
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
        <form method="post" id="lessonForm" novalidate>
            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-3">
                        <label for="title" class="form-label">Lesson Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Short Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            ><?php echo htmlspecialchars($lesson['description']); ?></textarea>
                        <div class="form-text">A brief description of what this lesson covers</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Lesson Content <span class="text-danger">*</span></label>
                        <div id="content-wrapper">
                            <textarea class="form-control" id="content" name="content" rows="10"><?php echo htmlspecialchars($lesson['content']); ?></textarea>
                            <div id="content-error" class="invalid-feedback">Please enter the lesson content</div>
                        </div>
                        <div class="form-text">You can use Markdown to format your content</div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            Lesson Details
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="video_url" class="form-label">Video URL (Optional)</label>
                                <input type="url" class="form-control" id="video_url" name="video_url"
                                       value="<?php echo htmlspecialchars($lesson['video_url']); ?>">
                                <div class="form-text">YouTube, Vimeo, or direct video URL</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="duration" name="duration" 
                                       min="0" step="1" value="<?php echo $lesson['duration']; ?>">
                                <div class="form-text">Estimated duration in minutes</div>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       value="1" <?php echo $lesson['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive lessons won't be visible to students</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Create Lesson
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-info-circle me-1"></i> Markdown Tips
                        </div>
                        <div class="card-body small">
                            <p>You can use Markdown to format your content:</p>
                            <ul class="mb-0">
                                <li><code># Heading</code> - Large heading</li>
                                <li><code>## Subheading</code> - Medium heading</li>
                                <li><code>**bold**</code> - <strong>bold text</strong></li>
                                <li><code>*italic*</code> - <em>italic text</em></li>
                                <li><code>- Item</code> - Bullet point</li>
                                <li><code>[Link](url)</code> - <a href="#">Link</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SimpleMDE Editor -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
// Initialize SimpleMDE editor
const easyMDE = new EasyMDE({
    element: document.getElementById('content'),
    spellChecker: false,
    status: false,
    placeholder: 'Enter your lesson content here...',
    toolbar: [
        'bold', 'italic', 'heading', '|',
        'quote', 'unordered-list', 'ordered-list', '|',
        'link', 'image', '|',
        'preview', 'side-by-side', 'fullscreen'
    ]
});

// Form validation
document.getElementById('lessonForm').addEventListener('submit', function(e) {
    // Update the textarea with the editor content
    const contentTextarea = document.getElementById('content');
    const contentWrapper = document.getElementById('content-wrapper');
    const contentError = document.getElementById('content-error');
    
    if (contentTextarea) {
        contentTextarea.value = easyMDE.value();
    }
    
    // Reset previous states
    contentWrapper.classList.remove('is-invalid');
    
    // Basic validation
    const title = document.getElementById('title').value.trim();
    const content = easyMDE.value().trim();
    let isValid = true;
    
    if (!title) {
        e.preventDefault();
        document.getElementById('title').classList.add('is-invalid');
        isValid = false;
    }
    
    if (!content) {
        e.preventDefault();
        contentWrapper.classList.add('is-invalid');
        contentError.style.display = 'block';
        isValid = false;
    }
    
    if (!isValid) {
        // Scroll to the first error
        const firstError = document.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    return isValid;
});

// Clear validation on input
document.getElementById('title').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});

// Initialize the editor with proper event handling
let isEditorInitialized = false;
const initEditor = () => {
    if (isEditorInitialized) return;
    
    easyMDE.codemirror.on('change', function() {
        const content = easyMDE.value().trim();
        const contentWrapper = document.getElementById('content-wrapper');
        
        if (content) {
            contentWrapper.classList.remove('is-invalid');
            const errorElement = contentWrapper.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }
    });
    
    isEditorInitialized = true;
};

// Initialize the editor when the page loads
document.addEventListener('DOMContentLoaded', initEditor);
</script>

<?php include '../includes/footer.php'; ?>
