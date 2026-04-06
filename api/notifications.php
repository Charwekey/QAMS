<?php
/**
 * Notifications Page
 * View and manage notifications for all roles.
 */
$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$userId = getCurrentUserId();

// ── Mark as read ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('error', 'Invalid security token.');
        redirect('api/notifications.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = intval($_POST['notif_id'] ?? 0);
        if ($notifId) {
            dbExecute("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", 'ii', [$notifId, $userId]);
        }
    } elseif ($action === 'mark_all_read') {
        dbExecute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$userId]);
        setFlash('success', 'All notifications marked as read.');
        redirect('api/notifications.php');
    } elseif ($action === 'delete') {
        $notifId = intval($_POST['notif_id'] ?? 0);
        if ($notifId) {
            dbExecute("DELETE FROM notifications WHERE id = ? AND user_id = ?", 'ii', [$notifId, $userId]);
        }
    } elseif ($action === 'clear_all') {
        dbExecute("DELETE FROM notifications WHERE user_id = ? AND is_read = 1", 'i', [$userId]);
        setFlash('success', 'Read notifications cleared.');
        redirect('api/notifications.php');
    }
}

// ── Fetch notifications ─────────────────────────────────
$filter = sanitize($_GET['filter'] ?? '');

$sql = "SELECT * FROM notifications WHERE user_id = ?";
$types = 'i';
$params = [$userId];

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

$sql .= " ORDER BY created_at DESC";
$notifications = dbFetchAll($sql, $types, $params);

$unreadCount = getUnreadNotificationCount($userId);
$totalCount = count($notifications);
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2>Notifications</h2>
            <p><?= $unreadCount ?> unread · <?= $totalCount ?> total</p>
        </div>
        <div style="display:flex; gap:8px;">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-sm btn-primary">✓ Mark All Read</button>
                </form>
            <?php endif; ?>
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-sm btn-secondary" data-confirm="Clear all read notifications?">🗑️
                    Clear Read</button>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Filter:</span>
            <a href="<?= BASE_URL ?>api/notifications.php"
                class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All (<?= $totalCount ?>)</a>
            <a href="<?= BASE_URL ?>api/notifications.php?filter=unread"
                class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-secondary' ?>">🔴 Unread
                (<?= $unreadCount ?>)</a>
            <a href="<?= BASE_URL ?>api/notifications.php?filter=read"
                class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-secondary' ?>">✅ Read</a>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding:48px;">
                <div style="font-size:3rem; margin-bottom:12px;">🔔</div>
                <h3 style="margin-bottom:8px;">No Notifications</h3>
                <p class="text-muted"><?= $filter ? 'No ' . $filter . ' notifications.' : 'You\'re all caught up!' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php foreach ($notifications as $i => $n):
                    $isUnread = !$n['is_read'];
                    ?>
                    <div style="display:flex; align-items:flex-start; gap:12px; padding:16px 20px;
                                border-bottom:1px solid var(--border);
                                <?= $isUnread ? 'background:rgba(79,70,229,0.04);' : '' ?>">
                        <!-- Indicator -->
                        <div style="padding-top:4px;">
                            <?php if ($isUnread): ?>
                                <div style="width:10px; height:10px; border-radius:50%; background:var(--primary);"></div>
                            <?php else: ?>
                                <div style="width:10px; height:10px; border-radius:50%; background:var(--gray-200);"></div>
                            <?php endif; ?>
                        </div>

                        <!-- Content -->
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                <strong style="<?= $isUnread ? 'color:var(--gray-900);' : 'color:var(--gray-600);' ?>">
                                    <?= htmlspecialchars($n['title']) ?>
                                </strong>
                                <small class="text-muted" style="white-space:nowrap;"><?= timeAgo($n['created_at']) ?></small>
                            </div>
                            <p style="margin:4px 0 8px; color:var(--gray-600); font-size:0.9rem;">
                                <?= htmlspecialchars($n['message']) ?>
                            </p>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <?php if ($n['link']): ?>
                                    <a href="<?= BASE_URL . htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-outline">View
                                        →</a>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">✓ Read</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary"
                                        data-confirm="Delete this notification?">🗑️</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>