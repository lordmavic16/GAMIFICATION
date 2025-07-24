<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

header('Content-Type: application/json');

// Get and validate input
$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT);

if (!$course_id || $new_status === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Update course status
    $stmt = $conn->prepare("UPDATE courses SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $new_status, $course_id);
    $result = $stmt->execute();
    
    if ($result) {
        // Log the activity
        log_activity($_SESSION['user_id'], 'course_status_updated', "Course ID: $course_id, Status: " . ($new_status ? 'Active' : 'Inactive'));
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update course status');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
