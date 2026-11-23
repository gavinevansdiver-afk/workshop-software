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

$employee_id = intval($_SESSION['employee_id'] ?? 0);
$workorder_id = intval($_GET['workorder_id'] ?? 0);

if ($workorder_id <= 0 || $employee_id <= 0) {
    startPage("Start Job", true);
    echo "<div class='erp-page-section small-text'>Invalid work order or employee.</div>";
    endPage();
    exit;
}

// ------------------------------------------------------------
// HARD AUTO‑STOP RULE (8 HOURS)
// ------------------------------------------------------------
$MAX_SESSION_HOURS = 8;

if ($stmt = $mysqli->prepare("
    SELECT session_id, start_time
    FROM workordersessions
    WHERE employee_id = ?
    AND end_time IS NULL
    LIMIT 1
")) {
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $active = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($active) {
        $active_session_id = intval($active['session_id']);
        $start_ts = strtotime($active['start_time']);
        $now_ts = time();

        $hours_running = ($now_ts - $start_ts) / 3600;

        if ($hours_running >= $MAX_SESSION_HOURS) {
            if ($stmt2 = $mysqli->prepare("
                UPDATE workordersessions
                SET end_time = FROM_UNIXTIME(?), auto_stopped = 1
                WHERE session_id = ?
            ")) {
                $stmt2->bind_param("ii", $now_ts, $active_session_id);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        else {
            startPage("Start Job", true);
            echo "<div class='erp-page-section small-text'>
                    You already have an active session running.<br>
                    Please stop or switch the session before starting a new job.
                  </div>";
            endPage();
            exit;
        }
    }
}

startPage("Start Job", true);

// ------------------------------------------------------------
// HANDLE SAVE
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $mysqli->prepare("
        INSERT INTO workordersessions (workorder_id, employee_id, start_time)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $workorder_id, $employee_id);
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
            <div class="form-label">Work Order</div>
            <div class="form-value"><?= htmlspecialchars($workorder_id) ?></div>
        </div>

        <div class="form-row">
            <div class="form-label">Mechanic</div>
            <div class="form-value">Employee #<?= htmlspecialchars($employee_id) ?></div>
        </div>

        <div class="form-row">
            <div class="form-label">Start Time</div>
            <div class="form-value">Now (on save)</div>
        </div>

    </div>
</div>

<form method="post">
<?php
bottomButtons([
    [
        'text' => 'Start Job',
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
