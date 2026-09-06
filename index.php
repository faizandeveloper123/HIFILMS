<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'admin';
    $dashMap = [
        'admin'    => 'dashboard.php',
        'staff'    => 'staff_dashboard.php',
        'teacher'  => 'teacher_dashboard.php',
        'accounts' => 'accounts_dashboard.php',
    ];
    header('Location: ' . BASE_URL . ($dashMap[$role] ?? 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['txt_email'] ?? '');
    $password = $_POST['txt_password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = db_prepare("SELECT user_id, email, password, full_name, role, status FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && $user['status'] == 1 && hash('sha256', $password) === $user['password']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $redirects = [
                'admin'    => 'dashboard.php',
                'staff'    => 'staff_dashboard.php',
                'teacher'  => 'teacher_dashboard.php',
                'accounts' => 'accounts_dashboard.php',
            ];
            $dest = $redirects[$user['role']] ?? 'dashboard.php';
            header('Location: ' . BASE_URL . $dest);
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HIIFI LMS</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: radial-gradient(1200px 800px at 10% 10%, #eef2ff 0%, #f4f4f4 50%, #f8fafc 100%);
        }
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding: 24px;
            gap: 24px;
        }
        .left-image {
            flex: 1;
            background-image: url('assets/img/loginbanner.png');
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.15);
        }
        .left-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(15, 23, 42, 0.35) 100%);
            z-index: 1;
        }
        .image-text {
            position: absolute;
            bottom: 40px;
            left: 32px;
            right: 32px;
            color: white;
            text-align: left;
            z-index: 2;
        }
        .image-text h3 {
            font-size: 34px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }
        .image-text p {
            font-size: 14px;
            margin: 6px 0 16px;
            color: #e2e8f0;
            line-height: 1.5;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #f8fafc;
        }
        .right-content {
            flex: 1;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: transparent;
        }
        .login-card {
            width: 100%;
            text-align: center;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            border: 1px solid #eef2f7;
        }
        .logo {
            max-width: 230px;
            margin-bottom: 8px;
        }
        h2 {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .login-card p {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }
        .underline-orange {
            width: 60px;
            height: 3px;
            background-color: #ff8c00;
            margin: 8px 0 16px;
            margin-left: auto;
            margin-right: auto;
        }
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        .login-card .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            width: 100%;
            font-size: 14px;
            background-color: #fff;
            transition: border-color 0.3s ease;
        }
        .login-card .form-control:focus {
            border-color: #ff8c00;
            box-shadow: 0 0 0 4px rgba(255, 140, 0, 0.12);
        }
        .form-group label {
            position: absolute;
            left: 15px;
            top: -10px;
            font-size: 14px;
            color: #64748b;
            background-color: white;
            padding: 0 5px;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .form-control:focus + label {
            color: #ff8c00;
        }
        .form-control::placeholder {
            color: transparent;
        }
        .btn-login {
            background-color: #ff8c00;
            color: white;
            border: none;
            padding: 12px 15px;
            width: 100%;
            font-size: 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-login:hover {
            background-color: #e07c00;
        }
        .password-container {
            position: relative;
        }
        .password-container .form-control {
            padding-right: 40px;
        }
        .password-container .eye-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: gray;
        }
        .alert-error {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECACA;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: left;
        }
        .powered-by {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .badge-modern {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .left-image { display: none; }
            .right-content { flex: 1; width: 100%; }
            .login-container { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="left-image">
        <div class="image-text">
            <h3>HIFI LMS 2.0</h3>
            <p>Modern SaaS experience for schools with smarter insights and a more reliable system.</p>
            <ul class="feature-list">
                <li><i class="fas fa-chart-line"></i> Modern analytics &amp; activity monitoring</li>
                <li><i class="fas fa-shield-alt"></i> Improved and error‑free workflows</li>
                <li><i class="fas fa-bolt"></i> Faster performance &amp; smoother experience</li>
                <li><i class="fas fa-lock"></i> Secure access and data protection!</li>
            </ul>
        </div>
    </div>

    <div class="right-content">
        <div class="login-card" text-align="center">
            <img src="assets/img/logo_new.png" alt="HIFI LMS logo" class="logo">
            <h2>Welcome Back</h2>
            <p>Sign in to continue to HIFI LMS</p>
            <div class="underline-orange"></div>

            <?php if ($error !== ''): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="index.php" method="post" id="login-form">
                <input type="hidden" name="action" value="user_login">
                <div class="form-group">
                    <input type="email" class="form-control" id="email" placeholder="Enter your email" name="txt_email" required="">
                    <label for="email">Email</label>
                </div>
                <div class="form-group password-container">
                    <input type="password" class="form-control" id="password" placeholder="Enter your password" name="txt_password" required="">
                    <label for="password">Password</label>
                    <i class="fas fa-eye eye-icon" id="toggle-password" onclick="togglePassword()"></i>
                </div>
                <button type="submit" class="btn-login">Login</button>
                <div class="text-end mt-2">
                    <a href="#" class="text-decoration-none" style="color: #ff6f00; font-size: 13px;">Forgot Password?</a>
                </div>
            </form>
        </div>

        <div class="powered-by">Powered by HIFI MARKETING ACADEMY</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        var passwordField = document.getElementById("password");
        var eyeIcon = document.getElementById("toggle-password");
        if (passwordField.type === "password") {
            passwordField.type = "text";
            eyeIcon.classList.remove("fa-eye");
            eyeIcon.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            eyeIcon.classList.remove("fa-eye-slash");
            eyeIcon.classList.add("fa-eye");
        }
    }
</script>
</body></html>