<?php
require_once 'assets\db.php';
require_once 'sidebar.php';
require_once __DIR__ . '/libs/SimpleXLSXGen.php';
require_once __DIR__ . '/libs/tcpdf/tcpdf.php';

// Fetch total product count
$stmt = $pdo->query("SELECT COUNT(*) FROM SAN_PHAM");
$totalProducts = $stmt->fetchColumn();

// Fetch total sold & revenue
$stmt = $pdo->query("SELECT SUM(SO_LUONG) AS total_sold, SUM(SO_LUONG * DON_GIA) AS total_revenue FROM CHI_TIET_DON_HANG");
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSold = $data['total_sold'] ?? 0;
$totalRevenue = $data['total_revenue'] ?? 0;

// Load category list for dropdown
$categories = $pdo->query("SELECT MA_DANH_MUC, TEN_DANH_MUC FROM DANH_MUC")->fetchAll(PDO::FETCH_KEY_PAIR);

// Load product list
$search = $_GET['search'] ?? '';
$min = $_GET['min_price'] ?? 0;
$max = $_GET['max_price'] ?? 1000000000;

$query = "SELECT sp.MA_SAN_PHAM AS id, sp.TEN_SAN_PHAM AS name, sp.GIA_BAN AS price, sp.SO_LUONG_TON AS stock,
    sp.MO_TA AS description, sp.HINH_ANH AS image, sp.MA_DANH_MUC AS category_id,
    dm.TEN_DANH_MUC AS category_name,
    COALESCE(SUM(ct.SL), 0) AS sold FROM SAN_PHAM sp
    LEFT JOIN (SELECT MA_SAN_PHAM, SUM(SO_LUONG) AS SL FROM CHI_TIET_DON_HANG GROUP BY MA_SAN_PHAM) ct
    ON sp.MA_SAN_PHAM = ct.MA_SAN_PHAM
    LEFT JOIN DANH_MUC dm ON sp.MA_DANH_MUC = dm.MA_DANH_MUC
    WHERE sp.TEN_SAN_PHAM LIKE ? AND GIA_BAN BETWEEN ? AND ?
    GROUP BY sp.MA_SAN_PHAM";

$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", $min, $max]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination setup
$page = $_GET['page'] ?? 1;
$perPage = 5;
$totalPages = ceil(count($products) / $perPage);
$paginated = array_slice($products, ($page - 1) * $perPage, $perPage);

// Helper to get product data
function getProductData($pdo, $filter = '', $startDate = '', $endDate = '') {
    $where = '';
    $params = [];

    if ($startDate && $endDate) {
        $where = "WHERE dh.NGAY_TAO >= :start AND dh.NGAY_TAO <= :end";
        $params['start'] = $startDate;
        $params['end'] = $endDate;
    }

    $sql = "
        SELECT sp.MA_SAN_PHAM AS id, sp.TEN_SAN_PHAM AS name, sp.GIA_BAN AS price,
               sp.SO_LUONG_TON AS stock,
               COALESCE(SUM(ctdh.SO_LUONG), 0) AS sold,
               dm.TEN_DANH_MUC AS category,
               sp.MO_TA AS description
        FROM SAN_PHAM sp
        LEFT JOIN DANH_MUC dm ON sp.MA_DANH_MUC = dm.MA_DANH_MUC
        LEFT JOIN CHI_TIET_DON_HANG ctdh ON sp.MA_SAN_PHAM = ctdh.MA_SAN_PHAM
        LEFT JOIN DON_HANG dh ON ctdh.MA_DON_HANG = dh.MA_DON_HANG
        $where
        GROUP BY sp.MA_SAN_PHAM
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":" . $key, $value);
    }
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($filter === 'top3') {
        usort($products, fn($a, $b) => $b['sold'] - $a['sold']);
        return array_slice($products, 0, 3);
    } elseif ($filter === 'top10') {
        usort($products, fn($a, $b) => $b['sold'] - $a['sold']);
        return array_slice($products, 0, 10);
    }
    return $products;
}

// Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filter = $_GET['filter'] ?? '';
    $start = $_GET['start_date'] ?? '';
    $end = $_GET['end_date'] ?? '';
    $products = getProductData($pdo, $filter, $start, $end);

    $rows = [["ID", "Name", "Price", "Stock", "Sold", "Category", "Description"]];
    foreach ($products as $p) {
        $rows[] = [$p['id'], $p['name'], $p['price'], $p['stock'], $p['sold'], $p['category'], $p['description']];
    }
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($rows);
    $xlsx->downloadAs("products_export.xlsx");
    exit;
}

// Export PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $filter = $_GET['filter'] ?? '';
    $start = $_GET['start_date'] ?? '';
    $end = $_GET['end_date'] ?? '';
    $products = getProductData($pdo, $filter, $start, $end);

    $pdf = new TCPDF();
    $pdf->SetCreator('Shop Admin');
    $pdf->SetTitle('Product Report');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Product Export Report', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 10);
    $headers = ['ID', 'Name', 'Price', 'Stock', 'Sold', 'Category', 'Description'];
    foreach ($headers as $col) {
        $pdf->Cell(28, 7, $col, 1);
    }
    $pdf->Ln();
    $pdf->SetFont('helvetica', '', 9);

    foreach ($products as $p) {
        $pdf->Cell(28, 6, $p['id'], 1);
        $pdf->Cell(28, 6, substr($p['name'], 0, 15), 1);
        $pdf->Cell(28, 6, $p['price'], 1);
        $pdf->Cell(28, 6, $p['stock'], 1);
        $pdf->Cell(28, 6, $p['sold'], 1);
        $pdf->Cell(28, 6, substr($p['category'], 0, 15), 1);
        $pdf->Cell(28, 6, substr($p['description'], 0, 15), 1);
        $pdf->Ln();
    }

    $pdf->Output('products.pdf', 'D');
    exit;
}


// Handle Add / Edit / Sell / Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO SAN_PHAM (TEN_SAN_PHAM, GIA_BAN, SO_LUONG_TON, MA_DANH_MUC, MO_TA, HINH_ANH) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], $_POST['category'], $_POST['description'], $_POST['image']]);
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE SAN_PHAM SET TEN_SAN_PHAM=?, GIA_BAN=?, SO_LUONG_TON=?, MA_DANH_MUC=?, MO_TA=?, HINH_ANH=? WHERE MA_SAN_PHAM=?");
        $stmt->execute([$_POST['name'], $_POST['price'], $_POST['stock'], $_POST['category'], $_POST['description'], $_POST['image'], $_POST['id']]);
    }
    header("Location: index.php");
    exit;
}

if (isset($_GET['sell_id'])) {
    $id = $_GET['sell_id'];
    $pdo->exec("UPDATE SAN_PHAM SET SO_LUONG_TON = SO_LUONG_TON - 1 WHERE MA_SAN_PHAM = $id AND SO_LUONG_TON > 0");
    header("Location: index.php");
    exit;
}

if (isset($_GET['remove_id'])) {
    $id = $_GET['remove_id'];
    $pdo->exec("DELETE FROM SAN_PHAM WHERE MA_SAN_PHAM = $id");
    header("Location: index.php");
    exit;
}

