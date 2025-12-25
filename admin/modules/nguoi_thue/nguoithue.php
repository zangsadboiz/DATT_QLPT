<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $userId <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$qraw = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$like = '%' . $qraw . '%';
$msg = (string)($_GET['msg'] ?? '');

function fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $row = $rs ? (mysqli_fetch_assoc($rs) ?: []) : [];
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Debug counts:
 * - total tenants in system
 * - tenants that are linked to contracts of this landlord
 */
$totalTenantsAll = (int)(fetch_one($conn, "SELECT COUNT(*) AS c FROM tenants")['c'] ?? 0);

$totalTenantsMine = (int)(fetch_one($conn, "
    SELECT COUNT(DISTINCT t.tenant_id) AS c
    FROM tenants t
    JOIN contract_tenants ct ON ct.tenant_id = t.tenant_id
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_user_id = ?
", "i", [$userId])['c'] ?? 0);

// COUNT for pagination (mine only)
$sqlCount = "
    SELECT COUNT(DISTINCT t.tenant_id) AS c
    FROM tenants t
    JOIN contract_tenants ct ON ct.tenant_id = t.tenant_id
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_user_id = ?
";
$typesCount = "i";
$paramsCount = [$userId];

if ($qraw !== '') {
    $sqlCount .= " AND (
        t.full_name LIKE ? OR
        t.phone LIKE ? OR
        t.id_number LIKE ? OR
        t.student_code LIKE ?
    )";
    $typesCount .= "ssss";
    $paramsCount[] = $like;
    $paramsCount[] = $like;
    $paramsCount[] = $like;
    $paramsCount[] = $like;
}

$total = (int)(fetch_one($conn, $sqlCount, $typesCount, $paramsCount)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

// LIST
$sqlList = "
    SELECT
        t.tenant_id,
        t.user_id,
        t.full_name,
        t.phone,
        t.email,
        t.id_number,
        t.student_code,
        u.username AS student_username,
        MAX(c.start_date) AS last_start_date,
        SUM(c.contract_status='ACTIVE') AS active_count
    FROM tenants t
    JOIN contract_tenants ct ON ct.tenant_id = t.tenant_id
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = t.user_id
    WHERE b.owner_user_id = ?
";
$typesList = "i";
$paramsList = [$userId];

if ($qraw !== '') {
    $sqlList .= " AND (
        t.full_name LIKE ? OR
        t.phone LIKE ? OR
        t.id_number LIKE ? OR
        t.student_code LIKE ?
    )";
    $typesList .= "ssss";
    $paramsList[] = $like;
    $paramsList[] = $like;
    $paramsList[] = $like;
    $paramsList[] = $like;
}

$sqlList .= "
    GROUP BY t.tenant_id
    ORDER BY MAX(c.contract_id) DESC
    LIMIT ? OFFSET ?
";
$typesList .= "ii";
$paramsList[] = $perPage;
$paramsList[] = $offset;

$rows = fetch_all($conn, $sqlList, $typesList, $paramsList);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Người thuê (Sinh viên)</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="add.php"><i class="bi bi-plus-circle"></i> Thêm người thuê</a>
  </div>
</div>

<?php if ($msg === 'added'): ?>
  <div class="alert alert-success">Đã thêm/gắn người thuê vào hợp đồng.</div>
<?php endif; ?>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-9">
    <label class="form-label">Tìm kiếm</label>
    <input name="q" class="form-control" value="<?= htmlspecialchars($qraw) ?>"
           placeholder="Tìm theo tên, SĐT, CCCD, mã SV...">
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Tìm</button>
  </div>
</form>

<section class="section">
  <div class="card">
    <div class="card-body">

      <div class="mb-3 text-muted small">
        Tổng tenants trong hệ thống: <strong><?= (int)$totalTenantsAll ?></strong> |
        Tenants thuộc hợp đồng của bạn: <strong><?= (int)$totalTenantsMine ?></strong>
      </div>

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Họ tên</th>
            <th>Mã SV</th>
            <th>Tài khoản</th>
            <th>SĐT</th>
            <th>CCCD</th>
            <th>HĐ hiệu lực</th>
            <th>Gần nhất</th>
            <th style="width:140px;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted">
              Không có dữ liệu người thuê thuộc hợp đồng của bạn.<br>
              Nếu bạn vừa “insert” vào bảng <code>tenants</code> mà chưa thấy: hãy đảm bảo bạn đã gắn vào hợp đồng bằng bảng <code>contract_tenants</code>
              (hoặc dùng nút “Thêm người thuê” để hệ thống tự gắn).
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['tenant_id'] ?></td>
              <td><?= htmlspecialchars((string)($r['full_name'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($r['student_code'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($r['student_username'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($r['phone'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($r['id_number'] ?? '-')) ?></td>
              <td>
                <?php if ((int)($r['active_count'] ?? 0) > 0): ?>
                  <span class="badge bg-success">Có</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Không</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)($r['last_start_date'] ?? '-')) ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary"
                   href="edit.php?tenant_id=<?= (int)$r['tenant_id'] ?>">
                  <i class="bi bi-pencil-square"></i> Sửa
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <?php
          $base = '?q=' . urlencode($qraw) . '&page=';
          $prev = max(1, $page - 1);
          $next = min($totalPages, $page + 1);
        ?>
        <nav>
          <ul class="pagination">
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.$prev ?>">«</a></li>
            <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $base.$p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= $base.$next ?>">»</a></li>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
