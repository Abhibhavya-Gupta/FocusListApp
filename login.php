<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Sign In — FocusList</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 36px 32px;
        }
        .auth-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
            margin-bottom: 24px;
        }
        .brand-badge {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: #fff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        .demo-box {
            background: #f0efff;
            border: 1px dashed var(--primary);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #3b368c;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center">
            <a class="auth-brand" href="#">
                <span class="brand-badge">✓</span>
                <span>FocusList</span>
            </a>
            <h1 class="h4 mb-1">Welcome back</h1>
            <p class="text-muted small mb-4">Sign in to manage your tasks and stay productive.</p>
        </div>


        <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>

        <form id="loginForm" novalidate>
            <div class="mb-3">
                <label for="loginEmail" class="form-label">Email address</label>
                <input type="email" class="form-control" id="loginEmail" placeholder="name@example.com" required autofocus value="abhibhavya@skit.ac.in">
                <div class="invalid-feedback">Please enter a valid email.</div>
            </div>

            <div class="mb-4">
                <label for="loginPassword" class="form-label">Password</label>
                <input type="password" class="form-control" id="loginPassword" placeholder="••••••••" required value="Password@123">
                <div class="invalid-feedback">Please enter your password.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3" id="loginSubmitBtn">
                <span class="btn-text">Sign In</span>
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>

        <div class="text-center mt-3">
            <span class="text-muted small">Don't have an account? </span>
            <a href="register.php" class="text-decoration-none fw-semibold">Create account</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        const alertBox = $('#alertBox');
        alertBox.addClass('d-none').text('');

        const email = $('#loginEmail').val().trim();
        const password = $('#loginPassword').val();

        if (!email || !password) {
            alertBox.removeClass('d-none').text('Please fill in both fields.');
            return;
        }

        const btn = $('#loginSubmitBtn');
        btn.prop('disabled', true);
        btn.find('.btn-text').text('Signing in...');
        btn.find('.spinner-border').removeClass('d-none');

        $.ajax({
            url: 'api/auth.php?action=login',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': csrfToken },
            data: JSON.stringify({ email: email, password: password }),
            success: function (res) {
                if (res.success && res.redirect) {
                    window.location.href = res.redirect;
                } else {
                    btn.prop('disabled', false);
                    btn.find('.btn-text').text('Sign In');
                    btn.find('.spinner-border').addClass('d-none');
                    alertBox.removeClass('d-none').text(res.message || 'Login failed.');
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                btn.find('.btn-text').text('Sign In');
                btn.find('.spinner-border').addClass('d-none');
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Invalid email or password.';
                alertBox.removeClass('d-none').text(msg);
            }
        });
    });
});
</script>
</body>
</html>
