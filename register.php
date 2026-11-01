<?php
require 'db.php';

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'phone'   => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];

    $pass = $_POST['password'] ?? '';
    $conf = $_POST['confirm'] ?? '';

    if ($old['name'] === '') {
        $errors[] = 'Please enter your full name.';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($pass !== $conf) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $st = $pdo->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $st->execute([$old['email']]);

        if ($st->fetch()) {
            $errors[] = 'This email address is already registered.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                "INSERT INTO users
                (member_code, name, email, phone, address, password_hash)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $ins->execute([
                uniqid('TMP'),
                $old['name'],
                $old['email'],
                $old['phone'],
                $old['address'],
                password_hash($pass, PASSWORD_DEFAULT)
            ]);

            $id = $pdo->lastInsertId();

            // Generate a member code, for example MW001.
            $code = 'MW' . str_pad($id, 3, '0', STR_PAD_LEFT);

            $pdo->prepare(
                "UPDATE users SET member_code = ? WHERE id = ?"
            )->execute([$code, $id]);

            $pdo->commit();

            header('Location: login.php?registered=1');
            exit;

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Register - Waste Bank</title>

    <link rel="stylesheet" href="auth.css">
</head>
<body>

<div class="leaf l1"></div>
<div class="leaf l2"></div>
<div class="leaf l3"></div>
<div class="leaf l4"></div>

<main class="card wide <?= $errors ? 'shake' : '' ?>">

    <div class="brand center">
        <svg viewBox="0 0 24 24" width="34" height="34"
             fill="#1a8a3e">
            <path d="M17 8C8 10 5.9 16.2 3.8 20.2c1.5.5 3 .8 4.5.8 7 0 12.7-5.5 12.7-14-1 .5-2 .8-3 1zM6 18c1-3.5 4-7 9-8.5-4.2 2-6.8 5-8 8.5z"/>
        </svg>

        <div>
            <h1>Waste Bank</h1>
            <small>Management System</small>
        </div>
    </div>

    <h2>Create Your Account</h2>

    <p class="sub">
        Join us and start saving for a cleaner future.
    </p>

    <?php foreach ($errors as $er): ?>
        <div class="alert"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post" id="regForm">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <label for="name">Full Name</label>
        <input
            type="text"
            id="name"
            name="name"
            placeholder="Enter your full name"
            value="<?= e($old['name'] ?? '') ?>"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email address"
            value="<?= e($old['email'] ?? '') ?>"
            required
        >

        <label for="phone">Phone Number</label>
        <input
            type="tel"
            id="phone"
            name="phone"
            placeholder="Enter your phone number"
            value="<?= e($old['phone'] ?? '') ?>"
        >

        <label for="address">Address</label>
        <input
            type="text"
            id="address"
            name="address"
            placeholder="Enter your address"
            value="<?= e($old['address'] ?? '') ?>"
        >

        <label for="pass">Password</label>
        <div class="pw">
            <input
                type="password"
                name="password"
                id="pass"
                placeholder="Create a password"
                required
            >
            <button type="button" class="eye" data-toggle>
                👁
            </button>
        </div>

        <div class="meter">
            <span id="bar"></span>
        </div>

        <label for="conf">Confirm Password</label>
        <div class="pw">
            <input
                type="password"
                name="confirm"
                id="conf"
                placeholder="Re-enter your password"
                required
            >
            <button type="button" class="eye" data-toggle>
                👁
            </button>
        </div>

        <button class="btn" type="submit">Create Account</button>
    </form>

    <p class="foot">
        Already have an account?
        <a href="login.php">Login</a>
    </p>

</main>

<script src="auth.js"></script>

</body>
</html>