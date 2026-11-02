  <?php if (!$latest): ?>
    <div class="text-center py-4 empty-state">
      <i class="bi bi-inbox display-1 text-secondary opacity-50"></i>
      <p class="text-muted mt-3">No approved listings yet. Be the first to post one!</p>
      <a href="register.php" class="btn btn-accent">Register Now</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($latest as $item): $dateCol = $item['item_type'] === 'found' ? 'found_date' : 'lost_date'; ?>
        <div class="col-sm-6 col-lg-4">
          <div class="card item-card border-0 shadow-sm h-100">
            <?= item_image_html($item['image']) ?>
            <span class="ribbon <?= $item['item_type'] === 'lost' ? 'ribbon-lost' : 'ribbon-found' ?>">
              <i class="bi <?= $item['item_type'] === 'lost' ? 'bi-exclamation-triangle' : 'bi-hand-thumbs-up' ?> me-1"></i><?= strtoupper($item['item_type']) ?>
            </span>
            <div class="card-body">
              <h6 class="fw-bold mb-1"><?= e($item['item_name']) ?></h6>
              <span class="badge bg-light text-dark border category-chip"><?= e($item['category_name']) ?></span>
              <ul class="list-unstyled small text-muted mt-2 mb-0">
                <li><i class="bi bi-geo-alt me-1"></i><?= e($item['location']) ?></li>
                <li><i class="bi bi-calendar-event me-1"></i><?= date_fmt($item[$dateCol]) ?></li>
              </ul>
            </div>
            <div class="card-footer bg-white border-0 pb-3">
              <a href="item_details.php?type=<?= $item['item_type'] ?>&id=<?= (int)$item['id'] ?>" class="btn btn-outline-primary btn-sm w-100">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="browse.php" class="btn btn-primary px-4">View All Listings <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
  <?php endif; ?>
