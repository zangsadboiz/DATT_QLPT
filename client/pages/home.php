<?php
// client/pages/home.php - Trang chủ đơn giản, sạch sẽ
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// === TẤT CẢ TIN ĐĂNG (sắp xếp theo priority từ cao đến thấp) ===
$sqlPosts = "
  SELECT p.*, pk.package_name, pk.highlight_color, pk.priority,
         d.district_name, pr.province_name,
         u.full_name as landlord_name, u.phone as landlord_phone,
         r.rental_type as room_rental_type,
         (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
  FROM posts p
  JOIN packages pk ON pk.package_id = p.package_id
  JOIN districts d ON d.district_id = p.district_id
  JOIN provinces pr ON pr.province_id = d.province_id
  JOIN users u ON u.user_id = p.user_id
  LEFT JOIN rooms r ON r.room_id = p.room_id
  WHERE p.status = 'APPROVED' 
    AND (p.end_date IS NULL OR p.end_date >= CURDATE())
  ORDER BY pk.priority DESC, p.created_at DESC
  LIMIT 6
";
$posts = mysqli_query($conn, $sqlPosts);

// Thống kê
$statsPosts = ['cnt' => 0];
$statsLandlords = ['cnt' => 0];
$statsRooms = ['cnt' => 0];

$rsPosts = @mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts WHERE status = 'APPROVED'");
if ($rsPosts) $statsPosts = mysqli_fetch_assoc($rsPosts) ?: ['cnt' => 0];

$rsLandlords = @mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as cnt FROM posts WHERE status = 'APPROVED'");
if ($rsLandlords) $statsLandlords = mysqli_fetch_assoc($rsLandlords) ?: ['cnt' => 0];

$rsRooms = @mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rooms WHERE room_status = 'VACANT' AND deleted_at IS NULL");
if ($rsRooms) $statsRooms = mysqli_fetch_assoc($rsRooms) ?: ['cnt' => 0];

// Resolve image path - SỬA ĐƯỜNG DẪN ĐÚNG
function resolvePostImg(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('~^https?://~i', $path)) return $path;
    if (str_starts_with($path, '/')) return $path;
    // Đường dẫn đúng là /quanlyphongtro/uploads/posts/ (không có admin)
    return '/quanlyphongtro/uploads/posts/' . ltrim($path, '/');
}

$postType = ['ROOM' => 'Phòng trọ', 'APARTMENT' => 'Căn hộ', 'HOUSE' => 'Nhà nguyên căn'];
?>

<!-- Carousel Start -->
<div class="container-fluid p-0 mb-5">
    <div id="header-carousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img class="w-100" src="<?= $hotelier ?>/img/carousel-1.jpg" alt="Image">
                <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                    <div class="p-3" style="max-width: 700px;">
                        <h6 class="section-title text-white text-uppercase mb-3 animated slideInDown">Phòng Trọ Sinh Viên</h6>
                        <h1 class="display-3 text-white mb-4 animated slideInDown">Tìm phòng trọ phù hợp với bạn</h1>
                        <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-primary py-md-3 px-md-5 me-3 animated slideInLeft">Xem tin đăng</a>
                        <a href="/quanlyphongtro/client/index.php?page=register&type=student" class="btn btn-light py-md-3 px-md-5 animated slideInRight">Đăng ký ngay</a>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <img class="w-100" src="<?= $hotelier ?>/img/carousel-2.jpg" alt="Image">
                <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                    <div class="p-3" style="max-width: 700px;">
                        <h6 class="section-title text-white text-uppercase mb-3 animated slideInDown">Dành Cho Sinh Viên</h6>
                        <h1 class="display-3 text-white mb-4 animated slideInDown">An toàn - Tiện nghi - Giá tốt</h1>
                        <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-primary py-md-3 px-md-5 me-3 animated slideInLeft">Tìm phòng</a>
                        <a href="/quanlyphongtro/admin/login.php?type=landlord" class="btn btn-light py-md-3 px-md-5 animated slideInRight">Đăng tin cho thuê</a>
                    </div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#header-carousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#header-carousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<!-- Carousel End -->

<!-- Search Box Start -->
<div class="container-fluid booking pb-5 wow fadeIn" data-wow-delay="0.1s">
    <div class="container">
        <div class="bg-white shadow" style="padding: 35px;">
            <form class="row g-2" method="get" action="/quanlyphongtro/client/index.php">
                <input type="hidden" name="page" value="phong">
                <div class="col-md-10">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input name="q" type="text" class="form-control" placeholder="Tìm theo tiêu đề, địa chỉ...">
                        </div>
                        <div class="col-md-3">
                            <input name="min" type="number" class="form-control" placeholder="Giá từ (VNĐ)">
                        </div>
                        <div class="col-md-3">
                            <input name="max" type="number" class="form-control" placeholder="Giá đến (VNĐ)">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Tìm kiếm</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Search Box End -->

