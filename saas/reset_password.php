<?php
/**
 * RDYS - Password Reset Tool
 * * پس از استفاده، این فایل را حتما پاک کنید.
 */

require_once 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $newPass = $_POST['new_password'];
    
    if (empty($username) || empty($newPass)) {
        $message = '<div style="color:red">لطفا نام کاربری و رمز جدید را وارد کنید.</div>';
    } else {
        // هش کردن رمز با الگوریتم استاندارد سیستم
        $hashedPassword = password_hash($newPass, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ? OR email = ?");
            $stmt->execute([$hashedPassword, $username, $username]);
            
            if ($stmt->rowCount() > 0) {
                $message = '<div style="color:green; font-weight:bold;">رمز عبور با موفقیت تغییر کرد!<br>حالا می‌توانید وارد شوید. <br> <span style="color:red">لطفا همین الان این فایل (reset_password.php) را از هاست پاک کنید.</span></div>';
            } else {
                $message = '<div style="color:orange">کاربری با این مشخصات یافت نشد یا رمز عبور تغییری نکرد.</div>';
            }
        } catch (PDOException $e) {
            $message = 'خطای دیتابیس: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تغییر رمز عبور اضطراری</title>
    <style>
        body { font-family: sans-serif; background: #010101; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: #0D0D0D; padding: 30px; border-radius: 10px; border: 1px solid #1F1F1F; width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #333; color: #fff; border-radius: 5px; box-sizing: border-box;}
        button { width: 100%; padding: 10px; background: #00B0A4; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #008F85; }
    </style>
</head>
<body>
    <div class="box">
        <h3>تغییر رمز عبور</h3>
        <?php echo $message; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="نام کاربری یا ایمیل" required>
            <input type="text" name="new_password" placeholder="رمز عبور جدید" required>
            <button type="submit">تغییر رمز</button>
        </form>
        <br>
        <a href="login.php" style="color:#999; text-decoration:none; font-size:12px;">بازگشت به صفحه ورود</a>
    </div>
</body>
</html>