function findProductById($id, $products) {
    foreach ($products as $p) {
        if ($p['id'] == $id) return $p;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets\style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>
        <div class="main-content">
            <div class="header">
                <h1>Quản lý sản phẩm</h1>
                <button id="showChartBtn">View Sales Chart</button>
            </div>
            <div class="main">
                <div class="stats">
                    <div class="card">
                        <h3>Tổng sản phẩm</h3>
                        <p><?php echo $totalProducts; ?></p>
                    </div>
                    <div class="card">
                        <h3>Tổng đã bán</h3>
                        <p><?php echo $totalSold; ?></p>
                    </div>
                    <div class="card">
                        <h3>Tổng lợi nhuận</h3>
                        <p><?php echo number_format($totalRevenue, 0, ',', '.') . ' VND'; ?></p>
                    </div>
                </div>
                <form method="get" class="inline">
                    <input type="text" name="search" placeholder="Search..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <input type="number" step="1000" name="min_price" placeholder="Min price"
                        value="<?php echo $min; ?>">
                    <input type="number" step="1000" name="max_price" placeholder="Max price"
                        value="<?php echo $max; ?>">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </form>

                <form method="get" class="inline">
                    <label>Ngày bắt đầu: <input type="date" name="start_date"></label>
                    <label>Ngày kết thúc: <input type="date" name="end_date"></label>
                    <label>Lọc:
                        <select name="filter">
                            <option value="">-- Tất cả sản phẩm --</option>
                            <option value="top3">Top 3 Most Sold</option>
                            <option value="top10">Top 10 Most Sold</option>
                        </select>
                    </label>
                    <button type="submit" name="export" value="excel">Export to Excel</button>
                    <button type="submit" name="export" value="pdf">Export to PDF</button>
                </form>

                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Giá</th>
                                <th>Còn</th>
                                <th>Đã bán</th>
                                <th>Danh mục</th>
                                <th>Mô tả</th>
                                <th>Hình ảnh</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated as $p): ?>
                            <tr>
                                <td><?php echo $p['id']; ?><input type="hidden" name="id"
                                        value="<?php echo $p['id']; ?>"></td>
                                <td><input name="name" value="<?php echo htmlspecialchars($p['name']); ?>"></td>
                                <td><input type="number" name="price" step="1000" value="<?php echo $p['price']; ?>">
                                    VND</td>
                                <td><input type="number" name="stock" value="<?php echo $p['stock']; ?>"></td>
                                <td><?php echo $p['sold']; ?></td>
                                <td>
                                    <select name="category">
                                        <?php foreach ($categories as $id => $catName): ?>
                                        <option value="<?php echo $id; ?>"
                                            <?php echo ($p['category_id'] == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($catName); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><textarea name="description" onclick="this.rows=5;" onblur="this.rows=1;"
                                        rows="1"><?php echo htmlspecialchars($p['description']); ?></textarea></td>
                                <td><input type="text" name="image"
                                        value="<?php echo htmlspecialchars($p['image']); ?>"></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">Apply Edit</button>
                                    <a class="btn btn-primary" href="?sell_id=<?php echo $p['id']; ?>">Sell</a>
                                    <a class="btn btn-primary" href="?remove_id=<?php echo $p['id']; ?>">Remove</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <div class="page-nav">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>"
                        class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <h2>Thêm sản phẩm</h2>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="add">
                    <input name="name" placeholder="Name" required>
                    <input type="number" step="1000" name="price" placeholder="Price (VND)" required>
                    <input type="number" name="stock" placeholder="Stock" required>
                    <select name="category">
                        <?php foreach ($categories as $id => $catName): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($catName); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="description" placeholder="Description">
                    <input name="image" placeholder="Image URL or path">
                    <button class="btn btn-primary" type="submit">Add</button>
                </form>
            </div>
            <!-- Chart Modal -->
            <div id="chartModal">
                <div id="chartContent">
                    <button id="chartClose">×</button>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <script>
            const modal = document.getElementById('chartModal');
            document.getElementById('showChartBtn').onclick = () => modal.style.display = 'flex';
            document.getElementById('chartClose').onclick = () => modal.style.display = 'none';
            window.onclick = e => {
                if (e.target === modal) modal.style.display = 'none';
            };
            new Chart(document.getElementById('salesChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($products, 'name')); ?>,
                    datasets: [{
                        label: 'Units Sold',
                        data: <?php echo json_encode(array_column($products, 'sold')); ?>
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });
            </script>
        </div>
    </div>
</body>

</html>