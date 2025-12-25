<?php
// admin/includes/status_vn.php

function vn_building_status(?string $s): string {
    return match($s) {
        'APPROVED' => 'Đã duyệt',
        'PENDING'  => 'Chờ duyệt',
        'HIDDEN'   => 'Đang ẩn',
        default    => 'Không rõ',
    };
}

function vn_publish_status(?string $s): string {
    return match($s) {
        'APPROVED' => 'Đã duyệt',
        'PENDING'  => 'Chờ duyệt',
        'HIDDEN'   => 'Đang ẩn',
        default    => 'Không rõ',
    };
}

function vn_room_status(?string $s): string {
    return match($s) {
        'VACANT'      => 'Phòng trống',
        'OCCUPIED'    => 'Đang thuê',
        'MAINTENANCE' => 'Bảo trì',
        'LOCKED'      => 'Tạm khóa',
        default       => 'Không rõ',
    };
}

function badge_vn(string $text, string $class): string {
    return '<span class="badge '.$class.'">'.$text.'</span>';
}

function badge_building_status(?string $s): string {
    return match($s) {
        'APPROVED' => badge_vn(vn_building_status($s), 'bg-success'),
        'PENDING'  => badge_vn(vn_building_status($s), 'bg-warning text-dark'),
        'HIDDEN'   => badge_vn(vn_building_status($s), 'bg-secondary'),
        default    => badge_vn(vn_building_status($s), 'bg-light text-dark'),
    };
}

function badge_publish_status(?string $s): string {
    return match($s) {
        'APPROVED' => badge_vn(vn_publish_status($s), 'bg-success'),
        'PENDING'  => badge_vn(vn_publish_status($s), 'bg-warning text-dark'),
        'HIDDEN'   => badge_vn(vn_publish_status($s), 'bg-secondary'),
        default    => badge_vn(vn_publish_status($s), 'bg-light text-dark'),
    };
}

function badge_room_status(?string $s): string {
    return match($s) {
        'VACANT'      => badge_vn(vn_room_status($s), 'bg-success'),
        'OCCUPIED'    => badge_vn(vn_room_status($s), 'bg-danger'),
        'MAINTENANCE' => badge_vn(vn_room_status($s), 'bg-warning text-dark'),
        'LOCKED'      => badge_vn(vn_room_status($s), 'bg-secondary'),
        default       => badge_vn(vn_room_status($s), 'bg-light text-dark'),
    };
}

/**
 * Booking status - Luồng: PENDING → CONFIRMED → DEPOSIT_PAID → CHECKED_IN → CHECKED_OUT
 */
function vn_booking_status(?string $s): string {
    return match($s) {
        'PENDING'      => 'Chờ duyệt',
        'CONFIRMED'    => 'Đã duyệt',
        'DEPOSIT_PAID' => 'Đã thanh toán',
        'CHECKED_IN'   => 'Đang thuê',
        'CHECKED_OUT'  => 'Đã trả phòng',
        'CANCELLED'    => 'Đã hủy',
        default        => 'Không rõ',
    };
}

function badge_booking_status(?string $s): string {
    return match($s) {
        'PENDING'      => badge_vn(vn_booking_status($s), 'bg-warning text-dark'),
        'CONFIRMED'    => badge_vn(vn_booking_status($s), 'bg-info'),
        'DEPOSIT_PAID' => badge_vn(vn_booking_status($s), 'bg-primary'),
        'CHECKED_IN'   => badge_vn(vn_booking_status($s), 'bg-success'),
        'CHECKED_OUT'  => badge_vn(vn_booking_status($s), 'bg-secondary'),
        'CANCELLED'    => badge_vn(vn_booking_status($s), 'bg-danger'),
        default        => badge_vn(vn_booking_status($s), 'bg-light text-dark'),
    };
}
