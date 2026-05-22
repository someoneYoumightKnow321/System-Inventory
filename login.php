<?php
// login.php - Halaman Login Sistem Inventaris Kantor
session_start();
require_once 'config.php';

// Jika user sudah login, redirect langsung ke dashboard
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        // Ambil data user dari database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verifikasi password hash
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role' => $user['role']
                ];
                
                header("Location: index.php");
                exit();
            } else {
                $error_message = "Password yang Anda masukkan salah.";
            }
        } else {
            $error_message = "Username tidak terdaftar.";
        }
        $stmt->close();
    } else {
        $error_message = "Harap masukkan username dan password.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Office Inventory</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        charcoal: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#0d9488', // Teal
                            accent: '#0891b2', // Cyan
                        }
                    },
                    boxShadow: {
                        'soft': '0 2px 12px -1px rgba(0, 0, 0, 0.03), 0 4px 30px -2px rgba(0, 0, 0, 0.02)',
                        'premium': '0 10px 30px -5px rgba(0, 0, 0, 0.04), 0 1px 3px 0 rgba(0, 0, 0, 0.01)',
                        'modal': '0 20px 50px -12px rgba(0, 0, 0, 0.08), 0 1px 2px 0 rgba(0, 0, 0, 0.02)',
                    }
                }
            }
        }
    </script>
    
    <style>
        .bg-grid-pattern {
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(226, 232, 240, 0.3) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(226, 232, 240, 0.3) 1px, transparent 1px);
        }
        
        input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }
    </style>
</head>
<body class="bg-charcoal-50 bg-grid-pattern text-charcoal-800 font-sans min-h-screen flex flex-col justify-center items-center p-4 selection:bg-brand-100 selection:text-brand-700">

    <!-- Card Container -->
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-100 shadow-modal p-8 space-y-6">
        
        <!-- Logo Header -->
        <div class="flex flex-col items-center text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-500 to-brand-accent flex items-center justify-center text-white shadow-soft">
                <i data-lucide="package" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-charcoal-900 tracking-tight">Office Inventory</h1>
                <p class="text-xs text-slate-400 mt-1">Sistem Administrasi Inventaris Kantor</p>
            </div>
        </div>

        <!-- Form Login -->
        <form action="login.php" method="POST" class="space-y-4">
            
            <?php if (!empty($error_message)): ?>
                <!-- Alert Message -->
                <div class="p-3.5 bg-rose-50 border border-rose-100 rounded-xl flex items-center gap-3 text-xs text-rose-600 font-medium">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Username Input -->
            <div class="space-y-1.5">
                <label for="username" class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-450">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                    </span>
                    <input 
                        type="text" 
                        name="username" 
                        id="username" 
                        required 
                        placeholder="Masukkan username..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm text-slate-700 placeholder-slate-400 transition-all focus:bg-white focus:border-brand-500"
                    >
                </div>
            </div>

            <!-- Password Input -->
            <div class="space-y-1.5">
                <label for="password" class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-450">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                    </span>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        required 
                        placeholder="Masukkan password..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm text-slate-700 placeholder-slate-400 transition-all focus:bg-white focus:border-brand-500"
                    >
                </div>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full py-2.5 bg-gradient-to-r from-brand-500 to-brand-accent text-white font-semibold text-sm rounded-xl hover:opacity-95 shadow-soft shadow-brand-500/10 hover:shadow-md active:scale-95 transition-all mt-2"
            >
                Masuk ke Dashboard
            </button>
        </form>

        <!-- Account Info Box for easy testing -->
        <div class="p-4 bg-slate-50/80 border border-slate-100 rounded-xl space-y-2 text-[11px] text-slate-500">
            <p class="font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1">
                <i data-lucide="key-round" class="w-3.5 h-3.5 text-brand-500"></i> Akun Demo Tersedia:
            </p>
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-white p-2 rounded-lg border border-slate-100">
                    <p class="font-bold text-slate-750">Role: Admin</p>
                    <p>User: <span class="font-mono bg-slate-50 px-1 rounded">admin</span></p>
                    <p>Pass: <span class="font-mono bg-slate-50 px-1 rounded">admin123</span></p>
                </div>
                <div class="bg-white p-2 rounded-lg border border-slate-100">
                    <p class="font-bold text-slate-750">Role: Karyawan</p>
                    <p>User: <span class="font-mono bg-slate-50 px-1 rounded">karyawan</span></p>
                    <p>Pass: <span class="font-mono bg-slate-50 px-1 rounded">karyawan123</span></p>
                </div>
            </div>
            <p class="text-[10px] text-center text-slate-400 mt-1 italic">Karyawan hanya diperbolehkan mengupdate stok & jumlah pakai.</p>
        </div>

    </div>

    <!-- Small Footer -->
    <div class="mt-8 text-center text-xs text-slate-400">
        &copy; 2026 Office Inventory. Dibuat dengan &hearts; untuk Admin Kantor.
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
