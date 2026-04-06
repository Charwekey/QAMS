<?php
/**
 * HOD – Graduate Output Data
 * Record graduation statistics by programme type and gender.
 */
$pageTitle = 'Graduate Output';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_HOD);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);
$deptId = $dept['id'] ?? 0;

$error = '';
$success = '';

// Get department programmes
$programmes = dbFetchAll(
    "SELECT * FROM programmes WHERE department_id = ? ORDER BY type, name",
    'i',
    [$deptId]
);

// ── Handle form submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $programmeId = intval($_POST['programme_id'] ?? 0);
        $graduationYear = intval($_POST['graduation_year'] ?? 0);
        $progType = sanitize($_POST['programme_type'] ?? '');

        if (!$programmeId || !$graduationYear || !$progType) {
            $error = 'Please fill in all required fields.';
        } else {
            // Check if record already exists
            $existing = dbFetchOne(
                "SELECT id FROM graduate_output WHERE hod_id = ? AND programme_id = ? AND session_id = ? AND graduation_year = ?",
                'iiii',
                [$userId, $programmeId, $sessionId ?: 0, $graduationYear]
            );

            // Common fields
            $fields = [
                'distinction_male' => intval($_POST['distinction_male'] ?? 0),
                'distinction_female' => intval($_POST['distinction_female'] ?? 0),
                'pass_male' => intval($_POST['pass_male'] ?? 0),
                'pass_female' => intval($_POST['pass_female'] ?? 0),
            ];

            // Bachelor-specific
            if ($progType === 'bachelor') {
                $fields = array_merge($fields, [
                    'first_class_male' => intval($_POST['first_class_male'] ?? 0),
                    'first_class_female' => intval($_POST['first_class_female'] ?? 0),
                    'second_upper_male' => intval($_POST['second_upper_male'] ?? 0),
                    'second_upper_female' => intval($_POST['second_upper_female'] ?? 0),
                    'second_lower_male' => intval($_POST['second_lower_male'] ?? 0),
                    'second_lower_female' => intval($_POST['second_lower_female'] ?? 0),
                    'third_class_male' => intval($_POST['third_class_male'] ?? 0),
                    'third_class_female' => intval($_POST['third_class_female'] ?? 0),
                ]);
            }

            if ($existing) {
                // Update
                $setClauses = [];
                $updateTypes = '';
                $updateParams = [];
                foreach ($fields as $col => $val) {
                    $setClauses[] = "$col = ?";
                    $updateTypes .= 'i';
                    $updateParams[] = $val;
                }
                $updateTypes .= 'i';
                $updateParams[] = $existing['id'];
                dbExecute(
                    "UPDATE graduate_output SET " . implode(', ', $setClauses) . " WHERE id = ?",
                    $updateTypes,
                    $updateParams
                );
                $success = 'Graduate output data updated!';
            } else {
                // Insert
                $cols = ['hod_id', 'programme_id', 'session_id', 'graduation_year', 'programme_type'];
                $vals = [$userId, $programmeId, $sessionId ?: 0, $graduationYear, $progType];
                $insTypes = 'iiiis';
                $placeholders = ['?', '?', '?', '?', '?'];

                foreach ($fields as $col => $val) {
                    $cols[] = $col;
                    $vals[] = $val;
                    $insTypes .= 'i';
                    $placeholders[] = '?';
                }

                dbInsert(
                    "INSERT INTO graduate_output (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")",
                    $insTypes,
                    $vals
                );
                $success = 'Graduate output data saved!';
            }
        }
    }
}

// ── Fetch existing records ────────────────────────────────
$records = dbFetchAll(
    "SELECT g.*, p.name as programme_name, p.type as prog_type
     FROM graduate_output g
     JOIN programmes p ON g.programme_id = p.id
     WHERE g.hod_id = ?
     ORDER BY g.graduation_year DESC, p.name",
    'i',
    [$userId]
);
?>

