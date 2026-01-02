<style>

    .sk-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 1000;
    }

    .sk-modal.sk-active {
      display: flex;
      align-items: center;
      justify-content: center;
      animation: skFadeIn 0.2s;
    }

    @keyframes skFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .sk-backdrop {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
    }

    .sk-container {
      position: relative;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      z-index: 1001;
      animation: skSlideUp 0.3s;
    }

    @keyframes skSlideUp {
      from {
        transform: translateY(20px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .sk-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
    }

    .sk-header h3 {
      font-size: 20px;
      color: var(--text);
    }

   
    .sk-body {
      padding: 24px;
    }

    .sk-form-group {
      margin-bottom: 20px;
    }

    .sk-form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text);
    }

    .sk-input-wrapper {
      display: flex;
      gap: 8px;
    }

    .sk-input-wrapper input {
      flex: 1;
      padding: 10px 14px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 14px;
      font-family: monospace;
    }

    .sk-input-wrapper input:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }

    .sk-icon-btn {
      padding: 10px 14px;
      background: var(--hover);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 44px;
    }

    .sk-icon-btn:hover {
      background: var(--accent1);
      color: white;
      border-color: var(--accent1);
    }

    .sk-download-link {
      display: block;
      padding: 12px 14px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--accent2);
      text-decoration: none;
      font-size: 14px;
      word-break: break-all;
      transition: all 0.2s;
      cursor: pointer;
    }

    .sk-download-link:hover {
      background: var(--hover);
      border-color: var(--accent2);
    }

    .sk-form-group input[type="email"] {
      width: 100%;
      padding: 10px 14px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 14px;
    }

    .sk-form-group input[type="email"]:focus {
      outline: none;
      border-color: var(--accent1);
    }

    .sk-help-text {
      font-size: 12px;
      color: var(--text);
      opacity: 0.6;
      margin-top: 6px;
      line-height: 1.4;
    }

    .sk-save-btn {
      width: 100%;
      padding: 12px;
      background: var(--accent1);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      margin-top: 24px;
    }

    .sk-save-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .sk-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 12px 20px;
      background: var(--accent1);
      color: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      display: none;
      animation: skSlideIn 0.3s;
      z-index: 2000;
    }

    .sk-toast.sk-show {
      display: block;
    }

    @keyframes skSlideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <div class="content">
    
  <!-- Modal only shows when there's NO secret key in URL or cookie -->
  <div class="sk-modal" id="secretKeyModal">
    <div class="sk-backdrop"></div>
    <div class="sk-container">
      <div class="sk-header">
        <h3>Generate Secret Key</h3>
        
      </div>
      <div class="sk-body">
        <form id="secretKeyForm">
          <div class="sk-form-group">
            <label>Secret Key</label>
            <div class="sk-input-wrapper">
              <input type="text" id="secretKey" disabled value="">
              <button type="button" class="sk-icon-btn" onclick="copySecretKey()" title="Copy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="sk-form-group">
            <label>Dashboard Link</label>
            <div class="sk-download-link" id="dashboardLinkDisplay" onclick="downloadLinkManually()">
              Click to download dashboard link
            </div>
          </div>

          <div class="sk-form-group">
            <label>Email (Optional)</label>
            <input type="email" id="email" name="email" placeholder="your@email.com">
            <div class="sk-help-text">
              The email will be used to send you your secret link in case you lost it.
            </div>
          </div>

          <button type="submit" class="sk-save-btn">Save</button>
        </form>
      </div>
    </div>
  </div>

  <div class="sk-toast" id="toast"></div>

  <script>
    // ============================================
    // LocalStorage Helper Functions
    // ============================================
    
    /**
     * Save data to localStorage (with fallback error handling)
     * @param {string} key - Storage key
     * @param {*} value - Value to store (will be JSON stringified)
     */
    function saveToLocalStorage(key, value) {
      try {
        const data = typeof value === 'string' ? value : JSON.stringify(value);
        localStorage.setItem(key, data);
      } catch (e) {
        console.warn('LocalStorage save failed:', e);
      }
    }
    
    /**
     * Get data from localStorage
     * @param {string} key - Storage key
     * @param {boolean} parseJSON - Whether to parse as JSON (default: false)
     * @returns {*} Stored value or null if not found
     */
    function getFromLocalStorage(key, parseJSON = false) {
      try {
        const data = localStorage.getItem(key);
        if (!data) return null;
        return parseJSON ? JSON.parse(data) : data;
      } catch (e) {
        console.warn('LocalStorage read failed:', e);
        return null;
      }
    }
    
    /**
     * Remove data from localStorage
     * @param {string} key - Storage key
     */
    function removeFromLocalStorage(key) {
      try {
        localStorage.removeItem(key);
      } catch (e) {
        console.warn('LocalStorage remove failed:', e);
      }
    }

    // ============================================
    // Secret Key Management
    // ============================================
    
    // Check if modal should auto-open (when NO secret key in URL or cookie)
    const autoOpenModal = <?php echo $autoOpenModal ? 'true' : 'false'; ?>;
    
    let currentSecretKey = '';
    let currentDashboardLink = '';

    // Generate a random secret key
    function generateSecretKey() {
      return 'sk_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    }

    // Initialize secret key when modal opens
    function openSecretKeyModal() {
      const modal = document.getElementById('secretKeyModal');
      const secretKeyInput = document.getElementById('secretKey');
      const dashboardLinkDisplay = document.getElementById('dashboardLinkDisplay');
      
      // Generate new secret key
      currentSecretKey = generateSecretKey();
      secretKeyInput.value = currentSecretKey;
      
      // Create dashboard link
      // *** SPACE FOR QUERY INSERTION ***
      // You can add additional query parameters here
      // Example: currentDashboardLink = `dashboard?secretkey=${currentSecretKey}&referrer=modal&timestamp=${Date.now()}`;
      currentDashboardLink = `<?php echo $url; ?>/dashboard?secretkey=${currentSecretKey}`;
      // *** END QUERY INSERTION SPACE ***
      
      dashboardLinkDisplay.textContent = currentDashboardLink;
      
      modal.classList.add('sk-active');
    }

    function closeSecretKeyModal() {
      const modal = document.getElementById('secretKeyModal');
      modal.classList.remove('sk-active');
      // Reset form
      document.getElementById('secretKeyForm').reset();
    }

    function copySecretKey() {
      navigator.clipboard.writeText(currentSecretKey).then(() => {
        showToast('Secret key copied to clipboard!');
      });
    }

    function downloadLinkFile(link) {
      const blob = new Blob([link], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'dashboard-link.txt';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    function downloadLinkManually() {
      downloadLinkFile(currentDashboardLink);
      showToast('Dashboard link downloaded!');
    }

    function showToast(message) {
      const toast = document.getElementById('toast');
      toast.textContent = message;
      toast.classList.add('sk-show');
      setTimeout(() => {
        toast.classList.remove('sk-show');
      }, 3000);
    }

    // Handle form submission
    document.getElementById('secretKeyForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const email = document.getElementById('email').value;
      
      // Save secret key to localStorage as backup
      saveToLocalStorage('user_secret_key', currentSecretKey);
      saveToLocalStorage('user_email', email);
      
      // Automatically download the link when saving
      downloadLinkFile(currentDashboardLink);
      
      // Send data to backend to save in database AND set cookie
      const formData = new FormData();
      formData.append('secretKey', currentSecretKey);
      formData.append('email', email);
      
      fetch('save_secret_key.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('Secret key saved and link downloaded!');
          setTimeout(() => {
            // Redirect to dashboard with secret key (this will set the cookie)
            window.location.href = currentDashboardLink;
          }, 1500);
        } else {
          alert(data.message || 'Error saving secret key');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error saving secret key. Please try again.');
      });
    });

    // Close modal on Escape key (only if not auto-opened)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !autoOpenModal) {
        closeSecretKeyModal();
      }
    });

    // Prevent closing modal by clicking backdrop if auto-opened (user MUST complete setup)
    document.querySelector('.sk-backdrop').addEventListener('click', function(e) {
      if (!autoOpenModal) {
        closeSecretKeyModal();
      }
    });

    // Auto-open modal if no secret key in URL or cookie
    if (autoOpenModal) {
      window.addEventListener('load', function() {
        openSecretKeyModal();
      });
    }
  </script>
</body>