<?php
if (!function_exists('isLoggedIn')) require_once 'config.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$menuItems = [
    ['page' => 'dashboard',  'icon' => '📈', 'label' => 'الإحصائيات',   'roles' => ['admin','haifa','khaoula','mohamed','ali','mourad','ahmed']],
    ['page' => 'index',      'icon' => '📊', 'label' => 'المتابعة',      'roles' => ['admin','haifa','khaoula','mohamed','ali','mourad','ahmed']],
    ['page' => 'calendrier', 'icon' => '📅', 'label' => 'تاريخ التوجية', 'roles' => ['admin','haifa','khaoula','mohamed','ali','mourad','ahmed']],
    ['page' => 'ajouter',    'icon' => '🧾', 'label' => 'إضافة شكاية',   'roles' => ['admin','haifa','ali','mourad','ahmed']],
    ['page' => 'membres',    'icon' => '⚙️', 'label' => 'الإدارة',       'roles' => ['admin']],
];
?>

<div id="sidebar-overlay"
     onclick="closeSidebar()"
     style="display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.5);z-index:998"></div>

<aside id="sidebar">

    <!-- Logo -->
    <div class="sb-logo">
        <img src="Logo_commune_Sousse.svg" alt="Logo commune" class="sb-logo-img">
        <div>
            <div style="font-size:14px;font-weight:700">بلدية سوسة</div>
            <div style="font-size:11px;opacity:.6;margin-top:1px">البنايات المتداعية</div>
        </div>
    </div>

    <!-- User -->
    <div class="sb-user">
        <div class="sb-user-avatar">👤</div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= htmlspecialchars($_SESSION['user']['nom'] ?? 'مستخدم') ?>
            </div>
            <div style="font-size:11px;opacity:.6;margin-top:1px">
                <?php
                $r = $_SESSION['user']['role'] ?? '';
                echo roleLabel($r);
                ?>
            </div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sb-nav">
        <?php foreach ($menuItems as $item):
            if (!hasAnyRole($item['roles'])) continue;
            $active = ($currentPage === $item['page']);
        ?>
        <a href="<?= $item['page'] ?>.php"
           class="sb-link <?= $active ? 'active' : '' ?>">
            <span class="sb-link-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="sb-footer">
        <a href="logout.php" class="sb-logout">
            <span style="font-size:18px">🚪</span>
            <span>تسجيل الخروج</span>
        </a>
    </div>

</aside>

<style>
    /* ── Sidebar styles ── */
    #sidebar {
        position: fixed;
        top: 0; right: 0;
        width: 220px;
        height: 100vh;
        background: linear-gradient(180deg, #1a3c5e 0%, #0f2540 100%);
        color: white;
        z-index: 999;
        display: flex;
        flex-direction: column;
        box-shadow: -4px 0 20px rgba(0,0,0,.3);
        transition: transform .3s ease;
        transform: translateX(0);
        overflow: hidden;
    }

    body {
        margin-right: 220px !important;
    }

    .sb-logo {
        padding: 20px 16px 14px;
        border-bottom: 1px solid rgba(255,255,255,.1);
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .sb-logo-img {
        width: 44px; height: 44px;
        object-fit: contain; flex-shrink: 0;
        background: white; border-radius: 50%; padding: 3px;
    }

    .sb-user {
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255,255,255,.08);
        background: rgba(255,255,255,.05);
        display: flex; align-items: center; gap: 10px;
        flex-shrink: 0;
    }
    .sb-user-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: linear-gradient(135deg, #2e6da4, #6f42c1);
        display: flex; align-items: center;
        justify-content: center; font-size: 16px;
        flex-shrink: 0;
    }

    .sb-nav {
        flex: 1;
        padding: 10px 8px;
        overflow-y: auto;
    }
    .sb-link {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 11px; border-radius: 8px;
        text-decoration: none; margin-bottom: 2px;
        font-size: 13px; font-weight: 500;
        color: rgba(255,255,255,.7);
        transition: all .18s;
        border-right: 3px solid transparent;
    }
    .sb-link:hover {
        background: rgba(255,255,255,.1);
        color: white;
    }
    .sb-link.active {
        background: rgba(255,255,255,.18);
        color: white;
        font-weight: 700;
        border-right-color: #2e6da4;
    }
    .sb-link-icon { font-size: 17px; flex-shrink: 0; }

    .sb-footer {
        padding: 10px 8px;
        border-top: 1px solid rgba(255,255,255,.1);
        flex-shrink: 0;
    }
    .sb-logout {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 11px; border-radius: 8px;
        text-decoration: none;
        color: rgba(255,255,255,.7);
        font-size: 13px; font-weight: 500;
        transition: all .18s;
    }
    .sb-logout:hover {
        background: rgba(220,53,69,.35);
        color: white;
    }

    /* Bouton toggle mobile */
    #sb-toggle {
        display: none;
        position: fixed;
        top: 12px; right: 12px;
        z-index: 1001;
        background: #1a3c5e;
        color: white; border: none;
        border-radius: 8px;
        width: 38px; height: 38px;
        font-size: 18px; cursor: pointer;
        align-items: center; justify-content: center;
    }

    @media (max-width: 900px) {
        body { margin-right: 0 !important; }
        #sidebar { transform: translateX(100%); }
        #sidebar.open { transform: translateX(0); }
        #sb-toggle { display: flex; }
    }
</style>

<button id="sb-toggle" onclick="toggleSidebar()">☰</button>

<script>
function toggleSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebar-overlay');
    var isOpen = s.classList.contains('open');
    s.classList.toggle('open');
    o.style.display = isOpen ? 'none' : 'block';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').style.display = 'none';
}
</script>
