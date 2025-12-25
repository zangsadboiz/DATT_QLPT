<?php
// client/pages/logout.php - Đăng xuất

session_unset();
session_destroy();

header('Location: /quanlyphongtro/client/index.php?page=home');
exit;
