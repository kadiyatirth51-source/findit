<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    flash_set('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $stmt = $conn->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $nid, $uid); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
        $stmt->bind_param('i', $nid);
        $stmt->execute();
        redirect($row['link'] ?: 'notifications.php');
    }
    redirect('notifications.php');
}

$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->bind_param('i', $uid);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Notifications';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:860px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>Notifications</h3>
    <form method="post">
      <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-all me-1"></i>Mark all as read</button>
    </form>
  </div>
  <?php if (!$notifications): ?>
    <div class="card border-0 shadow-sm py-5 text-center">
      <div class="card-body"><i class="bi bi-bell-slash display-1 text-secondary opacity-50"></i><p class="text-muted mt-3">No notifications yet.</p></div>
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
          <a href="notifications.php?read=<?= (int)$n['id'] ?>"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 <?= !$n['is_read'] ? 'unread-row' : '' ?>">
            <div class="d-flex align-items-center gap-3 min-w-0">
              <i class="bi <?= !$n['is_read'] ? 'bi-envelope-fill text-primary' : 'bi-envelope-open text-secondary' ?> fs-5"></i>
              <span><?= e($n['message']) ?></span>
            </div>
            <small class="text-muted text-nowrap"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
