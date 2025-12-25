<?php
// client/pages/tindang.php - Danh sách tin đăng (Posts)
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Filters
$search = trim($_GET['search'] ?? '');
$provinceId = (int)($_GET['province'] ?? 0);
$districtId = (int)($_GET['district'] ?? 0);
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 0);
$postType = $_GET['type'] ?? '';
$priority = $_GET['priority'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$where = ["p.status = 'APPROVED'", "(p.end_date IS NULL OR p.end_date >= CURDATE())"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(p.title LIKE ? OR p.address LIKE ?)";
    $searchLike = "%$search%";
    $params[] = &$searchLike;
    $params[] = &$searchLike;
    $types .= 'ss';
}

if ($provinceId > 0) {
    $where[] = "pr.province_id = ?";
    $params[] = &$provinceId;
    $types .= 'i';
}

if ($districtId > 0) {
    $where[] = "p.district_id = ?";
    $params[] = &$districtId;
    $types .= 'i';
}

if ($minPrice > 0) {
    $where[] = "p.price >= ?";
    $params[] = &$minPrice;
    $types .= 'i';
}

if ($maxPrice > 0) {
    $where[] = "p.price <= ?";
    $params[] = &$maxPrice;
    $types .= 'i';
}

if ($postType && in_array($postType, ['ROOM', 'APARTMENT', 'HOUSE'])) {
    $where[] = "p.post_type = '$postType'";
}

$packageId = (int)($_GET['package'] ?? 0);
if ($packageId > 0) {
    $where[] = "p.package_id = $packageId";
}

$whereSql = implode(' AND ', $where);

// Order
$orderBy = match($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular' => 'p.view_count DESC',
    default => 'pk.priority DESC, p.created_at DESC'
};

// Count total
$sqlCount = "SELECT COUNT(*) as total FROM posts p
             JOIN packages pk ON pk.package_id = p.package_id
             JOIN districts d ON d.district_id = p.district_id
             JOIN provinces pr ON pr.province_id = d.province_id
             WHERE $whereSql";
$resCount = mysqli_query($conn, $sqlCount);
$totalPosts = $resCount ? (int)mysqli_fetch_assoc($resCount)['total'] : 0;

// Pagination
$limit = 6;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalPosts / $limit);

