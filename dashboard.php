<?php
// dashboard.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Get statistics
$total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$available_assets = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
$assets_in_use = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'In Use'")->fetchColumn();
$under_maintenance = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();

// Get asset counts by type for the report
$assets_by_type = $pdo->query("
    SELECT asset_type, COUNT(*) as count, 
           SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
           SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) as in_use,
           SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance,
           SUM(CASE WHEN status = 'Retired' THEN 1 ELSE 0 END) as retired
    FROM assets 
    GROUP BY asset_type 
    ORDER BY count DESC
")->fetchAll();

// Get asset counts by status for the report
$assets_by_status = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM assets 
    GROUP BY status 
    ORDER BY count DESC
")->fetchAll();

// Get total asset value
$total_value = $pdo->query("SELECT SUM(current_value) as total_value FROM assets")->fetchColumn();

// Recent assets
$recent_assets = $pdo->query("SELECT * FROM assets ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">NaconM ICT Lab</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_assets; ?></h4>
                                <p>Total Assets</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-laptop fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $available_assets; ?></h4>
                                <p>Available</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $assets_in_use; ?></h4>
                                <p>In Use</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $under_maintenance; ?></h4>
                                <p>Maintenance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tools fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="assets.php" class="btn btn-primary me-2">
                            <i class="fas fa-list"></i> View All Assets
                        </a>
                        <a href="add_asset.php" class="btn btn-success me-2">
                            <i class="fas fa-plus"></i> Add New Asset
                        </a>
                        <a href="maintenance.php" class="btn btn-warning me-2">
                            <i class="fas fa-tools"></i> Maintenance
                        </a>
                        <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="fas fa-chart-bar"></i> Reports
                        </button>
                        <?php if (isAdmin()): ?>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Assets -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Recently Added Assets</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Asset Name</th>
                                        <th>Type</th>
                                        <th>Serial No.</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_assets as $asset): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['asset_type']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($asset['status']) {
                                                    case 'Available': echo 'success'; break;
                                                    case 'In Use': echo 'warning'; break;
                                                    case 'Under Maintenance': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>"><?php echo $asset['status']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                        <td>
                                            <a href="view_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="reportModalLabel">
                        <i class="fas fa-chart-bar me-2"></i>Asset Reports
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-primary"><?php echo $total_assets; ?></h3>
                                    <p class="mb-0">Total Assets</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-success">₵<?php echo number_format($total_value, 2); ?></h3>
                                    <p class="mb-0">Total Asset Value</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-info"><?php echo count($assets_by_type); ?></h3>
                                    <p class="mb-0">Asset Categories</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assets by Type -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Assets by Category</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Asset Type</th>
                                            <th>Total</th>
                                            <th>Available</th>
                                            <th>In Use</th>
                                            <th>Maintenance</th>
                                            <th>Retired</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets_by_type as $type): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($type['asset_type']); ?></strong></td>
                                            <td><?php echo $type['count']; ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $type['available']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $type['in_use']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $type['maintenance']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $type['retired']; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" 
                                                         role="progressbar" 
                                                         style="width: <?php echo ($type['count'] / $total_assets) * 100; ?>%"
                                                         aria-valuenow="<?php echo ($type['count'] / $total_assets) * 100; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo number_format(($type['count'] / $total_assets) * 100, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Assets by Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Assets by Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assets_by_status as $status): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($status['status']) {
                                                            case 'Available': echo 'success'; break;
                                                            case 'In Use': echo 'warning'; break;
                                                            case 'Under Maintenance': echo 'danger'; break;
                                                            case 'Retired': echo 'secondary'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>"><?php echo $status['status']; ?></span>
                                                </td>
                                                <td><strong><?php echo $status['count']; ?></strong></td>
                                                <td>
                                                    <div class="progress" style="height: 15px;">
                                                        <div class="progress-bar bg-<?php 
                                                            switch($status['status']) {
                                                                case 'Available': echo 'success'; break;
                                                                case 'In Use': echo 'warning'; break;
                                                                case 'Under Maintenance': echo 'danger'; break;
                                                                case 'Retired': echo 'secondary'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo ($status['count'] / $total_assets) * 100; ?>%"
                                                             aria-valuenow="<?php echo ($status['count'] / $total_assets) * 100; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo number_format(($status['count'] / $total_assets) * 100, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="mb-3">
                                            <span class="badge bg-success me-2">■</span> Available: <?php echo $available_assets; ?>
                                        </div>
                                        <div class="mb-3">
                                            <span class="badge bg-warning me-2">■</span> In Use: <?php echo $assets_in_use; ?>
                                        </div>
                                        <div class="mb-3">
                                            <span class="badge bg-danger me-2">■</span> Under Maintenance: <?php echo $under_maintenance; ?>
                                        </div>
                                        <?php 
                                        $retired_assets = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Retired'")->fetchColumn();
                                        if ($retired_assets > 0): ?>
                                        <div class="mb-3">
                                            <span class="badge bg-secondary me-2">■</span> Retired: <?php echo $retired_assets; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-download me-2"></i>Export Reports</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <a href="generate_report.php?type=summary" class="btn btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-file-pdf me-2"></i>Summary PDF
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="generate_report.php?type=detailed" class="btn btn-outline-success w-100 mb-2">
                                        <i class="fas fa-file-excel me-2"></i>Detailed Excel
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="generate_report.php?type=categorical" class="btn btn-outline-info w-100 mb-2">
                                        <i class="fas fa-file-csv me-2"></i>Category CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>