<?php
// view_asset.php
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

// Fetch asset data with additional information
$stmt = $pdo->prepare("
    SELECT a.*, 
           COUNT(mr.id) as maintenance_count,
           MAX(mr.maintenance_date) as last_maintenance_date,
           SUM(mr.cost) as total_maintenance_cost
    FROM assets a 
    LEFT JOIN maintenance_records mr ON a.id = mr.asset_id 
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    $_SESSION['error'] = "Asset not found!";
    redirect('assets.php');
}

// Fetch maintenance history for this asset
$maintenance_stmt = $pdo->prepare("
    SELECT * FROM maintenance_records 
    WHERE asset_id = ? 
    ORDER BY maintenance_date DESC
");
$maintenance_stmt->execute([$asset_id]);
$maintenance_history = $maintenance_stmt->fetchAll();

// Calculate asset age
$purchase_date = new DateTime($asset['purchase_date']);
$today = new DateTime();
$asset_age = $today->diff($purchase_date)->y;

// Calculate depreciation
$purchase_price = floatval($asset['purchase_price']);
$current_value = floatval($asset['current_value']);
$depreciation_amount = $purchase_price - $current_value;
$depreciation_percentage = $purchase_price > 0 ? ($depreciation_amount / $purchase_price) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($asset['asset_name']); ?> - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .asset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .info-card {
            border-left: 4px solid #007bff;
        }
        .financial-card {
            border-left: 4px solid #28a745;
        }
        .maintenance-card {
            border-left: 4px solid #ffc107;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        .specs-pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            white-space: pre-wrap;
            font-family: inherit;
            font-size: 0.9rem;
        }
        .value-change {
            font-size: 0.85rem;
        }
        .positive {
            color: #28a745;
        }
        .negative {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="card">
            <div class="card-header asset-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-laptop me-2"></i><?php echo htmlspecialchars($asset['asset_name']); ?></h2>
                        <p class="mb-0">Asset ID: <?php echo $asset['id']; ?> | <?php echo htmlspecialchars($asset['asset_type']); ?></p>
                    </div>
                    <div class="text-end">
                        <span class="status-badge badge bg-<?php 
                            switch($asset['status']) {
                                case 'Available': echo 'success'; break;
                                case 'In Use': echo 'warning'; break;
                                case 'Under Maintenance': echo 'danger'; break;
                                case 'Retired': echo 'secondary'; break;
                                default: echo 'secondary';
                            }
                        ?>"><?php echo $asset['status']; ?></span>
                        <div class="mt-2">
                            <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <a href="assets.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Assets
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Basic Information -->
            <div class="col-md-6">
                <!-- Basic Information Card -->
                <div class="card info-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted">Asset Type:</th>
                                        <td><?php echo htmlspecialchars($asset['asset_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Brand:</th>
                                        <td><?php echo htmlspecialchars($asset['brand'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Model:</th>
                                        <td><?php echo htmlspecialchars($asset['model'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Serial Number:</th>
                                        <td>
                                            <?php if ($asset['serial_number']): ?>
                                                <code><?php echo htmlspecialchars($asset['serial_number']); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted">Location:</th>
                                        <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Assigned To:</th>
                                        <td>
                                            <?php if ($asset['assigned_to']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($asset['assigned_to']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Purchase Date:</th>
                                        <td>
                                            <?php if ($asset['purchase_date']): ?>
                                                <?php echo date('M j, Y', strtotime($asset['purchase_date'])); ?>
                                                <small class="text-muted d-block">(<?php echo $asset_age; ?> year<?php echo $asset_age != 1 ? 's' : ''; ?> ago)</small>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Specifications Card -->
                <?php if ($asset['specifications']): ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Technical Specifications</h5>
                    </div>
                    <div class="card-body">
                        <pre class="specs-pre"><?php echo htmlspecialchars($asset['specifications']); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Financial & Maintenance -->
            <div class="col-md-6">
                <!-- Financial Information Card -->
                <div class="card financial-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Financial Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">Purchase Price</h6>
                                    <h4 class="text-primary">₵<?php echo number_format($asset['purchase_price'], 2); ?></h4>
                                    <small class="text-muted">Original cost</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted mb-2">Current Value</h6>
                                    <h4 class="text-success">₵<?php echo number_format($asset['current_value'], 2); ?></h4>
                                    <small class="text-muted">Current market value</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($asset['purchase_price'] > 0): ?>
                        <div class="mt-3">
                            <h6>Depreciation Analysis</h6>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo $depreciation_percentage > 50 ? 'danger' : 'warning'; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($depreciation_percentage, 100); ?>%"
                                     aria-valuenow="<?php echo $depreciation_percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo number_format($depreciation_percentage, 1); ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    Depreciation: ₵<?php echo number_format($depreciation_amount, 2); ?>
                                </small>
                                <small class="text-muted">
                                    Value Retained: <?php echo number_format(100 - $depreciation_percentage, 1); ?>%
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Maintenance Summary Card -->
                <div class="card maintenance-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-2">
                                    <h6 class="text-muted mb-1">Total Maintenance</h6>
                                    <h4 class="text-primary"><?php echo $asset['maintenance_count']; ?></h4>
                                    <small class="text-muted">Records</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-2">
                                    <h6 class="text-muted mb-1">Total Cost</h6>
                                    <h4 class="text-success">₵<?php echo number_format($asset['total_maintenance_cost'], 2); ?></h4>
                                    <small class="text-muted">Spent</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-2">
                                    <h6 class="text-muted mb-1">Last Maintenance</h6>
                                    <h6 class="text-info">
                                        <?php if ($asset['last_maintenance_date']): ?>
                                            <?php echo date('M j, Y', strtotime($asset['last_maintenance_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                            </div>
                        </div>

                        <?php if ($asset['next_maintenance']): ?>
                        <div class="alert alert-info mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Next Maintenance:</strong> 
                                    <?php echo date('M j, Y', strtotime($asset['next_maintenance'])); ?>
                                </div>
                                <?php
                                $next_date = strtotime($asset['next_maintenance']);
                                $today = strtotime('today');
                                $days_until = floor(($next_date - $today) / (60 * 60 * 24));
                                
                                if ($days_until < 0) {
                                    $alert_class = 'danger';
                                    $message = 'Overdue by ' . abs($days_until) . ' days';
                                } elseif ($days_until <= 7) {
                                    $alert_class = 'warning';
                                    $message = 'Due in ' . $days_until . ' days';
                                } else {
                                    $alert_class = 'success';
                                    $message = 'In ' . $days_until . ' days';
                                }
                                ?>
                                <span class="badge bg-<?php echo $alert_class; ?>"><?php echo $message; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="text-center">
                            <a href="maintenance.php?asset_filter=<?php echo $asset['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                <i class="fas fa-list me-1"></i>View All Maintenance
                            </a>
                            <a href="add_maintenance.php?asset_id=<?php echo $asset['id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Maintenance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance History Card -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Maintenance History</h5>
            </div>
            <div class="card-body">
                <?php if ($maintenance_history): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Cost (₵)</th>
                                    <th>Performed By</th>
                                    <th>Next Maintenance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_history as $record): ?>
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
                                    <td>
                                        <?php echo htmlspecialchars($record['description'] ?: 'No description'); ?>
                                        <?php if (strlen($record['description']) > 100): ?>
                                            <br><small class="text-muted"><?php echo substr($record['description'], 0, 100); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>₵<?php echo number_format($record['cost'], 2); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['performed_by'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php if ($record['next_maintenance_date']): ?>
                                            <?php echo date('M j, Y', strtotime($record['next_maintenance_date'])); ?>
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
                        <p class="text-muted">This asset has no maintenance history yet.</p>
                        <a href="add_maintenance.php?asset_id=<?php echo $asset['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add First Maintenance Record
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-stream me-2"></i>Asset Timeline</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php
                    $timeline_events = [];
                    
                    // Purchase event
                    if ($asset['purchase_date']) {
                        $timeline_events[] = [
                            'date' => $asset['purchase_date'],
                            'event' => 'Asset Purchased',
                            'icon' => 'fa-shopping-cart',
                            'color' => 'success'
                        ];
                    }
                    
                    // Maintenance events
                    foreach ($maintenance_history as $record) {
                        $timeline_events[] = [
                            'date' => $record['maintenance_date'],
                            'event' => $record['maintenance_type'] . ' Maintenance',
                            'icon' => 'fa-tools',
                            'color' => 'info',
                            'details' => $record['description']
                        ];
                    }
                    
                    // Sort events by date
                    usort($timeline_events, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });
                    
                    // Display events
                    if ($timeline_events):
                    ?>
                        <div class="position-relative">
                            <?php foreach (array_slice($timeline_events, 0, 5) as $event): ?>
                            <div class="position-relative ps-4 pb-3">
                                <div class="position-absolute top-0 start-0 translate-middle">
                                    <i class="fas <?php echo $event['icon']; ?> fa-fw text-<?php echo $event['color']; ?>"></i>
                                </div>
                                <div class="ms-4">
                                    <h6 class="mb-1"><?php echo $event['event']; ?></h6>
                                    <p class="mb-1 text-muted"><?php echo date('F j, Y', strtotime($event['date'])); ?></p>
                                    <?php if (isset($event['details']) && $event['details']): ?>
                                        <p class="mb-0 small"><?php echo htmlspecialchars(substr($event['details'], 0, 100)); ?><?php echo strlen($event['details']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No timeline events available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>