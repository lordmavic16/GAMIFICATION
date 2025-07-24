<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Set JSON content type
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get course ID from POST data
$course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

if (!$course_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, check if the course exists
    $check = $conn->prepare("SELECT id, title FROM courses WHERE id = ?");
    $check->bind_param('i', $course_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Course not found');
    }
    
    $course = $result->fetch_assoc();
    
    // Note: In a production environment, you might want to implement soft delete
    // or check for dependencies before deleting
    
    // Check for dependent records (example: lessons, enrollments, etc.)
    $check_deps = $conn->prepare("SELECT COUNT(*) as count FROM user_progress WHERE course_id = ?");
    $check_deps->bind_param('i', $course_id);
    $check_deps->execute();
    $deps_result = $check_deps->get_result()->fetch_assoc();
    
    if ($deps_result['count'] > 0) {
        throw new Exception('Cannot delete course because it has associated user progress records.');
    }
    
    // Delete the course
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param('i', $course_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete course');
    }
    
    // Log the activity
    log_activity($_SESSION['user_id'], 'course_deleted', "Course ID: $course_id - " . $course['title']);
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Course deleted successfully',
        'redirect' => 'index.php?deleted=1'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the error
    error_log('Error deleting course: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
