<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);

$type = $_REQUEST['type'] ?? 'lost';
if (!valid_type($type)) { $type = 'lost'; }
$table = type_table($type);
$dateCol = $type === 'found' ? 'found_date' : 'lost_date';
$id = (int)($_REQUEST['id'] ?? 0);

$stmt = $conn->prepare("SELECT i.*, c.name AS category_name FROM $table i JOIN categories c ON c.id = i.category_id WHERE i.id = ? AND i.user_id = ?");
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    flash_set('danger', 'Report not found or you do not have permission to edit it.');
    redirect('my_reports.php');
}
if ($item['status'] === 'returned') {
    flash_set('warning', 'Returned items cannot be edited.');
    redirect('my_reports.php');
}

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = post_str('item_name', 150);
    $description = post_str('description', 2000);
    $location = post_str('location', 150);
    $itemDate = post_str($dateCol, 10);
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($itemName === '') $errors[] = 'Item name is required.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $itemDate)) $errors[] = 'Please choose a valid date.';
    if ($location === '') $errors[] = 'Location is required.';
    if (!in_array($categoryId, array_column($categories, 'id'))) $errors[] = 'Please select a valid category.';

    if (!$errors) {
        $image = handle_image_upload($_FILES['image'] ?? [], 'items');
        if ($image) {
            delete_image($item['image'], 'items');
            $stmt = $conn->prepare("UPDATE $table SET item_name=?, description=?, location=?, $dateCol=?, category_id=?, image=? WHERE id=? AND user_id=?");
            $stmt->bind_param('ssssssii', $itemName, $description, $location, $itemDate, $categoryId, $image, $id, $user['id']);
        } else {
            $stmt = $conn->prepare("UPDATE $table SET item_name=?, description=?, location=?, $dateCol=?, category_id=? WHERE id=? AND user_id=?");
            $stmt->bind_param('sssssii', $itemName, $description, $location, $itemDate, $categoryId, $id, $user['id']);
        }
        $stmt->execute();
        flash_set('success', ucfirst($type) . ' item report updated successfully.');
        redirect('my_reports.php');
    }
} else {
    $itemName = $item['item_name'];
    $description = $item['description'];
    $location = $item['location'];
    $itemDate = $item[$dateCol];
    $categoryId = (int)$item['category_id'];
}

$page_title = 'Edit ' . ucfirst($type) . ' Report';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
          <h3 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Edit <?= e(ucfirst($type)) ?> Report</h3>
          <p class="text-muted">Update the details of your report below.</p>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" class="form-control" value="<?= e($itemName) ?>" required maxlength="150">
              </div>
              <div class="col-md-6">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoryId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Location <span class="text-danger">*</span></label>
                <input type="text" name="location" class="form-control" value="<?= e($location) ?>" required maxlength="150">
              </div>
              <div class="col-md-6">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="<?= e($dateCol) ?>" class="form-control" value="<?= e($itemDate) ?>" max="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" rows="4" class="form-control" maxlength="2000"><?= e($description) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Replace Photo</label>
                <?php if ($item['image']): ?>
                  <img src="uploads/items/<?= e($item['image']) ?>" alt="" class="d-block rounded-3 border mb-2" style="max-height:120px;">
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" class="form-control image-input" data-preview="#editPreview">
                <img id="editPreview" src="" alt="" class="img-preview d-none mt-2 rounded-3 border" style="max-height:160px;">
                <div class="form-text">Leave empty to keep the current photo.</div>
              </div>
              <div class="col-12 d-flex gap-2 pt-2">
                <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                <a href="my_reports.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
