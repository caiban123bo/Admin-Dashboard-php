<?php
// sidebar.php
function loadSidebar() {
    echo '<nav class="sidebar">
            <h2>Menu</h2>
            <ul>
                <li><a href="index.php">Sản phẩm</a></li>
                <li><a href="order_details.php">Lịch sử đặt hàng</a></li>
                <li><a href="promotions.php">Khuyến mãi</a></li>
                <li><a href="users.php" class="active">Khách hàng</a></li>
                <li><a href="user_opinions.php">Ý kiến khách hàng</a></li>
                <li><a href="promo_sale.php">Thông báo khuyến mãi</a></li>
            </ul>
        </nav>';
}
