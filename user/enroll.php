<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = 'Please log in to enroll in courses';
    redirect('login.php');
}

// Check if course_id is provided
if (!isset($_POST['course_id']) || !is_numeric($_POST['course_id'])) {
    $_SESSION['error'] = 'Invalid course selected';
    redirect('courses.php');
}

$user_id = $_SESSION['user_id'];
$course_id = (int)$_POST['course_id'];

// Check if the course exists and is active
$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND is_active = 1");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Course not found or not available for enrollment';
    redirect('courses.php');
}

// Check if user is already enrolled
$stmt = $conn->prepare("SELECT id FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->bind_param('ii', $user_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['info'] = 'You are already enrolled in this course';
    redirect('course.php?id=' . $course_id);
}

// Enroll the user in the course
try {
    // Start transaction
    $conn->begin_transaction();
    
    // Add to user_courses
    $enroll_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
    $enroll_stmt->bind_param('ii', $user_id, $course_id);
    $enroll_stmt->execute();
    
    // Log the enrollment
    $action = 'course_enrollment';
    $description = "Enrolled in course ID: $course_id";
    $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $log_stmt->bind_param('iss', $user_id, $action, $description);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = 'Successfully enrolled in the course!';
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Enrollment error: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while processing your enrollment. Please try again.';
}

// Redirect to the course page
redirect('course.php?id=' . $course_id);
