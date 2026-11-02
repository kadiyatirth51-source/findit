<?php if ($isOwner): ?>
  <div class="alert alert-info py-2 small"><i class="bi bi-person-check me-1"></i>This is your own report.</div>
  <div class="d-flex gap-2">
    <a href="edit_item.php?type=<?= $type ?>&id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?php if ($item['status'] === 'approved'): ?>
      <form method="post" onsubmit="return confirm('Mark this item as returned?');">
        <button type="submit" name="mark_returned" value="1" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Mark as Returned</button>
      </form>
    <?php endif; ?>
  </div>
<?php elseif (!empty($_SESSION['user_id'])): ?>
  <form method="post" novalidate>
    <input type="hidden" name="send_message" value="1">
    <label class="form-label fw-semibold">
      <i class="bi bi-chat-dots me-1 text-primary"></i>Contact the <?= $type === 'lost' ? 'owner' : 'finder' ?> &mdash; <?= e($item['owner_name']) ?>
    </label>
    <?php if ($errors): ?><div class="alert alert-danger py-2 small"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <textarea name="message" rows="3" class="form-control mb-2" maxlength="2000"
      placeholder="Hi! I think this <?= e($item['item_name']) ?> belongs to me / I have more details..." required></textarea>
    <button type="submit" class="btn btn-accent w-100"><i class="bi bi-send me-1"></i>Send Message</button>
  </form>
<?php else: ?>
  <a href="login.php" class="btn btn-accent w-100 btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Login to contact the <?= $type === 'lost' ? 'owner' : 'finder' ?></a>
<?php endif; ?>
