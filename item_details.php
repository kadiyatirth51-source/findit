<?php
require_once __DIR__ . '/includes/config.php';

$type = $_GET['type'] ?? 'lost';
if (!valid_type($type)) $type = 'lost';
$table = type_table($type);
$dateCol = $type === 'found' ? 'found_date' : 'lost_date';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT i.*, c.name AS category_name,
        u.id AS owner_id, u.fullname AS owner_name, u.department AS owner_dept, u.created_at AS owner_since
        FROM $table i
        JOIN categories c ON c.id = i.category_id
        JOIN users u ON u.id = i.user_id
        WHERE i.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

$isOwner = !empty($_SESSION['user_id']) && $item && (int)$item['owner_id'] === (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['admin_id']);

if (!$item || ($item['status'] !== 'approved' && !$isOwner && !$isAdmin)) {
    flash_set('danger', 'Item not found or not available.');
    redirect('browse.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (empty($_SESSION['user_id'])) {
        flash_set('warning', 'Please login to contact the ' . ($type === 'lost' ? 'owner' : 'finder') . '.');
        redirect('login.php');
    }
    $message = post_str('message', 2000);
    if ($message === '') {
        $errors[] = 'Please type a message before sending.';
    } elseif ((int)$item['owner_id'] === (int)$_SESSION['user_id']) {
        $errors[] = 'This is your own listing.';
    } else {
        $stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, item_type, item_id, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iisis', $_SESSION['user_id'], $item['owner_id'], $type, $id, $message);
        $stmt->execute();
        notify($conn, (int)$item['owner_id'], 'New message about your ' . $type . ' item "' . $item['item_name'] . '"', 'messages.php?u=' . (int)$_SESSION['user_id'] . '&t=' . $type . '&i=' . $id);
        flash_set('success', 'Your message has been sent to the ' . ($type === 'lost' ? 'owner' : 'finder') . '.');
        redirect('item_details.php?type=' . $type . '&id=' . $id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_returned'])) {
    if (!$isOwner) redirect('item_details.php?type=' . $type . '&id=' . $id);
    $stmt = $conn->prepare("UPDATE $table SET status='returned' WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt = $conn->prepare('SELECT DISTINCT sender_id FROM messages WHERE receiver_id=? AND item_type=? AND item_id=?');
    $stmt->bind_param('isi', $item['owner_id'], $type, $id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        notify($conn, (int)$row['sender_id'], 'The ' . $type . ' item "' . $item['item_name'] . '" has been marked as returned.', 'browse.php');
    }
    flash_set('success', 'Great news! The item has been marked as returned.');
    redirect('my_reports.php');
}

$page_title = $item['item_name'];
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="browse.php">Browse Items</a></li>
      <li class="breadcrumb-item active"><?= e($item['item_name']) ?></li>
    </ol>
  </nav>

  <div class="card border-0 shadow-sm overflow-hidden">
    <div class="row g-0">
      <div class="col-lg-6">
        <?= item_image_html($item['image'], 'uploads/', 'details-img') ?>
      </div>
      <div class="col-lg-6">
        <div class="p-4 p-lg-5 h-100 d-flex flex-column">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="ribbon-static <?= $type === 'lost' ? 'ribbon-lost' : 'ribbon-found' ?>">
              <i class="bi <?= $type === 'lost' ? 'bi-exclamation-triangle' : 'bi-hand-thumbs-up' ?> me-1"></i><?= strtoupper($type) ?>
            </span>
            <?= status_badge($item['status']) ?>
            <span class="badge bg-light text-dark border"><?= e($item['category_name']) ?></span>
          </div>
          <h2 class="fw-bold mb-3"><?= e($item['item_name']) ?></h2>
          <table class="table table-borderless detail-table mb-3">
            <tr><td><i class="bi bi-geo-alt me-2 text-primary"></i>Location</td><td><?= e($item['location']) ?></td></tr>
            <tr><td><i class="bi bi-calendar-event me-2 text-primary"></i>Date</td><td><?= date_fmt($item[$dateCol]) ?></td></tr>
            <tr><td><i class="bi bi-clock-history me-2 text-primary"></i>Posted On</td><td><?= date_fmt($item['created_at']) ?></td></tr>
          </table>
          <h6 class="fw-semibold">Description</h6>
          <p class="text-muted flex-grow-1" style="white-space:pre-line;"><?= nl2br(e($item['description'] ?: 'No description provided.')) ?></p>
<?php include __DIR__ . '/_details_actions.php'; ?>
          <hr class="mt-4">
          <div class="d-flex align-items-center gap-3 small text-muted">
            <?= profile_image_html(null, 36) ?>
            <div>
              Posted by <strong class="text-dark"><?= e($item['owner_name']) ?></strong><br>
              <?= e($item['owner_dept'] ?: 'Student') ?> &middot; Member since <?= date_fmt($item['owner_since']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