// Get posts
$sql = "SELECT p.*, pk.package_name, pk.highlight_color, pk.priority,
        d.district_name, pr.province_name,
        u.full_name as landlord_name, u.phone as landlord_phone,
        (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM posts p
        JOIN packages pk ON pk.package_id = p.package_id
        JOIN districts d ON d.district_id = p.district_id
        JOIN provinces pr ON pr.province_id = d.province_id
        JOIN users u ON u.user_id = p.user_id
        WHERE $whereSql
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset";
$posts = mysqli_query($conn, $sql);

// Get packages for filter
$packages = mysqli_query($conn, "SELECT * FROM packages ORDER BY priority DESC");

$postTypes = ['ROOM' => 'Phòng trọ', 'APARTMENT' => 'Căn hộ', 'HOUSE' => 'Nhà nguyên căn'];

function resolvePostImage(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (str_starts_with($path, '/') || preg_match('~^https?://~i', $path)) return $path;
    return '/quanlyphongtro/uploads/posts/' . $path;
}
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Tin đăng</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Tin đăng</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Sidebar Filters -->
            <div class="col-lg-3">
                <div class="bg-light rounded p-4 mb-4">
                    <h5 class="mb-3"><i class="fa fa-filter text-primary me-2"></i>Bộ lọc</h5>
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="tindang">
                        
                        <div class="mb-3">
                            <label class="form-label">Tìm kiếm</label>
                            <input type="text" name="search" class="form-control" placeholder="Nhập từ khóa..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Loại tin</label>
                            <select name="type" class="form-select">
                                <option value="">Tất cả</option>
                                <?php foreach ($postTypes as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $postType === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Giá từ</label>
                            <input type="number" name="min_price" class="form-control" placeholder="VNĐ" value="<?= $minPrice ?: '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Giá đến</label>
                            <input type="number" name="max_price" class="form-control" placeholder="VNĐ" value="<?= $maxPrice ?: '' ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa fa-search me-2"></i>Tìm kiếm
                        </button>
                        
                        <?php if ($search || $provinceId || $minPrice || $maxPrice || $postType): ?>
                            <a href="?page=tindang" class="btn btn-outline-secondary w-100 mt-2">Xóa bộ lọc</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Posts List -->
            <div class="col-lg-9">
                <!-- Sort & Count -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <p class="mb-0">Tìm thấy <strong><?= $totalPosts ?></strong> tin tức</p>
                    <select class="form-select" style="width: auto;" onchange="location.href='?page=tindang&sort='+this.value">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Phổ biến nhất</option>
                    </select>
                </div>
                
                <div class="row g-4">
                    <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                        <?php while ($p = mysqli_fetch_assoc($posts)): ?>
                            <?php
                            $img = resolvePostImage($p['primary_image'] ?? '');
                            $isVIP = ($p['priority'] ?? 0) >= 4;
                            $postRoomId = (int)($p['room_id'] ?? 0);
                            ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="room-item shadow rounded overflow-hidden h-100 d-flex flex-column">
                                    <div class="position-relative" style="height: 200px;">
                                        <?php if ($img): ?>
                                            <img class="img-fluid w-100 h-100" src="<?= htmlspecialchars($img) ?>" alt="" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                <i class="bi bi-image text-muted" style="font-size: 48px;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <small class="position-absolute start-0 top-100 translate-middle-y bg-primary text-white rounded py-1 px-3 ms-4">
                                            <?= number_format((float)$p['price']) ?>đ/tháng
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
                                            <h5 class="mb-0 flex-grow-1 me-2"><?= htmlspecialchars(mb_substr($p['title'], 0, 20)) ?><?= mb_strlen($p['title']) > 20 ? '...' : '' ?></h5>
                                            <?php 
                                            $typeColors = ['ROOM' => 'primary', 'APARTMENT' => 'info', 'HOUSE' => 'success'];
                                            $typeIcons = ['ROOM' => 'fa-door-open', 'APARTMENT' => 'fa-building', 'HOUSE' => 'fa-home'];
                                            $typeColor = $typeColors[$p['post_type']] ?? 'secondary';
                                            $typeIcon = $typeIcons[$p['post_type']] ?? 'fa-home';
                                            ?>
                                            <span class="badge bg-<?= $typeColor ?> rounded-pill px-2 py-1" style="font-size: 10px; white-space: nowrap;">
                                                <i class="fa <?= $typeIcon ?> me-1"></i><?= $postTypes[$p['post_type']] ?? 'Phòng' ?>
                                            </span>
                                        </div>
                                        <div class="d-flex mb-3 flex-wrap">
                                            <?php if (($p['area'] ?? 0) > 0): ?>
                                                <small class="border-end me-3 pe-3"><i class="fa fa-ruler-combined text-primary me-2"></i><?= $p['area'] ?> m²</small>
                                            <?php endif; ?>
                                            <?php if (($p['max_occupants'] ?? 0) > 0): ?>
                                                <small><i class="fa fa-users text-primary me-2"></i><?= $p['max_occupants'] ?> người</small>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-body mb-3">
                                            <i class="fa fa-map-marker-alt text-primary me-2"></i><?= htmlspecialchars(mb_substr($p['address'], 0, 30)) ?>...
                                        </p>
                                        <div class="d-flex justify-content-between mt-auto">
                                            <a class="btn btn-sm btn-primary rounded py-2 px-3" href="/quanlyphongtro/client/index.php?page=chitiet&post_id=<?= (int)$p['post_id'] ?>">Xem chi tiết</a>
                                            <?php if ($postRoomId > 0): ?>
                                                <a class="btn btn-sm btn-success rounded py-2 px-3" href="/quanlyphongtro/client/index.php?page=datphong&room_id=<?= $postRoomId ?>">Đặt phòng</a>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-dark rounded py-2 px-3" href="/quanlyphongtro/client/index.php?page=phong">Xem phòng</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 80px; color: #ddd;"></i>
                                <h4 class="mt-4 text-muted">Không tìm thấy tin đăng</h4>
                                <p class="text-muted">Thử điều chỉnh bộ lọc hoặc <a href="?page=tindang">xem tất cả</a></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=tindang&p=<?= $page - 1 ?>&sort=<?= $sort ?>">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=tindang&p=<?= $i ?>&sort=<?= $sort ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=tindang&p=<?= $page + 1 ?>&sort=<?= $sort ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
