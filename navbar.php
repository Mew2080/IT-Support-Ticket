<?php
require_once __DIR__ . '/config.php';
?>
<header class="top-navbar">
    <div class="top-navbar-shell">
        <div class="top-navbar-inner">
            <div class="nav-left">
                <?php 
                // ตรวจสอบว่าเราอยู่ในโฟลเดอร์ admin หรือไม่
                $is_admin_page = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
                $base_path = $is_admin_page ? '../' : '';
                ?>
                <a href="<?php echo $base_path; ?>index.php" class="nav-brand">
                    <img src="<?php echo $base_path; ?>img/logo2.png" alt="SET Logo" class="nav-logo">
                    <span class="nav-title">IT Support Ticket</span>
                </a>
            </div>

            <div class="nav-right">
                <?php 
                // ตรวจสอบว่าเราอยู่ในโฟลเดอร์ admin หรือไม่
                $is_admin_page = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
                $base_path = $is_admin_page ? '../' : '';
                ?>
                <?php if (!isLoggedIn()): ?>
                    
                <?php else: ?>
                    <div class="nav-dropdown">
                        <button type="button" class="nav-btn nav-btn-ghost nav-dropdown-toggle">
                            <span>Menu</span>
                            <span class="nav-dropdown-chevron">▾</span>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="<?php echo $base_path; ?>dashboard.php" class="nav-dropdown-item">
                                <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                <span>Home</span>
                            </a>
                            <a href="<?php echo $base_path; ?>my_tickets.php" class="nav-dropdown-item">
                                <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                    <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"></path>
                                </svg>
                                <span>My Tickets</span>
                            </a>
                            <a href="<?php echo $base_path; ?>profile.php" class="nav-dropdown-item">
                                <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span>Profile</span>
                            </a>
                            <?php if (isAdmin()): ?>
                                <a href="<?php echo $base_path; ?>admin/tickets.php" class="nav-dropdown-item">
                                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                                    </svg>
                                    <span>Admin</span>
                                </a>
                            <?php endif; ?>
                            <div class="nav-dropdown-divider"></div>
                            <a href="<?php echo $base_path; ?>logout.php" class="nav-dropdown-item nav-dropdown-item-danger">
                                <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<script>
// Dropdown menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggle = document.querySelector('.nav-dropdown-toggle');
    const dropdownMenu = document.querySelector('.nav-dropdown-menu');
    const dropdown = document.querySelector('.nav-dropdown');
    
    if (dropdownToggle && dropdownMenu) {
        dropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('nav-dropdown-active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('nav-dropdown-active');
            }
        });
        
        // Close dropdown when clicking on a menu item
        const menuItems = dropdownMenu.querySelectorAll('.nav-dropdown-item');
        menuItems.forEach(function(item) {
            item.addEventListener('click', function() {
                dropdown.classList.remove('nav-dropdown-active');
            });
        });
    }
});
</script>

