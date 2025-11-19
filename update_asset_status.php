<?php
// update_asset_status.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission (Admin or Technician)
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Technician') {
    $_SESSION['error'] = "Access denied! Technician or Admin privileges required.";
    redirect('dashboard.php');
}

$success = '';
$error = '';

// Get asset ID from query string
$asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;

// Fetch asset details
if ($asset_id) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    
    if (!$asset) {
        $_SESSION['error'] = "Asset not found!";
        redirect('maintenance.php');
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    try {
        $asset_id = intval($_POST['asset_id']);
        $new_status = $_POST['status'];
        $notes = trim($_POST['notes']);

        // Validate
        if (empty($asset_id) || empty($new_status)) {
            throw new Exception("Asset ID and status are required!");
        }

        // Verify asset exists
        $check_asset = $pdo->prepare("SELECT asset_name FROM assets WHERE id = ?");
        $check_asset->execute([$asset_id]);
        $asset = $check_asset->fetch();
        
        if (!$asset) {
            throw new Exception("Asset not found!");
        }

        // Update asset status
        $stmt = $pdo->prepare("UPDATE assets SET status = ? WHERE id = ?");
        
        if ($stmt->execute([$new_status, $asset_id])) {
            // Log this status change in maintenance records
            $log_stmt = $pdo->prepare("INSERT INTO maintenance_records (asset_id, maintenance_date, maintenance_type, description, performed_by) VALUES (?, CURDATE(), 'Repair', ?, ?)");
            $log_description = "Asset status changed to: " . $new_status;
            if (!empty($notes)) {
                $log_description .= ". Notes: " . $notes;
            }
            $log_stmt->execute([$asset_id, $log_description, $_SESSION['full_name']]);
            
            $success = "Asset status updated successfully! " . htmlspecialchars($asset['asset_name']) . " is now marked as '" . $new_status . "'.";
        } else {
            throw new Exception("Failed to update asset status!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// If no asset ID provided, redirect to maintenance page
if (!$asset_id) {
    redirect('maintenance.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Asset Status - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .status-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header status-header">
                        <h4 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Update Asset Status</h4>
                    </div>
                    <div class="card-body">
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

                        <!-- Asset Information -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h5>Asset Details</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Asset Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?><br>
                                        <strong>Type:</strong> <?php echo htmlspecialchars($asset['asset_type']); ?><br>
                                        <strong>Serial Number:</strong> <?php echo htmlspecialchars($asset['serial_number'] ?: 'N/A'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Current Status:</strong> 
                                        <span class="badge bg-<?php 
                                            switch($asset['status']) {
                                                case 'Available': echo 'success'; break;
                                                case 'In Use': echo 'warning'; break;
                                                case 'Under Maintenance': echo 'danger'; break;
                                                case 'Retired': echo 'secondary'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>"><?php echo $asset['status']; ?></span><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($asset['location']); ?><br>
                                        <strong>Assigned To:</strong> <?php echo htmlspecialchars($asset['assigned_to'] ?: 'Not Assigned'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Update Form -->
                        <form method="POST">
                            <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label required">New Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select New Status</option>
                                    <option value="Available" <?php echo $asset['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="In Use" <?php echo $asset['status'] == 'In Use' ? 'selected' : ''; ?>>In Use</option>
                                    <option value="Under Maintenance" <?php echo $asset['status'] == 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                    <option value="Retired" <?php echo $asset['status'] == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                                </select>
                                <div class="form-text">
                                    <strong>Available:</strong> Asset is ready for use<br>
                                    <strong>In Use:</strong> Asset is currently being used<br>
                                    <strong>Under Maintenance:</strong> Asset is being repaired/maintained<br>
                                    <strong>Retired:</strong> Asset is no longer in service
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Status Change Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional: Add notes about why the status is being changed (e.g., 'Repairs completed', 'Issued to IT Department', etc.)"></textarea>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> This status change will be logged in the maintenance records for tracking purposes.
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="maintenance.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Maintenance
                                </a>
                                <div>
                                    <a href="view_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-info me-2">
                                        <i class="fas fa-eye me-2"></i>View Asset
                                    </a>
                                    <button type="submit" name="update_status" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>Update Status
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>