<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);
$uid = (int)$user['id'];

$ITEM_JOIN = 'LEFT JOIN lost_items li ON m.item_type = \'lost\' AND li.id = m.item_id
              LEFT JOIN found_items fi ON m.item_type = \'found\' AND fi.id = m.item_id';
$ITEM_NAME = 'COALESCE(li.item_name, fi.item_name)';

$threadUser = (int)($_GET['u'] ?? 0);
$threadType = $_GET['t'] ?? '';
$threadItem = (int)($_GET['i'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply = post_str('message', 2000);
    $toUser = (int)($_POST['to_user'] ?? 0);
    $toType = $_POST['to_type'] ?? '';
    $toItem = (int)($_POST['to_item'] ?? 0);
    if ($reply !== '' && valid_type($toType) && $toUser > 0 && $toItem > 0 && $toUser !== $uid) {
        $stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, item_type, item_id, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iisis', $uid, $toUser, $toType, $toItem, $reply);
        $stmt->execute();
        notify($conn, $toUser, 'New message from ' . $user['fullname'] . ' about a ' . $toType . ' item', 'messages.php?u=' . $uid . '&t=' . $toType . '&i=' . $toItem);
        flash_set('success', 'Message sent.');
    }
    redirect('messages.php?u=' . $toUser . '&t=' . $toType . '&i=' . $toItem);
}

$thread = null;
if ($threadUser > 0 && $threadItem > 0 && valid_type($threadType)) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM messages WHERE item_type=? AND item_id=? AND (sender_id=? OR receiver_id=?)");
    $stmt->bind_param('siii', $threadType, $threadItem, $uid, $uid);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_assoc()['c'] > 0) {
        $stmt = $conn->prepare('SELECT id, fullname FROM users WHERE id = ?');
        $stmt->bind_param('i', $threadUser);
        $stmt->execute();
        $other = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("SELECT i.item_name FROM " . type_table($threadType) . " i WHERE i.id = ?");
        $stmt->bind_param('i', $threadItem);
        $stmt->execute();
        $threadItemName = $stmt->get_result()->fetch_assoc()['item_name'] ?? 'Deleted item';

        $stmt = $conn->prepare("SELECT m.*, s.fullname AS sender_name FROM messages m JOIN users s ON s.id=m.sender_id
                                WHERE m.item_type=? AND m.item_id=?
                                  AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
                                ORDER BY m.created_at ASC");
        $stmt->bind_param('siiiii', $threadType, $threadItem, $uid, $threadUser, $threadUser, $uid);
        $stmt->execute();
        $threadMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND item_type=? AND item_id=?");
        $stmt->bind_param('iisi', $uid, $threadUser, $threadType, $threadItem);
        $stmt->execute();

        $thread = ['other' => $other, 'type' => $threadType, 'item' => $threadItem, 'item_name' => $threadItemName, 'messages' => $threadMessages];
    }
}

