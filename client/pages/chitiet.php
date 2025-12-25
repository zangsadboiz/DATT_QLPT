<?php
// client/pages/chitiet.php - Chi tiết Tin Đăng
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

$postId = (int)($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    echo '<div class="container py-5"><div class="alert alert-danger">Thiếu post_id.</div></div>';
    return;
}

// Get post details
$sql = "
  SELECT p.*, pk.package_name, pk.highlight_color, pk.priority,
         d.district_name, pr.province_name,
         u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email,
         r.rental_type as room_rental_type
  FROM posts p
  JOIN packages pk ON pk.package_id = p.package_id
  JOIN districts d ON d.district_id = p.district_id
  JOIN provinces pr ON pr.province_id = d.province_id
  JOIN users u ON u.user_id = p.user_id
  LEFT JOIN rooms r ON r.room_id = p.room_id
  WHERE p.post_id = ? AND p.status = 'APPROVED'
  LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$post = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$post) {
    echo '<div class="container py-5"><div class="alert alert-warning">Tin đăng không tồn tại hoặc chưa được duyệt.</div></div>';
    return;
}

// Get images
$images = [];
$rsImg = mysqli_query($conn, "SELECT image_id, image_path, is_primary FROM post_images WHERE post_id = $postId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) {
    // Build correct path
    $path = $img['image_path'];
    if ($path && !str_starts_with($path, '/') && !preg_match('~^https?://~i', $path)) {
        $path = '/quanlyphongtro/uploads/posts/' . $path;
    }
    $images[] = $path;
}

// Update view count
mysqli_query($conn, "UPDATE posts SET view_count = view_count + 1 WHERE post_id = $postId");

// Format amenities
$amenities = [];
$amenitiesData = json_decode($post['amenities'] ?: '[]', true) ?: [];
$labels = [
    'wifi' => ['WiFi miễn phí', 'wifi'], 'ac' => ['Điều hòa', 'snowflake'],
    'wc_rieng' => ['WC riêng', 'toilet'], 'bep' => ['Bếp nấu ăn', 'utensils'],
    'tu_lanh' => ['Tủ lạnh', 'box'], 'may_giat' => ['Máy giặt', 'tshirt'],
    'ban_cong' => ['Ban công', 'door-open'], 'gac_lung' => ['Gác lửng', 'layer-group'],
    'thang_may' => ['Thang máy', 'building'], 'camera' => ['Camera an ninh', 'video'],
    'bao_ve' => ['Bảo vệ 24/7', 'shield-alt']
];
foreach ($amenitiesData as $a) {
    if (isset($labels[$a])) $amenities[] = ['name' => $labels[$a][0], 'icon' => $labels[$a][1]];
}

