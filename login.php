<?php
require 'db.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $st = $pdo->prepare(
        "SELECT * FROM users WHERE email=? AND status='active'"
    );
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'   => $u['id'],
            'name' => $u['name'],
            'role' => $u['role']
        ];

        header('Location: dashboard.php');
        exit;
    }

    $error = 'Incorrect email address or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Waste Bank</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>

<div class="leaf l1"></div>
<div class="leaf l2"></div>
<div class="leaf l3"></div>
<div class="leaf l4"></div>

<main class="card <?= $error ? 'shake' : '' ?>">

    <div class="brand">
        <svg viewBox="0 0 24 24" width="38" height="38"
             fill="#1a8a3e">
            <path d="M17 8C8 10 5.9 16.2 3.8 20.2c1.5.5 3 .8 4.5.8 7 0 12.7-5.5 12.7-14-1 .5-2 .8-3 1zM6 18c1-3.5 4-7 9-8.5-4.2 2-6.8 5-8 8.5z"/>
        </svg>

        <div>
            <h1>Waste Bank</h1>
            <small>Management System</small>
        </div>
    </div>

    <h2>Login to Your Account</h2>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email address"
            required
        >

        <label for="password">Password</label>
        <div class="pw">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
            >
            <button type="button" class="eye" data-toggle>
                👁
            </button>
        </div>

        <div class="row">
            <label class="check">
                <input type="checkbox" name="remember">
                Remember me
            </label>

            <a href="forgot_password.php">Forgot password?</a>
        </div>

        <button class="btn" type="submit">Login</button>
    </form>

    <p class="foot">
        Don't have an account?
        <a href="register.php">Register</a>
    </p>

    <p class="tag">Together for a Cleaner Environment</p>

</main>

<script src="auth.js"></script>
</body>
</html>