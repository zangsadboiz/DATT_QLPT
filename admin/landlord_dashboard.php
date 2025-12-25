<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

// Redirect if not landlord
if ($role !== 'LANDLORD') {
    if ($role === 'ADMIN') {
        header('Location: /quanlyphongtro/admin/index.php');
    } else {
        header('Location: /quanlyphongtro/client/index.php');
    }
    exit;
}

// LANDLORD DASHBOARD STATISTICS

// Buildings owned by this landlord
$buildingsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN building_status = 'PENDING' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN building_status = 'APPROVED' THEN 1 ELSE 0 END) as approved
    FROM buildings 
    WHERE owner_user_id = ? AND deleted_at IS NULL";
$stmt = mysqli_prepare($conn, $buildingsQuery);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$buildingsStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Rooms in landlord's buildings
$roomsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.room_status = 'VACANT' THEN 1 ELSE 0 END) as vacant,
    SUM(CASE WHEN r.room_status = 'OCCUPIED' THEN 1 ELSE 0 END) as occupied,
    SUM(CASE WHEN r.publish_status = 'PENDING' THEN 1 ELSE 0 END) as pending_approval
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_user_id = ? AND r.deleted_at IS NULL";
$stmt = mysqli_prepare($conn, $roomsQuery);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$roomsStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Calculate occupancy rate
$totalRooms = (int)($roomsStats['total'] ?? 0);
$occupiedRooms = (int)($roomsStats['occupied'] ?? 0);
$occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

// Bookings for landlord's rooms
$bookingsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN b.status = 'PENDING' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN b.status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN b.status = 'CHECKED_IN' THEN 1 ELSE 0 END) as checked_in
    FROM bookings b
    JOIN rooms r ON r.room_id = b.room_id
    JOIN buildings bd ON bd.building_id = r.building_id
    WHERE bd.owner_user_id = ?";
$stmt = mysqli_prepare($conn, $bookingsQuery);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$bookingsStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Recent booking requests (last 10)
$recentBookingsQuery = "SELECT b.*, r.room_code, bd.building_name, t.full_name as tenant_name
    FROM bookings b
    JOIN rooms r ON r.room_id = b.room_id
    JOIN buildings bd ON bd.building_id = r.building_id
    JOIN tenants t ON t.tenant_id = b.tenant_id
    WHERE bd.owner_user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10";
$stmt = mysqli_prepare($conn, $recentBookingsQuery);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$recentBookings = mysqli_stmt_get_result($stmt);

require_once __DIR__ . '/includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-house-heart me-2"></i>Dashboard Chủ trọ</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item active">Dashboard</li>
    </ol>
  </nav>
</div>

<section class="section dashboard">
  
  <!-- Overview Stats Row -->
  <div class="row mb-4">
    
    <!-- Buildings -->
    <div class="col-xl-3 col-md-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="stats-label">Dãy/Tòa của tôi</div>
              <div class="stats-number"><?= $buildingsStats['total'] ?? 0 ?></div>
              <small class="text-white-50">Chờ duyệt: <?= $buildingsStats['pending'] ?? 0 ?></small>
            </div>
            <div class="stats-icon">
              <i class="bi bi-buildings"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Rooms -->
    <div class="col-xl-3 col-md-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="stats-label">Tổng phòng</div>
              <div class="stats-number"><?= $roomsStats['total'] ?? 0 ?></div>
              <small class="text-white-50">Trống: <?= $roomsStats['vacant'] ?? 0 ?></small>
            </div>
            <div class="stats-icon">
              <i class="bi bi-door-closed"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Occupancy Rate -->
    <div class="col-xl-3 col-md-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="stats-label">Tỷ lệ lấp đầy</div>
              <div class="stats-number"><?= $occupancyRate ?>%</div>
              <small class="text-white-50"><?= $occupiedRooms ?>/<?= $totalRooms ?> phòng</small>
            </div>
            <div class="stats-icon">
              <i class="bi bi-graph-up-arrow"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Pending Bookings -->
    <div class="col-xl-3 col-md-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="stats-label">Yêu cầu đặt phòng</div>
              <div class="stats-number"><?= $bookingsStats['pending'] ?? 0 ?></div>
              <small class="text-dark">Cần duyệt</small>
            </div>
            <div class="stats-icon">
              <i class="bi bi-bell"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
  </div>

  <!-- Quick Actions -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Thao tác nhanh</h5>
          <div class="row g-3">
            <div class="col-md-3">
              <a href="<?= ADMIN_BASE_PATH ?>/modules/toanha/add.php" class="btn btn-primary w-100">
                <i class="bi bi-plus-circle me-2"></i>Thêm dãy/tòa mới
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= ADMIN_BASE_PATH ?>/modules/phong/add.php" class="btn btn-success w-100">
                <i class="bi bi-plus-circle me-2"></i>Thêm phòng mới
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/index.php" class="btn btn-warning w-100">
                <i class="bi bi-list-check me-2"></i>Xem yêu cầu thuê
              </a>
            </div>
            <div class="col-md-3">
              <a href="<?= ADMIN_BASE_PATH ?>/modules/hoadon/index.php" class="btn btn-info w-100">
                <i class="bi bi-receipt me-2"></i>Quản lý hóa đơn
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activities -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Yêu cầu đặt phòng gần đây</h5>
        </div>
        <div class="card-body">
          <?php if ($recentBookings && mysqli_num_rows($recentBookings) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Mã đặt phòng</th>
                    <th>Sinh viên</th>
                    <th>Phòng</th>
                    <th>Dãy/Tòa</th>
                    <th>Check-in</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($booking = mysqli_fetch_assoc($recentBookings)): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></td>
                      <td><?= htmlspecialchars($booking['tenant_name']) ?></td>
                      <td><?= htmlspecialchars($booking['room_code']) ?></td>
                      <td><?= htmlspecialchars($booking['building_name']) ?></td>
                      <td><?= date('d/m/Y', strtotime($booking['check_in'])) ?></td>
                      <td>
                        <?php
                        $statusMap = [
                          'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                          'CONFIRMED' => '<span class="badge bg-success">Đã duyệt</span>',
                          'CHECKED_IN' => '<span class="badge bg-info">Đang thuê</span>',
                          'CHECKED_OUT' => '<span class="badge bg-secondary">Đã trả phòng</span>',
                          'CANCELLED' => '<span class="badge bg-danger">Đã hủy</span>',
                        ];
                        echo $statusMap[$booking['status']] ?? $booking['status'];
                        ?>
                      </td>
                      <td>
                        <?php if ($booking['status'] === 'PENDING'): ?>
                          <a href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/approve.php?id=<?= $booking['booking_id'] ?>" 
                             class="btn btn-sm btn-success">Duyệt</a>
                        <?php elseif ($booking['status'] === 'CONFIRMED'): ?>
                          <a href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/checkin.php?id=<?= $booking['booking_id'] ?>" 
                             class="btn btn-sm btn-primary">Check-in</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted text-center py-4">Chưa có yêu cầu đặt phòng nào</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</section>

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/includes/footer.php'; 
?>
