<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$user = current_user($conn);
$uid = (int)$user['id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = post_str('fullname', 100);
    $email = post_str('email', 150);
    $phone = post_str('phone', 20);
    $department = post_str('department', 100);

    if (mb_strlen($fullname) < 3) $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) $errors[] = 'Enter a valid phone number.';

    if (!$errors && $email !== $user['email']) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->bind_param('si', $email, $uid); $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That email is already used by another account.';
    }

    if (!$errors) {
        $profileImage = handle_image_upload($_FILES['profile_image'] ?? [], 'profiles');
        if ($profileImage) {
            delete_image($user['profile_image'], 'profiles');
            $stmt = $conn->prepare('UPDATE users SET fullname=?, email=?, phone=?, department=?, profile_image=? WHERE id=?');
            $stmt->bind_param('sssssi', $fullname, $email, $phone, $department, $profileImage, $uid);
        } else {
            $stmt = $conn->prepare('UPDATE users SET fullname=?, email=?, phone=?, department=? WHERE id=?');
            $stmt->bind_param('ssssi', $fullname, $email, $phone, $department, $uid);
        }
        $stmt->execute();
        $_SESSION['fullname'] = $fullname;
        flash_set('success', 'Profile updated successfully.');
        redirect('profile.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hash, $uid);
        $stmt->execute();
        flash_set('success', 'Password changed successfully.');
        redirect('profile.php');
    }
}

$page_title = 'My Profile';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h3 class="fw-bold mb-4"><i class="bi bi-person-gear me-2"></i>My Profile</h3>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body p-4">
          <?= profile_image_html($user['profile_image'], 110) ?>
          <h5 class="fw-bold mt-3 mb-0"><?= e($user['fullname']) ?></h5>
          <p class="text-muted small"><?= e($user['department'] ?: 'Student') ?></p>
          <?= status_badge($user['status']) ?>
          <hr>
          <dl class="text-start small mb-0">
            <dt class="text-muted fw-normal">Email</dt><dd><?= e($user['email']) ?></dd>
            <dt class="text-muted fw-normal">Phone</dt><dd><?= e($user['phone'] ?: '-') ?></dd>
            <dt class="text-muted fw-normal">Member Since</dt><dd><?= date_fmt($user['created_at']) ?></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Profile</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="update_profile" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-control" value="<?= e($user['fullname']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Department / Course</label>
                <input type="text" name="department" class="form-control" value="<?= e($user['department']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Change Profile Photo</label>
                <input type="file" name="profile_image" accept="image/*" class="form-control image-input" data-preview="#profPreview">
                <img id="profPreview" src="" alt="" class="d-none mt-2 rounded-circle img-preview" style="width:80px;height:80px;">
                <div class="form-text">Leave empty to keep your current photo.</div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-accent"><i class="bi bi-check2 me-1"></i>Save Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-2 text-warning"></i>Change Password</div>
        <div class="card-body">
          <form method="post" novalidate>
            <input type="hidden" name="change_password" value="1">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" minlength="6" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Update Password</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