$inbox = [];
$stmt = $conn->prepare("SELECT m.*, u.fullname AS sender_name, $ITEM_NAME AS item_name
                        FROM messages m JOIN users u ON u.id = m.sender_id $ITEM_JOIN
                        WHERE m.receiver_id = ?
                        ORDER BY m.is_read ASC, m.created_at DESC LIMIT 100");
$stmt->bind_param('i', $uid); $stmt->execute();
$inbox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sent = [];
$stmt = $conn->prepare("SELECT m.*, u.fullname AS receiver_name, $ITEM_NAME AS item_name
                        FROM messages m JOIN users u ON u.id = m.receiver_id $ITEM_JOIN
                        WHERE m.sender_id = ?
                        ORDER BY m.created_at DESC LIMIT 100");
$stmt->bind_param('i', $uid); $stmt->execute();
$sent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Messages';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <?php if ($thread): ?>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-3">
          <a href="messages.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
          <div>
            <h5 class="fw-bold mb-0">Chat with <?= e($thread['other']['fullname']) ?></h5>
            <small class="text-muted">Regarding <?= strtoupper($thread['type']) ?>:
              <a href="item_details.php?type=<?= $thread['type'] ?>&id=<?= $thread['item'] ?>"><?= e($thread['item_name']) ?></a>
            </small>
          </div>
        </div>
        <div class="card border-0 shadow-sm chat-card">
          <div class="card-body chat-body">
            <?php foreach ($thread['messages'] as $m): ?>
              <div class="chat-row <?= (int)$m['sender_id'] === $uid ? 'mine' : '' ?>">
                <div class="chat-bubble <?= (int)$m['sender_id'] === $uid ? 'bubble-mine' : 'bubble-theirs' ?>">
                  <div><?= nl2br(e($m['message'])) ?></div>
                  <div class="chat-time"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer bg-white">
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="to_user" value="<?= $threadUser ?>">
              <input type="hidden" name="to_type" value="<?= e($threadType) ?>">
              <input type="hidden" name="to_item" value="<?= $threadItem ?>">
              <input type="hidden" name="send_reply" value="1">
              <input type="text" name="message" class="form-control" placeholder="Type your reply..." maxlength="2000" required>
              <button type="submit" class="btn btn-accent px-3"><i class="bi bi-send"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <h3 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2"></i>Messages</h3>
    <ul class="nav nav-pills mb-4" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#inboxTab"><i class="bi bi-inbox me-1"></i>Inbox</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#sentTab"><i class="bi bi-send me-1"></i>Sent</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="inboxTab">
        <?php if (!$inbox): ?>
          <div class="card border-0 shadow-sm py-5 text-center"><div class="card-body"><i class="bi bi-inbox display-1 text-secondary opacity-50"></i><p class="text-muted mt-3">No messages yet. Contact an owner or finder from any item page.</p></div></div>
        <?php else: ?>
          <div class="card border-0 shadow-sm">
            <div class="list-group list-group-flush">
              <?php foreach ($inbox as $m): ?>
                <a href="messages.php?u=<?= (int)$m['sender_id'] ?>&t=<?= e($m['item_type']) ?>&i=<?= (int)$m['item_id'] ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3 <?= !$m['is_read'] ? 'unread-row' : '' ?>">
                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between">
                      <strong><?= e($m['sender_name']) ?></strong>
                      <small class="text-muted text-nowrap"><?= date('d M, h:i A', strtotime($m['created_at'])) ?></small>
                    </div>
                    <span class="ribbon-static small <?= $m['item_type'] === 'lost' ? 'ribbon-lost' : 'ribbon-found' ?> me-1"><?= strtoupper($m['item_type']) ?></span>
                    <span class="small text-muted"><?= e($m['item_name'] ?? 'Item removed') ?></span>
                    <div class="small text-truncate mt-1"><?= e($m['message']) ?></div>
                  </div>
                  <?php if (!$m['is_read']): ?><span class="badge bg-danger mt-2">NEW</span><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="tab-pane fade" id="sentTab">
        <?php if (!$sent): ?>
          <div class="card border-0 shadow-sm py-5 text-center"><div class="card-body"><i class="bi bi-send-x display-1 text-secondary opacity-50"></i><p class="text-muted mt-3">You have not sent any messages yet.</p></div></div>
        <?php else: ?>
          <div class="card border-0 shadow-sm">
            <div class="list-group list-group-flush">
              <?php foreach ($sent as $m): ?>
                <a href="messages.php?u=<?= (int)$m['receiver_id'] ?>&t=<?= e($m['item_type']) ?>&i=<?= (int)$m['item_id'] ?>" class="list-group-item list-group-item-action">
                  <div class="d-flex justify-content-between">
                    <strong>To: <?= e($m['receiver_name']) ?></strong>
                    <small class="text-muted"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></small>
                  </div>
                  <span class="ribbon-static small <?= $m['item_type'] === 'lost' ? 'ribbon-lost' : 'ribbon-found' ?> me-1"><?= strtoupper($m['item_type']) ?></span>
                  <span class="small text-muted"><?= e($m['item_name'] ?? 'Item removed') ?></span>
                  <div class="small text-truncate mt-1"><?= e($m['message']) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
