<?php
// client/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_name('QPT_STUDENT');
    session_set_cookie_params([
        'path' => '/quanlyphongtro/client',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/db.php';

$page = $_GET['page'] ?? 'home';

$allow = [
    'home'     => 'home.php',
    'phong'    => 'phong.php',
    'tindang'  => 'tindang.php',
    'chitiet'  => 'chitiet.php',
    'chitiet_phong' => 'chitiet_phong.php',
    'thue'     => 'thue.php',
    'lienhe'   => 'lienhe.php',
    'login'    => 'login.php',
    'register' => 'register.php',
    'logout'   => 'logout.php',
    'datphong' => 'datphong.php',
    'lichsu_datphong' => 'lichsu_datphong.php',
    'chitiet_datphong' => 'chitiet_datphong.php',
    'huy_datphong' => 'huy_datphong.php',
    'thanhtoan_datcoc' => 'thanhtoan_datcoc.php',
    'traphong' => 'traphong.php',
    'lienhe_chutro' => 'lienhe_chutro.php',
    'lichsu_lienhe' => 'lichsu_lienhe.php',
    'profile'  => 'profile.php',
    'giahan'   => 'giahan.php',
];

$file = $allow[$page] ?? $allow['home'];

// === PRE-PROCESS: Xử lý các trang cần redirect TRƯỚC khi output ===
// Login page: xử lý POST login để redirect
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_GET['type'] ?? 'student';
    if ($type !== 'student') {
        header('Location: /quanlyphongtro/admin/login.php?type=landlord');
        exit;
    }
    
    $return = $_GET['return'] ?? '/quanlyphongtro/client/index.php?page=home';
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $sql = "
          SELECT u.user_id, u.full_name, u.username, u.password_hash, u.is_active, r.role_name
          FROM users u
          JOIN roles r ON r.role_id=u.role_id
          WHERE u.username=? OR u.email=?
          LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $u = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if ($u && (int)$u['is_active'] === 1 && ($u['role_name'] ?? '') === 'STUDENT' && password_verify($password, $u['password_hash'])) {
            // Update last_login
            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE user_id = {$u['user_id']}");
            
            $_SESSION['user_id']   = (int)$u['user_id'];
            $_SESSION['role_name'] = 'STUDENT';
            $_SESSION['full_name'] = $u['full_name'] ?? '';
            $_SESSION['username']  = $u['username'] ?? '';
            header('Location: ' . $return);
            exit;
        }
    }
}

