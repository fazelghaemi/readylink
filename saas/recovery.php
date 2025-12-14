<?php
/**
 * RDYS - Password Recovery
 */
require_once 'config.php';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    // در اینجا باید لاجیک ارسال ایمیل با PHPMailer یا mail() اضافه شود.
    // فعلاً یک پیام شبیه‌سازی شده نمایش می‌دهیم.
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='bg-success/10 text-success p-4 rounded-xl mb-6 text-sm border border-success/20'>لینک بازیابی رمز عبور به ایمیل شما ارسال شد. (شبیه‌سازی)</div>";
    } else {
        $msg = "<div class='bg-error/10 text-error p-4 rounded-xl mb-6 text-sm border border-error/20'>ایمیل نامعتبر است.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بازیابی رمز | RDYS</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{bg:"#010101",card:"#0D0D0D",primary:"#00B0A4",text:"#FFFFFF",sec:"#9CA3AF",border:"#1F1F1F",success:"#00B0A4",error:"#FF3B30"},fontFamily:{sans:['ReadyFont','sans-serif']}}}}</script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-bg text-text">
    <div class="w-full max-w-[420px] relative z-10">
        <div class="text-center mb-8"><h1 class="text-2xl font-black text-white">بازیابی رمز عبور</h1><p class="text-sec text-sm mt-2">ایمیل خود را وارد کنید تا لینک تغییر رمز برایتان ارسال شود.</p></div>
        <div class="bg-card border border-border rounded-3xl p-8 shadow-2xl">
            <?php echo $msg; ?>
            <form method="POST">
                <div class="mb-8"><label class="block text-sec text-xs font-bold mb-2">ایمیل ثبت‌نامی</label><input type="email" name="email" required class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 focus:border-primary outline-none ltr text-left"></div>
                <button class="w-full bg-primary hover:bg-[#008F85] text-white font-black py-4 rounded-xl shadow-glow">ارسال لینک</button>
            </form>
        </div>
        <div class="text-center mt-8 text-sm"><a href="login.php" class="text-sec hover:text-white">بازگشت به ورود</a></div>
    </div>
</body>
</html>