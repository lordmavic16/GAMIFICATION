<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get and validate input
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_BOOLEAN);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent self-deactivation
if ($user_id == $_SESSION['user_id'] && !$is_active) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
    exit;
}

// Update user status
$success = update_user_status($user_id, $is_active);

if ($success) {
    // Log the activity
    $action = $is_active ? 'user_activated' : 'user_deactivated';
    log_activity($_SESSION['user_id'], $action, "User ID: $user_id");
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
}