// Datphong page: xử lý POST để redirect đến VNPay
if ($page === 'datphong' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $isLoggedIn = isset($_SESSION['user_id']) && ($_SESSION['role_name'] ?? '') === 'STUDENT';
    
    if ($isLoggedIn) {
        require_once __DIR__ . '/../admin/includes/vnpay_config.php';
        
        $userId = (int)$_SESSION['user_id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $viewDate = !empty($_POST['view_date']) ? $_POST['view_date'] : null;
        $moveInDate = !empty($_POST['move_in_date']) ? $_POST['move_in_date'] : null;
        $checkOutDate = !empty($_POST['check_out_date']) ? $_POST['check_out_date'] : null;
        $message = trim($_POST['message'] ?? '');
        $roomId = (int)($_POST['room_id'] ?? 0);
        $postId = (int)($_POST['post_id'] ?? 0);
        $rentalType = $_POST['rental_type'] ?? 'MONTHLY';
        $dailyPricePost = (float)($_POST['daily_price'] ?? 0);
        
        // Tính số tiền cần thanh toán
        $paymentAmount = 0;
        if ($roomId > 0) {
            $roomData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT base_rent, rental_type, daily_price FROM rooms WHERE room_id = $roomId"));
            
            if (($roomData['rental_type'] ?? 'MONTHLY') === 'DAILY' && $moveInDate && $checkOutDate) {
                // Thuê theo ngày: tính tổng = daily_price × số ngày
                $d1 = new DateTime($moveInDate);
                $d2 = new DateTime($checkOutDate);
                $numDays = $d2->diff($d1)->days;
                $paymentAmount = (float)($roomData['daily_price'] ?? 0) * max(1, $numDays);
            } else {
                // Thuê theo tháng: deposit = 1 tháng
                $paymentAmount = (float)($roomData['base_rent'] ?? 0);
            }
        } elseif ($postId > 0) {
            $postData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT price FROM posts WHERE post_id = $postId"));
            $paymentAmount = (float)($postData['price'] ?? 0);
        }
        
        // Validation cơ bản
        if ($paymentAmount > 0 && $moveInDate) {
            // Tạo tenant
            $tenantId = 0;
            $checkTenant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tenant_id FROM tenants WHERE user_id = $userId"));
            if ($checkTenant) {
                $tenantId = (int)$checkTenant['tenant_id'];
                mysqli_query($conn, "UPDATE tenants SET full_name = '" . mysqli_real_escape_string($conn, $fullName) . "', 
                    phone = '" . mysqli_real_escape_string($conn, $phone) . "',
                    email = '" . mysqli_real_escape_string($conn, $email) . "' WHERE tenant_id = $tenantId");
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO tenants (user_id, full_name, phone, email, created_by) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isssi', $userId, $fullName, $phone, $email, $userId);
                mysqli_stmt_execute($stmt);
                $tenantId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
            }
            
            // Tạo booking PENDING
            $bookingCode = 'BK' . date('YmdHis') . rand(100, 999);
            $checkIn = $moveInDate;
            $checkOut = $checkOutDate ?: null;
            $note = $message;
            if ($viewDate) {
                $note = "Ngày xem phòng: $viewDate. " . $note;
            }
            
            $roomIdInsert = $roomId > 0 ? $roomId : null;
            $postIdInsert = $postId > 0 ? $postId : null;
            
            $insertSql = "INSERT INTO bookings (booking_code, tenant_id, room_id, post_id, check_in, check_out, adults, deposit_amount, note, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 'PENDING', NOW())";
            $stmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($stmt, 'siiissds', $bookingCode, $tenantId, $roomIdInsert, $postIdInsert, $checkIn, $checkOut, $paymentAmount, $note);
            
            if (mysqli_stmt_execute($stmt)) {
                $bookingId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                // Redirect đến VNPay
                $txnRef = "BOOKING_$bookingId";
                $isDailyRental = ($roomData['rental_type'] ?? 'MONTHLY') === 'DAILY';
                $orderInfo = $isDailyRental ? "Thanh toan phong - $bookingCode" : "Dat coc phong - $bookingCode";
                $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                
                define('VNPAY_BOOKING_RETURN_URL', 'http://localhost/quanlyphongtro/client/pages/vnpay_booking_return.php');
                $vnpayUrl = vnpay_create_booking_payment_url($txnRef, $paymentAmount, $orderInfo, $ipAddr);
                
                header('Location: ' . $vnpayUrl);
                exit;
            }
        }
    }
}

// Resume Payment - Redirect existing PENDING booking to VNPay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resume_payment'])) {
    $isLoggedIn = isset($_SESSION['user_id']) && ($_SESSION['role_name'] ?? '') === 'STUDENT';
    
    if ($isLoggedIn) {
        require_once __DIR__ . '/../admin/includes/vnpay_config.php';
        
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $userId = (int)$_SESSION['user_id'];
        
        // Verify booking belongs to user and is PENDING
        $sql = "SELECT b.*, r.rental_type, r.daily_price, r.base_rent
                FROM bookings b
                LEFT JOIN rooms r ON r.room_id = b.room_id
                JOIN tenants t ON t.tenant_id = b.tenant_id
                WHERE b.booking_id = $bookingId AND t.user_id = $userId AND b.status = 'PENDING'
                LIMIT 1";
        $booking = mysqli_fetch_assoc(mysqli_query($conn, $sql));
        
        if ($booking) {
            // Check if not expired (15 minutes)
            $bookingTime = strtotime($booking['created_at']);
            $expiryTime = $bookingTime + (15 * 60);
            
            if (time() < $expiryTime) {
                $paymentAmount = (float)($booking['deposit_amount'] ?? 0);
                $bookingCode = $booking['booking_code'];
                $isDailyRental = ($booking['rental_type'] ?? 'MONTHLY') === 'DAILY';
                
                $txnRef = "BOOKING_$bookingId";
                $orderInfo = $isDailyRental ? "Thanh toan phong - $bookingCode" : "Dat coc phong - $bookingCode";
                $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                
                if (!defined('VNPAY_BOOKING_RETURN_URL')) {
                    define('VNPAY_BOOKING_RETURN_URL', 'http://localhost/quanlyphongtro/client/pages/vnpay_booking_return.php');
                }
                $vnpayUrl = vnpay_create_booking_payment_url($txnRef, $paymentAmount, $orderInfo, $ipAddr);
                
                header('Location: ' . $vnpayUrl);
                exit;
            } else {
                // Expired - cancel booking
                mysqli_query($conn, "UPDATE bookings SET status = 'CANCELLED', cancelled_at = NOW() WHERE booking_id = $bookingId");
                header('Location: /quanlyphongtro/client/index.php?page=chitiet_datphong&id=' . $bookingId . '&expired=1');
                exit;
            }
        } else {
            header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=invalid');
            exit;
        }
    }
}

// Logout page
if ($page === 'logout') {
    session_destroy();
    header('Location: /quanlyphongtro/client/index.php?page=home');
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/pages/' . $file;
require_once __DIR__ . '/includes/footer.php';