<!-- About Start -->
<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h6 class="section-title text-start text-primary text-uppercase">Về Chúng Tôi</h6>
                <h1 class="mb-4">Chào mừng đến với <span class="text-primary text-uppercase">Phòng Trọ SV</span></h1>
                <p class="mb-4">Hệ thống tìm kiếm và đặt phòng trọ dành riêng cho sinh viên. Chúng tôi kết nối sinh viên với các chủ trọ uy tín, giúp bạn tìm được nơi ở phù hợp với nhu cầu và ngân sách.</p>
                <div class="row g-3 pb-4">
                    <div class="col-sm-4 wow fadeIn" data-wow-delay="0.1s">
                        <div class="border rounded p-1">
                            <div class="border rounded text-center p-4">
                                <i class="fa fa-newspaper fa-2x text-primary mb-2"></i>
                                <h2 class="mb-1" data-toggle="counter-up"><?= (int)($statsPosts['cnt'] ?? 0) ?></h2>
                                <p class="mb-0">Tin đăng</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 wow fadeIn" data-wow-delay="0.3s">
                        <div class="border rounded p-1">
                            <div class="border rounded text-center p-4">
                                <i class="fa fa-door-open fa-2x text-primary mb-2"></i>
                                <h2 class="mb-1" data-toggle="counter-up"><?= (int)($statsRooms['cnt'] ?? 0) ?></h2>
                                <p class="mb-0">Phòng trống</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 wow fadeIn" data-wow-delay="0.5s">
                        <div class="border rounded p-1">
                            <div class="border rounded text-center p-4">
                                <i class="fa fa-users fa-2x text-primary mb-2"></i>
                                <h2 class="mb-1" data-toggle="counter-up"><?= (int)($statsLandlords['cnt'] ?? 0) ?></h2>
                                <p class="mb-0">Chủ trọ</p>
                            </div>
                        </div>
                    </div>
                </div>
                <a class="btn btn-primary py-3 px-5 mt-2" href="/quanlyphongtro/client/index.php?page=phong">Xem tất cả phòng</a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6 text-end">
                        <img class="img-fluid rounded w-75 wow zoomIn" data-wow-delay="0.1s" src="<?= $hotelier ?>/img/about-1.jpg" style="margin-top: 25%;">
                    </div>
                    <div class="col-6 text-start">
                        <img class="img-fluid rounded w-100 wow zoomIn" data-wow-delay="0.3s" src="<?= $hotelier ?>/img/about-2.jpg">
                    </div>
                    <div class="col-6 text-end">
                        <img class="img-fluid rounded w-50 wow zoomIn" data-wow-delay="0.5s" src="<?= $hotelier ?>/img/about-3.jpg">
                    </div>
                    <div class="col-6 text-start">
                        <img class="img-fluid rounded w-75 wow zoomIn" data-wow-delay="0.7s" src="<?= $hotelier ?>/img/about-4.jpg">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- About End -->

