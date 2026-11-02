<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$errors = [];
$old = ['item_name' => '', 'description' => '', 'location' => '', 'lost_date' => date('Y-m-d'), 'category_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) { $old[$k] = post_str($k, $k === 'description' ? 2000 : 150); }
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($old['item_name'] === '') $errors[] = 'Item name is required.';
    if ($old['lost_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['lost_date'])) $errors[] = 'Please choose the date you lost the item.';
    if ($old['location'] === '') $errors[] = 'Please mention where you lost the item.';
    if (!$categories || !in_array($categoryId, array_column($categories, 'id'))) $errors[] = 'Please select a valid category.';

    if (!$errors) {
        $image = handle_image_upload($_FILES['image'] ?? [], 'items');
        $status = 'pending';
        $stmt = $conn->prepare('INSERT INTO lost_items (user_id, category_id, item_name, description, location, lost_date, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissssss', $user['id'], $categoryId, $old['item_name'], $old['description'], $old['location'], $old['lost_date'], $image, $status);
        $stmt->execute();
        flash_set('success', 'Your lost item report has been submitted and is awaiting admin approval.');
        redirect('my_reports.php');
    }
}

$page_title = 'Report Lost Item';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="form-icon lost-bg mx-auto"><i class="bi bi-exclamation-triangle"></i></div>
            <h3 class="fw-bold mt-3 mb-1">Report a Lost Item</h3>
            <p class="text-muted mb-0">Fill in the details below. Your report will be visible after admin verification.</p>
          </div>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" class="form-control" value="<?= e($old['item_name']) ?>" placeholder="e.g. Black JBL headphones" required maxlength="150">
              </div>
              <div class="col-md-6">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                  <option value="">-- Select category --</option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $old['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Location Lost <span class="text-danger">*</span></label>
                <input type="text" name="location" class="form-control" value="<?= e($old['location']) ?>" placeholder="e.g. Central Library, 2nd floor" required maxlength="150">
              </div>
              <div class="col-md-6">
                <label class="form-label">Date Lost <span class="text-danger">*</span></label>
                <input type="date" name="lost_date" class="form-control" value="<?= e($old['lost_date']) ?>" max="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" rows="4" class="form-control" maxlength="2000" placeholder="Describe the item: color, brand, contents, any identifying marks..."><?= e($old['description']) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Item Photo</label>
                <input type="file" name="image" accept="image/*" class="form-control image-input" data-preview="#lostPreview">
                <img id="lostPreview" src="" alt="" class="img-preview d-none mt-2 rounded-3 border" style="max-height:160px;">
                <div class="form-text">A clear photo helps others identify your item faster. JPG/PNG/GIF/WEBP, max 3 MB.</div>
              </div>
              <div class="col-12 d-flex gap-2 pt-2">
                <button type="submit" class="btn btn-lost btn-lg"><i class="bi bi-megaphone me-1"></i>Submit Report</button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
