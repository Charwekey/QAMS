<?php
/**
 * Helper / Utility Functions
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// ── Session helpers ──────────────────────────────────────

function startSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn()
{
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId()
{
    startSession();
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole()
{
    startSession();
    return $_SESSION['user_role'] ?? null;
}

function getCurrentSession()
{
    startSession();
    return $_SESSION['session_id'] ?? null;
}

function setSelectedFaculty($facultyId)
{
    startSession();
    $_SESSION['selected_faculty_id'] = $facultyId;
}

function getSelectedFaculty()
{
    startSession();
    return $_SESSION['selected_faculty_id'] ?? null;
}

function setSelectedDepartment($departmentId)
{
    startSession();
    $_SESSION['selected_department_id'] = $departmentId;
}

function getSelectedDepartment()
{
    startSession();
    return $_SESSION['selected_department_id'] ?? null;
}

function clearSelectedFaculty()
{
    startSession();
    unset($_SESSION['selected_faculty_id']);
}

function clearSelectedDepartment()
{
    startSession();
    unset($_SESSION['selected_department_id']);
}

function clearSelectedScope()
{
    clearSelectedFaculty();
    clearSelectedDepartment();
}

// ── Redirect helpers ─────────────────────────────────────

function redirect($path)
{
    header("Location: " . BASE_URL . $path);
    exit;
}

function redirectBack()
{
    $back = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
    header("Location: " . $back);
    exit;
}

// ── Flash message helpers ────────────────────────────────

function setFlash($type, $message)
{
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Security helpers ─────────────────────────────────────

function sanitize($input)
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function csrfToken()
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf()
{
    startSession();
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ── File upload helper ───────────────────────────────────

function uploadFile($fileInput, $subDir = '')
{
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error.'];
    }

    $file = $_FILES[$fileInput];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Only PDF files are allowed.'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File exceeds maximum size of 10MB.'];
    }

    $dir = UPLOAD_DIR . ($subDir ? $subDir . '/' : '');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = uniqid('qams_') . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
    $filepath = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => ($subDir ? $subDir . '/' : '') . $filename,
            'original' => $file['name']
        ];
    }

    return ['success' => false, 'error' => 'Failed to move uploaded file.'];
}

// ── Date/time helpers ────────────────────────────────────

function formatDate($date)
{
    return date('M d, Y', strtotime($date));
}

function formatDateTime($date)
{
    return date('M d, Y h:i A', strtotime($date));
}

function timeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0)
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0)
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0)
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// ── Status helpers ───────────────────────────────────────

function statusLabel($status)
{
    $labels = [
        STATUS_DRAFT => ['Draft', 'badge-draft'],
        STATUS_PENDING_HOD => ['Pending HOD Review', 'badge-pending'],
        STATUS_PENDING_DEAN => ['Pending Dean Review', 'badge-pending'],
        STATUS_PENDING_DIRECTOR => ['Pending Director Review', 'badge-pending'],
        STATUS_APPROVED => ['Approved', 'badge-approved'],
        STATUS_REVERTED_LECTURER => ['Reverted back to Lecturer', 'badge-reverted'],
        STATUS_REVERTED_HOD => ['Reverted back to HOD', 'badge-reverted'],
        STATUS_REVERTED_DEAN => ['Reverted back to Dean', 'badge-reverted'],
    ];
    $info = $labels[$status] ?? ['Unknown', 'badge-draft'];
    return '<span class="badge ' . $info[1] . '">' . $info[0] . '</span>';
}

// ── Notification helper ──────────────────────────────────

function getUnreadNotificationCount($userId)
{
    $row = dbFetchOne(
        "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0",
        'i',
        [$userId]
    );
    return $row['cnt'] ?? 0;
}

function createNotification($userId, $title, $message, $link = '')
{
    dbInsert(
        "INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())",
        'isss',
        [$userId, $title, $message, $link]
    );
}

// ── Academic data helpers ────────────────────────────────

function getFaculties()
{
    return dbFetchAll("SELECT * FROM faculties ORDER BY name");
}

function getDepartments($facultyId = null)
{
    if ($facultyId) {
        return dbFetchAll("SELECT * FROM departments WHERE faculty_id = ? ORDER BY name", 'i', [$facultyId]);
    }
    return dbFetchAll("SELECT d.*, f.name as faculty_name FROM departments d JOIN faculties f ON d.faculty_id = f.id ORDER BY f.name, d.name");
}

function getSessions()
{
    return dbFetchAll("SELECT * FROM sessions ORDER BY year DESC, semester DESC");
}

function getCurrentAcademicSession()
{
    return dbFetchOne("SELECT * FROM sessions WHERE is_current = 1 LIMIT 1");
}
