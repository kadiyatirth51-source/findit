<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] === 'blocked') {
            $error = 'Your account has been blocked. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            flash_set('success', 'Welcome back, ' . e($user['fullname']) . '!');
            redirect('dashboard.php');
        }
    }
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      <div class="card auth-card shadow border-0">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="auth-icon mx-auto"><i class="bi bi-box-arrow-in-right"></i></div>
            <h3 class="fw-bold mt-3 mb-1">Welcome Back</h3>
            <p class="text-muted">Login to your FindIt account</p>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post" novalidate>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" required>
              </div>
            </div>
            <button type="submit" class="btn btn-accent w-100 btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
          </form>
          <p class="text-center mt-4 mb-0">New here? <a href="register.php" class="fw-semibold">Create an account</a></p>
          <p class="text-center mt-2 mb-0"><a href="admin/login.php" class="small text-muted">Administrator login</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
