<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Get all roles
$roles = [];
$result = $conn->query("SELECT * FROM roles ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    // Validate input
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    } else {
        // Check if email is already taken
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'Email already in use';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($selected_roles)) {
        $errors[] = 'At least one role must be selected';
    }
    
    // If no errors, create user
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert user
            $sql = "INSERT INTO users (first_name, last_name, email, password, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssi', $first_name, $last_name, $email, $hashed_password, $is_active);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error creating user: ' . mysqli_error($conn));
            }
            
            $user_id = mysqli_insert_id($conn);
            
            // Assign roles
            if (!empty($selected_roles)) {
                $values = [];
                foreach ($selected_roles as $role_id) {
                    $role_id = (int)$role_id;
                    $values[] = "($user_id, $role_id, NOW())";
                }
                
                if (!empty($values)) {
                    $sql = "INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES " . implode(',', $values);
                    if (!mysqli_query($conn, $sql)) {
                        throw new Exception('Error assigning roles: ' . mysqli_error($conn));
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'user_created', "User ID: $user_id");
            
            set_message('User created successfully', 'success');
            // Redirect to edit page for the new user
            redirect("edit.php?id=$user_id");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $errors[] = $e->getMessage();
        }
    }
    
    // If we got here, there were errors
    if (!empty($errors)) {
        foreach ($errors as $error) {
            set_message($error, 'danger');
        }
    }
}

$page_title = 'Create New User';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Create New User</h1>
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
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">At least 8 characters long</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Roles <span class="text-danger">*</span></label>
                            <div class="row">
                                <?php foreach ($roles as $role): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="roles[]" value="<?php echo $role['id']; ?>"
                                               id="role_<?php echo $role['id']; ?>"
                                               <?php echo (isset($_POST['roles']) && in_array($role['id'], $_POST['roles'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>