<div class="page-content">
    <div class="page-header">
        <h2>Graduate Output Data</h2>
        <p>Record graduation statistics by programme, gender, and classification</p>
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

    <!-- Entry Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Enter Graduate Output</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate id="gradForm">
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Programme <span class="required">*</span></label>
                        <select name="programme_id" id="programmeSelect" class="form-control" required>
                            <option value="">Select programme</option>
                            <?php foreach ($programmes as $p): ?>
                                <option value="<?= $p['id'] ?>" data-type="<?= $p['type'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (
                                    <?= ucfirst($p['type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="programme_type" id="programmeType" value="">
                    </div>
                    <div class="form-group">
                        <label>Graduation Year <span class="required">*</span></label>
                        <input type="number" name="graduation_year" class="form-control" min="2020" max="2030"
                            value="<?= date('Y') ?>" required>
                    </div>
                </div>

                <!-- Grade Fields - Diploma / Postgraduate -->
                <div id="diplomaFields" style="display:none;">
                    <h4 style="margin:16px 0 12px;">Classification Breakdown</h4>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Classification</th>
                                    <th>Male</th>
                                    <th>Female</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Distinction</strong></td>
                                    <td><input type="number" name="distinction_male" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                    <td><input type="number" name="distinction_female" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Pass</strong></td>
                                    <td><input type="number" name="pass_male" class="form-control" min="0" value="0"
                                            style="width:80px;"></td>
                                    <td><input type="number" name="pass_female" class="form-control" min="0" value="0"
                                            style="width:80px;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grade Fields - Bachelor -->
                <div id="bachelorFields" style="display:none;">
                    <h4 style="margin:16px 0 12px;">Classification Breakdown</h4>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Classification</th>
                                    <th>Male</th>
                                    <th>Female</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>First Class</strong></td>
                                    <td><input type="number" name="first_class_male" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                    <td><input type="number" name="first_class_female" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Second Upper</strong></td>
                                    <td><input type="number" name="second_upper_male" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                    <td><input type="number" name="second_upper_female" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Second Lower</strong></td>
                                    <td><input type="number" name="second_lower_male" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                    <td><input type="number" name="second_lower_female" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Third Class</strong></td>
                                    <td><input type="number" name="third_class_male" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                    <td><input type="number" name="third_class_female" class="form-control" min="0"
                                            value="0" style="width:80px;"></td>
                                </tr>
                                <tr>
                                    <td><strong>Pass</strong></td>
                                    <td><input type="number" name="pass_male" class="form-control" min="0" value="0"
                                            style="width:80px;"></td>
                                    <td><input type="number" name="pass_female" class="form-control" min="0" value="0"
                                            style="width:80px;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top:16px; text-align:right;">
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;">🎓 Save Graduate
                        Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Records -->
    <div class="card">
        <div class="card-header">
            <h3>Saved Records</h3>
            <span class="text-muted text-sm">
                <?= count($records) ?> record
                <?= count($records) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Programme</th>
                        <th>Type</th>
                        <th>Year</th>
                        <th>Total Male</th>
                        <th>Total Female</th>
                        <th>Grand Total</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding:32px;">
                                No graduate output records yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r):
                            $totalMale = $r['distinction_male'] + $r['pass_male'] + $r['first_class_male']
                                + $r['second_upper_male'] + $r['second_lower_male'] + $r['third_class_male'];
                            $totalFemale = $r['distinction_female'] + $r['pass_female'] + $r['first_class_female']
                                + $r['second_upper_female'] + $r['second_lower_female'] + $r['third_class_female'];
                            ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($r['programme_name']) ?>
                                    </strong></td>
                                <td><span class="badge badge-draft">
                                        <?= ucfirst($r['prog_type']) ?>
                                    </span></td>
                                <td>
                                    <?= $r['graduation_year'] ?>
                                </td>
                                <td>
                                    <?= $totalMale ?>
                                </td>
                                <td>
                                    <?= $totalFemale ?>
                                </td>
                                <td><strong>
                                        <?= $totalMale + $totalFemale ?>
                                    </strong></td>
                                <td>
                                    <?= timeAgo($r['updated_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Dynamic form fields based on programme type
    document.getElementById('programmeSelect').addEventListener('change', function () {
        var selected = this.options[this.selectedIndex];
        var type = selected.getAttribute('data-type') || '';
        document.getElementById('programmeType').value = type;

        document.getElementById('diplomaFields').style.display = 'none';
        document.getElementById('bachelorFields').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'none';

        if (type === 'diploma' || type === 'postgraduate') {
            document.getElementById('diplomaFields').style.display = 'block';
            document.getElementById('submitBtn').style.display = 'inline-block';
        } else if (type === 'bachelor') {
            document.getElementById('bachelorFields').style.display = 'block';
            document.getElementById('submitBtn').style.display = 'inline-block';
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>