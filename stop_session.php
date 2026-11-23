<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
require_once 'page_setup.php';

// Role enforcement: Admin + Reception + Mechanic
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Admin', 'Reception', 'Mechanic'])) {
    header("Location: access_denied.php");
    exit;
}

$session_id = intval($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    startPage("Stop Job", true);
    echo "<div class='erp-page-section small-text'>Invalid session ID.</div>";
    endPage();
    exit;
}

startPage("Stop Job", true);

// ------------------------------------------------------------
// FETCH SESSION DETAILS
// ------------------------------------------------------------
$stmt = $mysqli->prepare("
    SELECT 
        ws.session_id,
        ws.workorder_id,
        ws.start_time,
        ws.end_time,
        ws.auto_stopped,
        e.name AS employee_name
    FROM workordersessions ws
    LEFT JOIN employees e ON ws.employee_id = e.employee_id
    WHERE ws.session_id = ?
");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    echo "<div class='erp-page-section small-text'>Session not found.</div>";
    endPage();
    exit;
}

// ------------------------------------------------------------
// PREVENT DOUBLE STOPPING
// ------------------------------------------------------------
if (!empty($session['end_time'])) {
    echo "<div class='erp-page-section small-text'>
            This session has already been stopped.
          </div>";
    endPage();
    exit;
}

// ------------------------------------------------------------
// HANDLE STOP SESSION
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $mysqli->prepare("
        UPDATE workordersessions
        SET end_time = NOW()
        WHERE session_id = ?
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>
        if (window.opener && !window.opener.closed) {
            window.opener.location.reload();
        }
        window.close();
    </script>";
    endPage();
    exit;
}
?>

<div class="erp-page-section">
    <div class="form-standard">

        <div class="form-row">
            <div class="form-label">Session ID</div>
            <div class="form-value"><?= htmlspecialchars($session['session_id']) ?></div>
        </div>

        <div class="form-row">
            <div class="form-label">Work Order</div>
            <div class="form-value"><?= htmlspecialchars($session['workorder_id']) ?></div>
        </div>

        <div class="form-row">
            <div class="form-label">Mechanic</div>
            <div class="form-value">
                <?= htmlspecialchars($session['employee_name'] ?? 'Unknown') ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-label">Start Time</div>
            <div class="form-value"><?= htmlspecialchars($session['start_time']) ?></div>
        </div>

        <div class="form-row">
            <div class="form-label">End Time</div>
            <div class="form-value">Now (on save)</div>
        </div>

        <?php if ($session['auto_stopped'] == 1): ?>
        <div class="form-row">
            <div class="form-label">Auto‑Stopped</div>
            <div class="form-value">Yes (Exceeded 8 hours)</div>
        </div>
        <?php endif; ?>

    </div>
</div>

<form method="post">
<?php
bottomButtons([
    [
        'text' => 'Stop Job',
        'type' => 'submit'
    ],
    [
        'text' => 'Cancel',
        'onclick' => 'window.close()'
    ]
]);
?>
</form>

<?php endPage(); ?>
