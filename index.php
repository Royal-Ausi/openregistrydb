<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
include 'includes/db.php';

// get system statistics
$stats = [];
try {
  // total records
  $result = $conn->query("SELECT COUNT(*) as total FROM master_register");
  $stats['total_records'] = $result->fetch_assoc()['total'];

  // active users
  $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
  $stats['active_users'] = $result->fetch_assoc()['total'];

  // recent activity
  $result = $conn->query("SELECT COUNT(*) as total FROM master_register WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
  $stats['recent_activity'] = $result->fetch_assoc()['total'];
} catch (Exception $e) {
  $stats = ['total_records' => 0, 'active_users' => 0, 'recent_activity' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Open Registry Database System | MLHUD</title>
  <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #E08D3C;
      --primary-text: #FCF1E3;
      --primary-hover: #c73e24;
      --secondary-color: #FCF1E3;
      --background-color: #FCF1E3;
      --text-color: #333;
      --border-color: #ddd;
      --accent-color: #b57526ff;
      --success-color: #28a745;
      --warning-color: #ffc107;
      --info-color: #17a2b8;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--background-color);
      color: var(--text-color);
      margin: 0;
      padding: 0;
    }

    .hero-section {
      background-color: var(--primary-color);
      color: var(--primary-text);
      padding: 80px 0;
      text-align: center;
      position: relative;
    }

    .hero-content {
      position: relative;
    }

    .hero-logo {
      max-width: 150px;
      height: auto;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      margin-bottom: 30px;
      transition: transform 0.3s ease;
    }

    .hero-logo:hover {
      transform: scale(1.05);
    }

    .hero-title {
      font-size: 3.5rem;
      font-weight: bold;
      margin-bottom: 20px;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .hero-subtitle {
      font-size: 1.3rem;
      margin-bottom: 40px;
      opacity: 0.9;
    }

    /* access cards */
    .access-cards {
      padding: 80px 0;
      background-color: white;
    }

    .access-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      padding: 50px 40px;
      margin-bottom: 30px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 2px solid transparent;
      text-align: center;
    }

    .access-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .access-card.user-card:hover {
      border-color: var(--info-color);
    }

    .access-card.admin-card:hover {
      border-color: var(--warning-color);
    }

    .card-icon {
      font-size: 3rem;
      margin-bottom: 20px;
      display: block;
    }

    .user-card .card-icon {
      color: var(--info-color);
    }

    .admin-card .card-icon {
      color: var(--warning-color);
    }

    .card-title {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 15px;
      color: var(--text-color);
    }

    .card-description {
      font-size: 1.1rem;
      color: #666;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .features-list {
      list-style: none;
      padding: 0;
      margin-bottom: 30px;
    }

    .features-list li {
      padding: 8px 0;
      color: #555;
      position: relative;
      padding-left: 25px;
    }

    .features-list li::before {
      content: '✓';
      position: absolute;
      left: 0;
      color: var(--success-color);
      font-weight: bold;
    }

    /* access buttons */
    .btn-access {
      background-color: var(--primary-color);
      border: none;
      color: white;
      padding: 15px 40px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .btn-access:hover {
      background-color: var(--primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(232, 73, 42, 0.4);
      color: white;
    }

    .btn-access.user-btn {
      background-color: var(--info-color);
    }

    .btn-access.user-btn:hover {
      background-color: #0b5ed7;
    }

    .btn-access.admin-btn {
      background-color: var(--accent-color);
    }

    .btn-access.admin-btn:hover {
      background-color: #9d5f1a;
    }

    /* modal Styles */
    .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      background-color: var(--primary-color);
      color: var(--primary-text);
      border-radius: 15px 15px 0 0;
      border: none;
    }

    .modal-header .btn-close {
      filter: invert(1);
    }

    .login-form {
      padding: 30px;
    }

    .form-control {
      border-radius: 8px;
      border: 2px solid var(--border-color);
      padding: 12px 15px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(232, 73, 42, 0.25);
    }

    .btn-login {
      background-color: var(--primary-color);
      border: none;
      color: white;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      width: 100%;
      transition: all 0.3s ease;
    }

    .btn-login:hover {
      background-color: var(--primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(232, 73, 42, 0.4);
    }

    .btn-admin-login {
      background-color: var(--accent-color);
    }

    .btn-admin-login:hover {
      background-color: #9d5f1a;
    }

    /* user Login Styles */
    .login-box {
      max-width: 450px;
      margin: auto;
      background-color: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .logo-container {
      text-align: center;
      margin-bottom: 25px;
    }

    .logo {
      max-width: 120px;
      height: auto;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .logo:hover {
      transform: scale(1.05);
    }

    .custom-login-btn {
      background-color: #E08D3C !important;
      color: white !important;
      border: none;
      width: 100%;
    }

    .password-wrapper {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #E08D3C;
      font-size: 1.2em;
      cursor: pointer;
      padding: 0;
    }

    /* admin Login Styles */
    .login-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      max-width: 450px;
      margin: 60px auto;
      padding: 30px;
    }

    .btn-custom {
      background-color: #E08D3C;
      color: #fff;
      font-weight: bold;
      border-radius: 6px;
      box-shadow: 0 2px 6px #5f5f5fff;
      transition: background 0.2s;
    }

    .btn-custom:hover,
    .btn-custom:focus {
      background-color: #E08D3C;
      color: #fff;
    }

    .link {
      color: #b57526ff;
      cursor: pointer;
      text-decoration: underline;
    }


    /* Footer */
    .footer {
      background-color: #2c3e50;
      color: white;
      padding: 40px 0;
      text-align: center;
    }

    .footer-logo {
      max-width: 100px;
      height: auto;
      margin-bottom: 20px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .hero-title {
        font-size: 2.5rem;
      }

      .hero-subtitle {
        font-size: 1.1rem;
      }

      .access-card {
        padding: 30px 20px;
      }

      .card-title {
        font-size: 1.5rem;
      }
    }

    /* Tab Navigation */
    .tab-nav {
      border-bottom: 2px solid var(--border-color);
      margin-bottom: 30px;
    }

    .tab-nav .nav-link {
      color: #666;
      border: none;
      padding: 15px 30px;
      font-weight: 600;
      border-radius: 0;
      transition: all 0.3s ease;
    }

    .tab-nav .nav-link.active {
      color: var(--primary-color);
      border-bottom: 3px solid var(--primary-color);
      background: none;
    }

    .tab-nav .nav-link:hover {
      color: var(--primary-color);
      background: rgba(232, 73, 42, 0.1);
    }

    /* Error Messages */
    .alert {
      border-radius: 8px;
      border: none;
      margin-top: 15px;
    }

    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
    }
  </style>
</head>

<body>
  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <div class="hero-content">
        <img src="includes/images/mlhud jpeg 6.jpg" alt="MLHUD Logo" class="hero-logo">
        <h1 class="hero-title">Open Registry Database System</h1>
        <p class="hero-subtitle">Streamlined Records Management for Office Operations</p>
      </div>
    </div>
  </section>

  <!-- access cards section -->
  <section class="access-cards">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="access-card user-card">
            <h3 class="card-title">Open Registry Access</h3>
            <p class="card-description">Login in to manage or view registry records.</p>
            <ul>
              <li>Master Register Management</li>
              <li>Contract Staff and Pensioners Register</li>
              <li>Semi-Current Records</li>
              <li>File Diary & Subject Index</li>

            </ul>
            <button type="button" class="btn btn-access user-btn" onclick="openAuthModal()">
              <i class="fas fa-sign-in-alt"></i> Access Portal
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- unified authentication -->
  <div class="modal fade" id="authModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Login or Sign up</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-center gap-2 mb-3">
            <button type="button" id="loginToggle" class="btn btn-sm btn-outline-primary active" onclick="setAuthMode('login')">Login</button>
            <button type="button" id="registerToggle" class="btn btn-sm btn-outline-primary" onclick="setAuthMode('register')">Sign up</button>
          </div>

          <?php if (!empty($_SESSION['auth_error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['auth_error']) ?></div>
            <?php unset($_SESSION['auth_error']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['session_expired'])): ?>
            <div class="alert alert-warning"> Session timeout, Please login again.</div>
            <?php unset($_SESSION['session_expired']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['auth_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['auth_success']) ?></div>
            <?php unset($_SESSION['auth_success']); ?>
          <?php endif; ?>

          <form method="POST" action="userlogin.php" id="authForm">
            <input type="hidden" name="action" id="authAction" value="login">
            <input type="hidden" name="account_type" id="authAccountType" value="admin">

            <div id="loginFields">
              <!-- <p id="loginNotice" class="form-text text-muted mb-3">Sign in with your assigned username or email.</p> -->
              <div class="mb-3">
                <label><strong>Username or Email</strong></label>
                <input type="text" name="username_or_email" id="authUsernameEmail" class="form-control" required>
              </div>
              <div class="mb-3">
                <label><strong>Password</strong></label>
                <div class="password-wrapper">
                  <input type="password" name="password" id="authPasswordInput" class="form-control" required>
                  <button type="button" class="toggle-password" tabindex="-1" onclick="toggleAuthPassword()">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>

            <div id="registerFields" style="display: none;">
              <div class="mb-3">
                <div id="registerNotice" class="form-text text-muted">
                  Only Admin Accounts Sign up Here.</div>
              </div>
              <div class="mb-3">
                <label><strong>Email</strong></label>
                <input type="email" name="email" id="registerEmail" class="form-control">
              </div>
              <div class="mb-3">
                <label><strong>Username</strong></label>
                <input type="text" name="username" id="registerUsername" class="form-control">
              </div>
              <div class="mb-3">
                <label><strong>Title</strong></label>
                <select class="form-select" name="title" id="authTitle">
                  <option value="">Choose your title</option>
                  <option value="Senior Records Officer">Senior Records Officer</option>
                  <option value="Asst Records Officer">Asst Records Officer</option>
                </select>
              </div>
              <div class="mb-3">
                <label><strong>Password</strong></label>
                <div class="password-wrapper">
                  <input type="password" name="password" id="registerPasswordInput" class="form-control">
                  <button type="button" class="toggle-password" tabindex="-1" onclick="toggleRegisterPassword()">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="mb-3">
                <label><strong>Confirm Password</strong></label>
                <div class="password-wrapper">
                  <input type="password" name="confirm_password" id="authConfirmPasswordInput" class="form-control">
                  <button type="button" class="toggle-password" tabindex="-1" onclick="toggleConfirmPassword()">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-custom" id="authSubmitBtn">Login</button>
            </div>

            <div class="mt-3 text-center">
              <a href="#" class="link" onclick="setAuthMode('register'); return false;">Sign up as Admin</a>
              <span class="mx-2">|</span>
              <a href="#" class="link" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#resetModal">Forgot Password?</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- reset password -->
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
            <div class="password-wrapper">
              <input type="password" name="new_password" id="resetPasswordInput" class="form-control" required>
              <i class="fas fa-eye toggle-password" onclick="toggleResetPassword()"></i>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-custom">Change Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- footer -->
  <footer class="footer">
    <div class="container">
      <img src="includes/images/mlhud jpeg 6.jpg" alt="MLHUD Logo" class="footer-logo">

      <h5>Ministry of Lands, Housing and Urban Development</h5>
      <p class="mb-0">Open Registry Database System</p>
      <div class="mt-3 d-flex flex-wrap justify-content-center gap-2 align-items-center">
        <a href="help_center.php" class="btn btn-sm btn-outline-light px-3">Get Help</a>
      </div>
      <p class="mb-0">© <?= date('Y') ?> All rights reserved</p>
      <!-- <div class="mt-3 d-flex flex-wrap justify-content-center gap-2 align-items-center">
        <a href="help_center.php" class="btn btn-sm btn-outline-light px-3">Get Help</a>
        <a href="system_admin_login.php" class="btn btn-sm btn-link text-white-50 small text-decoration-none">System operator</a> 
      </div> -->
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
          behavior: 'smooth'
        });
      });
    });

    // loading states to forms
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        submitBtn.disabled = true;

        // enable after 3 seconds as fallback
        setTimeout(() => {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }, 3000);
      });
    });

    // add animation on scroll for access cards
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, observerOptions);

    // observe access cards
    document.querySelectorAll('.access-card').forEach(card => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
      observer.observe(card);
    });

    // password toggle functions
    function setFieldGroupEnabled(containerId, enabled) {
      const container = document.getElementById(containerId);
      if (!container) {
        return;
      }
      container.querySelectorAll('input, select, textarea, button').forEach(function(el) {
        if (el.type === 'button' || el.classList.contains('toggle-password')) {
          el.disabled = false;
          return;
        }
        el.disabled = !enabled;
      });
    }

    function openAuthModal() {
      const modal = new bootstrap.Modal(document.getElementById('authModal'));
      setAuthMode('login');
      modal.show();
    }

    function setAuthMode(mode) {
      const loginToggle = document.getElementById('loginToggle');
      const registerToggle = document.getElementById('registerToggle');
      const loginFields = document.getElementById('loginFields');
      const registerFields = document.getElementById('registerFields');
      const authAction = document.getElementById('authAction');
      const authSubmitBtn = document.getElementById('authSubmitBtn');
      const usernameEmail = document.getElementById('authUsernameEmail');
      const registerUsername = document.getElementById('registerUsername');
      const registerEmail = document.getElementById('registerEmail');
      const confirmPassword = document.getElementById('authConfirmPasswordInput');
      const authPassword = document.getElementById('authPasswordInput');
      const registerPassword = document.getElementById('registerPasswordInput');
      const titleField = document.getElementById('authTitle');
      const accountTypeField = document.getElementById('authAccountType');

      if (mode === 'register') {
        accountTypeField.value = 'admin';
        authAction.value = 'register';
        loginToggle.classList.remove('active');
        registerToggle.classList.add('active');
        loginFields.style.display = 'none';
        registerFields.style.display = 'block';
        authSubmitBtn.textContent = 'Register';
        usernameEmail.required = false;
        registerUsername.required = true;
        registerEmail.required = true;
        confirmPassword.required = true;
        registerPassword.required = true;
        authPassword.required = false;
        titleField.required = true;
        setFieldGroupEnabled('loginFields', false);
        setFieldGroupEnabled('registerFields', true);
      } else {
        accountTypeField.value = 'admin';
        authAction.value = 'login';
        loginToggle.classList.add('active');
        registerToggle.classList.remove('active');
        loginFields.style.display = 'block';
        registerFields.style.display = 'none';
        authSubmitBtn.textContent = 'Login';
        usernameEmail.required = true;
        registerUsername.required = false;
        registerEmail.required = false;
        confirmPassword.required = false;
        registerPassword.required = false;
        authPassword.required = true;
        titleField.required = false;
        setFieldGroupEnabled('loginFields', true);
        setFieldGroupEnabled('registerFields', false);
      }
    }

    function toggleAuthPassword() {
      const input = document.getElementById('authPasswordInput');
      const icon = input.nextElementSibling.querySelector('i');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }

    function toggleRegisterPassword() {
      const input = document.getElementById('registerPasswordInput');
      const icon = input.nextElementSibling.querySelector('i');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }

    function toggleConfirmPassword() {
      const input = document.getElementById('authConfirmPasswordInput');
      const icon = input.nextElementSibling.querySelector('i');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }

    function toggleResetPassword() {
      const input = document.getElementById('resetPasswordInput');
      const icon = input.nextElementSibling;
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }
  </script>
</body>

</html>