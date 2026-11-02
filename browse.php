<?php
require_once __DIR__ . '/includes/config.php';

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'lost', 'found'], true)) $type = 'all';
$q = trim((string)($_GET['q'] ?? ''));
$category = (int)($_GET['category'] ?? 0);
$location = trim((string)($_GET['location'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$categories = $conn->query('SELECT id, name FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

function fetch_items(mysqli $conn, string $type, array $filters): array {
    $table = $type === 'found' ? 'found_items' : 'lost_items';
    $dateCol = $type === 'found' ? 'found_date' : 'lost_date';
    $where = ["i.status = 'approved'"];
    $params = '';
    $values = [];

    if ($filters['q'] !== '') {
        $where[] = '(i.item_name LIKE ? OR i.description LIKE ?)';
        $like = '%' . $filters['q'] . '%';
        $params .= 'ss';
        $values[] = $like;
        $values[] = $like;
    }
    if ($filters['category'] > 0) {
        $where[] = 'i.category_id = ?';
        $params .= 'i';
        $values[] = $filters['category'];
    }
    if ($filters['location'] !== '') {
        $where[] = 'i.location LIKE ?';
        $params .= 's';
        $values[] = '%' . $filters['location'] . '%';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
        $where[] = "i.$dateCol >= ?";
        $params .= 's';
        $values[] = $filters['date_from'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
        $where[] = "i.$dateCol <= ?";
        $params .= 's';
        $values[] = $filters['date_to'];
    }

    $sql = "SELECT i.*, c.name AS category_name, '$type' AS item_type FROM $table i
            JOIN categories c ON c.id = i.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC LIMIT 120";
    $stmt = $conn->prepare($sql);
    if ($params !== '') {
        $stmt->bind_param($params, ...$values);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$filters = ['q' => $q, 'category' => $category, 'location' => $location, 'date_from' => $dateFrom, 'date_to' => $dateTo];

$items = [];
if ($type === 'all') {
    $items = array_merge(fetch_items($conn, 'lost', $filters),
                         fetch_items($conn, 'found', $filters));
    usort($items, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
} else {
    $items = fetch_items($conn, $type, $filters);
}

$page_title = 'Browse Items';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h3 class="fw-bold mb-1"><i class="bi bi-funnel me-2"></i>Browse Lost &amp; Found Items</h3>
  <p class="text-muted">Use the filters below to search through all verified listings.</p>

  <div class="card border-0 shadow-sm filter-card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label small fw-semibold">Keyword</label>
          <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search item name / description">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small fw-semibold">Type</label>
          <select name="type" class="form-select">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All items</option>
            <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost only</option>
            <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found only</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small fw-semibold">Category</label>
          <select name="category" class="form-select">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small fw-semibold">Location</label>
          <input type="text" name="location" class="form-control" value="<?= e($location) ?>" placeholder="e.g. Library">
        </div>
        <div class="col-lg-3 col-md-6 d-flex gap-2">
          <div class="flex-fill">
            <label class="form-label small fw-semibold">Date from</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
          </div>
          <div class="flex-fill">
            <label class="form-label small fw-semibold">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
          </div>
        </div>
        <div class="col-lg-9 col-md-6 d-flex gap-2">
          <button type="submit" class="btn btn-accent px-4"><i class="bi bi-search me-1"></i>Search</button>
          <a href="browse.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
      </form>
    </div>
  </div>

  <?php if (!$items): ?>
    <div class="text-center py-5 empty-state">
      <i class="bi bi-inbox display-1 text-secondary opacity-50"></i>
      <h5 class="mt-3 text-muted">No matching items found</h5>
      <p class="text-muted">Try changing your filters or check back later.</p>
    </div>
  <?php else: ?>
    <div class="text-muted mb-3"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> found</div>
    <div class="row g-4">
      <?php foreach ($items as $item): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
          <div class="card item-card border-0 shadow-sm h-100">
            <?= item_image_html($item['image']) ?>
            <span class="ribbon <?= $item['item_type'] === 'lost' ? 'ribbon-lost' : 'ribbon-found' ?>">
              <i class="bi <?= $item['item_type'] === 'lost' ? 'bi-exclamation-triangle' : 'bi-hand-thumbs-up' ?> me-1"></i><?= strtoupper($item['item_type']) ?>
            </span>
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <h6 class="fw-bold mb-1 text-truncate"><?= e($item['item_name']) ?></h6>
                <?= status_badge($item['status']) ?>
              </div>
              <span class="badge bg-light text-dark border category-chip"><?= e($item['category_name']) ?></span>
              <ul class="list-unstyled small text-muted mt-2 mb-0 item-meta">
                <li><i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?></li>
                <li><i class="bi bi-calendar-event me-1"></i><?= date_fmt($item[$item['item_type'] === 'found' ? 'found_date' : 'lost_date']) ?></li>
              </ul>
            </div>
            <div class="card-footer bg-white border-0 pb-3">
              <a href="item_details.php?type=<?= $item['item_type'] ?>&id=<?= (int)$item['id'] ?>" class="btn btn-outline-primary btn-sm w-100">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
