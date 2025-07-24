<?php
require_once __DIR__ . '/../config/database.php';

// Function to execute SQL queries
execute_query("SET FOREIGN_KEY_CHECKS = 0");

// Clear existing data
execute_query("TRUNCATE TABLE user_achievements");
execute_query("TRUNCATE TABLE user_courses");
execute_query("TRUNCATE TABLE user_progress");
execute_query("TRUNCATE TABLE user_roles");
execute_query("TRUNCATE TABLE role_permissions");
execute_query("TRUNCATE TABLE user_sessions");
execute_query("TRUNCATE TABLE activity_logs");
execute_query("TRUNCATE TABLE users");
execute_query("TRUNCATE TABLE roles");
execute_query("TRUNCATE TABLE permissions");
execute_query("TRUNCATE TABLE courses");
execute_query("TRUNCATE TABLE achievements");

execute_query("SET FOREIGN_KEY_CHECKS = 1");

// Insert roles
$roles = [
    ['name' => 'admin', 'description' => 'Administrator with full access'],
    ['name' => 'instructor', 'description' => 'Course instructor'],
    ['name' => 'student', 'description' => 'Regular student']
];

foreach ($roles as $role) {
    $sql = "INSERT INTO roles (name, description) VALUES (?, ?)";
    execute_query($sql, [$role['name'], $role['description']]);
}

// Insert permissions
$permissions = [
    'manage_users', 'manage_courses', 'manage_roles', 'manage_permissions',
    'enroll_courses', 'take_courses', 'grade_assignments', 'view_reports',
    'manage_own_profile', 'manage_own_courses'
];

foreach ($permissions as $permission) {
    $sql = "INSERT INTO permissions (name) VALUES (?)";
    execute_query($sql, [$permission]);
}

// Assign permissions to admin role
$admin_permissions = [
    'manage_users', 'manage_courses', 'manage_roles', 'manage_permissions',
    'enroll_courses', 'take_courses', 'grade_assignments', 'view_reports',
    'manage_own_profile', 'manage_own_courses'
];

foreach ($admin_permissions as $perm_name) {
    $sql = "INSERT INTO role_permissions (role_id, permission_id) 
            SELECT r.id, p.id FROM roles r, permissions p 
            WHERE r.name = 'admin' AND p.name = ?";
    execute_query($sql, [$perm_name]);
}

// Assign permissions to instructor role
$instructor_permissions = [
    'manage_own_courses', 'grade_assignments', 'view_reports',
    'manage_own_profile', 'take_courses'
];

foreach ($instructor_permissions as $perm_name) {
    $sql = "INSERT INTO role_permissions (role_id, permission_id) 
            SELECT r.id, p.id FROM roles r, permissions p 
            WHERE r.name = 'instructor' AND p.name = ?";
    execute_query($sql, [$perm_name]);
}

// Assign permissions to student role
$student_permissions = [
    'enroll_courses', 'take_courses', 'manage_own_profile'
];

foreach ($student_permissions as $perm_name) {
    $sql = "INSERT INTO role_permissions (role_id, permission_id) 
            SELECT r.id, p.id FROM roles r, permissions p 
            WHERE r.name = 'student' AND p.name = ?";
    execute_query($sql, [$perm_name]);
}

