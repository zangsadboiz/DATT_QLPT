<?php
declare(strict_types=1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

admin_session_boot();

$role = (string)($_SESSION['role_name'] ?? '');
$displayName = (string)($_SESSION['full_name'] ?? $_SESSION['admin_username'] ?? 'User');
$userId = (int)($_SESSION['user_id'] ?? 0);

$isAdmin = ($role === 'ADMIN');
$isLandlord = ($role === 'LANDLORD');

$curPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function menu_active(string $needle, string $curPath): string {
    return (strpos($curPath, $needle) !== false) ? 'active' : 'collapsed';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdmin ? 'Admin' : 'Chủ trọ' ?> | Quản lý phòng trọ</title>

    <!-- Vendor CSS -->
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="/quanlyphongtro/admin/assets/css/style.css" rel="stylesheet">
    
    <style>
        .stats-card {
            color: #fff;
            border: none;
            border-radius: 15px;
        }
        .stats-card .stats-label {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .stats-card .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stats-card .stats-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        .info-card .card-icon {
            width: 48px;
            height: 48px;
            font-size: 24px;
        }
        .info-card h6 {
            font-size: 24px;
            font-weight: 700;
            color: #012970;
        }
        .sidebar .nav-heading {
            font-size: 11px;
            text-transform: uppercase;
            color: #899bbd;
            font-weight: 600;
            margin: 10px 0 5px 15px;
        }
    </style>
</head>

<body>

<!-- Header -->
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="<?= ADMIN_BASE_PATH ?>/index.php" class="logo d-flex align-items-center">
            <i class="bi bi-house-door me-2"></i>
            <span class="d-none d-lg-block">Phòng Trọ</span>
        </a>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">
            
            <?php if ($isLandlord): ?>
                <!-- Landlord Balance -->
                <?php
                $balanceResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $userId"));
                $balance = (float)($balanceResult['balance'] ?? 0);
                ?>
                <li class="nav-item me-3">
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/topup.php" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-wallet2 me-1"></i>
                        <?= number_format($balance, 0, ',', '.') ?>đ
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- User Dropdown -->
            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-4"></i>
                    <span class="d-none d-md-block dropdown-toggle ps-2"><?= htmlspecialchars($displayName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?= htmlspecialchars($displayName) ?></h6>
                        <span><?= $isAdmin ? 'Quản trị viên' : 'Chủ trọ' ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" 
                           href="<?= ADMIN_BASE_PATH ?>/modules/<?= $isLandlord ? 'landlord/profile.php' : 'profile/index.php' ?>">
                            <i class="bi bi-person me-2"></i> Tài khoản
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="<?= ADMIN_BASE_PATH ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                        </a>
                    </li>
                </ul>
            </li>
            
        </ul>
    </nav>
</header>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">

        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link <?= menu_active('/index.php', $curPath) ?>" 
               href="<?= $isLandlord ? ADMIN_BASE_PATH . '/modules/landlord/dashboard.php' : ADMIN_BASE_PATH . '/index.php' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if ($isAdmin): ?>
            <!-- ========== ADMIN MENU ========== -->
            
            <li class="nav-heading">Quản lý người dùng</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/chutro/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/chutro/index.php">
                    <i class="bi bi-people"></i>
                    <span>Chủ trọ</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/sinhvien/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/sinhvien/index.php">
                    <i class="bi bi-person-badge"></i>
                    <span>Sinh viên</span>
                </a>
            </li>
            
            <li class="nav-heading">Quản lý tin đăng</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/posts/pending', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/posts/pending.php">
                    <i class="bi bi-clock-history"></i>
                    <span>Tin chờ duyệt</span>
                    <?php
                    $pendingPosts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts WHERE status = 'PENDING'"));
                    if (($pendingPosts['cnt'] ?? 0) > 0):
                    ?>
                        <span class="badge bg-danger ms-auto"><?= $pendingPosts['cnt'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/posts/index', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/posts/index.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Tất cả tin đăng</span>
                </a>
            </li>
            
            <li class="nav-heading">Quản lý phòng trọ</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/admin_buildings/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php">
                    <i class="bi bi-building"></i>
                    <span>Dãy trọ</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/admin_rooms/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/admin_rooms/index.php">
                    <i class="bi bi-door-open"></i>
                    <span>Phòng</span>
            </a>
            </li>
            
            <li class="nav-heading">Hỗ trợ</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/admin/contacts', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/admin/contacts.php">
                    <i class="bi bi-envelope"></i>
                    <span>Liên hệ</span>
                    <?php 
                    $newContacts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM contacts WHERE status='NEW'"));
                    if (($newContacts['c'] ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $newContacts['c'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-heading">Cấu hình</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/packages/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/packages/index.php">
                    <i class="bi bi-box-seam"></i>
                    <span>Gói đăng tin</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/reports/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/reports/index.php">
                    <i class="bi bi-graph-up"></i>
                    <span>Báo cáo doanh thu</span>
                </a>
            </li>
            
            <li class="nav-heading">Tài chính</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/withdrawals/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/withdrawals/index.php">
                    <i class="bi bi-cash-coin"></i>
                    <span>Yêu cầu rút tiền</span>
                    <?php
                    $pwRs = @mysqli_query($conn, "SELECT COUNT(*) c FROM withdrawal_requests WHERE status = 'PENDING'");
                    $pendingWdCount = $pwRs ? (int)(mysqli_fetch_assoc($pwRs)['c'] ?? 0) : 0;
                    if ($pendingWdCount > 0):
                    ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $pendingWdCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/modules/commission/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/commission/index.php">
                    <i class="bi bi-percent"></i>
                    <span>Hoa hồng</span>
                </a>
            </li>

        <?php elseif ($isLandlord): ?>
            <!-- ========== LANDLORD MENU ========== -->
            
            <li class="nav-heading">Tin đăng</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/posts/add', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php">
                    <i class="bi bi-plus-circle"></i>
                    <span>Đăng tin mới</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/posts/index', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php">
                    <i class="bi bi-list-ul"></i>
                    <span>Tin đăng của tôi</span>
                </a>
            </li>
            
            <li class="nav-heading">Tài chính</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/topup', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/topup.php">
                    <i class="bi bi-wallet-fill"></i>
                    <span>Nạp tiền</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/withdraw', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/withdraw.php">
                    <i class="bi bi-cash-coin"></i>
                    <span>Rút tiền</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/transactions', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/transactions.php">
                    <i class="bi bi-clock-history"></i>
                    <span>Lịch sử giao dịch</span>
                </a>
            </li>
            
            <!-- Hidden: Thu tiền phòng
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/payments/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/payments/index.php">
                    <i class="bi bi-receipt"></i>
                    <span>Thu tiền phòng</span>
                </a>
            </li>
            -->
            
            <li class="nav-heading">Quản lý phòng</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/buildings', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php">
                    <i class="bi bi-building"></i>
                    <span>Dãy trọ / Tòa nhà</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/rooms', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php">
                    <i class="bi bi-door-open"></i>
                    <span>Phòng</span>
                </a>
            </li>
            
            <li class="nav-heading">Khách thuê</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/nguoithue_owner/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/nguoithue_owner/index.php">
                    <i class="bi bi-people"></i>
                    <span>Người thuê</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/hopdong_owner/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/hopdong_owner/index.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Hợp đồng</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/thuchi_owner/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/thuchi_owner/index.php">
                    <i class="bi bi-cash-stack"></i>
                    <span>Thu chi</span>
                </a>
            </li>

            
            <li class="nav-heading">Tiện ích</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/yeucau_thue_owner/', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/index.php">
                    <i class="bi bi-bell"></i>
                    <span>Yêu cầu thuê</span>
                </a>
            </li>
            
            <li class="nav-heading">Tài khoản</li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/profile', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/profile.php">
                    <i class="bi bi-person"></i>
                    <span>Thông tin cá nhân</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?= menu_active('/bank_account', $curPath) ?>" 
                   href="<?= ADMIN_BASE_PATH ?>/modules/landlord/bank_account.php">
                    <i class="bi bi-bank"></i>
                    <span>Tài khoản ngân hàng</span>
                </a>
            </li>

        <?php endif; ?>


    </ul>
</aside>

<main id="main" class="main">
