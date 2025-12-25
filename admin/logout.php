<?php
session_start();
session_destroy();
header('Location: /quanlyphongtro/admin/login.php');
exit;
