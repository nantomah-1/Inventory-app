<?php
// assets.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM assets WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (asset_name LIKE ? OR serial_number LIKE ? OR model LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Asset Management</h2>
            <a href="add_asset.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Asset
            </a>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search assets..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Available" <?php echo $status == 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="In Use" <?php echo $status == 'In Use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="Under Maintenance" <?php echo $status == 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="assets.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assets Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Asset Name</th>
                                <th>Type</th>
                                <th>Serial No.</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><?php echo $asset['id']; ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_type']); ?></td>
                                <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                <td><?php echo htmlspecialchars($asset['model']); ?></td>
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
                                <td><?php echo htmlspecialchars($asset['assigned_to'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="view_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (isAdmin()): ?>
                                    <a href="delete_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>