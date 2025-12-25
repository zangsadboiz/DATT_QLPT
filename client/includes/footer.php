<?php
// client/includes/footer.php - Sử dụng template Hotelier
$base = '/quanlyphongtro/client/index.php';
$hotelier = '/quanlyphongtro/hotelier-1.0.0';
?>
        <!-- Footer Start -->
        <div class="container-fluid bg-dark text-light footer mt-5 pt-5 wow fadeIn" data-wow-delay="0.1s">
            <div class="container pb-5">
                <div class="row g-5">
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-primary rounded p-4">
                            <a href="<?= $base ?>?page=home"><h1 class="text-white text-uppercase mb-3">Phòng Trọ</h1></a>
                            <p class="text-white mb-0">
                                Hệ thống tìm kiếm và đặt phòng trọ dành cho sinh viên. 
                                Dễ dàng tìm phòng phù hợp với nhu cầu và ngân sách của bạn.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <h6 class="section-title text-start text-primary text-uppercase mb-4">Liên hệ</h6>
                        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>Địa chỉ hỗ trợ</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>0xxx xxx xxx</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i>support@phongtro.vn</p>
                        <div class="d-flex pt-2">
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-12">
                        <div class="row gy-5 g-4">
                            <div class="col-md-6">
                                <h6 class="section-title text-start text-primary text-uppercase mb-4">Menu</h6>
                                <a class="btn btn-link" href="<?= $base ?>?page=home">Trang chủ</a>
                                <a class="btn btn-link" href="<?= $base ?>?page=phong">Phòng trọ</a>
                                <a class="btn btn-link" href="<?= $base ?>?page=lienhe">Liên hệ</a>
                            </div>
                            <div class="col-md-6">
                                <h6 class="section-title text-start text-primary text-uppercase mb-4">Tài khoản</h6>
                                <a class="btn btn-link" href="<?= $base ?>?page=login&type=student">Sinh viên đăng nhập</a>
                                <a class="btn btn-link" href="<?= $base ?>?page=register&type=student">Sinh viên đăng ký</a>
                                <a class="btn btn-link" href="/quanlyphongtro/admin/login.php?type=landlord">Chủ trọ đăng tin</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="copyright">
                    <div class="row">
                        <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                            &copy; <?= date('Y') ?> <a class="border-bottom" href="#">Phòng Trọ Sinh Viên</a>. All Rights Reserved.
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <div class="footer-menu">
                                <a href="<?= $base ?>?page=home">Trang chủ</a>
                                <a href="<?= $base ?>?page=phong">Phòng trọ</a>
                                <a href="<?= $base ?>?page=lienhe">Hỗ trợ</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->

        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- HIDE SPINNER IMMEDIATELY - Before any JS loads -->
    <script>
    (function() {
        var sp = document.getElementById('spinner');
        if (sp) {
            sp.classList.remove('show');
            sp.style.display = 'none';
        }
    })();
    </script>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $hotelier ?>/lib/wow/wow.min.js"></script>
    <script src="<?= $hotelier ?>/lib/easing/easing.min.js"></script>
    <script src="<?= $hotelier ?>/lib/waypoints/waypoints.min.js"></script>
    <script src="<?= $hotelier ?>/lib/counterup/counterup.min.js"></script>
    <script src="<?= $hotelier ?>/lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- Template Javascript -->
    <script src="<?= $hotelier ?>/js/main.js"></script>
    
    <!-- Force hide spinner after all loads -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var sp = document.getElementById('spinner');
        if (sp) {
            sp.classList.remove('show');
            sp.style.display = 'none';
        }
    });
    window.addEventListener('load', function() {
        var sp = document.getElementById('spinner');
        if (sp) {
            sp.classList.remove('show');
            sp.style.display = 'none';
        }
    });
    // Fallback timeout
    setTimeout(function() {
        var sp = document.getElementById('spinner');
        if (sp) {
            sp.classList.remove('show');
            sp.style.display = 'none';
        }
    }, 500);
    </script>
</body>

</html>
