<?php
// FILE: signup.php
require_once __DIR__ . '/includes/config.php';

// If user already logged in, redirect
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Load countries list
require_once __DIR__ . '/includes/countries.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Signup – Shona Cloud</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
      /* small tweak so datalist input matches other controls size-wise */
      .country-input { min-width: 0; }
    </style>
</head>
<body class="d-flex vh-100 align-items-center justify-content-center bg-gradient-to-r from-purple-500 via-pink-500 to-red-500">

    <div class="bg-white p-4 rounded shadow w-100" style="max-width: 500px;">
        <h2 class="mb-3 text-center">Create Account</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form action="includes/register.php" method="post" class="row g-3">
            <div class="col-12">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Country</label>

                <!-- Using a datalist gives a searchable dropdown-like UX without extra JS.
                     The input name must be 'country' to match existing register.php handling. -->
                <input list="countries" name="country" class="form-control country-input" placeholder="Start typing your country..." required>
                <datalist id="countries">
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <!-- If you prefer a classic select (non-searchable), replace the above with:
                <select name="country" class="form-select" required>
                    <option value="" selected disabled>Choose your country</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                -->
            </div>

            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            </div>
        </form>

        <p class="mt-3 text-center">
            Already have an account? <a href="login.php">Login here</a>.
        </p>
    </div>

</body>
</html>
