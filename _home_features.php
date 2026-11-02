<?php
$features = [
    ['bi-shield-check', 'Verified Listings', 'Every report is checked by administrators before going live.'],
    ['bi-camera', 'Photo Evidence', 'Upload clear photos so items are easy to recognize.'],
    ['bi-funnel', 'Smart Search', 'Filter by category, location and date to find matches quickly.'],
    ['bi-chat-square-heart', 'Secure Messaging', 'Chat with owners and finders without sharing personal contacts.'],
    ['bi-arrow-repeat', 'Status Tracking', 'Follow each item from reported to returned.'],
    ['bi-phone', 'Fully Responsive', 'Works perfectly on phones, tablets and desktops.'],
];
foreach ($features as [$icon, $title, $desc]): ?>
  <div class="col-md-6 col-lg-4">
    <div class="feature-card h-100 p-4 rounded-4">
      <div class="feature-icon"><i class="bi <?= $icon ?>"></i></div>
      <h6 class="fw-bold mt-3 mb-1"><?= $title ?></h6>
      <p class="small mb-0 opacity-75"><?= $desc ?></p>
    </div>
  </div>
<?php endforeach; ?>
