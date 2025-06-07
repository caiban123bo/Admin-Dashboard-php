<?php
require_once 'assets\db.php';
require_once 'sidebar.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order') {
    $stmt = $pdo->prepare("
        UPDATE CHI_TIET_DON_HANG c
        JOIN DON_HANG d ON c.MA_DON_HANG = d.MA_DON_HANG
        SET 
            c.MA_DON_HANG            = ?,
            c.MA_SAN_PHAM            = ?,
            c.SO_LUONG               = ?,
            c.DON_GIA                = ?,      
            d.TRANG_THAI_DON_HANG    = ?
        WHERE c.MA_CHI_TIET_DON_HANG = ?
    ");
    $stmt->execute([
        $_POST['ma_don_hang'], 
        $_POST['ma_san_pham'], 
        $_POST['so_luong'], 
        $_POST['price'],         
        $_POST['trang_thai_don_hang'],
        $_POST['id']
    ]);
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
        c.DON_GIA,
        d.TRANG_THAI_DON_HANG
    FROM CHI_TIET_DON_HANG c
    JOIN SAN_PHAM s ON c.MA_SAN_PHAM = s.MA_SAN_PHAM
    JOIN DON_HANG d ON c.MA_DON_HANG = d.MA_DON_HANG
");
$orderDetails = $stmt->fetchAll();

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
            <form method="post">
                <input type="hidden" name="action" value="update_order">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderDetails as $row): ?>
                        <tr>
                            <form method="post" style="display:flex; gap:0;">
                                <input type="hidden" name="action" value="update_order">
                                <input type="hidden" name="id" value="<?= $row['MA_CHI_TIET_DON_HANG'] ?>">
                                <td><?= $row['MA_CHI_TIET_DON_HANG'] ?></td>
                                <td>
                                    <input name="ma_don_hang" value="<?= $row['MA_DON_HANG'] ?>" style="width:80px;">
                                </td>
                                <td>
                                    <input name="ma_san_pham" value="<?= $row['MA_SAN_PHAM'] ?>" style="width:80px;">
                                    — <?= htmlspecialchars($row['TEN_SAN_PHAM']) ?>
                                </td>
                                <td>
                                    <input type="number" name="so_luong" value="<?= $row['SO_LUONG'] ?>"
                                        style="width:80px;">
                                </td>
                                <td>
                                    <input type="number" name="price" step="1000" value="<?= $row['DON_GIA'] ?>"
                                        style="width:120px;">
                                    <?= number_format($row['DON_GIA'], 0, ',', '.') ?> VND
                                </td>
                                <td>
                                    <select name="trang_thai_don_hang">
                                        <?php 
              $statuses = ['CHO_XU_LY','DANG_XU_LY','DA_GIAO','HOAN_THANH','DA_HUY'];
              foreach ($statuses as $status): 
            ?>
                                        <option value="<?= $status ?>"
                                            <?= $status === $row['TRANG_THAI_DON_HANG'] ? 'selected' : '' ?>>
                                            <?= $status ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <button class="btn btn-primary" type="submit">Apply Edit</button>
                                    <a class="btn btn-primary" href="?delete=<?= $row['MA_CHI_TIET_DON_HANG'] ?>"
                                        onclick="return confirm('Delete this entry?')">
                                        Remove
                                    </a>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</body>

</html>