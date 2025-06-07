<?php
require_once 'assets/db.php';
require_once 'sidebar.php';
require_once 'assets/mail_information.php';
require_once 'libs/PHPMailer/PHPMailer.php';
require_once 'libs/PHPMailer/SMTP.php';
require_once 'libs/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// ——————————————————————————
// 1) Fetch & check events
// ——————————————————————————
$events = $pdo->query("SELECT * FROM su_kien_khuyen_mai")->fetchAll();
function checkEventUsability(array $e): array {
    $now = date('Y-m-d');
    $usable = true; $reason = 'Có thể sử dụng';
    if ($e['NGAY_KET_THUC'] < $now)      { $usable = false; $reason = 'Sự kiện đã hết hạn'; }
    elseif ($e['NGAY_BAT_DAU'] > $now)   { $usable = false; $reason = 'Sự kiện chưa bắt đầu'; }
    elseif ((int)$e['TY_LE_GIAM_GIA'] <= 0) { $usable = false; $reason = 'Tỷ lệ giảm không hợp lệ'; }
    $v = (int)$e['TY_LE_GIAM_GIA'];
    $display = $v < 100 ? "$v%" : number_format($v,0,',','.') . " VND";
    $msg = $v < 100 ? "Giảm $v%" : "Giảm giá $display";
    return ['usable'=>$usable,'reason'=>$reason,'DISPLAY_VALUE'=>$display,'DISCOUNT_MSG'=>$msg];
}
$checked_events = [];
foreach ($events as $e) {
    $c = checkEventUsability($e);
    $checked_events[] = array_merge($e, $c);
}

// ——————————————————————————
// 2) Fetch & check coupons
// ——————————————————————————
$coupons = $pdo->query("SELECT * FROM ma_giam_gia")->fetchAll();
function checkCouponUsability(array $c): array {
    $now = date('Y-m-d');
    $usable = true; $reason = 'Có thể sử dụng';
    if ($c['SO_LAN_DA_SU_DUNG'] >= $c['SO_LAN_SU_DUNG_TOI_DA']) {
        $usable = false; $reason = 'Hết lượt sử dụng';
    } elseif ($c['NGAY_HET_HAN'] < $now) {
        $usable = false; $reason = 'Mã đã hết hạn';
    } elseif ((int)$c['GIA_TRI_GIAM'] <= 0) {
        $usable = false; $reason = 'Giá trị giảm không hợp lệ';
    }
    $v = (int)$c['GIA_TRI_GIAM'];
    $display = $v < 100 ? "$v%" : number_format($v,0,',','.') . " VND";
    $msg = $v < 100 ? "Giảm $v%" : "Giảm giá $display";
    return ['usable'=>$usable,'reason'=>$reason,'DISPLAY_VALUE'=>$display,'DISCOUNT_MSG'=>$msg];
}
$checked_coupons = [];
foreach ($coupons as $c) {
    $d = checkCouponUsability($c);
    $checked_coupons[] = array_merge($c, $d);
}

