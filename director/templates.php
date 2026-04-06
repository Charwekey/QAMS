<?php
/**
 * Director – Manage Templates
 * Upload, edit, and manage templates for lecturers/departments.
 */
$pageTitle = 'Manage Templates';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$userId = getCurrentUserId();
$error = '';
$success = '';

$validCategories = ['course_delivery', 'assessment', 'reporting', 'policy', 'other'];

// ── Handle actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'upload') {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $category = sanitize($_POST['category'] ?? 'other');

            if (empty($title)) {
                $error = 'Title is required.';
            } elseif (!in_array($category, $validCategories)) {
                $error = 'Invalid category.';
            } else {
                $upload = uploadFile('template_file', 'templates');
                if (!$upload['success']) {
                    $error = $upload['error'];
                } else {
                    dbInsert(
                        "INSERT INTO templates (uploaded_by, title, description, category, file_path, original_name, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, 1)",
                        'isssss',
                        [$userId, $title, $description, $category, $upload['filepath'], $upload['original']]
                    );
                    $success = 'Template uploaded successfully!';
                }
            }
        } elseif ($action === 'toggle') {
            $templateId = intval($_POST['template_id'] ?? 0);
            if ($templateId) {
                $t = dbFetchOne("SELECT is_active FROM templates WHERE id = ?", 'i', [$templateId]);
                if ($t) {
                    $newActive = $t['is_active'] ? 0 : 1;
                    dbExecute("UPDATE templates SET is_active = ? WHERE id = ?", 'ii', [$newActive, $templateId]);
                    $success = $newActive ? 'Template activated.' : 'Template deactivated.';
                }
            }
        } elseif ($action === 'delete') {
            $templateId = intval($_POST['template_id'] ?? 0);
            if ($templateId) {
                dbExecute("DELETE FROM templates WHERE id = ?", 'i', [$templateId]);
                $success = 'Template deleted.';
            }
        }
    }
}

// ── Fetch templates ──────────────────────────────────────
$templates = dbFetchAll(
    "SELECT t.*, u.full_name as uploaded_by_name FROM templates t JOIN users u ON t.uploaded_by = u.id ORDER BY t.created_at DESC",
    '',
    []
);

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
        <h2>Manage Templates</h2>
        <p>Upload and manage templates & guidelines for the university</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Upload New Template</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="upload">

                <div class="form-row">
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required
                            placeholder="e.g. Course Outline Template">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <?php foreach ($categoryLabels as $val => $meta): ?>
                                <option value="<?= $val ?>">
                                    <?= $meta[0] ?>
                                    <?= $meta[1] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="Brief description of what this template is for..."></textarea>
                </div>

                <div class="form-group">
                    <label>File (PDF) <span class="required">*</span></label>
                    <div class="file-upload">
                        <input type="file" name="template_file" accept=".pdf" required>
                        <p>Click to upload or drag & drop</p>
                        <small>PDF only, max 10MB</small>
                    </div>
                </div>

                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">📁 Upload Template</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Templates -->
    <div class="card">
        <div class="card-header">
            <h3>All Templates</h3>
            <span class="text-muted text-sm">
                <?= count($templates) ?> template
                <?= count($templates) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">No templates uploaded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $t):
                            $catInfo = $categoryLabels[$t['category']] ?? ['📁', 'Other'];
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($t['title']) ?>
                                    </strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($t['original_name']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= $catInfo[0] ?>
                                    <?= $catInfo[1] ?>
                                </td>
                                <td>
                                    <span class="badge <?= $t['is_active'] ? 'badge-approved' : 'badge-draft' ?>">
                                        <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= timeAgo($t['created_at']) ?>
                                </td>
                                <td style="display:flex; gap:4px;">
                                    <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($t['file_path']) ?>" target="_blank"
                                        class="btn btn-sm btn-outline">📥</a>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="toggle">
                                        <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-sm <?= $t['is_active'] ? 'btn-secondary' : 'btn-success' ?>">
                                            <?= $t['is_active'] ? '🚫' : '✅' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="Delete this template?">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>