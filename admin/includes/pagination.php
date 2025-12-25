<?php
/**
 * Pagination Helper
 * 
 * Usage:
 * 1. Include this file
 * 2. Call pagination_build_url($params) to build URL with current GET params
 * 3. Call pagination_render($currentPage, $totalPages, $baseUrl) to render pagination UI
 */

/**
 * Build pagination URL preserving current GET parameters
 */
function pagination_build_url(array $overrideParams = []): string {
    $params = array_merge($_GET, $overrideParams);
    return '?' . http_build_query($params);
}

/**
 * Render pagination HTML
 */
function pagination_render(int $currentPage, int $totalPages, int $totalItems, int $perPage = 10): void {
    if ($totalPages <= 1) return;
    
    $startItem = (($currentPage - 1) * $perPage) + 1;
    $endItem = min($currentPage * $perPage, $totalItems);
    ?>
    <nav class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">
            Hiển thị <?= $startItem ?>-<?= $endItem ?> / <?= $totalItems ?> kết quả
        </small>
        <ul class="pagination pagination-sm mb-0">
            <?php if ($currentPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= pagination_build_url(['page' => $currentPage - 1]) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            <?php endif; ?>
            
            <?php
            // Show page numbers with ellipsis
            $showPages = [];
            $showPages[] = 1;
            
            if ($currentPage > 3) $showPages[] = '...';
            
            for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++) {
                if (!in_array($i, $showPages)) $showPages[] = $i;
            }
            
            if ($currentPage < $totalPages - 2) $showPages[] = '...';
            
            if ($totalPages > 1 && !in_array($totalPages, $showPages)) $showPages[] = $totalPages;
            
            foreach ($showPages as $p):
                if ($p === '...'):
            ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
                <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="<?= pagination_build_url(['page' => $p]) ?>"><?= $p ?></a>
                </li>
            <?php 
                endif;
            endforeach;
            ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= pagination_build_url(['page' => $currentPage + 1]) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php
}

/**
 * Calculate pagination params
 */
function pagination_calc(int $totalItems, int $perPage = 10): array {
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = (int)max(1, ceil($totalItems / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'per_page' => $perPage,
        'total_items' => $totalItems
    ];
}
