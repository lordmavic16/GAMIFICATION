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

// Get lesson ID from POST data
$lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
$course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

if (!$lesson_id || !$course_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid lesson or course ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, check if the lesson exists and get its title for logging
    $stmt = $conn->prepare("SELECT title FROM lessons WHERE id = ? AND course_id = ?");
    $stmt->bind_param('ii', $lesson_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Lesson not found or does not belong to the specified course');
    }
    
    $lesson = $result->fetch_assoc();
    
    // Check for dependent records (e.g., user progress, quiz attempts, etc.)
    $check_deps = $conn->prepare("SELECT COUNT(*) as count FROM user_progress WHERE lesson_id = ?");
    $check_deps->bind_param('i', $lesson_id);
    $check_deps->execute();
    $deps_result = $check_deps->get_result()->fetch_assoc();
    
    if ($deps_result['count'] > 0) {
        throw new Exception('Cannot delete lesson because it has associated user progress records.');
    }
    
    // Delete the lesson
    $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
    $stmt->bind_param('ii', $lesson_id, $course_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete lesson');
    }
    
    // Log the activity
    log_activity($_SESSION['user_id'], 'lesson_deleted', "Lesson ID: $lesson_id - " . $lesson['title']);
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Lesson deleted successfully',
        'redirect' => "index.php?course_id=$course_id&deleted=1"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the error
    error_log('Error deleting lesson: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
