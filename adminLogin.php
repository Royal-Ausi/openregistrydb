<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login | Open Registry</title>
  <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #FCF1E3;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .login-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      max-width: 450px;
      margin: 60px auto;
      padding: 30px;
    }
    .logo-container {
      text-align: center;
      margin-bottom: 25px;
    }
    .logo {
      max-width: 120px;
      height: auto;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .logo:hover {
      transform: scale(1.05);
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
    <div class="logo-container">
      <img src="includes/images/mlhud jpeg 6.jpg" alt="Open Registry Logo" class="logo">
    </div>
    <h3 class="text-center mb-4">Admin Login</h3>
    <!-- login Form -->
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

  <div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="userlogin.php">
        <div class="modal-header">
          <h5 class="modal-title">Create Admin Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="register">
          <input type="hidden" name="account_type" value="admin">
          <div class="mb-3">
            <label><strong>Username</strong></label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>Email</strong></label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>Title</strong></label>
            <select class="form-select" name="title" required>
              <option value="">Choose your title</option>
              <option value="Senior Records Officer">Senior Records Officer</option>
              <option value="Asst Records Officer">Asst Records Officer</option>
            </select>
          </div>
          <div class="mb-3">
            <label><strong>Password</strong></label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label><strong>Confirm Password</strong></label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-custom">Create Account</button>
        </div>
        </form>
    </div>
  </div>

  <!-- change password -->
  <div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="admin_reset_password.php">
        <div class="modal-header">
          <h5 class="modal-title">Change Password</h5>
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
