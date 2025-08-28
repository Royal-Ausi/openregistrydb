
<!-- filepath: c:\xampp\htdocs\openregistrydb\adminLogin.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #F7E5D9;
      font-family: Arial, sans-serif;
    }
    .login-container {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 6px #5f5f5fff;
      max-width: 400px;
      margin: 60px auto;
      padding: 30px;
    }
    .btn-custom {
      background-color: #b57526ff;
      color: #fff;
      font-weight: bold;
      border-radius: 6px;
      box-shadow: 0 2px 6px #5f5f5fff;
      transition: background 0.2s;
      }
    .btn-custom:hover, .btn-custom:focus {
      background-color: #a05f1d;
      color: #fff;
    }
    .link {
      color: #b57526ff;
      cursor: pointer;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h3 class="text-center mb-4">Admin Login</h3>
    <!-- Login Form -->
    <form method="POST" action="admin_login.php">
      <div class="mb-3">
        <label><strong>Username</strong></label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label><strong>Password</strong></label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-custom w-100">Login</button>
    </form>
    <div class="mt-3 text-center">
      <span class="link" data-bs-toggle="modal" data-bs-target="#registerModal">Create Admin Account</span>
      <span class="mx-2">|</span>
      <span class="link" data-bs-toggle="modal" data-bs-target="#resetModal">Forgot/Change Password?</span>
    </div>
  </div>

  <!-- Create Admin Modal -->
  <div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="admin_register.php">
        <div class="modal-header">
          <h5 class="modal-title">Create Admin Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label><strong>Username</strong></label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>Email</strong></label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>Password</strong></label>
            <input type="password" name="password" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-custom">Create Account</button>
        </div>
        </form>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="admin_reset_password.php">
        <div class="modal-header">
          <h5 class="modal-title">Reset/Change Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label><strong>Username or Email</strong></label>
            <input type="text" name="user_identifier" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>New Password</strong></label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-custom">Change Password</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