// Create admin user (password: Admin@123)
$admin_password = password_hash('Admin@123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, first_name, last_name, is_active, is_verified, points, level, experience, next_level_exp) 
        VALUES (?, ?, ?, ?, ?, 1, 1, 1000, 5, 750, 1000)";
execute_query($sql, [
    'admin', 
    'admin@example.com', 
    $admin_password,
    'Admin',
    'User'
]);

$admin_id = $conn->insert_id;

// Assign admin role to admin user
$sql = "INSERT INTO user_roles (user_id, role_id) 
        SELECT ?, id FROM roles WHERE name = 'admin'";
execute_query($sql, [$admin_id]);

// Create sample instructor (password: Instructor@123)
$instructor_password = password_hash('Instructor@123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, first_name, last_name, is_active, is_verified, points, level, experience, next_level_exp) 
        VALUES (?, ?, ?, ?, ?, 1, 1, 500, 3, 400, 500)";
execute_query($sql, [
    'instructor', 
    'instructor@example.com', 
    $instructor_password,
    'John',
    'Doe'
]);

$instructor_id = $conn->insert_id;

// Assign instructor role
$sql = "INSERT INTO user_roles (user_id, role_id) 
        SELECT ?, id FROM roles WHERE name = 'instructor'";
execute_query($sql, [$instructor_id]);

// Create sample student (password: Student@123)
$student_password = password_hash('Student@123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, first_name, last_name, is_active, is_verified, points, level, experience, next_level_exp) 
        VALUES (?, ?, ?, ?, ?, 1, 1, 200, 2, 150, 200)";
execute_query($sql, [
    'student', 
    'student@example.com', 
    $student_password,
    'Jane',
    'Smith'
]);

$student_id = $conn->insert_id;

// Assign student role
$sql = "INSERT INTO user_roles (user_id, role_id) 
        SELECT ?, id FROM roles WHERE name = 'student'";
execute_query($sql, [$student_id]);

// Insert sample courses
$courses = [
    [
        'title' => 'Introduction to Web Development',
        'description' => 'Learn the basics of HTML, CSS, and JavaScript',
        'created_by' => $instructor_id,
        'difficulty' => 'beginner',
        'points' => 500
    ],
    [
        'title' => 'PHP and MySQL Fundamentals',
        'description' => 'Build dynamic websites with PHP and MySQL',
        'created_by' => $instructor_id,
        'difficulty' => 'intermediate',
        'points' => 750
    ],
    [
        'title' => 'Advanced JavaScript',
        'description' => 'Master modern JavaScript concepts and frameworks',
        'created_by' => $instructor_id,
        'difficulty' => 'advanced',
        'points' => 1000
    ]
];

foreach ($courses as $course) {
    $sql = "INSERT INTO courses (title, description, created_by, difficulty, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    execute_query($sql, [
        $course['title'],
        $course['description'],
        $course['created_by'],
        $course['difficulty']
    ]);
    
    $course_id = $conn->insert_id;
    
    // Enroll admin in all courses
    enroll_user_in_course($admin_id, $course_id);
    
    // Enroll student in some courses
    if ($course['difficulty'] !== 'advanced') {
        enroll_user_in_course($student_id, $course_id);
    }
}

// Insert sample achievements
$achievements = [
    ['name' => 'Bronze Star', 'description' => 'Earn 500 points', 'icon' => 'fas fa-star', 'points_required' => 500],
    ['name' => 'Silver Shield', 'description' => 'Earn 1,000 points', 'icon' => 'fas fa-shield-alt', 'points_required' => 1000],
    ['name' => 'Golden Crown', 'description' => 'Earn 5,000 points', 'icon' => 'fas fa-crown', 'points_required' => 5000],
    ['name' => 'Platinum Chalice', 'description' => 'Earn 10,000 points', 'icon' => 'fas fa-trophy', 'points_required' => 10000],
    ['name' => 'Diamond Trophy', 'description' => 'Earn 20,000 points', 'icon' => 'fas fa-gem', 'points_required' => 20000]
];

foreach ($achievements as $achievement) {
    $sql = "INSERT INTO achievements (name, description, icon, points_required) VALUES (?, ?, ?, ?)";
    execute_query($sql, [
        $achievement['name'],
        $achievement['description'],
        $achievement['icon'],
        $achievement['points_required']
    ]);
}

// Helper function to execute queries with parameters
function execute_query($sql, $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error . "\nQuery: $sql");
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error . "\nQuery: $sql");
    }
    
    return $stmt;
}

// Helper function to enroll a user in a course
function enroll_user_in_course($user_id, $course_id) {
    global $conn;
    
    $sql = "INSERT INTO user_courses (user_id, course_id, enrolled_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $course_id);
    
    if (!$stmt->execute()) {
        // Ignore duplicate entry errors
        if ($stmt->errno != 1062) {
            die("Error enrolling user in course: " . $stmt->error);
        }
    }
}

echo "Database seeded successfully!\n";
?>
