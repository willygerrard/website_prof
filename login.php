<?php
// 1. HIDUPKAN SESSION START JUGA DI SINI
session_start();

// 2. KALAU SUDAH LOGIN KOK ISENG BUKA HALAMAN INI, LANGSUNG LEMPAR KE INDEX
if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Server - Modul Ajar</title>
    <style>
        /* CSS Sederhana ala Router Web Admin */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background-color: #ffffff;
            padding: 25px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
            width: 280px;
        }
        .login-box h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
            border: 1px solid #aaa;
            border-radius: 3px;
        }
        .btn-submit {
            width: 100%;
            padding: 8px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-submit:hover {
            background-color: #004085;
        }
        .error-msg {
            color: red;
            font-size: 12px;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h3>SYSTEM AUTH</h3>
    
    <?php if(isset($_GET['error'])): ?>
    <div class="error-msg">
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>
    
    <form action="proses_login.php" method="POST">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required autocomplete="off" placeholder="admin">
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="******">
        </div>
        
        <button type="submit" class="btn-submit">Connect</button>
    </form>
</div>

</body>
</html>