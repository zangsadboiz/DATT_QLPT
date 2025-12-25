<?php
// client/pages/lienhe.php - Trang liên hệ
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

$submitSuccess = false;
$submitError = '';

// Lấy thông tin user nếu đã đăng nhập
$userInfo = null;
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $userInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email, phone FROM users WHERE user_id = $userId"));
}

// Xử lý form gửi liên hệ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = $_POST['subject'] ?? 'OTHER';
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ tên';
    } elseif (mb_strlen($fullName) < 3) {
        $errors[] = 'Họ tên phải có ít nhất 3 ký tự';
    }
    
    if ($email === '') {
        $errors[] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if ($phone !== '' && !preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    $validSubjects = ['SUPPORT', 'FEEDBACK', 'COMPLAINT', 'PARTNERSHIP', 'OTHER'];
    if (!in_array($subject, $validSubjects)) {
        $subject = 'OTHER';
    }
    
    if ($message === '') {
        $errors[] = 'Vui lòng nhập nội dung tin nhắn';
    } elseif (mb_strlen($message) < 10) {
        $errors[] = 'Nội dung phải có ít nhất 10 ký tự';
    }
    
    if (!empty($errors)) {
        $submitError = '<ul class="mb-0"><li>' . implode('</li><li>', $errors) . '</li></ul>';
    } else {
        // Lưu vào database
        $stmt = mysqli_prepare($conn, "INSERT INTO contacts (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $fullName, $email, $phone, $subject, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            $submitSuccess = true;
        } else {
            $submitError = 'Có lỗi xảy ra, vui lòng thử lại sau.';
        }
        mysqli_stmt_close($stmt);
    }
}

$subjectLabels = [
    'SUPPORT' => 'Hỗ trợ kỹ thuật',
    'FEEDBACK' => 'Góp ý về website',
    'COMPLAINT' => 'Khiếu nại',
    'PARTNERSHIP' => 'Hợp tác kinh doanh',
    'OTHER' => 'Khác',
];
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-2.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Liên hệ</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Liên hệ</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Left: Thông tin liên hệ -->
            <div class="col-lg-4">
                <div class="wow fadeInUp" data-wow-delay="0.1s">
                    <div class="bg-light rounded p-4 mb-4">
                        <h5 class="mb-4"><i class="fa fa-info-circle text-primary me-2"></i>Thông tin liên hệ</h5>
                        
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0 btn-square bg-primary text-white rounded-circle me-3">
                                <i class="fa fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Địa chỉ</h6>
                                <p class="mb-0 text-muted">Thành phố Vinh, Nghệ An</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0 btn-square bg-primary text-white rounded-circle me-3">
                                <i class="fa fa-phone-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Điện thoại</h6>
                                <p class="mb-0 text-muted">0123 456 789</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0 btn-square bg-primary text-white rounded-circle me-3">
                                <i class="fa fa-envelope"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Email</h6>
                                <p class="mb-0 text-muted">support@phongtrosv.vn</p>
                            </div>
                        </div>
                        
                        <div class="d-flex">
                            <div class="flex-shrink-0 btn-square bg-primary text-white rounded-circle me-3">
                                <i class="fa fa-clock"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Giờ làm việc</h6>
                                <p class="mb-0 text-muted">8:00 - 17:00 (Thứ 2 - Thứ 7)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-light rounded p-4">
                        <h6 class="mb-3"><i class="fa fa-share-alt text-primary me-2"></i>Kết nối với chúng tôi</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="fab fa-youtube"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-tiktok"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Form liên hệ -->
            <div class="col-lg-8">
                <div class="wow fadeInUp" data-wow-delay="0.2s">
                    <?php if ($submitSuccess): ?>
                        <div class="bg-light rounded p-5 text-center">
                            <i class="fa fa-check-circle text-success mb-4" style="font-size: 80px;"></i>
                            <h4 class="text-success mb-3">Gửi liên hệ thành công!</h4>
                            <p class="mb-4">Cảm ơn bạn đã liên hệ với chúng tôi.<br>Chúng tôi sẽ phản hồi qua email trong thời gian sớm nhất.</p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="/quanlyphongtro/client/index.php?page=lichsu_lienhe" class="btn btn-primary py-2 px-4">
                                    <i class="fa fa-history me-2"></i>Xem lịch sử liên hệ
                                </a>
                                <a href="/quanlyphongtro/client/index.php?page=home" class="btn btn-outline-secondary py-2 px-4">
                                    <i class="fa fa-home me-2"></i>Về trang chủ
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-light rounded p-4 p-lg-5">
                            <h4 class="mb-4"><i class="fa fa-paper-plane text-primary me-2"></i>Gửi tin nhắn cho chúng tôi</h4>
                            
                            <?php if ($submitError): ?>
                                <div class="alert alert-danger"><?= $submitError ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control py-3" name="full_name" 
                                               value="<?= htmlspecialchars($_POST['full_name'] ?? $userInfo['full_name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control py-3" name="email"
                                               value="<?= htmlspecialchars($_POST['email'] ?? $userInfo['email'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Số điện thoại</label>
                                        <input type="tel" class="form-control py-3" name="phone"
                                               value="<?= htmlspecialchars($_POST['phone'] ?? $userInfo['phone'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Chủ đề <span class="text-danger">*</span></label>
                                        <select class="form-select py-3" name="subject" required>
                                            <?php foreach ($subjectLabels as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= ($_POST['subject'] ?? '') === $key ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="message" rows="5" required 
                                                  placeholder="Nhập nội dung tin nhắn của bạn..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" name="submit_contact" class="btn btn-primary w-100 py-3">
                                            <i class="fa fa-paper-plane me-2"></i>Gửi tin nhắn
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Google Map -->
<div class="container-xxl pb-5">
    <div class="container">
        <div class="wow fadeInUp" data-wow-delay="0.1s">
            <iframe class="w-100 rounded" 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d119886.2552883!2d105.604!3d18.679!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3139cddf5e7a3d8f%3A0x5f6b1e5d7b5b6a2a!2zVGjDoG5oIHBo4buRIFZpbmgsIE5naOG7hyBBbg!5e0!3m2!1svi!2s!4v1" 
                    style="height: 400px; border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</div>

<?php 
// Phần lịch sử liên hệ (nếu đã đăng nhập)
if (isset($_SESSION['user_id']) && !empty($userInfo['email'])):
    $userEmail = $userInfo['email'];
    $myContacts = mysqli_query($conn, "SELECT * FROM contacts WHERE email = '" . mysqli_real_escape_string($conn, $userEmail) . "' ORDER BY created_at DESC LIMIT 5");
    
    $statusInfo = [
        'NEW' => ['label' => 'Chờ xử lý', 'class' => 'warning'],
        'READ' => ['label' => 'Đang xử lý', 'class' => 'info'],
        'REPLIED' => ['label' => 'Đã phản hồi', 'class' => 'success'],
        'CLOSED' => ['label' => 'Đã đóng', 'class' => 'secondary'],
    ];
    
    if ($myContacts && mysqli_num_rows($myContacts) > 0):
?>
<div class="container-xxl py-5 bg-light">
    <div class="container">
        <div class="wow fadeInUp" data-wow-delay="0.1s">
            <h4 class="mb-4"><i class="fa fa-history text-primary me-2"></i>Lịch sử liên hệ của bạn</h4>
            
            <div class="row g-3">
                <?php while ($c = mysqli_fetch_assoc($myContacts)): 
                    $status = $statusInfo[$c['status']] ?? $statusInfo['NEW'];
                    $hasReply = in_array($c['status'], ['REPLIED', 'CLOSED']) && !empty($c['admin_note']);
                    // Đổi "Đã đóng" thành "Đã xử lý" cho user
                    $displayStatus = $c['status'] === 'CLOSED' ? 'Đã xử lý' : $status['label'];
                    $statusClass = $c['status'] === 'CLOSED' ? 'secondary' : $status['class'];
                ?>
                    <div class="col-lg-6">
                        <div class="card h-100 <?= $hasReply ? 'border-success' : '' ?>" 
                             style="cursor: pointer;" 
                             data-bs-toggle="modal" 
                             data-bs-target="#contactModal<?= $c['contact_id'] ?>">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                                <small class="text-muted">
                                    <i class="bi bi-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                </small>
                                <span class="badge bg-<?= $statusClass ?>"><?= $displayStatus ?></span>
                            </div>
                            <div class="card-body">
                                <p class="card-text small mb-2">
                                    <?= htmlspecialchars(mb_substr($c['message'], 0, 100)) ?><?= mb_strlen($c['message']) > 100 ? '...' : '' ?>
                                </p>
                                <?php if ($hasReply): ?>
                                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Có phản hồi - Ấn để xem</small>
                                <?php elseif ($c['status'] === 'REPLIED' || $c['status'] === 'CLOSED'): ?>
                                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Đã xử lý</small>
                                <?php else: ?>
                                    <small class="text-warning"><i class="bi bi-hourglass-split me-1"></i>Đang chờ xử lý...</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal xem chi tiết -->
                    <div class="modal fade" id="contactModal<?= $c['contact_id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-envelope-open me-2"></i>Chi tiết liên hệ
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>Gửi lúc: <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                        </small>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $displayStatus ?></span>
                                    </div>
                                    
                                    <div class="border rounded p-3 mb-3 bg-light">
                                        <strong class="d-block mb-2"><i class="bi bi-chat-left-text me-1"></i>Nội dung bạn gửi:</strong>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
                                    </div>
                                    
                                    <?php if ($hasReply): ?>
                                        <div class="border border-success rounded p-3" style="background-color: #d1e7dd;">
                                            <strong class="d-block mb-2 text-dark">
                                                <i class="bi bi-reply-fill text-success me-1"></i>Phản hồi từ Admin
                                                <?php if ($c['replied_at']): ?>
                                                    <small class="fw-normal text-muted">(<?= date('d/m/Y H:i', strtotime($c['replied_at'])) ?>)</small>
                                                <?php endif; ?>
                                            </strong>
                                            <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($c['admin_note'])) ?></p>
                                        </div>
                                    <?php elseif ($c['status'] === 'REPLIED' || $c['status'] === 'CLOSED'): ?>
                                        <div class="alert alert-success mb-0">
                                            <i class="bi bi-check-circle me-2"></i>Liên hệ này đã được xử lý. Nếu Admin có phản hồi, bạn sẽ nhận được qua email.
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="bi bi-hourglass-split me-2"></i>Liên hệ đang chờ xử lý. Chúng tôi sẽ phản hồi sớm nhất có thể.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>
<?php 
    endif;
endif; 
?>
