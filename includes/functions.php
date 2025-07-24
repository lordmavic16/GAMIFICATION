<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to sanitize user input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

// Function to check if user has a specific role
function has_role($role_name) {
    global $conn;
    
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? AND r.name = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $role_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_num_rows($result) > 0;
}

// Function to get all users with their roles
function get_all_users() {
    global $conn;
    
    $sql = "SELECT u.*, GROUP_CONCAT(r.name) as role_names 
            FROM users u 
            LEFT JOIN user_roles ur ON u.id = ur.user_id 
            LEFT JOIN roles r ON ur.role_id = r.id 
            GROUP BY u.id";
    
    $result = mysqli_query($conn, $sql);
    $users = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    return $users;
}

// Function to get user roles
function get_user_roles($user_id) {
    global $conn;
    
    $sql = "SELECT r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $roles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row['name'];
    }
    
    return $roles;
}

// Function to get user by ID
function get_user_by_id($user_id) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_assoc($result);
}

// Function to update user status
function update_user_status($user_id, $status) {
    global $conn;
    
    $sql = "UPDATE users SET is_active = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $status = $status ? 1 : 0;
    mysqli_stmt_bind_param($stmt, 'ii', $status, $user_id);
    
    return mysqli_stmt_execute($stmt);
}

// Function to get avatar URL
function get_avatar_url($filename = '') {
    if (!empty($filename) && file_exists('uploads/avatars/' . $filename)) {
        return 'uploads/avatars/' . $filename;
    }
    return 'assets/img/default-avatar.png';
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to set flash message
function set_message($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Function to show flash message
function show_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        // Clear the message after displaying
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// Function to check if user is admin
function is_admin() {
    return has_role('admin');
}

// Function to require admin access
function require_admin() {
    require_login();
    
    if (!is_admin()) {
        set_message('Access denied. Admin privileges required.', 'danger');
        redirect('user/dashboard.php');
    }
}

// Function to log activity
function log_activity($user_id, $action, $description = '') {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Create activity_logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating activity_logs table: " . mysqli_error($conn));
        return false;
    }
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $action, $description, $ip_address, $user_agent);
    
    return mysqli_stmt_execute($stmt);
}

/**
 * Get total number of users
 */
function get_user_count() {
    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get total number of active courses
 */
function get_course_count() {
    global $conn;
    
    // First check if courses table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'courses'");
    if (mysqli_num_rows($table_check) === 0) {
        return 0; // Return 0 if table doesn't exist
    }
    
    // Check if is_active column exists
    $column_check = mysqli_query($conn, "SHOW COLUMNS FROM courses LIKE 'is_active'");
    $has_is_active = mysqli_num_rows($column_check) > 0;
    
    $query = "SELECT COUNT(*) as count FROM courses";
    if ($has_is_active) {
        $query .= " WHERE is_active = 1";
    }
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] ?? 0;
    }
    
    return 0; // Return 0 if there was an error
}

/**
 * Get total number of enrollments
 */
function get_enrollment_count() {
    global $conn;
    
    // First check if user_courses table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'user_courses'");
    if (mysqli_num_rows($table_check) === 0) {
        return 0; // Return 0 if table doesn't exist
    }
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_courses");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] ?? 0;
    }
    
    return 0; // Return 0 if there was an error
}

/**
 * Get recent activities for dashboard
 * @param int $limit Number of activities to return
 * @return string HTML table rows
 */
function get_recent_activities($limit = 5) {
    global $conn;
    
    // Ensure activity_logs table exists
    $check_table = "SHOW TABLES LIKE 'activity_logs'";
    $table_exists = mysqli_query($conn, $check_table);
    
    if (mysqli_num_rows($table_exists) === 0) {
        return '<tr><td colspan="5" class="text-center">No activity logs available</td></tr>';
    }
    
    $sql = "SELECT a.*, u.username, u.first_name, u.last_name 
            FROM activity_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC 
            LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return '<tr><td colspan="5" class="text-center">No recent activities</td></tr>';
    }
    
    $output = '';
    $counter = 1;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $user_name = !empty($row['first_name']) ? 
                   htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 
                   htmlspecialchars($row['username']);
        
        $output .= '<tr>';
        $output .= '<td>' . $counter++ . '</td>';
        $output .= '<td>' . $user_name . '</td>';
        $output .= '<td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['action']))) . '</td>';
        $output .= '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
        $output .= '<td>' . htmlspecialchars($row['ip_address']) . '</td>';
        $output .= '</tr>';
    }
    
    return $output;
}

/**
 * Check and award achievements based on user points.
 *
 * @param int $user_id The ID of the user to check.
 * @return array A list of newly awarded achievement names.
 */
function check_and_award_achievements($user_id) {
    global $conn;

    // 1. Get user's current points
    $user_stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
    $user_stmt->bind_param('i', $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result->num_rows === 0) {
        return []; // User not found
    }
    $user_points = $user_result->fetch_assoc()['points'];
    $user_stmt->close();

    // 2. Find achievements the user is eligible for but hasn't earned yet
    $sql = "SELECT a.id, a.name
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            WHERE a.points_required <= ? AND ua.user_id IS NULL";
    
    $ach_stmt = $conn->prepare($sql);
    $ach_stmt->bind_param('ii', $user_id, $user_points);
    $ach_stmt->execute();
    $new_achievements = $ach_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ach_stmt->close();

    if (empty($new_achievements)) {
        return []; // No new achievements to award
    }

    // 3. Award the new achievements
    $award_stmt = $conn->prepare("INSERT INTO user_achievements (user_id, achievement_id, achieved_at) VALUES (?, ?, NOW())");
    $awarded_names = [];

    foreach ($new_achievements as $ach) {
        $award_stmt->bind_param('ii', $user_id, $ach['id']);
        if ($award_stmt->execute()) {
            $awarded_names[] = $ach['name'];
            // Optional: Log this achievement
            log_activity($user_id, 'achievement_unlocked', 'Unlocked: ' . $ach['name']);
        }
    }
    $award_stmt->close();

    // 4. Set a session flash message for the user
    if (!empty($awarded_names)) {
        $message = 'New Achievement Unlocked: ' . implode(', ', $awarded_names);
        set_message($message, 'info');
    }

    return $awarded_names;
}

?>
