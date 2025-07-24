<?php
/**
 * Reports functions for the admin dashboard
 */

/**
 * Get user statistics
 */
function get_user_stats($conn, $start_date = null, $end_date = null) {
    $stats = [
        'total_users' => 0,
        'new_users' => 0,
        'active_users' => 0,
        'users_by_role' => []
    ];

    try {
        // Total users
        $query = "SELECT COUNT(*) as count FROM users";
        $result = $conn->query($query);
        $stats['total_users'] = $result->fetch_assoc()['count'];

        // New users in date range
        $query = "SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $start = $start_date ?: date('Y-m-01');
        $end = $end_date ?: date('Y-m-d 23:59:59');
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['new_users'] = $result->fetch_assoc()['count'];

        // Active users (logged in last 30 days)
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $query = "SELECT COUNT(DISTINCT user_id) as count FROM user_sessions WHERE created_at > ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $thirty_days_ago);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['active_users'] = $result->fetch_assoc()['count'];

        // Users by role
        $query = "
            SELECT r.name as role_name, COUNT(ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.name
        ";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $stats['users_by_role'][$row['role_name']] = $row['user_count'];
        }

    } catch (Exception $e) {
        error_log("Error getting user stats: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Get course statistics
 */
function get_course_stats($conn, $start_date = null, $end_date = null) {
    $stats = [
        'total_courses' => 0,
        'active_courses' => 0,
        'enrollments' => 0,
        'completion_rate' => 0,
        'popular_courses' => []
    ];

    try {
        // Total courses
        $query = "SELECT COUNT(*) as count FROM courses";
        $result = $conn->query($query);
        $stats['total_courses'] = $result->fetch_assoc()['count'];

        // Active courses (with enrollments in date range)
        $query = "
            SELECT COUNT(DISTINCT c.id) as count 
            FROM courses c
            JOIN user_courses uc ON c.id = uc.course_id
            WHERE uc.enrolled_at BETWEEN ? AND ?
        ";
        $stmt = $conn->prepare($query);
        $start = $start_date ?: date('Y-m-01');
        $end = $end_date ?: date('Y-m-d 23:59:59');
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['active_courses'] = $result->fetch_assoc()['count'];

        // Total enrollments
        $query = "SELECT COUNT(*) as count FROM user_courses";
        $result = $conn->query($query);
        $stats['enrollments'] = $result->fetch_assoc()['count'];

        // Completion rate
        $query = "SELECT 
                    COUNT(CASE WHEN completed = 1 THEN 1 END) as completed,
                    COUNT(*) as total
                  FROM user_courses";
        $result = $conn->query($query);
        $data = $result->fetch_assoc();
        $stats['completion_rate'] = $data['total'] > 0 
            ? round(($data['completed'] / $data['total']) * 100, 1) 
            : 0;

        // Popular courses
        $query = "
            SELECT c.id, c.title, COUNT(uc.id) as enrollment_count
            FROM courses c
            LEFT JOIN user_courses uc ON c.id = uc.course_id
            GROUP BY c.id, c.title
            ORDER BY enrollment_count DESC
            LIMIT 5
        ";
        $result = $conn->query($query);
        $stats['popular_courses'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['popular_courses'][] = $row;
        }

    } catch (Exception $e) {
        error_log("Error getting course stats: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Get achievement statistics
 */
function get_achievement_stats($conn, $start_date = null, $end_date = null) {
    $stats = [
        'total_achievements' => 0,
        'achievements_awarded' => 0,
        'top_achievements' => [],
        'top_achievers' => []
    ];

    try {
        // Total achievements
        $query = "SELECT COUNT(*) as count FROM achievements";
        $result = $conn->query($query);
        $stats['total_achievements'] = $result->fetch_assoc()['count'];

        // Achievements awarded in date range
        $query = "
            SELECT COUNT(*) as count 
            FROM user_achievements 
            WHERE achieved_at BETWEEN ? AND ?
        ";
        $stmt = $conn->prepare($query);
        $start = $start_date ?: date('Y-m-01');
        $end = $end_date ?: date('Y-m-d 23:59:59');
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['achievements_awarded'] = $result->fetch_assoc()['count'];

        // Top achievements
        $query = "
            SELECT a.id, a.name, COUNT(ua.achievement_id) as award_count
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id
            GROUP BY a.id, a.name
            ORDER BY award_count DESC
            LIMIT 5
        ";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $stats['top_achievements'][] = $row;
        }

        // Top achievers
        $query = "
            SELECT 
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) as name,
                COUNT(ua.achievement_id) as achievement_count,
                u.points
            FROM users u
            LEFT JOIN user_achievements ua ON u.id = ua.user_id
            GROUP BY u.id, u.first_name, u.last_name, u.points
            HAVING achievement_count > 0
            ORDER BY achievement_count DESC, u.points DESC
            LIMIT 5
        ";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $stats['top_achievers'][] = $row;
        }

    } catch (Exception $e) {
        error_log("Error getting achievement stats: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Get activity data for charts
 */
function get_activity_data($conn, $start_date = null, $end_date = null) {
    $data = [
        'labels' => [],
        'datasets' => [
            'logins' => [],
            'enrollments' => [],
            'completions' => []
        ]
    ];

    try {
        $start = $start_date ?: date('Y-m-01');
        $end = $end_date ?: date('Y-m-d 23:59:59');
        
        // Generate date range for labels
        $period = new DatePeriod(
            new DateTime($start),
            new DateInterval('P1D'),
            new DateTime($end)
        );

        // Initialize data arrays with zeros
        $loginData = [];
        $enrollmentData = [];
        $completionData = [];
        
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $data['labels'][] = $date->format('M j');
            $loginData[$dateStr] = 0;
            $enrollmentData[$dateStr] = 0;
            $completionData[$dateStr] = 0;
        }

        // Get login data
        $query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT user_id) as count
            FROM user_sessions
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $loginData[$row['date']] = (int)$row['count'];
        }

        // Get enrollment data
        $query = "
            SELECT 
                DATE(enrolled_at) as date,
                COUNT(*) as count
            FROM user_courses
            WHERE enrolled_at BETWEEN ? AND ?
            GROUP BY DATE(enrolled_at)
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $enrollmentData[$row['date']] = (int)$row['count'];
        }

        // Get completion data
        $query = "
            SELECT 
                DATE(completed_at) as date,
                COUNT(*) as count
            FROM user_courses
            WHERE completed = 1 
            AND completed_at BETWEEN ? AND ?
            GROUP BY DATE(completed_at)
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $completionData[$row['date']] = (int)$row['count'];
        }

        // Set the data in the format expected by the chart
        $data['datasets'] = [
            [
                'label' => 'Logins',
                'data' => array_values($loginData),
                'borderColor' => '#4e73df',
                'tension' => 0.3
            ],
            [
                'label' => 'Enrollments',
                'data' => array_values($enrollmentData),
                'borderColor' => '#1cc88a',
                'tension' => 0.3
            ],
            [
                'label' => 'Completions',
                'data' => array_values($completionData),
                'borderColor' => '#f6c23e',
                'tension' => 0.3
            ]
        ];

    } catch (Exception $e) {
        error_log("Error getting activity data: " . $e->getMessage());
    }

    return $data;
}
?>
