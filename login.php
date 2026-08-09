<?php
// 1. HIDUPKAN SESSION START JUGA DI SINI
session_start();
include 'csrf_helper.php';

// 2. KALAU SUDAH LOGIN KOK ISENG BUKA HALAMAN INI, LANGSUNG LEMPAR KE INDEX
if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    header("Location: index.php");
    exit();
}
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Server - Modul Ajar</title>
    <style>
        :root {
            --bg-dark: #07111f;
            --panel: rgba(9, 16, 29, 0.82);
            --panel-border: rgba(255, 255, 255, 0.12);
            --text-main: #f2f7ff;
            --text-muted: #9fb0c9;
            --primary: #2f7bff;
            --primary-dark: #1f5ed6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #07111f 0%, #0e1b2d 45%, #081221 100%);
            color: var(--text-main);
            overflow: hidden;
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            filter: blur(10px);
            opacity: 0.45;
            animation: floatOrb 8s ease-in-out infinite;
            pointer-events: none;
        }

        body::before {
            width: 320px;
            height: 320px;
            background: rgba(61, 220, 151, 0.16);
            top: -80px;
            left: -80px;
        }

        body::after {
            width: 260px;
            height: 260px;
            background: rgba(47, 123, 255, 0.18);
            bottom: -70px;
            right: -70px;
            animation-delay: -3s;
        }

        @keyframes floatOrb {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
            }
            50% {
                transform: translate3d(22px, -18px, 0) scale(1.05);
            }
        }

        .login-shell {
            position: relative;
            z-index: 1;
            width: min(92%, 420px);
            animation: slideUp 0.7s ease both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-box {
            background: var(--panel);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--panel-border);
            padding: 32px 28px;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
        }

        .brand {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(47, 123, 255, 0.9), rgba(61, 220, 151, 0.8));
            font-size: 24px;
            box-shadow: 0 10px 24px rgba(47, 123, 255, 0.22);
            animation: welcomeFloat 2.4s ease-in-out infinite;
        }

        @keyframes welcomeFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-4px);
            }
        }

        .login-box h3 {
            margin: 0 0 8px;
            color: var(--text-main);
            text-align: center;
            font-size: 24px;
            animation: fadeInUp 0.7s ease both;
        }

        .subtitle {
            margin: 0 0 22px;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            animation: fadeInUp 0.8s ease both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            color: var(--text-muted);
        }

        .form-group input {
            width: 100%;
            padding: 11px 12px;
            box-sizing: border-box;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-main);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .form-group input::placeholder {
            color: #7f8ea8;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(47, 123, 255, 0.16);
            transform: translateY(-1px);
        }

        .btn-submit {
            width: 100%;
            padding: 12px 14px;
            margin-top: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 10px 24px rgba(47, 123, 255, 0.22);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(47, 123, 255, 0.28);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .signup-text {
            margin: 14px 0 8px;
            color: var(--text-muted);
            font-size: 14px;
            text-align: center;
        }

        .signup-link {
            display: block;
            width: 100%;
            padding: 12px 0;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-main);
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }

        .signup-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.24);
            transform: translateY(-1px);
        }

        .error-msg {
            color: #ff8a8a;
            font-size: 13px;
            margin-bottom: 12px;
            text-align: center;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 138, 138, 0.12);
            border: 1px solid rgba(255, 138, 138, 0.25);
        }
    </style>
</head>
<body>

<?php if ($error === 'nonaktif'): ?>
<script>
    alert('Akun ini sudah tidak aktif. Hubungi guru pembimbing.');
</script>
<?php endif; ?>

<div class="login-shell">
    <div class="login-box">
        <div class="brand">🎓</div>
        <h3>Welcome Back</h3>
        <p class="subtitle">Masuk untuk melanjutkan perjalanan belajar Anda</p>

        <?php if (isset($_GET['error'])): ?>
        <div class="error-msg">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <form action="proses_login.php" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="off" placeholder="admin">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="******">
            </div>

            <button type="submit" class="btn-submit">Connect</button>
            <p class="signup-text">Belum punya akun siswa?</p>
            <a href="signup.php" class="signup-link">Sign Up</a>
        </form>
    </div>
</div>
  

</body>
</html>