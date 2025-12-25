<?php
// client/includes/header.php - Sử dụng template Hotelier
$base = '/quanlyphongtro/client/index.php';
$hotelier = '/quanlyphongtro/hotelier-1.0.0/hotelier-1.0.0';

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';

$currentPage = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Phòng Trọ Sinh Viên</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="Phòng trọ sinh viên, thuê phòng" name="keywords">
    <meta content="Hệ thống tìm kiếm và đặt phòng trọ cho sinh viên" name="description">

    <!-- Favicon -->
    <link href="<?= $hotelier ?>/img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">  

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="<?= $hotelier ?>/lib/animate/animate.min.css" rel="stylesheet">
    <link href="<?= $hotelier ?>/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="<?= $hotelier ?>/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="<?= $hotelier ?>/css/style.css" rel="stylesheet">
    
    <style>
        /* Custom styles for phòng trọ */
        .room-price { color: var(--primary); font-weight: 600; }
        .img-room { height: 220px; object-fit: cover; width: 100%; }
        .no-img-room {
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f6f6;
            color: #777;
        }
        .badge-status { font-size: 12px; }
        .filter-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: white; }
        .auth-form { max-width: 480px; margin: 0 auto; }
        .auth-card { border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
    </style>
</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner Start - Hidden by default -->
        <div id="spinner" class="bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center" style="display:none !important; opacity:0 !important; visibility:hidden !important;">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Header Start -->
        <div class="container-fluid bg-dark px-0">
            <div class="row gx-0">
                <div class="col-lg-3 bg-dark d-none d-lg-block">
                    <a href="<?= $base ?>?page=home" class="navbar-brand w-100 h-100 m-0 p-0 d-flex align-items-center justify-content-center">
                        <h1 class="m-0 text-primary text-uppercase">Phòng Trọ</h1>
                    </a>
                </div>
                <div class="col-lg-9">
                    <div class="row gx-0 bg-white d-none d-lg-flex">
                        <div class="col-lg-7 px-5 text-start">
                            <div class="h-100 d-inline-flex align-items-center py-2 me-4">
                                <i class="fa fa-map-marker-alt text-primary me-2"></i>
                                <p class="mb-0">Tìm phòng trọ cho sinh viên</p>
                            </div>
                            <div class="h-100 d-inline-flex align-items-center py-2">
                                <i class="fa fa-phone-alt text-primary me-2"></i>
                                <p class="mb-0">Hotline: 0xxx xxx xxx</p>
                            </div>
                        </div>
                        <div class="col-lg-5 px-5 text-end">
                            <div class="d-inline-flex align-items-center py-2">
                                <?php if ($userId <= 0): ?>
                                    <a class="me-3 text-dark" href="<?= $base ?>?page=login&type=student">
                                        <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                                    </a>
                                    <a class="text-dark" href="<?= $base ?>?page=register&type=student">
                                        <i class="fas fa-user-plus me-1"></i>Đăng ký
                                    </a>
                                <?php else: ?>
                                    <a class="me-3 text-dark" href="<?= $base ?>?page=profile" style="text-decoration: none;">
                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($fullName) ?>
                                    </a>
                                    <a class="text-danger" href="<?= $base ?>?page=logout">
                                        <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <nav class="navbar navbar-expand-lg bg-dark navbar-dark p-3 p-lg-0">
                        <a href="<?= $base ?>?page=home" class="navbar-brand d-block d-lg-none">
                            <h1 class="m-0 text-primary text-uppercase">Phòng Trọ</h1>
                        </a>
                        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
                            <div class="navbar-nav mr-auto py-0">
                                <a href="<?= $base ?>?page=home" class="nav-item nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">Trang chủ</a>
                                <a href="<?= $base ?>?page=tindang" class="nav-item nav-link <?= $currentPage === 'tindang' ? 'active' : '' ?>">Tin tức</a>
                                <a href="<?= $base ?>?page=phong" class="nav-item nav-link <?= $currentPage === 'phong' ? 'active' : '' ?>">Phòng trọ</a>
                                <?php if ($userId > 0): ?>
                                    <a href="<?= $base ?>?page=lichsu_datphong" class="nav-item nav-link <?= $currentPage === 'lichsu_datphong' ? 'active' : '' ?>">Lịch sử đặt phòng</a>
                                <?php endif; ?>
                                <a href="<?= $base ?>?page=lienhe" class="nav-item nav-link <?= $currentPage === 'lienhe' ? 'active' : '' ?>">Liên hệ</a>
                            </div>
                            <a href="/quanlyphongtro/admin/login.php?type=landlord" class="btn btn-primary rounded-0 py-4 px-md-5 d-none d-lg-block">
                                Đăng tin<i class="fa fa-arrow-right ms-3"></i>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        <!-- Header End -->
