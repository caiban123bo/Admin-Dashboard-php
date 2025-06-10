<?php
require_once 'assets\db.php';
require_once 'sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Event
    if ($action === 'inline_update_event') {
        $stmt = $pdo->prepare("
            UPDATE SU_KIEN_KHUYEN_MAI 
            SET TEN_SU_KIEN = ?, MO_TA = ?, TY_LE_GIAM_GIA = ?, NGAY_BAT_DAU = ?, NGAY_KET_THUC = ? 
            WHERE MA_SU_KIEN = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            floatval($_POST['discount']),
            $_POST['start'],
            $_POST['end'],
            intval($_POST['id'])
        ]);
    }

    // Update Coupon
    elseif ($action === 'inline_update_coupon') {
        $stmt = $pdo->prepare("
            UPDATE MA_GIAM_GIA 
            SET MA = ?, GIA_TRI_GIAM = ?, SO_LAN_SU_DUNG_TOI_DA = ?, SO_LAN_DA_SU_DUNG = ?, NGAY_HET_HAN = ? 
            WHERE MA_CODE = ?
        ");
        $stmt->execute([
            $_POST['code'],
            floatval($_POST['value']),
            intval($_POST['usage_limit']),
            0, // Reset or keep used? You can change this if needed
            $_POST['expiry'],
            intval($_POST['id'])
        ]);
    }

    // Add Event
    elseif ($action === 'add_event') {
        $stmt = $pdo->prepare("
            INSERT INTO SU_KIEN_KHUYEN_MAI (TEN_SU_KIEN, NGAY_BAT_DAU, NGAY_KET_THUC, MO_TA, TY_LE_GIAM_GIA)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['start'],
            $_POST['end'],
            $_POST['description'],
            floatval($_POST['discount'])
        ]);
    }

    // Add Coupon
    elseif ($action === 'add_coupon') {
        $stmt = $pdo->prepare("
            INSERT INTO MA_GIAM_GIA (MA, GIA_TRI_GIAM, SO_LAN_SU_DUNG_TOI_DA, SO_LAN_DA_SU_DUNG, NGAY_HET_HAN)
            VALUES (?, ?, ?, 0, ?)
        ");
        $stmt->execute([
            $_POST['code'],
            floatval($_POST['value']),
            intval($_POST['usage_limit']),
            $_POST['expiry']
        ]);
    }

    header('Location: promotions.php');
    exit;
}

// Delete Event
if (isset($_GET['delete_event'])) {
    $id = intval($_GET['delete_event']);
    $stmt = $pdo->prepare("DELETE FROM SU_KIEN_KHUYEN_MAI WHERE MA_SU_KIEN = ?");
    $stmt->execute([$id]);
    header('Location: promotions.php');
    exit;
}

// Delete Coupon
if (isset($_GET['delete_coupon'])) {
    $id = intval($_GET['delete_coupon']);
    $stmt = $pdo->prepare("DELETE FROM MA_GIAM_GIA WHERE MA_CODE = ?");
    $stmt->execute([$id]);
    header('Location: promotions.php');
    exit;
}

// Fetch data
$events = $pdo->query(
    "SELECT MA_SU_KIEN AS id, TEN_SU_KIEN AS name, MO_TA AS description, TY_LE_GIAM_GIA AS discount,
     DATE(NGAY_BAT_DAU) AS start, DATE(NGAY_KET_THUC) AS end FROM SU_KIEN_KHUYEN_MAI"
)->fetchAll(PDO::FETCH_ASSOC);
$coupons = $pdo->query(
    "SELECT MA_CODE AS id, MA AS code, GIA_TRI_GIAM AS value, SO_LAN_SU_DUNG_TOI_DA AS usage_limit,
     SO_LAN_DA_SU_DUNG AS used, DATE(NGAY_HET_HAN) AS expiry FROM MA_GIAM_GIA"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khuyến mãi/sự kiện</title>
    <link rel="stylesheet" href="assets\style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>
        <div class="main-content">
            <div class="header">
                <h1>Quản lý khuyến mãi/sự kiện</h1>
            </div>
            <h2>Sự kiện</h2>

            <form method="post">
                <input type="hidden" name="action" value="inline_update_event">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Bắt đầu</th>
                            <th>Kết thúc</th>
                            <th>Mô tả</th>
                            <th>Giảm %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                        <tr>
                            <form method="post">
                                <td>
                                    <?= $e['id'] ?>
                                    <input type="hidden" name="action" value="inline_update_event">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                </td>
                                <td><input name="name" value="<?= htmlspecialchars($e['name']) ?>"></td>
                                <td><input type="date" name="start" value="<?= $e['start'] ?>"></td>
                                <td><input type="date" name="end" value="<?= $e['end'] ?>"></td>
                                <td>
                                    <textarea name="description" rows="1" onclick="this.rows=3;" onblur="this.rows=1;">
                <?= htmlspecialchars($e['description']) ?>
            </textarea>
                                </td>
                                <td><input type="number" step="0.01" name="discount" value="<?= $e['discount'] ?>"></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">Apply Edit</button>
                                    <a class="btn" href="?delete_event=<?= $e['id'] ?>"
                                        onclick="return confirm('Delete?');">Remove</a>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </form>
            <!-- Inline Edit Coupons -->
            <h2>Mã khuyến mãi</h2>
            <form method="post">
                <input type="hidden" name="action" value="inline_update_coupon">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Giá trị</th>
                            <th>Số lần sử dụng</th>
                            <th>Đã sử dụng</th>
                            <th>Hết hạn</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c): ?>
                        <tr>
                            <form method="post">
                                <td>
                                    <?php echo $c['id']; ?>
                                    <input type="hidden" name="action" value="inline_update_coupon">
                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                </td>
                                <td><input name="code" value="<?php echo htmlspecialchars($c['code']); ?>"></td>
                                <td>
                                    <input type="number" name="value" step="1000" value="<?php echo $c['value']; ?>">
                                    VND
                                </td>
                                <td><input type="number" name="usage_limit" value="<?php echo $c['usage_limit']; ?>">
                                </td>
                                <td><input type="number" name="used" value="<?php echo $c['used']; ?>"></td>
                                <td><input type="date" name="expiry" value="<?php echo $c['expiry']; ?>"></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">Apply Edit</button>
                                    <a class="btn" href="?delete_coupon=<?php echo $c['id']; ?>"
                                        onclick="return confirm('Delete?');">Remove</a>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <!-- Add New Forms -->
            <h3>Thêm sự kiện</h3>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="add_event">
                <input name="name" placeholder="Tên sự kiện" required>
                <input type="date" name="start" required>
                <input type="date" name="end" required>
                <input name="description" placeholder="Mô tả">
                <input type="number" step="0.01" name="discount" placeholder="Giảm (%)" required>
                <button class="btn btn-primary" type="submit">Add</button>
            </form>
            <h3>Thêm mã giảm giá</h3>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="add_coupon">
                <input name="code" placeholder="Mã" required>
                <input type="number" name="value" step="1000" placeholder="Giá trị giảm" required>
                <input type="number" name="usage_limit" placeholder="Số lần sử dụng" required>
                <input type="date" name="expiry" required>
                <button class="btn btn-primary" type="submit">Add</button>
            </form>
        </div>
    </div>
</body>

</html>