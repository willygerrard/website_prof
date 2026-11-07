<?php
// 1. HIDUPKAN SESSION START JUGA DI SINI
require_once 'session.php';      // session dulu    
require_once 'csrf_helper.php';  // baru helper

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
    <title>Login - SIJA Learning Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-panel: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --border: #e2e8f0;
            --input-bg: #f8fafc;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --error-border: #fecaca;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- KOLOM KIRI (BRANDING & GAMBAR) --- */
        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 60px 40px 20px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 40px;
        }

        /* CSS BARU UNTUK GAMBAR LOGO */
        .brand-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border-radius: 8px; /* Opsional, agar sudut gambar sedikit membulat */
        }

        .brand-text h2 {
            font-size: 18px;
            font-weight: 700;
        }
        
        .brand-text p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .left-panel h1 {
            font-size: 42px;
            line-height: 1.2;
            margin-bottom: 30px;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .illustration-box {
            width: 100%;
            aspect-ratio: 16 / 10;
            max-height: 42vh;
            background-color: #e0f2fe;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

        .illustration-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }


        /* --- KOLOM KANAN (FORM LOGIN) --- */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* PERBESAR UKURAN KOTAK LOGIN */
        .login-card {
            background: var(--bg-panel);
            width: 100%;
            max-width: 480px; /* Dibesarkan dari 420px */
            padding: 36px 36px; /* Padding atas-bawah ditambah */
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid var(--border);
        }

        .login-card h3 {
            font-size: 26px; /* Judul dibesarkan */
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-card .login-subtitle {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 22px;
        }

        .form-group {
            margin-bottom: 18px; /* Jarak antar form ditambah */
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .input-wrapper {
            position: relative;
        }

        /* INPUT DIBESARKAN */
        .form-group input {
            width: 100%;
            padding: 14px 16px; /* Lebih tebal */
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 15px; /* Font dibesarkan */
            background-color: var(--input-bg);
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        /* Toggle Password Icon */
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            background: none;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }

        .toggle-password:hover {
            color: var(--text-main);
        }

        /* TOMBOL DIBESARKAN */
        .btn-submit {
            width: 100%;
            padding: 14px; /* Lebih tebal */
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px; /* Font dibesarkan */
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background-color 0.2s ease, transform 0.1s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-muted);
            text-decoration: none;
        }

        .forgot-password:hover {
            color: var(--primary);
        }

        .divider {
            height: 1px;
            background-color: var(--border);
            margin: 22px 0;
        }

        .signup-text {
            text-align: center;
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .btn-signup {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary); /* Border dipertebal */
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-signup:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }

        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            padding: 14px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
            text-align: center;
        }

        .footer-text {
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-muted);
            text-align: center;
        }

        /* --- RESPONSIVE DESIGN --- */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
            }
            .left-panel {
                padding: 40px 20px 20px 20px;
                text-align: center;
                align-items: center;
            }
            .brand-logo {
                justify-content: center;
            }
            .left-panel h1 {
                font-size: 32px;
            }
            .illustration-box {
                display: none; /* Sembunyikan gambar di mobile */
            }
            .right-panel {
                width: 100%;
                padding: 0 20px 40px 20px;
            }
            .login-card {
                padding: 32px 24px; /* Padding disesuaikan untuk layar HP */
            }
        }
    </style>
</head>
<body>

<?php if ($error === 'nonaktif'): ?>
<script>
    alert('Akun ini sudah tidak aktif. Hubungi guru pembimbing.');
</script>
<?php endif; ?>

<div class="container">
    <!-- KOLOM KIRI -->
    <div class="left-panel">
        <div class="brand-logo">
            <!-- GAMBAR LOGO SEKOLAH MENGGANTIKAN KOTAK BIRU SIJA -->
            <img src="images.jpeg" alt="Logo SMKN 11 Malang" class="brand-img">
            <div class="brand-text">
                <h2>SIJA Learning Hub</h2>
                <p>SMKN 11 Malang</p>
            </div>
        </div>

        <h1>Belajar jaringan dan aplikasi, di mana saja.</h1>
       
        <div class="illustration-box">
            <!-- Gambar Siswa Belajar (Placeholder dari Unsplash) -->
            <img src="LMS.jpeg" 
                 alt="Siswa belajar di depan komputer" 
                 onerror="this.style.display='none'; document.querySelector('.illustration-placeholder-text').style.zIndex='1';">
            <span class="illustration-placeholder-text">Foto siswa belajar di depan komputer<br>(Ganti dengan foto asli, rasio 16:9)</span>
        </div>
    </div>

    <!-- KOLOM KANAN -->
    <div class="right-panel">
        <div class="login-card">
            <h3>Masuk</h3>
            <p class="login-subtitle">Gunakan username dan password Anda.</p>

            <?php if (isset($_GET['error'])): ?>
            <div class="error-msg">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required autocomplete="off" placeholder="Masukkan username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Masukkan password">
                        <!-- Ikon Toggle Password -->
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Masuk</button>
                <a href="#" class="forgot-password">Lupa password? Hubungi guru untuk reset.</a>
            </form>

            <div class="divider"></div>

            <p class="signup-text">Belum punya akun siswa?</p>
            <a href="signup.php" class="btn-signup">Daftar</a>
        </div>
        
        <div class="footer-text">
            &copy; SMKN 11 Malang &middot; Jurusan SIJA
        </div>
    </div>
</div>

<script>
    // Fungsi untuk Toggle Password
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
    }
</script>

</body>
</html>