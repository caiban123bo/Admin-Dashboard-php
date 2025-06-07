<?php
require_once 'assets\db.php';
require_once 'sidebar.php';
// Load sidebar


// Handle actions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO NGUOI_DUNG (HO_TEN, EMAIL, SO_DIEN_THOAI, MAT_KHAU, DIA_CHI, TRANG_THAI) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['ho_ten'], $_POST['email'], $_POST['so_dien_thoai'],
            password_hash($_POST['mat_khau'], PASSWORD_DEFAULT),
            $_POST['dia_chi'], $_POST['trang_thai']
        ]);
    } elseif ($action === 'update') {
    $stmt = $pdo->prepare("UPDATE NGUOI_DUNG SET HO_TEN=?, EMAIL=?, SO_DIEN_THOAI=?, DIA_CHI=?, TRANG_THAI=? WHERE MA_NGUOI_DUNG=?");
    $stmt->execute([
        $_POST['ho_ten'], $_POST['email'], $_POST['so_dien_thoai'],
        $_POST['dia_chi'], $_POST['trang_thai'], $_POST['id']
    ]);
    }
    header("Location: users.php");
    exit;
}

if (isset($_GET['remove_id'])) {
    $id = $_GET['remove_id'];
    $pdo->exec("DELETE FROM NGUOI_DUNG WHERE MA_NGUOI_DUNG = $id");
    header("Location: users.php");
    exit;
}

$users = $pdo->query("SELECT * FROM NGUOI_DUNG")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Quản lý người dùng</title>
    <link rel="stylesheet" href="assets\style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>
        <div class="main-content">
            <div class="header">
                <h1>Quản lý người dùng</h1>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Họ & Tên</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th>Địa chỉ</th>
                            <th>Trạng thái</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <form method="post">
                                <td>
                                    <?= $user['MA_NGUOI_DUNG'] ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $user['MA_NGUOI_DUNG'] ?>">
                                </td>
                                <td><input name="ho_ten" value="<?= htmlspecialchars($user['HO_TEN']) ?>"></td>
                                <td><input name="email" value="<?= htmlspecialchars($user['EMAIL']) ?>"></td>
                                <td><input name="so_dien_thoai" value="<?= htmlspecialchars($user['SO_DIEN_THOAI']) ?>">
                                </td>
                                <td><input name="dia_chi" value="<?= htmlspecialchars($user['DIA_CHI']) ?>"></td>
                                <td><input name="trang_thai" value="<?= htmlspecialchars($user['TRANG_THAI']) ?>"></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">Apply Edit</button>
                                    <a class="btn btn-primary" href="?remove_id=<?= $user['MA_NGUOI_DUNG'] ?>"
                                        onclick="return confirm('Are you sure you want to delete this user?')">
                                        Remove
                                    </a>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </form>
            <h2>Thêm người dùng</h2>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="add">
                <input name="ho_ten" placeholder="Full Name" required>
                <input name="email" type="email" placeholder="Email" required>
                <input name="so_dien_thoai" placeholder="Phone" required>
                <input name="mat_khau" type="password" placeholder="Password" required>
                <input name="dia_chi" placeholder="Address">
                <input name="trang_thai" placeholder="Status">
                <button class="btn btn-primary" type="submit">Add</button>
            </form>
        </div>
    </div>
</body>

</html>

</html>