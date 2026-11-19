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
    <title>Create Account — FocusList</title>

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
            max-width: 460px;
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
            <h1 class="h4 mb-1">Create an account</h1>
            <p class="text-muted small mb-4">Start organizing your tasks with smart categories & priorities.</p>
        </div>

        <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>

        <form id="registerForm" novalidate>
            <div class="mb-3">
                <label for="regName" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="regName" placeholder="e.g. Abhibhavya Gupta" required>
            </div>

            <div class="mb-3">
                <label for="regEmail" class="form-label">Email address</label>
                <input type="email" class="form-control" id="regEmail" placeholder="name@domain.com" required>
            </div>

            <div class="mb-3">
                <label for="regPassword" class="form-label">Password</label>
                <input type="password" class="form-control" id="regPassword" placeholder="Minimum 6 characters" minlength="6" required>
            </div>

            <div class="mb-4">
                <label for="regConfirmPassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="regConfirmPassword" placeholder="Re-enter password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3" id="registerSubmitBtn">
                <span class="btn-text">Create Account</span>
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>

        <div class="text-center mt-3">
            <span class="text-muted small">Already have an account? </span>
            <a href="login.php" class="text-decoration-none fw-semibold">Sign in</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#registerForm').on('submit', function (e) {
        e.preventDefault();
        const alertBox = $('#alertBox');
        alertBox.addClass('d-none').text('');

        const name = $('#regName').val().trim();
        const email = $('#regEmail').val().trim();
        const password = $('#regPassword').val();
        const confirmPassword = $('#regConfirmPassword').val();

        if (!name || !email || !password || !confirmPassword) {
            alertBox.removeClass('d-none').text('All fields are required.');
            return;
        }

        if (password.length < 6) {
            alertBox.removeClass('d-none').text('Password must be at least 6 characters long.');
            return;
        }

        if (password !== confirmPassword) {
            alertBox.removeClass('d-none').text('Passwords do not match.');
            return;
        }

        const btn = $('#registerSubmitBtn');
        btn.prop('disabled', true);
        btn.find('.btn-text').text('Creating account...');
        btn.find('.spinner-border').removeClass('d-none');

        $.ajax({
            url: 'api/auth.php?action=register',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': csrfToken },
            data: JSON.stringify({
                name: name,
                email: email,
                password: password,
                confirm_password: confirmPassword
            }),
            success: function (res) {
                if (res.success && res.redirect) {
                    window.location.href = res.redirect;
                } else {
                    btn.prop('disabled', false);
                    btn.find('.btn-text').text('Create Account');
                    btn.find('.spinner-border').addClass('d-none');
                    alertBox.removeClass('d-none').text(res.message || 'Registration failed.');
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                btn.find('.btn-text').text('Create Account');
                btn.find('.spinner-border').addClass('d-none');
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Registration failed. Try again.';
                alertBox.removeClass('d-none').text(msg);
            }
        });
    });
});
</script>
</body>
</html>
