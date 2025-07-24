<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();



// Get user ID from URL
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    set_message('Invalid user ID', 'danger');
    redirect('users/');
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    set_message('User not found', 'danger');
    redirect('index.php');
}

// Get all roles
$roles = [];
$result = $conn->query("SELECT * FROM roles ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Get user's current roles
$user_roles = [];
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$role_result = $stmt->get_result();
while ($row = $role_result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_message('Invalid email format', 'danger');
    } else {
        // Check if email is already taken by another user
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            set_message('Email already in use by another account', 'danger');
        } else {
            // Update user data
            $sql = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    is_active = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssii', $first_name, $last_name, $email, $is_active, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update roles
                mysqli_query($conn, "DELETE FROM user_roles WHERE user_id = $user_id");
                
                if (!empty($selected_roles)) {
                    $values = [];
                    foreach ($selected_roles as $role_id) {
                        $role_id = (int)$role_id;
                        $values[] = "($user_id, $role_id, NOW())";
                    }
                    
                    if (!empty($values)) {
                        $sql = "INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES " . implode(',', $values);
                        mysqli_query($conn, $sql);
                    }
                }
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'user_updated', "User ID: $user_id");
                
                set_message('User updated successfully', 'success');
                // Redirect back to the edit page to show the updated data
                redirect("edit.php?id=$user_id");
            } else {
                set_message('Error updating user: ' . mysqli_error($conn), 'danger');
            }
        }
    }
    
    // Refresh user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

$page_title = 'Edit User';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit User</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

            <?php show_message(); ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Roles</label>
                            <div class="row">
                                <?php foreach ($roles as $role): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="roles[]" value="<?php echo $role['id']; ?>"
                                               id="role_<?php echo $role['id']; ?>"
                                               <?php echo in_array($role['id'], $user_roles) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>

