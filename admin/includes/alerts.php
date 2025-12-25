<?php
/**
 * Alert Helper Functions
 * Quản lý phòng trọ - Consistent Alert Messages
 */

/**
 * Show success alert
 */
function show_success($message) {
    return show_alert('success', $message, 'check-circle');
}

/**
 * Show error alert  
 */
function show_error($message) {
    return show_alert('danger', $message, 'exclamation-circle');
}

/**
 * Show warning alert
 */
function show_warning($message) {
    return show_alert('warning', $message, 'exclamation-triangle');
}

/**
 * Show info alert
 */
function show_info($message) {
    return show_alert('info', $message, 'info-circle');
}

/**
 * Generic alert function
 */
function show_alert($type, $message, $icon = 'info-circle') {
    $html = sprintf(
        '<div class="alert alert-%s alert-dismissible fade show" role="alert">
            <i class="bi bi-%s me-2"></i>
            <strong>%s</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>',
        htmlspecialchars($type),
        htmlspecialchars($icon),
        htmlspecialchars($message)
    );
    
    return $html;
}

/**
 * Display alerts from GET parameters
 */
function display_get_alerts() {
    if (isset($_GET['success'])) {
        echo show_success($_GET['success']);
    }
    
    if (isset($_GET['error'])) {
        echo show_error($_GET['error']);
    }
    
    if (isset($_GET['warning'])) {
        echo show_warning($_GET['warning']);
    }
    
    if (isset($_GET['info'])) {
        echo show_info($_GET['info']);
    }
    
    // Common message mappings
    $common_messages = [
        'created' => 'Tạo mới thành công!',
        'updated' => 'Cập nhật thành công!',
        'deleted' => 'Xóa thành công!',
        'approved' => 'Duyệt thành công!',
        'rejected' => 'Từ chối thành công!',
        'cancelled' => 'Hủy thành công!',
        'confirmed' => 'Xác nhận thành công!',
        'checkin' => 'Nhận phòng thành công!',
        'checkout' => 'Trả phòng thành công!',
        'not_found' => 'Không tìm thấy dữ liệu!',
        'no_permission' => 'Bạn không có quyền thực hiện hành động này!',
        'invalid_data' => 'Dữ liệu không hợp lệ!',
        'duplicate_entry' => 'Dữ liệu đã tồn tại!',
    ];
    
    if (isset($_GET['msg']) && isset($common_messages[$_GET['msg']])) {
        echo show_success($common_messages[$_GET['msg']]);
    }
    
    if (isset($_GET['err']) && isset($common_messages[$_GET['err']])) {
        echo show_error($common_messages[$_GET['err']]);
    }
}

/**
 * Flash message helper (stores in session)
 */
function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $func = 'show_' . $flash['type'];
        if (function_exists($func)) {
            return $func($flash['message']);
        }
        return show_alert($flash['type'], $flash['message']);
    }
    
    return '';
}
