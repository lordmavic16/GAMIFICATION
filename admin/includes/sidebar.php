<?php
// Get current page for active state
$current_uri = $_SERVER['REQUEST_URI'];
$current_path = parse_url($current_uri, PHP_URL_PATH);
$segments = explode('/', trim($current_path, '/'));
$current_section = $segments[count($segments) - 2] ?? ''; // Get the section (e.g., 'users', 'courses')
?>

<div class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white" style="width: 280px;">
    <a href="/gamification/admin/dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="bi bi-joystick me-2" style="font-size: 1.5rem;"></i>
        <span class="fs-4">Gamification</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item mb-1">
            <a href="/gamification/admin/dashboard.php" class="nav-link text-white <?php echo $current_section === 'admin' && basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>
        
        <li class="nav-item mb-1">
            <a href="/gamification/admin/users/" class="nav-link text-white <?php echo $current_section === 'users' ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>
                Users
            </a>
        </li>
        
        <li class="nav-item mb-1">
            <a href="/gamification/admin/courses/" class="nav-link text-white <?php echo $current_section === 'courses' ? 'active' : ''; ?>">
                <i class="bi bi-book me-2"></i>
                Courses
            </a>
            <?php if ($current_section === 'courses' || $current_section === 'lessons'): ?>
            <ul class="nav flex-column ms-4 mt-1">
                <li class="nav-item">
                    <a href="/gamification/admin/lessons/index.php?course_id=<?php echo isset($_GET['id']) ? (int)$_GET['id'] : ''; ?>" 
                       class="nav-link text-white-50 <?php echo $current_section === 'lessons' ? 'active' : ''; ?>">
                        <i class="bi bi-journal-text me-2"></i>
                        Lessons
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>
        
        <li class="nav-item mb-1">
            <a href="/gamification/admin/achievements/" class="nav-link text-white <?php echo $current_section === 'achievements' ? 'active' : ''; ?>">
                <i class="bi bi-trophy me-2"></i>
                Achievements
            </a>
        </li>
        
        <li class="nav-item mb-1">
            <a href="/gamification/admin/reports/" class="nav-link text-white <?php echo $current_section === 'reports' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up me-2"></i>
                Reports
            </a>
        </li>
    </ul>
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-2"></i>
            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
            </a>
        </div>
    </div>
</div>
