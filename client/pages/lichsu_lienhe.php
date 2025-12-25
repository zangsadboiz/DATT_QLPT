<?php
// client/pages/lichsu_lienhe.php - Lịch sử liên hệ của người dùng
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: /quanlyphongtro/client/index.php?page=login&return=' . urlencode('/quanlyphongtro/client/index.php?page=lichsu_lienhe'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userEmail = $_SESSION['email'] ?? '';

// Lấy lịch sử liên hệ theo email của user
$contacts = mysqli_query($conn, "SELECT * FROM contacts WHERE email = '" . mysqli_real_escape_string($conn, $userEmail) . "' ORDER BY created_at DESC");

$subjectLabels = [
    'SUPPORT' => 'Hỗ trợ kỹ thuật',
    'FEEDBACK' => 'Góp ý',
    'COMPLAINT' => 'Khiếu nại',
    'PARTNERSHIP' => 'Hợp tác',
    'OTHER' => 'Khác',
];

$statusInfo = [
    'NEW' => ['label' => 'Chờ xử lý', 'class' => 'warning', 'icon' => 'hourglass-split'],
    'READ' => ['label' => 'Đang xử lý', 'class' => 'info', 'icon' => 'eye'],
    'REPLIED' => ['label' => 'Đã phản hồi', 'class' => 'success', 'icon' => 'check-circle'],
    'CLOSED' => ['label' => 'Đã đóng', 'class' => 'secondary', 'icon' => 'x-circle'],
];

// Đếm theo trạng thái
$countAll = $contacts ? mysqli_num_rows($contacts) : 0;
mysqli_data_seek($contacts, 0); // Reset pointer

$filterStatus = $_GET['status'] ?? '';
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-2.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Lịch sử liên hệ</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Lịch sử liên hệ</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="wow fadeInUp" data-wow-delay="0.1s">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fa fa-history text-primary me-2"></i>Lịch sử liên hệ của bạn</h4>
                        <a href="/quanlyphongtro/client/index.php?page=lienhe" class="btn btn-primary">
                            <i class="fa fa-plus me-2"></i>Gửi liên hệ mới
                        </a>
                    </div>
                    
                    <!-- Filter tabs -->
                    <ul class="nav nav-pills mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?= $filterStatus === '' ? 'active' : '' ?>" href="?page=lichsu_lienhe">
                                Tất cả <span class="badge bg-secondary"><?= $countAll ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $filterStatus === 'pending' ? 'active' : '' ?>" href="?page=lichsu_lienhe&status=pending">
                                <i class="bi bi-hourglass-split me-1"></i>Chờ phản hồi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $filterStatus === 'replied' ? 'active' : '' ?>" href="?page=lichsu_lienhe&status=replied">
                                <i class="bi bi-check-circle me-1"></i>Đã phản hồi
                            </a>
                        </li>
                    </ul>
                    
                    <?php if ($contacts && mysqli_num_rows($contacts) > 0): ?>
                        <div class="row g-4">
                            <?php while ($c = mysqli_fetch_assoc($contacts)): 
                                // Filter
                                if ($filterStatus === 'pending' && in_array($c['status'], ['REPLIED', 'CLOSED'])) continue;
                                if ($filterStatus === 'replied' && !in_array($c['status'], ['REPLIED', 'CLOSED'])) continue;
                                
                                $status = $statusInfo[$c['status']] ?? $statusInfo['NEW'];
                            ?>
                                <div class="col-lg-6">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                            <span class="badge bg-info text-dark">
                                                <?= $subjectLabels[$c['subject']] ?? $c['subject'] ?>
                                            </span>
                                            <span class="badge bg-<?= $status['class'] ?>">
                                                <i class="bi bi-<?= $status['icon'] ?> me-1"></i><?= $status['label'] ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="card-text">
                                                <?= nl2br(htmlspecialchars(mb_substr($c['message'], 0, 150))) ?>
                                                <?= mb_strlen($c['message']) > 150 ? '...' : '' ?>
                                            </p>
                                            
                                            <?php if ($c['status'] === 'REPLIED' && $c['replied_at']): ?>
                                                <div class="alert alert-success mb-0 py-2">
                                                    <small>
                                                        <i class="bi bi-check-circle me-1"></i>
                                                        Admin đã phản hồi lúc <?= date('d/m/Y H:i', strtotime($c['replied_at'])) ?>
                                                        <br>Vui lòng kiểm tra email <?= htmlspecialchars($c['email']) ?>
                                                    </small>
                                                </div>
                                            <?php elseif ($c['status'] === 'CLOSED'): ?>
                                                <div class="alert alert-secondary mb-0 py-2">
                                                    <small><i class="bi bi-x-circle me-1"></i>Liên hệ này đã được đóng</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0 py-2">
                                                    <small><i class="bi bi-hourglass-split me-1"></i>Đang chờ xử lý, bạn sẽ nhận phản hồi qua email</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-white text-muted small">
                                            <i class="bi bi-hash"></i> Mã liên hệ: LH<?= str_pad($c['contact_id'], 6, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 bg-light rounded">
                            <i class="bi bi-inbox text-muted" style="font-size: 60px;"></i>
                            <h5 class="mt-3 text-muted">Bạn chưa có liên hệ nào</h5>
                            <p class="text-muted mb-4">Gửi liên hệ để được hỗ trợ từ ban quản trị</p>
                            <a href="/quanlyphongtro/client/index.php?page=lienhe" class="btn btn-primary">
                                <i class="fa fa-paper-plane me-2"></i>Gửi liên hệ
                            </a>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>
