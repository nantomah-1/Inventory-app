<?php
// add_asset.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_name = $_POST['asset_name'];
    $asset_type = $_POST['asset_type'];
    $serial_number = $_POST['serial_number'];
    $model = $_POST['model'];
    $brand = $_POST['brand'];
    $specifications = $_POST['specifications'];
    $purchase_date = $_POST['purchase_date'];
    $purchase_price = $_POST['purchase_price'];
    $current_value = $_POST['current_value'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    $assigned_to = $_POST['assigned_to'];

    $stmt = $pdo->prepare("INSERT INTO assets (asset_name, asset_type, serial_number, model, brand, specifications, purchase_date, purchase_price, current_value, status, location, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$asset_name, $asset_type, $serial_number, $model, $brand, $specifications, $purchase_date, $purchase_price, $current_value, $status, $location, $assigned_to])) {
        $_SESSION['success'] = "Asset added successfully!";
        redirect('assets.php');
    } else {
        $error = "Failed to add asset!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Asset - NaconM ICT Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Asset</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="asset_name" class="form-label">Asset Name *</label>
                                    <input type="text" class="form-control" id="asset_name" name="asset_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="asset_type" class="form-label">Asset Type *</label>
                                    <select class="form-select" id="asset_type" name="asset_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Computer">Computer</option>
                                        <option value="Laptop">Laptop</option>
                                        <option value="Printer">Printer</option>
                                        <option value="Scanner">Scanner</option>
                                        <option value="Network Device">Network Device</option>
                                        <option value="Projector">Projector</option>
                                        <option value="Software">Software</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="purchase_price" class="form-label">Purchase Price</label>
                                    <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="current_value" class="form-label">Current Value</label>
                                    <input type="number" step="0.01" class="form-control" id="current_value" name="current_value">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Available">Available</option>
                                        <option value="In Use">In Use</option>
                                        <option value="Under Maintenance">Under Maintenance</option>
                                        <option value="Retired">Retired</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" value="NaconM ICT Lab">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="assigned_to" class="form-label">Assigned To</label>
                                <input type="text" class="form-control" id="assigned_to" name="assigned_to" placeholder="Person or department">
                            </div>

                            <div class="mb-3">
                                <label for="specifications" class="form-label">Specifications</label>
                                <textarea class="form-control" id="specifications" name="specifications" rows="4"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Asset</button>
                            <a href="assets.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>