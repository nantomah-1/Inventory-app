<?php
// maintenance.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle maintenance record submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_maintenance'])) {
    try {
        $asset_id = intval($_POST['asset_id']);
        $maintenance_date = $_POST['maintenance_date'];
        $maintenance_type = $_POST['maintenance_type'];
        $description = trim($_POST['description']);
        $cost = $_POST['cost'] ?: 0;
        $performed_by = trim($_POST['performed_by']);
        $next_maintenance_date = $_POST['next_maintenance_date'];
        $new_asset_status = $_POST['new_asset_status'] ?? '';

        // Validate required fields
        if (empty($asset_id) || empty($maintenance_date) || empty($maintenance_type)) {
            throw new Exception("Asset, Maintenance Date, and Type are required!");
        }

        // Verify asset exists
        $check_asset = $pdo->prepare("SELECT id, asset_name, status FROM assets WHERE id = ?");
        $check_asset->execute([$asset_id]);
        $asset = $check_asset->fetch();
        
        if (!$asset) {
            throw new Exception("Selected asset not found!");
        }

        // Insert maintenance record
        $stmt = $pdo->prepare("INSERT INTO maintenance_records (asset_id, maintenance_date, maintenance_type, description, cost, performed_by, next_maintenance_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$asset_id, $maintenance_date, $maintenance_type, $description, $cost, $performed_by, $next_maintenance_date])) {
            
            // Determine the new status for the asset
            $final_status = $asset['status']; // Default to current status
            
            if (!empty($new_asset_status)) {
                // Use the manually selected status
                $final_status = $new_asset_status;
            } else {
                // Auto-update status based on maintenance type
                if ($maintenance_type == 'Repair') {
                    $final_status = 'Under Maintenance';
                } elseif ($maintenance_type == 'Routine' && $asset['status'] == 'Under Maintenance') {
                    $final_status = 'Available';
                }
            }
            
            // Update asset's maintenance dates and status
            $update_asset = $pdo->prepare("UPDATE assets SET last_maintenance = ?, next_maintenance = ?, status = ? WHERE id = ?");
            $update_asset->execute([$maintenance_date, $next_maintenance_date, $final_status, $asset_id]);
            
            $status_message = $final_status != $asset['status'] ? " and status updated to {$final_status}" : "";
            $success = "Maintenance record added successfully for " . htmlspecialchars($asset['asset_name']) . "!{$status_message}";
        } else {
            throw new Exception("Failed to add maintenance record!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle maintenance record deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        $stmt = $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = "Maintenance record deleted successfully!";
        } else {
            throw new Exception("Failed to delete maintenance record!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle quick status update from maintenance records
if (isset($_GET['update_asset_status'])) {
    try {
        $asset_id = intval($_GET['asset_id']);
        $new_status = $_GET['new_status'];
        
        $stmt = $pdo->prepare("UPDATE assets SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $asset_id])) {
            $asset_name = $pdo->prepare("SELECT asset_name FROM assets WHERE id = ?")->execute([$asset_id]);
            $asset = $pdo->prepare("SELECT asset_name FROM assets WHERE id = ?")->fetch();
            $success = "Asset status updated to {$new_status} for " . htmlspecialchars($asset['asset_name']) . "!";
        } else {
            throw new Exception("Failed to update asset status!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filter parameters
$asset_filter = $_GET['asset_filter'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for maintenance records
$query = "SELECT mr.*, a.asset_name, a.serial_number, a.status as asset_status 
          FROM maintenance_records mr 
          JOIN assets a ON mr.asset_id = a.id 
          WHERE 1=1";
$params = [];

if (!empty($asset_filter)) {
    $query .= " AND mr.asset_id = ?";
    $params[] = $asset_filter;
}

if (!empty($type_filter)) {
    $query .= " AND mr.maintenance_type = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $query .= " AND mr.maintenance_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND mr.maintenance_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY mr.maintenance_date DESC, mr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenance_records = $stmt->fetchAll();

// Get assets for dropdown (include status)
$assets = $pdo->query("SELECT id, asset_name, serial_number, status FROM assets ORDER BY asset_name")->fetchAll();

// Get statistics
$total_maintenance = $pdo->query("SELECT COUNT(*) FROM maintenance_records")->fetchColumn();
$total_maintenance_cost = $pdo->query("SELECT SUM(cost) FROM maintenance_records")->fetchColumn();
$upcoming_maintenance = $pdo->query("SELECT COUNT(*) FROM assets WHERE next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND next_maintenance IS NOT NULL")->fetchColumn();
$assets_under_maintenance = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .maintenance-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .badge-routine { background-color: #17a2b8; }
        .badge-repair { background-color: #ffc107; color: #000; }
        .badge-upgrade { background-color: #28a745; }
        .asset-status-badge {
            font-size: 0.75rem;
            cursor: pointer;
        }
        .status-dropdown {
            min-width: 150px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tools me-2"></i>Maintenance Management</h2>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                <i class="fas fa-plus me-2"></i>Add Maintenance Record
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_maintenance; ?></h4>
                                <p>Total Maintenance Records</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clipboard-list fa-2x"></i>
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
                                <h4>₵<?php echo number_format($total_maintenance_cost, 2); ?></h4>
                                <p>Total Maintenance Cost</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
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
                                <h4><?php echo $upcoming_maintenance; ?></h4>
                                <p>Upcoming Maintenance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $assets_under_maintenance; ?></h4>
                                <p>Assets Under Maintenance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-wrench fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Maintenance Records</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="asset_filter" class="form-label">Asset</label>
                        <select name="asset_filter" id="asset_filter" class="form-select">
                            <option value="">All Assets</option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?php echo $asset['id']; ?>" <?php echo $asset_filter == $asset['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asset['asset_name']); ?> (<?php echo htmlspecialchars($asset['serial_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type_filter" class="form-label">Maintenance Type</label>
                        <select name="type_filter" id="type_filter" class="form-select">
                            <option value="">All Types</option>
                            <option value="Routine" <?php echo $type_filter == 'Routine' ? 'selected' : ''; ?>>Routine</option>
                            <option value="Repair" <?php echo $type_filter == 'Repair' ? 'selected' : ''; ?>>Repair</option>
                            <option value="Upgrade" <?php echo $type_filter == 'Upgrade' ? 'selected' : ''; ?>>Upgrade</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                        <a href="maintenance.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>
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

        <!-- Maintenance Records Table -->
        <div class="card">
            <div class="card-header maintenance-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Maintenance Records</h5>
            </div>
            <div class="card-body">
                <?php if ($maintenance_records): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Asset</th>
                                    <th>Current Status</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Cost (₵)</th>
                                    <th>Performed By</th>
                                    <th>Next Maintenance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_records as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['maintenance_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['asset_name']); ?></strong>
                                        <?php if ($record['serial_number']): ?>
                                            <br><small class="text-muted">SN: <?php echo htmlspecialchars($record['serial_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <span class="badge asset-status-badge bg-<?php 
                                                switch($record['asset_status']) {
                                                    case 'Available': echo 'success'; break;
                                                    case 'In Use': echo 'warning'; break;
                                                    case 'Under Maintenance': echo 'danger'; break;
                                                    case 'Retired': echo 'secondary'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>" data-bs-toggle="dropdown">
                                                <?php echo $record['asset_status']; ?>
                                                <i class="fas fa-caret-down ms-1"></i>
                                            </span>
                                            <ul class="dropdown-menu status-dropdown">
                                                <li><a class="dropdown-item" href="maintenance.php?update_asset_status=1&asset_id=<?php echo $record['asset_id']; ?>&new_status=Available">Available</a></li>
                                                <li><a class="dropdown-item" href="maintenance.php?update_asset_status=1&asset_id=<?php echo $record['asset_id']; ?>&new_status=In Use">In Use</a></li>
                                                <li><a class="dropdown-item" href="maintenance.php?update_asset_status=1&asset_id=<?php echo $record['asset_id']; ?>&new_status=Under Maintenance">Under Maintenance</a></li>
                                                <li><a class="dropdown-item" href="maintenance.php?update_asset_status=1&asset_id=<?php echo $record['asset_id']; ?>&new_status=Retired">Retired</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($record['maintenance_type']); ?>">
                                            <?php echo $record['maintenance_type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['description'] ?: 'No description'); ?></td>
                                    <td>
                                        <strong>₵<?php echo number_format($record['cost'], 2); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['performed_by'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php if ($record['next_maintenance_date']): ?>
                                            <?php 
                                                $next_date = strtotime($record['next_maintenance_date']);
                                                $today = strtotime('today');
                                                $days_diff = floor(($next_date - $today) / (60 * 60 * 24));
                                                
                                                if ($days_diff < 0) {
                                                    $badge_class = 'bg-danger';
                                                    $status = 'Overdue';
                                                } elseif ($days_diff <= 7) {
                                                    $badge_class = 'bg-warning';
                                                    $status = 'Soon';
                                                } else {
                                                    $badge_class = 'bg-success';
                                                    $status = 'Scheduled';
                                                }
                                            ?>
                                            <?php echo date('M j, Y', $next_date); ?>
                                            <br><span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_maintenance.php?id=<?php echo $record['id']; ?>" class="btn btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (isAdmin()): ?>
                                            <a href="maintenance.php?delete_id=<?php echo $record['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this maintenance record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
                        <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No maintenance records found</h5>
                        <p class="text-muted">Get started by adding your first maintenance record.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class="fas fa-plus me-2"></i>Add Maintenance Record
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="addMaintenanceModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add Maintenance Record
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="asset_id" class="form-label required">Asset</label>
                                <select class="form-select" id="asset_id" name="asset_id" required onchange="updateAssetStatus()">
                                    <option value="">Select Asset</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['id']; ?>" data-status="<?php echo $asset['status']; ?>">
                                            <?php echo htmlspecialchars($asset['asset_name']); ?> (<?php echo htmlspecialchars($asset['serial_number']); ?>) - <?php echo $asset['status']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_date" class="form-label required">Maintenance Date</label>
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_type" class="form-label required">Maintenance Type</label>
                                <select class="form-select" id="maintenance_type" name="maintenance_type" required onchange="updateStatusOptions()">
                                    <option value="">Select Type</option>
                                    <option value="Routine">Routine Maintenance</option>
                                    <option value="Repair">Repair</option>
                                    <option value="Upgrade">Upgrade</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cost" class="form-label">Cost (₵)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₵</span>
                                    <input type="number" step="0.01" class="form-control" id="cost" name="cost" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="performed_by" class="form-label">Performed By</label>
                            <input type="text" class="form-control" id="performed_by" name="performed_by" placeholder="Technician name or company">
                        </div>

                        <!-- Asset Status Update Section -->
                        <div class="mb-3">
                            <label for="new_asset_status" class="form-label">Update Asset Status</label>
                            <select class="form-select" id="new_asset_status" name="new_asset_status">
                                <option value="">Keep Current Status</option>
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Retired">Retired</option>
                            </select>
                            <div class="form-text" id="statusHelpText">
                                Select the new status for this asset after maintenance
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="next_maintenance_date" class="form-label">Next Maintenance Date</label>
                            <input type="date" class="form-control" id="next_maintenance_date" name="next_maintenance_date">
                            <div class="form-text">Schedule the next maintenance date (optional)</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description & Notes</label>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe the maintenance work performed, parts replaced, issues found, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_maintenance" class="btn btn-success">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default next maintenance date (3 months from today)
            const today = new Date();
            const nextDate = new Date();
            nextDate.setMonth(today.getMonth() + 3);
            
            const nextMaintenanceField = document.getElementById('next_maintenance_date');
            if (nextMaintenanceField && !nextMaintenanceField.value) {
                nextMaintenanceField.value = nextDate.toISOString().split('T')[0];
            }

            // Auto-format cost field
            const costField = document.getElementById('cost');
            if (costField) {
                costField.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            }

            // Show success message if modal was submitted
            <?php if ($success && $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                const modal = new bootstrap.Modal(document.getElementById('addMaintenanceModal'));
                modal.hide();
            <?php endif; ?>
        });

        function updateAssetStatus() {
            const assetSelect = document.getElementById('asset_id');
            const selectedOption = assetSelect.options[assetSelect.selectedIndex];
            const currentStatus = selectedOption.getAttribute('data-status');
            
            // Update help text to show current status
            const helpText = document.getElementById('statusHelpText');
            if (helpText) {
                helpText.innerHTML = `Select the new status for this asset after maintenance. Current status: <strong>${currentStatus}</strong>`;
            }
        }

        function updateStatusOptions() {
            const maintenanceType = document.getElementById('maintenance_type').value;
            const statusSelect = document.getElementById('new_asset_status');
            const assetSelect = document.getElementById('asset_id');
            const selectedOption = assetSelect.options[assetSelect.selectedIndex];
            const currentStatus = selectedOption ? selectedOption.getAttribute('data-status') : '';
            
            // Auto-select appropriate status based on maintenance type
            if (maintenanceType === 'Repair' && currentStatus !== 'Under Maintenance') {
                statusSelect.value = 'Under Maintenance';
            } else if (maintenanceType === 'Routine' && currentStatus === 'Under Maintenance') {
                statusSelect.value = 'Available';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateAssetStatus();
        });
    </script>
</body>
</html>