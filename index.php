<?php
require_once __DIR__ . '/includes/config.php';

$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM lost_items WHERE status='approved') AS lost,
    (SELECT COUNT(*) FROM found_items WHERE status='approved') AS found,
    (SELECT COUNT(*) FROM lost_items WHERE status='returned') + (SELECT COUNT(*) FROM found_items WHERE status='returned') AS returned,
    (SELECT COUNT(*) FROM users) AS users")->fetch_assoc();

$latest = [];
foreach (['lost', 'found'] as $t) {
    $table = type_table($t);
    $stmt = $conn->prepare("SELECT i.*, c.name AS category_name, '$t' AS item_type
                            FROM $table i JOIN categories c ON c.id=i.category_id
                            WHERE i.status='approved'
                            ORDER BY i.created_at DESC LIMIT 3");
    $stmt->execute();
    $latest = array_merge($latest, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}
usort($latest, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$latest = array_slice($latest, 0, 6);

$page_title = 'Lost & Found Campus Portal';
include __DIR__ . '/includes/header.php';
?>
<section class="hero-section text-white">
  <div class="container py-5">
    <div class="row align-items-center g-5 py-lg-4">
      <div class="col-lg-6">
        <span class="badge rounded-pill hero-badge mb-3"><i class="bi bi-stars me-1"></i>Campus Lost &amp; Found Portal</span>
        <h1 class="display-4 fw-bold">Lost something on campus?<br><span class="hero-highlight">We help you find it.</span></h1>
        <p class="lead mt-4 opacity-90">FindIt connects students who lost belongings with those who found them.
          Report an item in seconds, browse verified listings, and get your things back fast.</p>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="report_lost.php" class="btn btn-accent btn-lg px-4"><i class="bi bi-exclamation-triangle me-2"></i>Report Lost Item</a>
            <a href="browse.php" class="btn btn-outline-light btn-lg px-4"><i class="bi bi-search me-2"></i>Browse Items</a>
          <?php else: ?>
            <a href="register.php" class="btn btn-accent btn-lg px-4"><i class="bi bi-person-plus me-2"></i>Get Started Free</a>
            <a href="browse.php" class="btn btn-outline-light btn-lg px-4"><i class="bi bi-search me-2"></i>Browse Items</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row g-3">
          <?php
          $heroCards = [
              ['Approved Lost Posts', (int)$stats['lost'], 'bi-exclamation-triangle', 'lost-tile'],
              ['Approved Found Posts', (int)$stats['found'], 'bi-hand-thumbs-up', 'found-tile'],
              ['Items Returned', (int)$stats['returned'], 'bi-arrow-repeat', 'returned-tile'],
              ['Registered Students', (int)$stats['users'], 'bi-people', 'users-tile'],
          ];
          foreach ($heroCards as [$label, $value, $icon, $cls]): ?>
            <div class="col-6">
              <div class="card hero-stat-card border-0 <?= $cls ?> h-100">
                <div class="card-body text-center p-4">
                  <i class="bi <?= $icon ?> fs-1"></i>
                  <div class="fs-2 fw-bold mt-2"><?= $value ?></div>
                  <div class="small opacity-75"><?= $label ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container py-5">
  <div class="row g-4">
    <?php
    $steps = [
        [1, 'Report It', 'Lost something? Found something? Post the details with a photo, location and date.'],
        [2, 'Get Verified', 'Admins review every listing to keep the portal genuine and spam-free.'],
        [3, 'Reunite', 'Contact the owner or finder securely through messages and track the item status.'],
    ];
    foreach ($steps as [$num, $title, $desc]): ?>
      <div class="col-md-4">
        <div class="card how-card border-0 shadow-sm h-100">
          <div class="card-body text-center p-4">
            <div class="step-num mx-auto"><?= $num ?></div>
            <h5 class="fw-bold mt-3"><?= $title ?></h5>
            <p class="text-muted small mb-0"><?= $desc ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="container pb-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold">Latest Verified Listings</h2>
    <p class="text-muted">Fresh lost &amp; found reports from around campus</p>
  </div>
<?php include __DIR__ . '/_home_items.php'; ?>
</div>

<section class="features-strip py-5">
  <div class="container">
    <div class="text-center text-white mb-5">
      <h2 class="fw-bold">Why Use FindIt?</h2>
      <p class="opacity-75">Everything you need to recover lost belongings on campus</p>
    </div>
    <div class="row g-4">
<?php include __DIR__ . '/_home_features.php'; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