<!-- Posts Start -->
<div class="container-xxl py-5">
    <div class="container">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
            <h6 class="section-title text-center text-primary text-uppercase">Tin Đăng</h6>
            <h1 class="mb-5">Phòng trọ <span class="text-primary text-uppercase">Mới nhất</span></h1>
        </div>
        <div class="row g-4">
            <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                <?php while($p = mysqli_fetch_assoc($posts)): ?>
                    <?php
                    $img = resolvePostImg($p['primary_image'] ?? '');
                    // Chỉ hiển thị badge VIP Nổi Bật (priority cao nhất)
                    $isVIPNoiBat = ($p['priority'] >= 4); // VIP Nổi Bật có priority = 4
                    ?>
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="room-item shadow rounded overflow-hidden h-100 d-flex flex-column">
                            <div class="position-relative" style="height: 220px;">
                                <?php if ($img): ?>
                                    <img class="img-fluid w-100 h-100" src="<?= htmlspecialchars($img) ?>" alt="" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                        <i class="bi bi-image text-muted" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                <small class="position-absolute start-0 top-100 translate-middle-y bg-primary text-white rounded py-1 px-3 ms-4">
                                    <?php 
                                    $priceUnit = (($p['room_rental_type'] ?? 'MONTHLY') === 'DAILY') ? 'đ/ngày' : 'đ/tháng';
                                    ?>
                                    <?= number_format((float)$p['price']) ?><?= $priceUnit ?>
                                </small>
                                <?php 
                                $pri = (int)($p['priority'] ?? 0);
                                if ($pri >= 4): // Nổi bật
                                ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-warning text-dark">
                                        <i class="fas fa-crown me-1"></i>Nổi bật
                                    </span>
                                <?php elseif ($pri >= 3): // Ưu tiên ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-info text-white">
                                        <i class="fas fa-star me-1"></i>Ưu tiên
                                    </span>
                                <?php elseif ($pri >= 2): // VIP ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-success text-white">
                                        <i class="fas fa-bolt me-1"></i>VIP
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 mt-2 flex-grow-1 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0 flex-grow-1 me-2"><?= htmlspecialchars(mb_substr($p['title'], 0, 22)) ?><?= mb_strlen($p['title']) > 22 ? '...' : '' ?></h5>
                                    <?php 
                                    $typeColors = ['ROOM' => 'primary', 'APARTMENT' => 'info', 'HOUSE' => 'success'];
                                    $typeIcons = ['ROOM' => 'fa-door-open', 'APARTMENT' => 'fa-building', 'HOUSE' => 'fa-home'];
                                    $typeColor = $typeColors[$p['post_type']] ?? 'secondary';
                                    $typeIcon = $typeIcons[$p['post_type']] ?? 'fa-home';
                                    ?>
                                    <span class="badge bg-<?= $typeColor ?> rounded-pill px-2 py-1" style="font-size: 11px; white-space: nowrap;">
                                        <i class="fa <?= $typeIcon ?> me-1"></i><?= $postType[$p['post_type']] ?? 'Phòng' ?>
                                    </span>
                                </div>
                                <div class="d-flex mb-3 flex-wrap">
                                    <?php if ($p['area']): ?>
                                        <small class="border-end me-3 pe-3"><i class="fa fa-ruler-combined text-primary me-2"></i><?= $p['area'] ?> m²</small>
                                    <?php endif; ?>
                                    <?php if ($p['max_occupants']): ?>
                                        <small><i class="fa fa-users text-primary me-2"></i><?= $p['max_occupants'] ?> người</small>
                                    <?php endif; ?>
                                </div>
                                <p class="text-body mb-3">
                                    <i class="fa fa-map-marker-alt text-primary me-2"></i><?= htmlspecialchars(mb_substr($p['address'], 0, 35)) ?>...
                                </p>
                                <div class="d-flex justify-content-between mt-auto">
                                    <a class="btn btn-sm btn-primary rounded py-2 px-4" href="/quanlyphongtro/client/index.php?page=chitiet&post_id=<?= (int)$p['post_id'] ?>">Xem chi tiết</a>
                                    <a class="btn btn-sm btn-dark rounded py-2 px-4" href="/quanlyphongtro/client/index.php?page=lienhe_chutro&post_id=<?= (int)$p['post_id'] ?>">Liên hệ</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-info-circle me-2"></i>
                        Chưa có tin đăng nào được duyệt.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
            <div class="text-center mt-5">
                <a href="/quanlyphongtro/client/index.php?page=tindang" class="btn btn-primary py-3 px-5">
                    Xem tất cả tin <i class="fa fa-arrow-right ms-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- Posts End -->

<!-- Service Start -->
<div class="container-xxl py-5 bg-light">
    <div class="container">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
            <h6 class="section-title text-center text-primary text-uppercase">Dịch Vụ</h6>
            <h1 class="mb-5">Tại sao chọn <span class="text-primary text-uppercase">Chúng Tôi</span></h1>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                <a class="service-item rounded" href="#">
                    <div class="service-icon bg-transparent border rounded p-1">
                        <div class="w-100 h-100 border rounded d-flex align-items-center justify-content-center">
                            <i class="fa fa-search fa-2x text-primary"></i>
                        </div>
                    </div>
                    <h5 class="mb-3">Tìm kiếm dễ dàng</h5>
                    <p class="text-body mb-0">Tìm phòng theo vị trí, giá cả và tiện nghi phù hợp với nhu cầu của bạn.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.2s">
                <a class="service-item rounded" href="#">
                    <div class="service-icon bg-transparent border rounded p-1">
                        <div class="w-100 h-100 border rounded d-flex align-items-center justify-content-center">
                            <i class="fa fa-shield-alt fa-2x text-primary"></i>
                        </div>
                    </div>
                    <h5 class="mb-3">An toàn & Uy tín</h5>
                    <p class="text-body mb-0">Tất cả tin đăng đều được Admin kiểm duyệt, đảm bảo thông tin chính xác.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.3s">
                <a class="service-item rounded" href="#">
                    <div class="service-icon bg-transparent border rounded p-1">
                        <div class="w-100 h-100 border rounded d-flex align-items-center justify-content-center">
                            <i class="fa fa-money-bill-wave fa-2x text-primary"></i>
                        </div>
                    </div>
                    <h5 class="mb-3">Giá cả hợp lý</h5>
                    <p class="text-body mb-0">Nhiều mức giá phù hợp với ngân sách sinh viên, không phí môi giới.</p>
                </a>
            </div>
        </div>
    </div>
</div>
<!-- Service End -->
