<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['fullname' => '', 'email' => '', 'phone' => '', 'department' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) {
        $old[$k] = post_str($k, 150);
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($old['fullname'] === '' || mb_strlen($old['fullname']) < 3) {
        $errors[] = 'Please enter your full name (at least 3 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $old['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'This email is already registered. Try logging in instead.';
        }
    }

    if (!$errors) {
        $profileImage = handle_image_upload($_FILES['profile_image'] ?? [], 'profiles');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (fullname, email, phone, department, password, profile_image) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $old['fullname'], $old['email'], $old['phone'], $old['department'], $hash, $profileImage);
        $stmt->execute();
        flash_set('success', 'Registration successful! You can now login.');
        redirect('login.php');
    }
}

$page_title = 'Create Account';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
      <div class="card auth-card shadow border-0">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="auth-icon mx-auto"><i class="bi bi-person-plus-fill"></i></div>
            <h3 class="fw-bold mt-3 mb-1">Create Your Account</h3>
            <p class="text-muted">Join the campus Lost &amp; Found community</p>
          </div>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" name="fullname" class="form-control" value="<?= e($old['fullname']) ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                  <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>" placeholder="Optional">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department / Course</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                  <input type="text" name="department" class="form-control" value="<?= e($old['department']) ?>" placeholder="e.g. Computer Science">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" name="confirm_password" class="form-control" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="profile_image" accept="image/*" class="form-control image-input" data-preview="#regPreview">
                <img id="regPreview" src="" alt="" class="img-preview mt-2 d-none rounded-circle" style="width:80px;height:80px;">
                <div class="form-text">JPG/PNG/GIF/WEBP, max 3 MB. Optional.</div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-accent w-100 btn-lg"><i class="bi bi-person-plus me-1"></i>Register</button>
              </div>
            </div>
          </form>
          <p class="text-center mt-4 mb-0">Already have an account? <a href="login.php" class="fw-semibold">Login here</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