// ——————————————————————————
// 3) Fetch user favorites
// ——————————————————————————
$favs = $pdo->query("
  SELECT y.MA_NGUOI_DUNG,u.EMAIL,u.HO_TEN,s.TEN_SAN_PHAM,s.GIA_BAN
  FROM yeu_thich y
  JOIN nguoi_dung u ON y.MA_NGUOI_DUNG=u.MA_NGUOI_DUNG
  JOIN san_pham s ON y.MA_SAN_PHAM=s.MA_SAN_PHAM
")->fetchAll();

$user_favs = [];
foreach ($favs as $f) {
    $uid = $f['MA_NGUOI_DUNG'];
    $user_favs[$uid]['name']     = $f['HO_TEN'];
    $user_favs[$uid]['email']    = $f['EMAIL'];
    $user_favs[$uid]['products'][] = ['name'=>$f['TEN_SAN_PHAM'],'price'=>$f['GIA_BAN']];
}

// ——————————————————————————
// 4) Best-discount helper
// ——————————————————————————
function getBestDiscount($price, $events, $coupons) {
    $best = ['type'=>null,'discount'=>null,'amount_saved'=>0];
    foreach ($events as $e) {
        if ($e['usable']) {
            $amt = $price * ($e['TY_LE_GIAM_GIA']/100);
            if ($amt > $best['amount_saved']) {
                $best = ['type'=>'event','discount'=>$e,'amount_saved'=>$amt];
            }
        }
    }
    foreach ($coupons as $c) {
        if ($c['usable']) {
            $amt = $c['GIA_TRI_GIAM']<100 ? $price*($c['GIA_TRI_GIAM']/100) : $c['GIA_TRI_GIAM'];
            if ($amt > $best['amount_saved']) {
                $best = ['type'=>'coupon','discount'=>$c,'amount_saved'=>$amt];
            }
        }
    }
    return $best;
}

// ——————————————————————————
// 5) sendEmail helper
// ——————————————————————————
function sendEmail($to,$name,$subject,$body) {
    global $mail_username,$mail_password;
    $m = new PHPMailer(true);
    try {
        $m->isSMTP(); $m->Host='smtp.gmail.com'; $m->SMTPAuth=true;
        $m->Username=$mail_username; $m->Password=$mail_password;
        $m->SMTPSecure='tls'; $m->Port=587;
        $m->setFrom($mail_username,'Shop Admin');
        $m->addAddress($to,$name);
        $m->isHTML(true); $m->Subject=$subject; $m->Body=$body;
        $m->send();
        echo "<script>alert('Email đã gửi đến $to');</script>";
    } catch (\Exception $e) {
        echo "<script>alert('Lỗi gửi email: {$m->ErrorInfo}');</script>";
    }
}

// ——————————————————————————
// 6) Handle form submit
// ——————————————————————————
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_email'])) {
    $uid   = $_POST['user_id'];
    $email = $_POST['email'];
    $name  = $_POST['username'];
    $prods = $_POST['products']; // serialized JSON or pipe...
    $promo_sel = $_POST['promo'] ?? 'auto';
    $event_sel = $_POST['selected_event'] ?? null;

    // Reconstruct product array
    $products = [];
    foreach ($user_favs[$uid]['products'] as $p) {
        $products[] = $p;
    }

    // Build email body
    $body = "<p>Xin chào <b>$name</b>,</p><p>Sản phẩm bạn yêu thích:</p><ul>";
    $body = "<p>Xin chào <b>$name</b>,</p><p>Sản phẩm bạn yêu thích và giá sau khuyến mãi:</p><ul>";

$best_deal = null;
$lowest_price = PHP_FLOAT_MAX;

foreach ($products as $p) {
    $best = getBestDiscount($p['price'], $checked_events, $checked_coupons);
    $final_price = $p['price'] - $best['amount_saved'];
    $display_price = number_format($final_price, 0, ',', '.'); // format to VND style
    $body .= "<li>{$p['name']} - <b>{$display_price}₫</b></li>";

    if ($final_price < $lowest_price) {
        $lowest_price = $final_price;
        $best_deal = [
            'name' => $p['name'],
            'final_price' => $final_price,
            'display_price' => $display_price,
            'original_price' => $p['price']
        ];
    }
}
$body .= "</ul>";

if ($best_deal) {
    $original = number_format($best_deal['original_price'], 0, ',', '.');
    $body .= "<p><b>Gợi ý:</b> Sản phẩm <b>{$best_deal['name']}</b> đang giảm còn <b>{$best_deal['display_price']}₫</b> (giá gốc {$original}₫) — đừng bỏ lỡ!</p>";
}


    // Determine chosen discount
    $chosen = null;
    if ($promo_sel==='auto') {
        // compare best across all favorites
        $global_best = ['amount_saved'=>0];
        foreach ($products as $p) {
            $b = getBestDiscount($p['price'],$checked_events,$checked_coupons);
            if ($b['amount_saved']>$global_best['amount_saved']) {
                $global_best = $b;
            }
        }
        $chosen = $global_best;
    } elseif ($promo_sel!=='auto') {
        // find that coupon
        foreach ($checked_coupons as $c) {
            if ($c['MA']==$promo_sel && $c['usable']) {
                $chosen = ['type'=>'coupon','discount'=>$c,'amount_saved'=>0];
                break;
            }
        }
    }
    if ($event_sel) {
        foreach ($checked_events as $e) {
            if ($e['MA_SU_KIEN']==$event_sel && $e['usable']) {
                $chosen = ['type'=>'event','discount'=>$e,'amount_saved'=>0];
                break;
            }
        }
    }

    if ($chosen && $chosen['discount']) {
        if ($chosen['type']==='event') {
            $e = $chosen['discount'];
            $body .= "<p>Sự kiện <b>{$e['TEN_SU_KIEN']}</b>: {$e['DISCOUNT_MSG']}</p>";
        } else {
            $c = $chosen['discount'];
            $body .= "<p>Mã <b>{$c['MA_CODE']}</b>: {$c['DISCOUNT_MSG']}</p>";
        }
    } else {
        echo "<script>
            if(!confirm('Không tìm thấy khuyến mãi hợp lệ. Vẫn gửi email?')){history.back();}
        </script>";
    }

    sendEmail($email,$name,'Your favorite products are on SALE!!!',$body);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>promo_sale</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>
        <div class="main-content">
            <h2>Các sự kiện khuyến mãi</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên</th>
                        <th>Mô tả</th>
                        <th>Giảm giá</th>
                        <th>BD</th>
                        <th>KT</th>
                        <th>Trạng thái</th>
                        <th>Chọn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($checked_events as $e): ?>
                    <tr>
                        <td><?=htmlspecialchars($e['TEN_SU_KIEN'])?></td>
                        <td><?=htmlspecialchars($e['MO_TA'])?></td>
                        <td><?=$e['DISPLAY_VALUE']?> (<?=$e['DISCOUNT_MSG']?>)</td>
                        <td><?=$e['NGAY_BAT_DAU']?></td>
                        <td><?=$e['NGAY_KET_THUC']?></td>
                        <td style="color:<?=$e['usable']?'green':'red'?>"><?=$e['reason']?></td>
                        <td><?php if($e['usable']):?><input type="radio" name="selected_event"
                                value="<?=$e['MA_SU_KIEN']?>"><?php else:?>-<?php endif;?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Mã giảm giá</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Giá trị</th>
                        <th>Hết hạn</th>
                        <th>SL dùng/SL tối đa</th>
                        <th>Trạng thái</th>
                        <th>Chọn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($checked_coupons as $c): ?>
                    <tr>
                        <td><?=$c['MA_CODE']?></td>
                        <td><?=$c['DISPLAY_VALUE']?> (<?=$c['DISCOUNT_MSG']?>)</td>
                        <td><?=$c['NGAY_HET_HAN']?></td>
                        <td><?=$c['SO_LAN_DA_SU_DUNG']?>/<?=$c['SO_LAN_SU_DUNG_TOI_DA']?></td>
                        <td style="color:<?=$c['usable']?'green':'red'?>"><?=$c['reason']?></td>
                        <td><?php if($c['usable']):?><input type="radio" name="selected_coupon"
                                value="<?=$c['MA']?>"><?php else:?>-<?php endif;?></td>
                    </tr>
                    <?php endforeach;?>
                </tbody>
            </table>

            <h2>Favorites by Users</h2>
            <?php foreach($user_favs as $uid=>$d):?>
            <form method="post" style="border:1px solid #ccc;padding:10px;margin:10px;">
                <input type="hidden" name="user_id" value="<?=$uid?>">
                <input type="hidden" name="email" value="<?=htmlspecialchars($d['email'])?>">
                <input type="hidden" name="username" value="<?=htmlspecialchars($d['name'])?>">
                <input type="hidden" name="products" value="<?= implode('|', $d['products']) ?>">
                <p><b><?=$d['name']?> (<?=$d['email']?>)</b></p>
                <?php 
                    $names = array_column($d['products'], 'name'); 
                ?>
                <p>Favorites: <?= htmlspecialchars(implode(', ', $names)) ?></p> <label>Mã giảm giá:
                    <select name="promo">
                        <option value="auto">Tự động</option>
                        <?php foreach($checked_coupons as $c): if($c['usable']):?>
                        <option value="<?=$c['MA']?>"><?=$c['MA_CODE']?> (<?=$c['DISPLAY_VALUE']?>)</option>
                        <?php endif;endforeach;?>
                    </select>
                </label>
                <br><br>
                <button type="submit" name="send_email">Send Promotion Email</button>
            </form>
            <?php endforeach;?>
        </div>
    </div>
</body>

</html>