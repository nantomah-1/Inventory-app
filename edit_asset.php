<?php
// edit_asset.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if asset ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Asset ID is required!";
    redirect('assets.php');
}

$asset_id = intval($_GET['id']);
$success = '';
$error = '';

// Fetch asset data
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    $_SESSION['error'] = "Asset not found!";
    redirect('assets.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $asset_name = trim($_POST['asset_name']);
        $asset_type = trim($_POST['asset_type']);
        $serial_number = trim($_POST['serial_number']);
        $model = trim($_POST['model']);
        $brand = trim($_POST['brand']);
        $specifications = trim($_POST['specifications']);
        $purchase_date = $_POST['purchase_date'];
        $purchase_price = $_POST['purchase_price'] ?: 0;
        $current_value = $_POST['current_value'] ?: 0;
        $status = $_POST['status'];
        $location = trim($_POST['location']);
        $assigned_to = trim($_POST['assigned_to']);

        // Validate required fields
        if (empty($asset_name) || empty($asset_type)) {
            throw new Exception("Asset Name and Asset Type are required!");
        }

        // Check if serial number already exists (excluding current asset)
        if (!empty($serial_number)) {
            $check_stmt = $pdo->prepare("SELECT id FROM assets WHERE serial_number = ? AND id != ?");
            $check_stmt->execute([$serial_number, $asset_id]);
            if ($check_stmt->fetch()) {
                throw new Exception("Serial number already exists in the system!");
            }
        }

        // Update the asset
        $stmt = $pdo->prepare("UPDATE assets SET asset_name = ?, asset_type = ?, serial_number = ?, model = ?, brand = ?, specifications = ?, purchase_date = ?, purchase_price = ?, current_value = ?, status = ?, location = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        
        if ($stmt->execute([$asset_name, $asset_type, $serial_number, $model, $brand, $specifications, $purchase_date, $purchase_price, $current_value, $status, $location, $assigned_to, $asset_id])) {
            $success = "Asset updated successfully!";
            // Refresh asset data
            $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$asset_id]);
            $asset = $stmt->fetch();
        } else {
            throw new Exception("Failed to update asset!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .currency-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .asset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header asset-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Asset</h4>
                            <span class="badge bg-light text-dark">ID: <?php echo $asset['id']; ?></span>
                        </div>
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

                        <!-- Asset Quick Info -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="row text-center">
                                            <div class="col-md-3 border-end">
                                                <small class="text-muted">Current Status</small>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        switch($asset['status']) {
                                                            case 'Available': echo 'success'; break;
                                                            case 'In Use': echo 'warning'; break;
                                                            case 'Under Maintenance': echo 'danger'; break;
                                                            case 'Retired': echo 'secondary'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?> fs-6"><?php echo $asset['status']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-3 border-end">
                                                <small class="text-muted">Location</small>
                                                <div class="fw-bold"><?php echo htmlspecialchars($asset['location']); ?></div>
                                            </div>
                                            <div class="col-md-3 border-end">
                                                <small class="text-muted">Assigned To</small>
                                                <div class="fw-bold"><?php echo htmlspecialchars($asset['assigned_to'] ?: 'Not Assigned'); ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Last Updated</small>
                                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($asset['updated_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" id="assetForm">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Basic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="asset_name" class="form-label required">Asset Name</label>
                                        <input type="text" class="form-control" id="asset_name" name="asset_name" 
                                               value="<?php echo htmlspecialchars($asset['asset_name']); ?>" 
                                               required placeholder="e.g., Desktop Computer, Laser Printer">
                                    </div>

                                    <div class="mb-3">
                                        <label for="asset_type" class="form-label required">Asset Type</label>
                                        <select class="form-select" id="asset_type" name="asset_type" required>
                                            <option value="">Select Asset Type</option>
                                            <option value="Desktop Computer" <?php echo $asset['asset_type'] == 'Desktop Computer' ? 'selected' : ''; ?>>Desktop Computer</option>
                                            <option value="Laptop" <?php echo $asset['asset_type'] == 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                                            <option value="Printer" <?php echo $asset['asset_type'] == 'Printer' ? 'selected' : ''; ?>>Printer</option>
                                            <option value="Scanner" <?php echo $asset['asset_type'] == 'Scanner' ? 'selected' : ''; ?>>Scanner</option>
                                            <option value="Projector" <?php echo $asset['asset_type'] == 'Projector' ? 'selected' : ''; ?>>Projector</option>
                                            <option value="Network Switch" <?php echo $asset['asset_type'] == 'Network Switch' ? 'selected' : ''; ?>>Network Switch</option>
                                            <option value="Router" <?php echo $asset['asset_type'] == 'Router' ? 'selected' : ''; ?>>Router</option>
                                            <option value="Server" <?php echo $asset['asset_type'] == 'Server' ? 'selected' : ''; ?>>Server</option>
                                            <option value="Monitor" <?php echo $asset['asset_type'] == 'Monitor' ? 'selected' : ''; ?>>Monitor</option>
                                            <option value="Tablet" <?php echo $asset['asset_type'] == 'Tablet' ? 'selected' : ''; ?>>Tablet</option>
                                            <option value="Software" <?php echo $asset['asset_type'] == 'Software' ? 'selected' : ''; ?>>Software</option>
                                            <option value="Other" <?php echo $asset['asset_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="serial_number" class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                               value="<?php echo htmlspecialchars($asset['serial_number']); ?>" 
                                               placeholder="Unique serial number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="brand" class="form-label">Brand</label>
                                        <input type="text" class="form-control" id="brand" name="brand" 
                                               value="<?php echo htmlspecialchars($asset['brand']); ?>" 
                                               placeholder="e.g., Dell, HP, Lenovo">
                                    </div>

                                    <div class="mb-3">
                                        <label for="model" class="form-label">Model</label>
                                        <input type="text" class="form-control" id="model" name="model" 
                                               value="<?php echo htmlspecialchars($asset['model']); ?>" 
                                               placeholder="e.g., OptiPlex 7070, ThinkPad X1">
                                    </div>
                                </div>

                                <!-- Additional Information -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Additional Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="purchase_date" class="form-label">Purchase Date</label>
                                        <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                               value="<?php echo $asset['purchase_date']; ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="purchase_price" class="form-label currency-label">Purchase Price (₵)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₵</span>
                                                <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" 
                                                       value="<?php echo $asset['purchase_price']; ?>" 
                                                       placeholder="0.00">
                                            </div>
                                            <div class="form-text">Original purchase price in Ghana Cedis</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="current_value" class="form-label currency-label">Current Value (₵)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₵</span>
                                                <input type="number" step="0.01" class="form-control" id="current_value" name="current_value" 
                                                       value="<?php echo $asset['current_value']; ?>" 
                                                       placeholder="0.00">
                                            </div>
                                            <div class="form-text">Current market value in Ghana Cedis</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="Available" <?php echo $asset['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="In Use" <?php echo $asset['status'] == 'In Use' ? 'selected' : ''; ?>>In Use</option>
                                            <option value="Under Maintenance" <?php echo $asset['status'] == 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                            <option value="Retired" <?php echo $asset['status'] == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($asset['location']); ?>" 
                                               placeholder="Asset location">
                                    </div>

                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">Assigned To</label>
                                        <input type="text" class="form-control" id="assigned_to" name="assigned_to" 
                                               value="<?php echo htmlspecialchars($asset['assigned_to']); ?>" 
                                               placeholder="Person, department, or lab">
                                    </div>
                                </div>
                            </div>

                            <!-- Specifications -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-3">Technical Specifications</h5>
                                    <div class="mb-3">
                                        <label for="specifications" class="form-label">Specifications & Notes</label>
                                        <textarea class="form-control" id="specifications" name="specifications" rows="4" 
                                                  placeholder="Enter technical specifications, configuration details, or any additional notes..."><?php echo htmlspecialchars($asset['specifications']); ?></textarea>
                                        <div class="form-text">
                                            Example: Intel Core i7, 16GB RAM, 512GB SSD, Windows 11 Pro, installed software list, etc.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="assets.php" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left me-2"></i>Back to Assets
                                            </a>
                                            <a href="view_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-info ms-2">
                                                <i class="fas fa-eye me-2"></i>View Asset
                                            </a>
                                        </div>
                                        <div>
                                            <button type="reset" class="btn btn-outline-secondary me-2">
                                                <i class="fas fa-redo me-2"></i>Reset Changes
                                            </button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save me-2"></i>Update Asset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Maintenance History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance History</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $maintenance_stmt = $pdo->prepare("SELECT * FROM maintenance_records WHERE asset_id = ? ORDER BY maintenance_date DESC LIMIT 5");
                        $maintenance_stmt->execute([$asset_id]);
                        $maintenance_records = $maintenance_stmt->fetchAll();
                        
                        if ($maintenance_records): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Cost (₵)</th>
                                            <th>Performed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($maintenance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($record['maintenance_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($record['maintenance_type']) {
                                                        case 'Routine': echo 'info'; break;
                                                        case 'Repair': echo 'warning'; break;
                                                        case 'Upgrade': echo 'success'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>"><?php echo $record['maintenance_type']; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['description']); ?></td>
                                            <td>₵<?php echo number_format($record['cost'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($record['performed_by']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center">
                                <a href="maintenance.php?asset_id=<?php echo $asset_id; ?>" class="btn btn-sm btn-outline-primary">
                                    View All Maintenance Records
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No maintenance records found.</p>
                            <div class="text-center mt-2">
                                <a href="add_maintenance.php?asset_id=<?php echo $asset_id; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Add Maintenance Record
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-calculate current value if purchase price is changed
            const purchasePriceField = document.getElementById('purchase_price');
            const currentValueField = document.getElementById('current_value');
            
            if (purchasePriceField && currentValueField) {
                purchasePriceField.addEventListener('input', function() {
                    if (this.value && confirm('Would you like to update the current value based on the new purchase price?')) {
                        const depreciatedValue = (parseFloat(this.value) * 0.8).toFixed(2);
                        currentValueField.value = depreciatedValue;
                    }
                });
            }

            // Form validation
            document.getElementById('assetForm').addEventListener('submit', function(e) {
                const assetName = document.getElementById('asset_name').value.trim();
                const assetType = document.getElementById('asset_type').value;
                
                if (!assetName || !assetType) {
                    e.preventDefault();
                    alert('Please fill in all required fields (marked with *).');
                    return false;
                }

                // Validate price fields
                const purchasePrice = document.getElementById('purchase_price').value;
                const currentValue = document.getElementById('current_value').value;
                
                if (purchasePrice && parseFloat(purchasePrice) < 0) {
                    e.preventDefault();
                    alert('Purchase price cannot be negative.');
                    return false;
                }
                
                if (currentValue && parseFloat(currentValue) < 0) {
                    e.preventDefault();
                    alert('Current value cannot be negative.');
                    return false;
                }

                return true;
            });

            // Format currency display on input
            function formatCurrencyInput(input) {
                input.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            }

            formatCurrencyInput(purchasePriceField);
            formatCurrencyInput(currentValueField);

            // Confirm reset
            document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reset all changes? This cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>