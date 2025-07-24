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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['lesson_id']) || !isset($input['is_active'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$lesson_id = filter_var($input['lesson_id'], FILTER_VALIDATE_INT);
$is_active = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);

if (!$lesson_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit;
}

try {
    // Get lesson title for logging
    $stmt = $conn->prepare("SELECT title FROM lessons WHERE id = ?");
    $stmt->bind_param('i', $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Lesson not found');
    }
    
    $lesson = $result->fetch_assoc();
    
    // Update the lesson status
    $stmt = $conn->prepare("UPDATE lessons SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $is_active, $lesson_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $action = $is_active ? 'lesson_activated' : 'lesson_deactivated';
        log_activity($_SESSION['user_id'], $action, "Lesson ID: $lesson_id - " . $lesson['title']);
        
        // Return success response
        echo json_encode([
            'success' => true, 
            'message' => $is_active ? 'Lesson activated successfully' : 'Lesson deactivated successfully',
            'is_active' => $is_active
        ]);
    } else {
        throw new Exception('Failed to update lesson status');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Error toggling lesson status: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
