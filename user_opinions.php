<?php
require_once 'assets\db.php';
require_once 'sidebar.php';
require 'libs/PHPMailer/PHPMailer.php';
require 'libs/PHPMailer/SMTP.php';
require 'libs/PHPMailer/Exception.php';
require 'assets/mail_information.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Handle status update and email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_processed'])) {
    $opinionId = $_POST['opinion_id'];
    $customMessage = $_POST['custom_message'] ?? '';
    // Get user info
    $stmt = $pdo->prepare("SELECT yk.*, nd.EMAIL, nd.HO_TEN FROM y_kien_khach_hang yk 
                           JOIN nguoi_dung nd ON yk.MA_NGUOI_DUNG = nd.MA_NGUOI_DUNG 
                           WHERE yk.MA_Y_KIEN = ?");
    $stmt->execute([$opinionId]);
    $feedback = $stmt->fetch();

    if ($feedback) {
        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $mail_username;
            $mail->Password = $mail_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom($mail_username, 'Admin WebQuanAoNhom2');
            $mail->addAddress($feedback['EMAIL'], $feedback['HO_TEN']);

            $mail->isHTML(true);
            $mail->Subject = 'Thank you for your feedback';

            $message = !empty($customMessage) ? $customMessage : 'Trước tiên, chúng tôi xin gửi đến Quý Khách lời chúc sức khỏe và lời cảm ơn chân thành nhất từ phía công ty chúng tôi. Được biết đến với sự tin tưởng và ủng hộ của Quý Khách trong suốt thời gian qua, chúng tôi vô cùng vinh dự và hạnh phúc khi có cơ hội đồng hành cùng Quý Khách trong hành trình phát triển của mình. Chính vì vậy, việc nhận được những phản hồi quý báu của Quý Khách là một niềm vinh dự lớn lao đối với chúng tôi.
            Phản hồi của Quý Khách không chỉ là nguồn động viên to lớn, mà còn là kim chỉ nam giúp chúng tôi nhìn nhận rõ hơn về chất lượng dịch vụ mà mình cung cấp. Với sự chuyên nghiệp và tỉ mỉ trong từng lời nhận xét của Quý Khách, chúng tôi đã có thể hiểu rõ hơn những điểm mạnh cũng như những lĩnh vực còn tồn tại cần phải cải thiện. Chắc chắn rằng, những thông tin này sẽ được chúng tôi tiếp thu và xem xét một cách nghiêm túc, nhằm không ngừng nâng cao chất lượng sản phẩm, dịch vụ và trải nghiệm mà Quý Khách nhận được.
            Chúng tôi vô cùng trân trọng từng chi tiết trong những đánh giá của Quý Khách, từ việc chỉ ra những ưu điểm nổi bật đến việc chỉ ra những yếu điểm cần khắc phục. Đây là những thông tin cực kỳ quý giá giúp chúng tôi nhận thức sâu sắc về những kỳ vọng và mong muốn của Quý Khách đối với công ty chúng tôi. Qua đó, chúng tôi cam kết sẽ không ngừng nỗ lực để đáp ứng tốt nhất các yêu cầu và mong đợi đó.
            Cũng trong dịp này, chúng tôi muốn khẳng định rằng chúng tôi luôn xem mỗi phản hồi, mỗi ý kiến của Quý Khách là một cơ hội để phát triển. Sự hài lòng của Quý Khách chính là tiêu chí hàng đầu mà chúng tôi không ngừng phấn đấu đạt được. Từ những ý kiến đóng góp của Quý Khách, chúng tôi đã có thể đưa ra các kế hoạch cải tiến cụ thể, qua đó mang đến cho Quý Khách những trải nghiệm vượt trội hơn trong tương lai.
            Ngoài ra, chúng tôi rất mong muốn tiếp tục nhận được sự hợp tác và hỗ trợ từ Quý Khách trong thời gian tới. Sự hợp tác của Quý Khách là yếu tố quan trọng trong việc giúp chúng tôi duy trì và phát triển không ngừng. Chúng tôi cam kết sẽ luôn nỗ lực, sáng tạo và cải tiến, để mỗi lần Quý Khách trải nghiệm dịch vụ của chúng tôi là một lần hài lòng tuyệt đối.
            Lời cảm ơn của chúng tôi không chỉ dừng lại ở những dòng chữ này, mà là một lời hứa về sự cam kết mạnh mẽ trong việc không ngừng hoàn thiện. Chúng tôi rất hy vọng sẽ tiếp tục nhận được sự tin tưởng và đồng hành của Quý Khách trong tương lai, để cùng nhau tạo dựng những giá trị bền vững và lâu dài.
            Một lần nữa, xin chân thành cảm ơn Quý Khách đã dành thời gian quý báu để chia sẻ những ý kiến vô cùng giá trị. Chúng tôi mong rằng mối quan hệ giữa chúng ta sẽ ngày càng bền chặt và phát triển mạnh mẽ hơn.

            Trân trọng kính chào và chúc Quý Khách sức khỏe, thành công trong công việc cũng như trong cuộc sống.

            Thân ái,

            [Assmin]
            [CEO]
            ';
            $mail->Body = nl2br($message);

            $mail->send();

            // Update status in DB
            $update = $pdo->prepare("UPDATE y_kien_khach_hang SET TRANG_THAI = 'DA_XU_LY' WHERE MA_Y_KIEN = ?");
            $update->execute([$opinionId]);

            header("Location: user_opinions.php");
            exit;
        } catch (Exception $e) {
            echo "Email could not be sent. Error: {$mail->ErrorInfo}";
        }
    }
}

// Fetch all feedback
$stmt = $pdo->query("SELECT yk.*, nd.HO_TEN, nd.EMAIL FROM y_kien_khach_hang yk 
                     JOIN nguoi_dung nd ON yk.MA_NGUOI_DUNG = nd.MA_NGUOI_DUNG 
                     ORDER BY NGAY_GUI DESC");
$opinions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Ý kiến khách hàng</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="layout">
        <?php loadSidebar(); ?>
        <div class="main-content">
            <h1>Ý kiến khách hàng</h1>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ & tên</th>
                        <th>Email</th>
                        <th>Nội dung</th>
                        <th>Ngày gửi</th>
                        <th>Trạng thái</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($opinions as $op): ?>
                    <tr>
                        <td><?= $op['MA_Y_KIEN'] ?></td>
                        <td><?= htmlspecialchars($op['HO_TEN']) ?></td>
                        <td><?= htmlspecialchars($op['EMAIL']) ?></td>
                        <td><?= htmlspecialchars($op['NOI_DUNG']) ?></td>
                        <td><?= $op['NGAY_GUI'] ?></td>
                        <td><?= $op['TRANG_THAI'] ?></td>
                        <td>
                            <?php if ($op['TRANG_THAI'] === 'CHUA_XU_LY'): ?>
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="opinion_id" value="<?= $op['MA_Y_KIEN'] ?>">
                                <textarea name="custom_message" placeholder="Nhập mail (optional)..."></textarea>
                                <button type="submit" name="mark_processed" class="btn">Đánh dấu hoành thành & Gửi
                                    Email</button>
                            </form>
                            <?php else: ?>
                            Đã xử lý
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($opinions)): ?>
                    <tr>
                        <td colspan="7">No feedback available.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>