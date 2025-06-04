<?php
require_once 'assets\db.php';
require_once 'sidebar.php';
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO CHI_TIET_DON_HANG (MA_DON_HANG, MA_SAN_PHAM, SO_LUONG, DON_GIA) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['ma_don_hang'],
            $_POST['ma_san_pham'],
            $_POST['so_luong'],
            $_POST['don_gia']
        ]);
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE CHI_TIET_DON_HANG SET MA_DON_HANG = ?, MA_SAN_PHAM = ?, SO_LUONG = ?, DON_GIA = ? WHERE MA_CHI_TIET_DON_HANG = ?");
        $stmt->execute([
            $_POST['ma_don_hang'],
            $_POST['ma_san_pham'],
            $_POST['so_luong'],
            $_POST['don_gia'],
            $_POST['id']
        ]);
    }
    header("Location: order_details.php");
    exit;
}

// Handle deletion
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM CHI_TIET_DON_HANG WHERE MA_CHI_TIET_DON_HANG = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: order_details.php");
    exit;
}

// Fetch all records
$stmt = $pdo->query("
    SELECT 
        c.MA_CHI_TIET_DON_HANG,
        c.MA_DON_HANG,
        c.MA_SAN_PHAM,
        s.TEN_SAN_PHAM,
        c.SO_LUONG,
        c.DON_GIA
    FROM CHI_TIET_DON_HANG c
    JOIN SAN_PHAM s ON c.MA_SAN_PHAM = s.MA_SAN_PHAM
");
$orderDetails = $stmt->fetchAll();

// Fetch one record for editing
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM CHI_TIET_DON_HANG WHERE MA_CHI_TIET_DON_HANG = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch();
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="assets\style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>

        <div class="main-content">
            <div class="header">
                <h1>Quản lý đơn hàng</h1>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order ID</th>
                        <th>Product ID</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderDetails as $row): ?>
                    <tr>
                        <td><?= $row['MA_CHI_TIET_DON_HANG'] ?></td>
                        <td><?= $row['MA_DON_HANG'] ?></td>
                        <td><?= $row['MA_SAN_PHAM'] . ' - ' . htmlspecialchars($row['TEN_SAN_PHAM']) ?></td>
                        <td><?= $row['SO_LUONG'] ?></td>
                        <td><?= number_format($row['DON_GIA'], 0, ',', '.') ?> VND</td>
                        <td>
                            <a href="?edit=<?= $row['MA_CHI_TIET_DON_HANG'] ?>" style="text-decoration:none;color:cyan">Edit</a> |
                            <a href="?delete=<?= $row['MA_CHI_TIET_DON_HANG'] ?>"
                                onclick="return confirm('Delete this entry?')"style="text-decoration:none;color:cyan">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?= $editData ? 'Edit' : 'Add New' ?> Order Detail</h3>
            <form method="post">
                <input type="hidden" name="action" value="<?= $editData ? 'update' : 'add' ?>">
                <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= $editData['MA_CHI_TIET_DON_HANG'] ?>">
                <?php endif; ?>
                <input name="ma_don_hang" placeholder="Order ID" value="<?= $editData['MA_DON_HANG'] ?? '' ?>" required>
                <input name="ma_san_pham" placeholder="Product ID" value="<?= $editData['MA_SAN_PHAM'] ?? '' ?>"
                    required>
                <input type="number" name="so_luong" placeholder="Quantity" value="<?= $editData['SO_LUONG'] ?? '' ?>"
                    required>
                <input type="number" step="0.01" name="don_gia" placeholder="Price"
                    value="<?= $editData['DON_GIA'] ?? '' ?>" required>
                <button type="submit"><?= $editData ? 'Update' : 'Add' ?> Detail</button>
            </form>
        </div>
    </div>
</body>

</html>