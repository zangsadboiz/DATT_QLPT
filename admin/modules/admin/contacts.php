<?php
// Admin - Quản lý liên hệ
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php?error=no_permission');
    exit;
}

// Cập nhật trạng thái
if (isset($_POST['action']) && isset($_POST['contact_id'])) {
    $contactId = (int)$_POST['contact_id'];
    $action = $_POST['action'];
    
    if ($action === 'mark_read') {
        mysqli_query($conn, "UPDATE contacts SET status = 'READ' WHERE contact_id = $contactId AND status = 'NEW'");
    } elseif ($action === 'mark_replied') {
        mysqli_query($conn, "UPDATE contacts SET status = 'REPLIED', replied_at = NOW() WHERE contact_id = $contactId");
    } elseif ($action === 'close') {
        mysqli_query($conn, "UPDATE contacts SET status = 'CLOSED' WHERE contact_id = $contactId");
    } elseif ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM contacts WHERE contact_id = $contactId");
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Lưu ghi chú
if (isset($_POST['save_note']) && isset($_POST['contact_id'])) {
    $contactId = (int)$_POST['contact_id'];
    $note = trim($_POST['admin_note'] ?? '');
    $stmt = mysqli_prepare($conn, "UPDATE contacts SET admin_note = ? WHERE contact_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $note, $contactId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header('Location: contacts.php');
    exit;
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$allowedStatuses = ['', 'NEW', 'READ', 'REPLIED', 'CLOSED'];
if (!in_array($filterStatus, $allowedStatuses)) {
    $filterStatus = '';
}

$where = "1=1";
if ($filterStatus !== '') {
    $where .= " AND status = '$filterStatus'";
}

// Lấy danh sách liên hệ
$contacts = mysqli_query($conn, "SELECT * FROM contacts WHERE $where ORDER BY 
    CASE status 
        WHEN 'NEW' THEN 1 
        WHEN 'READ' THEN 2 
        WHEN 'REPLIED' THEN 3 
        WHEN 'CLOSED' THEN 4 
    END, 
    created_at DESC");

// Đếm theo trạng thái
$countNew = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM contacts WHERE status = 'NEW'"))['c'] ?? 0;
$countRead = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM contacts WHERE status = 'READ'"))['c'] ?? 0;
$countReplied = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM contacts WHERE status = 'REPLIED'"))['c'] ?? 0;
$countClosed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM contacts WHERE status = 'CLOSED'"))['c'] ?? 0;
$countAll = $countNew + $countRead + $countReplied + $countClosed;

$subjectLabels = [
    'SUPPORT' => 'Hỗ trợ kỹ thuật',
    'FEEDBACK' => 'Góp ý',
    'COMPLAINT' => 'Khiếu nại',
    'PARTNERSHIP' => 'Hợp tác',
    'OTHER' => 'Khác',
];

$statusLabels = [
    'NEW' => '<span class="badge bg-danger">Mới</span>',
    'READ' => '<span class="badge bg-warning text-dark">Đã đọc</span>',
    'REPLIED' => '<span class="badge bg-success">Đã trả lời</span>',
    'CLOSED' => '<span class="badge bg-secondary">Đã đóng</span>',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-envelope me-2"></i>Quản lý liên hệ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Liên hệ</li>
        </ol>
    </nav>
</div>

<section class="section">
    <!-- Thống kê dạng card -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-primary mb-1"><?= $countAll ?></h4>
                    <small class="text-muted">Tất cả</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-danger mb-1"><?= $countNew ?></h4>
                    <small class="text-muted">Mới</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-success mb-1"><?= $countReplied ?></h4>
                    <small class="text-muted">Đã trả lời</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-secondary mb-1"><?= $countClosed ?></h4>
                    <small class="text-muted">Đã đóng</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bộ lọc riêng -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 me-2">Trạng thái:</label>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tất cả</option>
                        <option value="NEW" <?= $filterStatus === 'NEW' ? 'selected' : '' ?>>Mới</option>
                        <option value="READ" <?= $filterStatus === 'READ' ? 'selected' : '' ?>>Đã đọc</option>
                        <option value="REPLIED" <?= $filterStatus === 'REPLIED' ? 'selected' : '' ?>>Đã trả lời</option>
                        <option value="CLOSED" <?= $filterStatus === 'CLOSED' ? 'selected' : '' ?>>Đã đóng</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="contacts.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>


    <!-- Danh sách liên hệ -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Người gửi</th>
                            <th>Chủ đề</th>
                            <th>Nội dung</th>
                            <th style="width: 100px;">Trạng thái</th>
                            <th style="width: 120px;">Ngày gửi</th>
                            <th style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($contacts && mysqli_num_rows($contacts) > 0): ?>
                            <?php $i = 1; while ($c = mysqli_fetch_assoc($contacts)): ?>
                                <tr class="<?= $c['status'] === 'NEW' ? 'table-light fw-bold' : '' ?>">
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['full_name']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($c['email']) ?>
                                            <?php if ($c['phone']): ?>
                                                <br><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($c['phone']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= $subjectLabels[$c['subject']] ?? $c['subject'] ?></span>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px;">
                                            <?= nl2br(htmlspecialchars(mb_substr($c['message'], 0, 100))) ?>
                                            <?= mb_strlen($c['message']) > 100 ? '...' : '' ?>
                                        </div>
                                        <?php if ($c['admin_note']): ?>
                                            <small class="text-primary d-block mt-1">
                                                <i class="bi bi-sticky me-1"></i><?= htmlspecialchars(mb_substr($c['admin_note'], 0, 50)) ?>...
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $statusLabels[$c['status']] ?? $c['status'] ?></td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                        <?php if ($c['replied_at']): ?>
                                            <br><i class="bi bi-reply"></i> <?= date('d/m H:i', strtotime($c['replied_at'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $c['contact_id'] ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if ($c['status'] === 'NEW'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="contact_id" value="<?= $c['contact_id'] ?>">
                                                            <button type="submit" name="action" value="mark_read" class="dropdown-item">
                                                                <i class="bi bi-check me-2"></i>Đánh dấu đã đọc
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($c['status'] !== 'REPLIED'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="contact_id" value="<?= $c['contact_id'] ?>">
                                                            <button type="submit" name="action" value="mark_replied" class="dropdown-item">
                                                                <i class="bi bi-reply me-2"></i>Đánh dấu đã trả lời
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($c['status'] !== 'CLOSED'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="contact_id" value="<?= $c['contact_id'] ?>">
                                                            <button type="submit" name="action" value="close" class="dropdown-item">
                                                                <i class="bi bi-x-circle me-2"></i>Đóng liên hệ
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa liên hệ này?')">
                                                        <input type="hidden" name="contact_id" value="<?= $c['contact_id'] ?>">
                                                        <button type="submit" name="action" value="delete" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i>Xóa
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Modal xem chi tiết -->
                                <div class="modal fade" id="viewModal<?= $c['contact_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-envelope-open me-2"></i>Chi tiết liên hệ #<?= $c['contact_id'] ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Người gửi:</strong><br>
                                                        <?= htmlspecialchars($c['full_name']) ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Trạng thái:</strong><br>
                                                        <?= $statusLabels[$c['status']] ?>
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Email:</strong><br>
                                                        <a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Điện thoại:</strong><br>
                                                        <?= $c['phone'] ? htmlspecialchars($c['phone']) : '<span class="text-muted">-</span>' ?>
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Chủ đề:</strong><br>
                                                        <span class="badge bg-info text-dark"><?= $subjectLabels[$c['subject']] ?? $c['subject'] ?></span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Ngày gửi:</strong><br>
                                                        <?= date('d/m/Y H:i:s', strtotime($c['created_at'])) ?>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Nội dung:</strong>
                                                    <div class="border rounded p-3 bg-light mt-1">
                                                        <?= nl2br(htmlspecialchars($c['message'])) ?>
                                                    </div>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="contact_id" value="<?= $c['contact_id'] ?>">
                                                    <div class="mb-3">
                                                        <strong>Ghi chú của Admin:</strong>
                                                        <?php if ($c['status'] === 'CLOSED'): ?>
                                                            <div class="form-control mt-1 bg-light" style="min-height: 80px;"><?= nl2br(htmlspecialchars($c['admin_note'] ?? '(Không có ghi chú)')) ?></div>
                                                            <small class="text-muted"><i class="bi bi-lock me-1"></i>Liên hệ đã đóng, không thể chỉnh sửa</small>
                                                        <?php else: ?>
                                                            <textarea name="admin_note" class="form-control mt-1" rows="3"><?= htmlspecialchars($c['admin_note'] ?? '') ?></textarea>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($c['status'] !== 'CLOSED'): ?>
                                                        <button type="submit" name="save_note" class="btn btn-primary">
                                                            <i class="bi bi-save me-1"></i>Lưu ghi chú
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Không có liên hệ nào
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
