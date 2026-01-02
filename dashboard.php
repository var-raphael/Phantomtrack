<?php 
session_start();
include "includes/functions.php";

// Cookie config
define('COOKIE_NAME', 'user_secret_key');
define('COOKIE_EXPIRY', 365 * 24 * 60 * 60);

$secretKeyFromURL = $_GET['secretkey'] ?? null;
$secretKeyFromCookie = $_COOKIE[COOKIE_NAME] ?? null;
$secretKeyToValidate = $secretKeyFromURL ?: $secretKeyFromCookie;

$hasSecretKey = false;
$currentSecretKey = null;
$autoOpenModal = false;
$user_id = null;

if ($secretKeyToValidate) {
    $user = fetchOne("SELECT user_id, secret_key FROM users WHERE secret_key = ?", [$secretKeyToValidate]);

    if ($user) {
        // Set sessions
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['secret_key'] = $user['secret_key'];
        
        // Set website_id
        if (isset($_GET['website_id'])) {
            $_SESSION['website_id'] = $_GET['website_id'];
        } elseif (!isset($_SESSION['website_id'])) {
            // Only set if not already set
            $website = fetchOne("SELECT website_id FROM website WHERE user_id = ? ORDER BY created_at ASC LIMIT 1", [$user['user_id']]);
            $_SESSION['website_id'] = $website['website_id'] ?? null;
        }
        
        $hasSecretKey = true;
        $currentSecretKey = $secretKeyToValidate;
        $user_id = $user['user_id'];
        
        // Save cookie if secret key came from URL
        if ($secretKeyFromURL) {
            setcookie(COOKIE_NAME, $secretKeyFromURL, time() + COOKIE_EXPIRY, '/', '', isset($_SERVER['HTTPS']), true);
            
            // Build clean redirect URL without secretkey but WITH website_id
            $urlParts = parse_url($_SERVER['REQUEST_URI']);
            $basePath = $urlParts['path'];
            $queryParams = [];
            
            // Parse existing query params
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryParams);
            }
            
            // Remove secretkey from params
            unset($queryParams['secretkey']);
            
            // Ensure website_id is in the URL
            if ($_SESSION['website_id']) {
                $queryParams['website_id'] = $_SESSION['website_id'];
            }
            
            // Build redirect URL
            $redirectUrl = $basePath . '?' . http_build_query($queryParams);
            
            header("Location: " . $redirectUrl);
            exit;
        }
    } else {
        session_unset();
        $autoOpenModal = true;
    }
} else {
    session_unset();
    $autoOpenModal = true;
}

$website_id = $_SESSION['website_id'] ?? null;
$url = env('PHANTOMTRACK_URL');
include "header.php";
?>
<body data-theme="dark">
    
    <div class="dashboard-container">
      <!-- Sidebar Overlay -->
      
      
     
     <?php include "nav.php"; ?>
     
     
      <!-- Main Content -->
      <main class="main-content" id="mainContent">
     <?php include "main-content.php"; ?>
        
        <!-- Stats Cards -->
<?php include "stat-cards.php"; ?>
        
        <!-- Filter Buttons & Charts -->
        
        <?php include "charts.php"; ?>
        
        <!-- Data Cards -->
        <?php include "data-cards.php"; ?>
        
          <!-- Top Referrers -->
          
          <?php include "top-referrer.php"; ?>
       
          
          <!-- Country Breakdown -->
         <?php include "country-tier.php"; ?>
          
          <!-- Device Types -->
          <?php require "device-type.php"; ?>
          
          <!-- Browsers -->
          <?php require "browser.php"; ?>
          
            <!-- Ai Review -->
          
          <?php require "ai-review.php"; ?>
          
          <!-- Universal Modal -->
  				
  				<?php require "universal-modal.php"; ?>
  
  
  
  <!--secret-key--modal-->
          <?php include "mod.php"; ?>
        </div>
      </main>
    </div>
    
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
    // Open modal IMMEDIATELY when clicked, BEFORE data loads
    document.body.addEventListener('htmx:beforeRequest', function(event) {
      if (event.detail.target.id === 'modal-content') {
        const triggerElement = event.detail.elt;
        const modalTitle = triggerElement.getAttribute('data-modal-title') || 'Details';
        const modalSize = triggerElement.getAttribute('data-modal-size') || 'default';
        
        // Set title
        document.getElementById('modal-title').textContent = modalTitle;
        
        // Set size
        const modalContainer = document.querySelector('.modal-container');
        modalContainer.className = 'modal-container ' + (modalSize !== 'default' ? modalSize : '');
        
        // Show loading spinner in modal
        document.getElementById('modal-content').innerHTML = `
          <div class="spinner-container">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading...</span>
          </div>
        `;
        
        // OPEN MODAL IMMEDIATELY
        document.getElementById('universal-modal').classList.add('active');
      }
    });

    function closeModal() {
      document.getElementById('universal-modal').classList.remove('active');
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeModal();
    });
    
    // Handle errors gracefully abeg i no want headache
    document.body.addEventListener('htmx:responseError', function(event) {
      if (event.detail.target.id === 'modal-content') {
        document.getElementById('modal-content').innerHTML = `
          <div style="color: #dc2626; text-align: center; padding: 40px;">
            <i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
            <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Error loading data</p>
            <p style="font-size: 14px; color: #6b7280;">Please try again later</p>
          </div>
        `;
      }
    });
  </script>
    
</body>
</html>