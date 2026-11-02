<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);

$uid = (int)$user['id'];

$counts = ['lost' => 0, 'found' => 0, 'returned' => 0, 'pending' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) c FROM lost_items WHERE user_id=?");
$stmt->bind_param('i', $uid); $stmt->execute();
$counts['lost'] = (int)$stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) c FROM found_items WHERE user_id=?");
$stmt->bind_param('i', $uid); $stmt->execute();
$counts['found'] = (int)$stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("(SELECT COUNT(*) c FROM lost_items WHERE user_id=? AND status='returned') UNION ALL (SELECT COUNT(*) FROM found_items WHERE user_id=? AND status='returned')");
$stmt->bind_param('ii', $uid, $uid); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $counts['returned'] += (int)$row['c']; }

$stmt = $conn->prepare("(SELECT COUNT(*) c FROM lost_items WHERE user_id=? AND status='pending') UNION ALL (SELECT COUNT(*) FROM found_items WHERE user_id=? AND status='pending')");
$stmt->bind_param('ii', $uid, $uid); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $counts['pending'] += (int)$row['c']; }

$notifications = [];
$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$stmt->bind_param('i', $uid); $stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if ($notifications) {
    $conn->query('UPDATE notifications SET is_read = 1 WHERE user_id = ' . $uid . ' AND is_read = 0');
}

$page_title = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="fw-bold mb-0">Hi, <?= e($user['fullname']) ?> <span class="wave">&#128075;</span></h3>
      <p class="text-muted mb-0">Here is an overview of your activity on FindIt.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="report_lost.php" class="btn btn-lost"><i class="bi bi-exclamation-triangle me-1"></i>Report Lost Item</a>
      <a href="report_found.php" class="btn btn-found"><i class="bi bi-hand-thumbs-up me-1"></i>Report Found Item</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card stat-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-search"></i></div>
          <div><div class="fs-4 fw-bold"><?= $counts['lost'] ?></div><div class="text-muted small">Lost Reports</div></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card stat-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-hand-thumbs-up"></i></div>
          <div><div class="fs-4 fw-bold"><?= $counts['found'] ?></div><div class="text-muted small">Found Reports</div></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card stat-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-arrow-repeat"></i></div>
          <div><div class="fs-4 fw-bold"><?= $counts['returned'] ?></div><div class="text-muted small">Items Returned</div></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card stat-card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
          <div><div class="fs-4 fw-bold"><?= $counts['pending'] ?></div><div class="text-muted small">Awaiting Approval</div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</div>
        <div class="card-body">
          <div class="row g-3">
            <?php
            $actions = [
                ['report_lost.php', 'bi-exclamation-triangle', 'Report a Lost Item', 'Post details of something you lost on campus.', 'lost'],
                ['report_found.php', 'bi-hand-thumbs-up', 'Report a Found Item', 'Found something? Help it reach its owner.', 'found'],
                ['browse.php', 'bi-funnel', 'Search &amp; Filter Items', 'Look through all approved listings.', 'browse'],
                ['messages.php', 'bi-chat-dots', 'Messages', 'See conversations with owners and finders.', 'chat'],
            ];
            foreach ($actions as $a): ?>
              <div class="col-sm-6">
                <a href="<?= $a[0] ?>" class="quick-action-card d-block p-3 rounded-3 h-100 <?= $a[4] ?>">
                  <i class="bi <?= $a[1] ?> fs-3"></i>
                  <div class="fw-semibold mt-2"><?= $a[2] ?></div>
                  <div class="small text-muted"><?= $a[3] ?></div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-2 text-primary"></i>Recent Notifications</div>
        <div class="list-group list-group-flush">
          <?php if (!$notifications): ?>
            <div class="list-group-item text-muted py-4 text-center">No notifications yet.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <a href="<?= e($n['link'] ?: '#') ?>" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between gap-2">
                  <span><?= e($n['message']) ?></span>
                  <small class="text-muted text-nowrap"><?= date('d M', strtotime($n['created_at'])) ?></small>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-white text-center">
          <a href="notifications.php" class="small">View all notifications</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
