<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $delType = $_POST['type'] ?? '';
    $delId = (int)($_POST['id'] ?? 0);
    if (valid_type($delType)) {
        $table = type_table($delType);
        $stmt = $conn->prepare("SELECT image FROM $table WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $delId, $uid); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            delete_image($row['image'], 'items');
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $delId, $uid); $stmt->execute();
            flash_set('success', 'Report deleted successfully.');
        }
    }
    redirect('my_reports.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_returned'])) {
    $rType = $_POST['type'] ?? '';
    $rId = (int)($_POST['id'] ?? 0);
    if (valid_type($rType)) {
        $table = type_table($rType);
        $stmt = $conn->prepare("UPDATE $table SET status='returned' WHERE id=? AND user_id=? AND status='approved'");
        $stmt->bind_param('ii', $rId, $uid); $stmt->execute();
        if ($stmt->affected_rows > 0) {
            flash_set('success', 'Item marked as returned.');
        }
    }
    redirect('my_reports.php');
}

function reports_table(mysqli $conn, int $uid, string $type): array {
    $table = type_table($type);
    $dateCol = $type === 'found' ? 'found_date' : 'lost_date';
    $sql = "SELECT i.*, c.name AS category_name FROM $table i
            JOIN categories c ON c.id = i.category_id
            WHERE i.user_id = ? ORDER BY i.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return [$stmt->get_result()->fetch_all(MYSQLI_ASSOC), $dateCol];
}

[$lostItems, $lostDateCol] = reports_table($conn, $uid, 'lost');
[$foundItems, $foundDateCol] = reports_table($conn, $uid, 'found');

$page_title = 'My Reports';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h3 class="fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>My Reports</h3>
  <p class="text-muted">Track the status of everything you have reported.</p>

  <ul class="nav nav-pills mb-4" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#lostTab"><i class="bi bi-exclamation-triangle me-1"></i>Lost (<?= count($lostItems) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#foundTab"><i class="bi bi-hand-thumbs-up me-1"></i>Found (<?= count($foundItems) ?>)</button></li>
  </ul>

  <div class="tab-content">
    <?php foreach ([['lostTab', true, 'lost', $lostItems, $lostDateCol], ['foundTab', false, 'found', $foundItems, $foundDateCol]] as [$tabId, $active, $type, $items, $dateCol]): ?>
      <div class="tab-pane fade <?= $active ? 'show active' : '' ?>" id="<?= $tabId ?>">
        <?php if (!$items): ?>
          <div class="empty-state card border-0 shadow-sm py-5 text-center">
            <div class="card-body">
              <i class="bi bi-inbox display-1 text-secondary opacity-50"></i>
              <h6 class="mt-3 text-muted">You have not reported any <?= $tabId === 'lostTab' ? 'lost' : 'found' ?> items yet.</h6>
              <a href="report_<?= $tabId === 'lostTab' ? 'lost' : 'found' ?>.php" class="btn btn-accent mt-2">Create one now</a>
            </div>
          </div>
        <?php else: ?>
          <div class="card border-0 shadow-sm">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:70px;">Photo</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                    <tr>
                      <td><?= item_image_html($item['image'], 'uploads/', 'thumb-img rounded') ?></td>
                      <td><a href="item_details.php?type=<?= $type ?>&id=<?= (int)$item['id'] ?>" class="fw-semibold text-decoration-none"><?= e($item['item_name']) ?></a></td>
                      <td><span class="badge bg-light text-dark border"><?= e($item['category_name']) ?></span></td>
                      <td class="small"><?= e($item['location']) ?></td>
                      <td class="small"><?= date_fmt($item[$dateCol]) ?></td>
                      <td><?= status_badge($item['status']) ?></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <?php if ($item['status'] !== 'returned'): ?>
                            <a href="edit_item.php?type=<?= $type ?>&id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                          <?php endif; ?>
                          <?php if ($item['status'] === 'approved'): ?>
                            <form method="post" onsubmit="return confirm('Mark this item as returned?');">
                              <input type="hidden" name="type" value="<?= $type ?>">
                              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                              <button type="submit" name="mark_returned" value="1" class="btn btn-sm btn-outline-success" title="Mark as Returned"><i class="bi bi-check2-circle"></i></button>
                            </form>
                          <?php endif; ?>
                          <form method="post" onsubmit="return confirm('Delete this report permanently?');">
                            <input type="hidden" name="type" value="<?= $type ?>">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" name="delete_item" value="1" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php';
