<?php
/**
 * Lecturer – Templates & Guidelines
 * View and download templates uploaded by the QAMS Director.
 */
$pageTitle = 'Templates & Guidelines';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_LECTURER);

// ── Fetch active templates ────────────────────────────────
$category = sanitize($_GET['category'] ?? '');
$validCategories = ['course_delivery', 'assessment', 'reporting', 'policy', 'other'];

$sql = "SELECT t.*, u.full_name as uploaded_by_name FROM templates t JOIN users u ON t.uploaded_by = u.id WHERE t.is_active = 1";
$types = '';
$params = [];

if ($category && in_array($category, $validCategories)) {
    $sql .= " AND t.category = ?";
    $types = 's';
    $params = [$category];
}

$sql .= " ORDER BY t.created_at DESC";

$templates = dbFetchAll($sql, $types, $params);

// Category labels
$categoryLabels = [
    'course_delivery' => ['📚', 'Course Delivery'],
    'assessment' => ['📝', 'Assessment'],
    'reporting' => ['📊', 'Reporting'],
    'policy' => ['📜', 'Policy'],
    'other' => ['📁', 'Other'],
];
?>

<div class="page-content">
    <div class="page-header">
        <h2>Templates & Guidelines</h2>
        <p>Download templates and guidelines provided by the QAMS Director</p>
    </div>

    <!-- ── Category Filters ─────────────────────────── -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Filter:</span>
            <a href="<?= BASE_URL ?>lecturer/templates.php"
                class="btn btn-sm <?= !$category ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <?php foreach ($categoryLabels as $key => $meta): ?>
                <a href="<?= BASE_URL ?>lecturer/templates.php?category=<?= $key ?>"
                    class="btn btn-sm <?= $category === $key ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $meta[0] ?>
                    <?= $meta[1] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Templates Grid ───────────────────────────── -->
    <?php if (empty($templates)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding:48px;">
                <div style="font-size:3rem; margin-bottom:12px;">📁</div>
                <h3 style="margin-bottom:8px;">No Templates Available</h3>
                <p class="text-muted">
                    <?= $category ? 'No templates found in this category.' : 'The QAMS Director has not uploaded any templates yet.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <?php foreach ($templates as $t):
                $catInfo = $categoryLabels[$t['category']] ?? ['📁', 'Other'];
                ?>
                <div class="card" style="margin-bottom:0;">
                    <div class="card-body">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                            <div style="font-size:2rem;">
                                <?= $catInfo[0] ?>
                            </div>
                            <span class="badge badge-draft">
                                <?= $catInfo[1] ?>
                            </span>
                        </div>
                        <h4 style="margin-bottom:8px;">
                            <?= htmlspecialchars($t['title']) ?>
                        </h4>
                        <?php if ($t['description']): ?>
                            <p class="text-muted" style="font-size:0.8125rem; margin-bottom:12px;">
                                <?= htmlspecialchars(mb_strimwidth($t['description'], 0, 120, '...')) ?>
                            </p>
                        <?php endif; ?>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-top:auto; padding-top:12px; border-top:1px solid var(--border);">
                            <small class="text-muted">
                                <?= timeAgo($t['created_at']) ?>
                                · by
                                <?= htmlspecialchars($t['uploaded_by_name']) ?>
                            </small>
                            <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($t['file_path']) ?>" target="_blank"
                                class="btn btn-sm btn-primary" download>
                                ⬇ Download
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>