$postTypes = ['ROOM' => 'Phòng trọ', 'APARTMENT' => 'Căn hộ', 'HOUSE' => 'Nhà nguyên căn'];
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-4 text-white mb-3 animated slideInDown"><?= htmlspecialchars(mb_substr($post['title'], 0, 50)) ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=phong" class="text-white">Tin đăng</a></li>
                    <li class="breadcrumb-item text-white active">Chi tiết</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Left: Images & Description -->
            <div class="col-lg-8">
                
                <!-- GALLERY ẢNH ĐƠN GIẢN -->
                <div class="wow fadeInUp mb-4" data-wow-delay="0.1s">
                    <?php if (count($images) > 0): ?>
                        <!-- Ảnh chính lớn -->
                        <div class="position-relative rounded overflow-hidden mb-3" style="background: #f8f9fa;">
                            <img id="mainImage" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($post['title']) ?>" 
                                 class="w-100" style="height: 400px; object-fit: cover;">
                            
                            <?php if (count($images) > 1): ?>
                                <!-- Nút điều hướng -->
                                <button onclick="changeImage(-1)" class="btn btn-light btn-lg position-absolute top-50 start-0 translate-middle-y ms-2 rounded-circle shadow" style="width:50px;height:50px;">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button onclick="changeImage(1)" class="btn btn-light btn-lg position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle shadow" style="width:50px;height:50px;">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                            
                            <!-- Counter -->
                            <div class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white px-3 py-1 m-2 rounded">
                                <span id="imageCounter">1</span>/<?= count($images) ?>
                            </div>
                        </div>
                        
                        <!-- Thumbnails -->
                        <?php if (count($images) > 1): ?>
                            <div class="d-flex gap-2 overflow-auto pb-2">
                                <?php foreach ($images as $idx => $imgPath): ?>
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="" 
                                         onclick="setImage(<?= $idx ?>)"
                                         class="rounded thumbnail-item <?= $idx === 0 ? 'active' : '' ?>" 
                                         style="height: 70px; width: 100px; object-fit: cover; cursor: pointer; flex-shrink: 0;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Không có ảnh -->
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 350px;">
                            <div class="text-center text-muted">
                                <i class="bi bi-image" style="font-size: 80px;"></i>
                                <p class="mt-2">Chưa có hình ảnh</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Title & Package -->
                <div class="d-flex justify-content-between align-items-start mb-4 wow fadeInUp" data-wow-delay="0.2s">
                    <div>
                        <h2 class="mb-2"><?= htmlspecialchars($post['title']) ?></h2>
                        <p class="text-muted mb-0">
                            <i class="fa fa-map-marker-alt text-primary me-2"></i><?= htmlspecialchars($post['address']) ?>, <?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?>
                        </p>
                    </div>
                    <?php if ($post['highlight_color']): ?>
                        <span class="badge fs-6 py-2 px-3" style="background: <?= $post['highlight_color'] ?>">
                            <?= htmlspecialchars($post['package_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Key Info -->
                <div class="row g-3 mb-4 wow fadeInUp" data-wow-delay="0.3s">
                    <div class="col-md-3 col-6">
                        <div class="bg-light rounded p-3 text-center">
                            <i class="fa fa-money-bill-wave text-primary fa-2x mb-2"></i>
                            <div class="small text-muted">Giá thuê</div>
                            <strong class="text-primary"><?= number_format((float)$post['price']) ?>đ</strong>
                        </div>
                    </div>
                    <?php if ($post['deposit']): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-hand-holding-usd text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Tiền cọc</div>
                                <strong><?= number_format((float)$post['deposit']) ?>đ</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($post['area']): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-ruler-combined text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Diện tích</div>
                                <strong><?= $post['area'] ?> m²</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($post['max_occupants']): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-users text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Số người ở</div>
                                <strong><?= $post['max_occupants'] ?> người</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Amenities -->
                <?php if (count($amenities) > 0): ?>
                    <div class="mb-4 wow fadeInUp" data-wow-delay="0.4s">
                        <h5><i class="fa fa-check-circle text-primary me-2"></i>Tiện nghi</h5>
                        <div class="row g-3">
                            <?php foreach ($amenities as $a): ?>
                                <div class="col-md-4 col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-<?= $a['icon'] ?> text-primary me-2"></i>
                                        <span><?= $a['name'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Description -->
                <?php if (!empty($post['description'])): ?>
                    <div class="mb-4 wow fadeInUp" data-wow-delay="0.5s">
                        <h5><i class="fa fa-file-alt text-primary me-2"></i>Mô tả chi tiết</h5>
                        <div class="bg-light rounded p-4">
                            <?= nl2br(htmlspecialchars($post['description'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Contact Info -->
            <div class="col-lg-4">
                <!-- Price Card -->
                <?php 
                $isDaily = (($post['room_rental_type'] ?? 'MONTHLY') === 'DAILY');
                $priceUnit = $isDaily ? 'đ/ngày' : 'đ/tháng';
                ?>
                <div class="bg-primary text-white rounded p-4 mb-4 wow fadeInUp" data-wow-delay="0.1s">
                    <h3 class="mb-0"><?= number_format((float)$post['price']) ?><?= $priceUnit ?></h3>
                    <small><?= $postTypes[$post['post_type']] ?? 'Phòng trọ' ?></small>
                </div>
                
                <!-- Landlord Info -->
                <div class="bg-light rounded p-4 mb-4 wow fadeInUp" data-wow-delay="0.2s">
                    <h5 class="mb-3"><i class="fa fa-user text-primary me-2"></i>Thông tin liên hệ</h5>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Chủ cho thuê</div>
                        <strong class="fs-5"><?= htmlspecialchars($post['contact_name'] ?: $post['landlord_name']) ?></strong>
                    </div>
                    
                    <?php $phone = $post['contact_phone'] ?: $post['landlord_phone']; ?>
                    <?php if ($phone): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Số điện thoại</div>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="fs-5 text-primary fw-bold">
                                <i class="fa fa-phone-alt me-2"></i><?= htmlspecialchars($phone) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- NÚT ĐẶT PHÒNG NGAY -->
                    <?php 
                    $roomId = (int)($post['room_id'] ?? 0);
                    if ($roomId > 0): 
                        // Có liên kết phòng → đặt trực tiếp phòng đó
                    ?>
                        <a href="/quanlyphongtro/client/index.php?page=datphong&room_id=<?= $roomId ?>" class="btn btn-success w-100 py-3 mb-2">
                            <i class="fa fa-calendar-check me-2"></i>Đặt phòng ngay
                        </a>
                    <?php else: 
                        // Không liên kết phòng → về danh sách phòng
                    ?>
                        <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-success w-100 py-3 mb-2">
                            <i class="fa fa-search me-2"></i>Xem danh sách phòng
                        </a>
                    <?php endif; ?>
                    
                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="btn btn-outline-primary w-100 py-3">
                        <i class="fa fa-phone-alt me-2"></i>Gọi điện
                    </a>
                </div>
                
                <!-- Post Info -->
                <div class="bg-light rounded p-4 wow fadeInUp" data-wow-delay="0.3s">
                    <h6 class="mb-3">Thông tin tin đăng</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Mã tin</span>
                        <strong><?= htmlspecialchars($post['post_code']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Ngày đăng</span>
                        <strong><?= date('d/m/Y', strtotime($post['created_at'])) ?></strong>
                    </div>
                    <?php if ($post['end_date']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Hết hạn</span>
                            <strong><?= date('d/m/Y', strtotime($post['end_date'])) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Lượt xem</span>
                        <strong><?= number_format($post['view_count'] ?? 0) ?></strong>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="mt-4">
                    <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-outline-secondary w-100 py-3">
                        <i class="fa fa-arrow-left me-2"></i>Quay lại danh sách
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.thumbnail-item {
    border: 3px solid transparent;
    transition: all 0.2s;
    opacity: 0.6;
}
.thumbnail-item:hover,
.thumbnail-item.active {
    border-color: #FEA116;
    opacity: 1;
}
</style>

<script>
// Image gallery
const images = <?= json_encode($images) ?>;
let currentIndex = 0;

function setImage(index) {
    if (index < 0 || index >= images.length) return;
    currentIndex = index;
    document.getElementById('mainImage').src = images[index];
    document.getElementById('imageCounter').textContent = index + 1;
    
    // Update thumbnails
    document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function changeImage(direction) {
    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = images.length - 1;
    if (newIndex >= images.length) newIndex = 0;
    setImage(newIndex);
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') changeImage(-1);
    if (e.key === 'ArrowRight') changeImage(1);
});
</script>
