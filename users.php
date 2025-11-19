<?php
// users.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!isAdmin()) {
    $_SESSION['error'] = "Access denied! Admin privileges required.";
    redirect('dashboard.php');
}

$success = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        // Validate required fields
        if (empty($username) || empty($password) || empty($full_name)) {
            throw new Exception("Username, password, and full name are required!");
        }

        // Check if passwords match
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match!");
        }

        // Check password strength
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long!");
        }

        // Check if username already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->execute([$username]);
        if ($check_stmt->fetch()) {
            throw new Exception("Username already exists!");
        }

        // Check if email already exists (if provided)
        if (!empty($email)) {
            $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->execute([$email]);
            if ($check_email->fetch()) {
                throw new Exception("Email already exists!");
            }
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $hashed_password, $full_name, $email, $role])) {
            $success = "User created successfully!";
        } else {
            throw new Exception("Failed to create user!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle user deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        // Prevent admin from deleting themselves
        if ($delete_id == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account!");
        }

        // Prevent deletion of the last admin
        $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
        $is_admin = $pdo->prepare("SELECT role FROM users WHERE id = ?")->execute([$delete_id]);
        $user_to_delete = $pdo->prepare("SELECT role FROM users WHERE id = ?")->fetch();
        
        if ($user_to_delete && $user_to_delete['role'] == 'Admin' && $admin_count <= 1) {
            throw new Exception("Cannot delete the last admin user!");
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = "User deleted successfully!";
        } else {
            throw new Exception("Failed to delete user!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle user role update
if (isset($_GET['update_role'])) {
    try {
        $user_id = intval($_GET['user_id']);
        $new_role = $_GET['new_role'];

        // Prevent admin from changing their own role
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception("You cannot change your own role!");
        }

        // Prevent removing the last admin
        if ($new_role != 'Admin') {
            $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
            $current_role = $pdo->prepare("SELECT role FROM users WHERE id = ?")->execute([$user_id]);
            $user = $pdo->prepare("SELECT role FROM users WHERE id = ?")->fetch();
            
            if ($user && $user['role'] == 'Admin' && $admin_count <= 1) {
                throw new Exception("Cannot remove the last admin user!");
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt->execute([$new_role, $user_id])) {
            $success = "User role updated successfully!";
        } else {
            throw new Exception("Failed to update user role!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Get user statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$admin_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$technician_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Technician'")->fetchColumn();
$regular_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'User'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .users-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
        }
        .table-actions {
            min-width: 120px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i>User Management</h2>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_users; ?></h4>
                                <p>Total Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $admin_users; ?></h4>
                                <p>Administrators</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-shield fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $technician_users; ?></h4>
                                <p>Technicians</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tools fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $regular_users; ?></h4>
                                <p>Regular Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header users-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>System Users</h5>
            </div>
            <div class="card-body">
                <?php if ($users): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th class="table-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-primary">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <?php if ($user['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge role-badge bg-<?php 
                                            switch($user['role']) {
                                                case 'Admin': echo 'danger'; break;
                                                case 'Technician': echo 'warning'; break;
                                                case 'User': echo 'info'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>"><?php echo $user['role']; ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                                // This would typically come from a last_login field
                                                // For now, we'll use created_at as placeholder
                                                echo date('M j, Y', strtotime($user['created_at'])); 
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <!-- Role Change Dropdown -->
                                            <div class="dropdown me-1">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-user-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item <?php echo $user['role'] == 'Admin' ? 'active' : ''; ?>" 
                                                           href="users.php?update_role=true&user_id=<?php echo $user['id']; ?>&new_role=Admin">
                                                            <i class="fas fa-user-shield me-2"></i>Admin
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item <?php echo $user['role'] == 'Technician' ? 'active' : ''; ?>" 
                                                           href="users.php?update_role=true&user_id=<?php echo $user['id']; ?>&new_role=Technician">
                                                            <i class="fas fa-tools me-2"></i>Technician
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item <?php echo $user['role'] == 'User' ? 'active' : ''; ?>" 
                                                           href="users.php?update_role=true&user_id=<?php echo $user['id']; ?>&new_role=User">
                                                            <i class="fas fa-user me-2"></i>User
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>

                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal"
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-role="<?php echo $user['role']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- Delete Button -->
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="users.php?delete_id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-danger" disabled title="You cannot delete your own account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No users found</h5>
                        <p class="text-muted">Get started by adding your first user.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i>Add First User
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="addUserModalLabel">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label required">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Enter username">
                            <div class="form-text">Username must be unique.</div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required 
                                   placeholder="Enter full name">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter email address">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label required">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Enter password" minlength="6">
                                <div class="form-text">Minimum 6 characters.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                       placeholder="Confirm password">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="User">User</option>
                                <option value="Technician">Technician</option>
                                <option value="Admin">Administrator</option>
                            </select>
                            <div class="form-text">
                                <strong>Admin:</strong> Full system access<br>
                                <strong>Technician:</strong> Can manage assets and maintenance<br>
                                <strong>User:</strong> Can view assets only
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-success">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editUserForm">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="editUserModalLabel">
                            <i class="fas fa-user-edit me-2"></i>Edit User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_user_id" name="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label required">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="edit_username" required 
                                   placeholder="Enter username">
                        </div>

                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="edit_full_name" required 
                                   placeholder="Enter full name">
                        </div>

                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="edit_email" 
                                   placeholder="Enter email address">
                        </div>

                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="edit_role">
                                <option value="User">User</option>
                                <option value="Technician">Technician</option>
                                <option value="Admin">Administrator</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                To change the user's password, use the "Reset Password" feature (to be implemented).
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-warning">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password confirmation validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords do not match");
                } else {
                    confirmPassword.setCustomValidity("");
                }
            }
            
            if (password && confirmPassword) {
                password.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
            }

            // Edit User Modal Handler
            const editUserModal = document.getElementById('editUserModal');
            if (editUserModal) {
                editUserModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-user-id');
                    const username = button.getAttribute('data-username');
                    const fullName = button.getAttribute('data-full-name');
                    const email = button.getAttribute('data-email');
                    const role = button.getAttribute('data-role');

                    document.getElementById('edit_user_id').value = userId;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_full_name').value = fullName;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_role').value = role;
                });
            }

            // Show success message if modal was submitted
            <?php if ($success && $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
                modal.hide();
            <?php endif; ?>
        });
    </script>
</body>
</html>