<?php
session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    header("Location: dashboard");
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

// Check for payment status
$paymentStatus = $_GET['status'] ?? '';
$paymentMessage = $_GET['message'] ?? '';
$selectedPlan = $_GET['plan'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pricing Plans - Phantom Track</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/plans.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"/>
    <script src="https://phantomtrack-cdn.vercel.app/phantom.v1.0.0.js?trackid=track_428e608b90b694c4bee3"></script>
</head>
<body data-theme="dark">

    <!-- Payment Status Alert -->
    <?php if ($paymentStatus === 'success'): ?>
    <div class="alert alert-success" style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px 25px; border-radius: 8px; background: #10B981; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
        <i class="fas fa-check-circle"></i> Payment successful! Welcome to <?= htmlspecialchars($selectedPlan) ?> plan.
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; margin-left: 10px; cursor: pointer;">&times;</button>
    </div>
    <?php elseif ($paymentStatus === 'failed'): ?>
    <div class="alert alert-error" style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px 25px; border-radius: 8px; background: #EF4444; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
        <i class="fas fa-times-circle"></i> <?= htmlspecialchars($paymentMessage ?: 'Payment failed') ?>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; margin-left: 10px; cursor: pointer;">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-tags"></i> Pricing Plans</h1>
            <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                <span id="themeIcon"></span>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="pricing-container">

            <div class="pricing-header">
                <h1>Choose Your Perfect Plan</h1>
                <p>Start free and scale as you grow. All plans include full API access.</p>
                
                <!-- Current Plan Info -->
                <div id="currentPlanInfo" 
                     hx-get="api/check_plan" 
                     hx-trigger="load" 
                     hx-swap="innerHTML"
                     style="min-height: 60px;">
                    <!-- Content loads here via HTMX -->
                </div>
            </div>

            <!-- Pricing Grid -->
            <div class="pricing-grid">
                
                <!-- Free Plan -->
                <div class="pricing-card" id="free-plan">
                    <div class="plan-name">Free</div>
                    <div class="plan-price">$0<span>/month</span></div>
                    <div class="plan-requests">10,000 requests/month</div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> Full API & Features Access</li>
                        <li><i class="fas fa-check-circle"></i> Email Support</li>
                        <li><i class="fas fa-check-circle"></i> 7-Day Refund Policy</li>
                        <li><i class="fas fa-check-circle"></i> Quick Customer Support</li>
                        
                        <li><i class="fas fa-check-circle"></i> Custom Integration</li>
                    </ul>
                    <button class="btn-outline btn-block" onclick="downgradeToFree()" id="freeBtn" style="display: none;">
                        <i class="fas fa-arrow-down"></i> Downgrade to Free
                    </button>
                    <button class="btn-outline btn-block" disabled id="currentPlanBtn" style="display: none;">
                        <i class="fas fa-check"></i> Current Plan
                    </button>
                </div>

                <!-- Pro Plan -->
                <div class="pricing-card featured" id="pro-plan">
                    <div class="plan-name">Pro</div>
                    <div class="plan-price">$3<span>/month</span></div>
                    <div class="plan-requests">30,000 requests/month</div>
                     <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> Full API & Features Access</li>
                        <li><i class="fas fa-check-circle"></i> Email Support</li>
                        <li><i class="fas fa-check-circle"></i> 7-Day Refund Policy</li>
                        <li><i class="fas fa-check-circle"></i> Quick Customer Support</li>
                        
                        <li><i class="fas fa-check-circle"></i> Custom Integration</li>
                    </ul>
                    <button class="btn btn-block" onclick="selectPlan('pro')">
                        Choose Pro
                    </button>
                </div>

                <!-- Premium Plan -->
                <div class="pricing-card" id="premium-plan">
                    <div class="plan-name">Premium</div>
                    <div class="plan-price">$5<span>/month</span></div>
                    <div class="plan-requests">60,000 requests/month</div>
                   <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> Full API & Features Access</li>
                        <li><i class="fas fa-check-circle"></i> Email Support</li>
                        <li><i class="fas fa-check-circle"></i> 7-Day Refund Policy</li>
                        <li><i class="fas fa-check-circle"></i> Quick Customer Support</li>
                        
                        <li><i class="fas fa-check-circle"></i> Custom Integration</li>
                    </ul>
                    <button class="btn-outline btn-block" onclick="selectPlan('premium')">
                        Choose Premium
                    </button>
                </div>

                <!-- Enterprise Plan -->
                <div class="pricing-card" id="enterprise-plan">
                    <div class="plan-name">Enterprise</div>
                    <div class="plan-price">$8<span>/month</span></div>
                    <div class="plan-requests">100,000 requests/month</div>
                     <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> Full API & Features Access</li>
                        <li><i class="fas fa-check-circle"></i> Email Support</li>
                        <li><i class="fas fa-check-circle"></i> 7-Day Refund Policy</li>
                        <li><i class="fas fa-check-circle"></i> Quick Customer Support</li>
                        
                        <li><i class="fas fa-check-circle"></i> Custom Integration</li>
                    </ul>
                    <button class="btn-outline btn-block" onclick="selectPlan('enterprise')">
                        Choose Enterprise
                    </button>
                </div>

                <!-- Lifetime Access Plan -->
                <div class="pricing-card" id="lifetime-plan" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(6, 182, 212, 0.15)); border-color: var(--accent1); border-width: 3px;">
                    <div class="plan-name" style="display: flex; align-items: center; gap: 8px;">
                        Lifetime
                        <span class="lifetime-badge">BEST VALUE</span>
                    </div>
                    <div class="plan-price">$20<span> one-time</span></div>
                    <div class="plan-requests">Unlimited requests</div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> Full API & Features Access</li>
                        <li><i class="fas fa-check-circle"></i> Email Support</li>
                        <li><i class="fas fa-check-circle"></i> 7-Day Refund Policy</li>
                        <li><i class="fas fa-check-circle"></i> Quick Customer Support</li>
                        
                        <li><i class="fas fa-check-circle"></i> Custom Integration</li>
                        
                        <li><i class="fas fa-check-circle"></i> No Recurring Fees</li>
                    </ul>
                    <button class="btn btn-block" onclick="selectPlan('lifetime')">
                        <i class="fas fa-crown"></i> Get Lifetime Access
                    </button>
                </div>

            </div>

            <!-- Promo Code Section -->
            <div class="promo-section">
                <h3><span><i class="fas fa-gift"></i></span> Have a Promo Code?</h3>
                <div class="promo-input-group">
                    <input type="text" class="promo-input" placeholder="ENTER CODE" id="promoCode">
                    <button class="btn" onclick="applyPromo()">
                        <i class="fas fa-check"></i> Apply
                    </button>
                </div>
                <div id="promoMessage" style="margin-top: 10px; text-align: center;"></div>
            </div>

            <!-- Disclaimer -->
            <div class="disclaimer">
                <h4><i class="fas fa-shield-alt"></i> 7-Day Money-Back Guarantee</h4>
                <p>
                    All fees are fully refundable within 7 days of purchase. If you experience any issues or are not satisfied with our service, simply contact us at 
                    <a href="mailto:phantomtrack@gmail.com">phantomtrack@gmail.com</a> and we'll process your refund immediately. No questions asked.
                </p>
            </div>

        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="text-align: center; color: white;">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 20px;"></i>
            <h3>Processing Payment...</h3>
            <p>Please wait while we redirect you to Paystack</p>
        </div>
    </div>

    <script src="assets/js/toogle.js"></script>
<script src="assets/js/htmx.min.js"></script>
<script>
    let currentPromoCode = '';
    let promoDiscount = 0;
    let userCurrentTier = 'free';

    // Handle current plan display
    document.body.addEventListener('htmx:afterSettle', function(event) {
        if (event.detail.target.id === 'currentPlanInfo') {
            const response = JSON.parse(event.detail.xhr.response);
            if (response.success) {
                const data = response.data;
                userCurrentTier = data.tier;
                
                const planInfo = `
                    <div style="background: var(--card-bg); padding: 15px; border-radius: 8px; margin: 20px 0; border: 2px solid var(--accent1);">
                        <strong>Current Plan:</strong> ${data.display_plan} ${data.tier === 'paid' ? '💎' : '🆓'} | 
                        <strong>Status:</strong> <span style="color: ${data.subscription_status === 'active' ? '#10B981' : '#EF4444'}">${data.subscription_status.toUpperCase()}</span> | 
                        <strong>Usage:</strong> ${data.usage.current.toLocaleString()} / ${data.usage.limit.toLocaleString()} requests 
                        <span style="color: ${data.usage.percentage > 80 ? '#EF4444' : '#10B981'}">(${data.usage.percentage.toFixed(1)}%)</span>
                        ${data.subscription_ends ? '<br><strong>Renews:</strong> ' + data.subscription_ends : ''}
                    </div>
                `;
                event.detail.target.innerHTML = planInfo;
                
                // Show/hide downgrade button
                if (data.tier === 'paid') {
                    document.getElementById('freeBtn').style.display = 'block';
                    document.getElementById('currentPlanBtn').style.display = 'none';
                } else {
                    document.getElementById('freeBtn').style.display = 'none';
                    document.getElementById('currentPlanBtn').style.display = 'block';
                }
            }
        }
    });

    // Main selectPlan function with email check
    function selectPlan(plan) {
        // First, check if user has email
        fetch('api/check_email')
        .then(response => response.json())
        .then(emailData => {
            if (!emailData.has_email) {
                // Show email prompt modal
                showEmailModal(plan);
                return;
            }
            
            // Email exists, proceed with payment
            proceedWithPayment(plan);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    function showEmailModal(plan) {
        const modal = document.createElement('div');
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div style="background: var(--card-bg); padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; border: 2px solid var(--accent1);">
                    <h2 style="margin-bottom: 15px; color: var(--accent1);">
                        <i class="fas fa-envelope"></i> Email Required
                    </h2>
                    <p style="margin-bottom: 20px; color: var(--text-secondary);">
                        We need your email address to process payments and send receipts.
                    </p>
                    <input type="email" 
                           id="emailInput" 
                           placeholder="your@email.com" 
                           style="width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 2px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-size: 16px;">
                    <div style="display: flex; gap: 10px;">
                        <button onclick="saveEmailAndProceed('${plan}')" 
                                class="btn" 
                                style="flex: 1;">
                            <i class="fas fa-check"></i> Enter Email
                        </button>
                        <button onclick="this.closest('div').parentElement.parentElement.remove()" 
                                class="btn-outline" 
                                style="flex: 0 0 auto; padding: 0 20px;">
                            Cancel
                        </button>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: var(--text-secondary); text-align: center;">
                        <i class="fas fa-lock"></i> Your email is secure and won't be shared
                    </p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('emailInput').focus();
    }

    function saveEmailAndProceed(plan) {
        const email = document.getElementById('emailInput').value.trim();
        
        if (!email || !email.includes('@')) {
            alert('Please enter a valid email address');
            return;
        }
        
        // Save email to database
        const formData = new FormData();
        formData.append('email', email);
        
        fetch('api/update_email', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            
            if (data.success) {
                // Hide the old modal
                const oldModals = document.querySelectorAll('[style*="position: fixed"]');
                oldModals.forEach(modal => {
                    modal.style.display = 'none';
                });
                
                // Create a brand new success modal
                const successModal = document.createElement('div');
                successModal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 99999; display: flex; align-items: center; justify-content: center;';
                successModal.innerHTML = `
                    <div style="background: var(--card-bg); padding: 40px; border-radius: 12px; max-width: 500px; width: 90%; border: 2px solid #10B981; text-align: center;">
                        <div style="font-size: 64px; margin-bottom: 20px; color: #10B981;">✓</div>
                        <h2 style="color: #10B981; margin-bottom: 15px; font-size: 24px;">Email Saved Successfully!</h2>
                        <p style="color: var(--text-secondary); margin-bottom: 25px; font-size: 16px;">
                            You can now retry your plan purchase.
                        </p>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            Refreshing page in <span id="countdown" style="color: #10B981; font-weight: bold; font-size: 20px;">3</span> seconds...
                        </p>
                    </div>
                `;
                document.body.appendChild(successModal);
                
                // Countdown timer
                let seconds = 3;
                const countdownInterval = setInterval(() => {
                    seconds--;
                    const countdownEl = document.getElementById('countdown');
                    if (countdownEl) {
                        countdownEl.textContent = seconds;
                    }
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
                
                // Refresh page after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                alert('Error saving email: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('An error occurred. Please try again. Check console for details.');
        });
    }

    function proceedWithPayment(plan) {
        // Confirm upgrade/downgrade
        if (userCurrentTier === 'paid') {
            if (!confirm(`You are currently on a paid plan. Upgrading will CANCEL your current subscription and start a new one for the ${plan.toUpperCase()} plan. Continue?`)) {
                return;
            }
        }
        
        const loadingModal = document.getElementById('loadingModal');
        loadingModal.style.display = 'flex';

        const formData = new FormData();
        formData.append('plan', plan);
        formData.append('promo_code', currentPromoCode);

        fetch('api/initialize_payment', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.style.display = 'none';
            
            if (data.success) {
                window.location.href = data.data.authorization_url;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            loadingModal.style.display = 'none';
            alert('An error occurred. Please try again.');
            console.error('Error:', error);
        });
    }

    function downgradeToFree() {
        if (!confirm('⚠️ Are you sure you want to downgrade to the FREE plan?\n\n• Your subscription will be CANCELLED\n• Request limit will drop to 10,000/month\n• This takes effect immediately\n\nContinue?')) {
            return;
        }
        
        const loadingModal = document.getElementById('loadingModal');
        loadingModal.style.display = 'flex';
        loadingModal.querySelector('h3').textContent = 'Processing Downgrade...';
        loadingModal.querySelector('p').textContent = 'Cancelling your subscription';
        
        fetch('api/downgrade_to_free', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.style.display = 'none';
            
            if (data.success) {
                alert('✓ ' + data.message);
                location.reload();
            } else {
                alert('✗ Error: ' + data.message);
            }
        })
        .catch(error => {
            loadingModal.style.display = 'none';
            alert('Error processing downgrade: ' + error);
            console.error('Error:', error);
        });
    }

    function applyPromo() {
        const code = document.getElementById('promoCode').value.trim().toUpperCase();
        const messageDiv = document.getElementById('promoMessage');
        
        if (code === '') {
            messageDiv.innerHTML = '<span style="color: #EF4444;">Please enter a promo code</span>';
            return;
        }

        // Validate promo
        const formData = new FormData();
        formData.append('promo_code', code);

        fetch('api/validate_promo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPromoCode = code;
                promoDiscount = data.data.discount_value;
                
                messageDiv.innerHTML = `<span style="color: #10B981;"><i class="fas fa-check-circle"></i> ${data.message} - ${data.data.discount_type === 'percentage' ? data.data.discount_value + '%' : '$' + data.data.discount_value} off!</span>`;
                document.getElementById('promoCode').style.borderColor = 'var(--accent1)';
            } else {
                messageDiv.innerHTML = `<span style="color: #EF4444;"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                document.getElementById('promoCode').style.borderColor = '#EF4444';
                currentPromoCode = '';
                promoDiscount = 0;
            }
        })
        .catch(error => {
            messageDiv.innerHTML = '<span style="color: #EF4444;">Error validating promo code</span>';
            console.error('Error:', error);
        });
    }

    // Enter key to apply promo
    document.getElementById('promoCode').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyPromo();
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
    }, 5000);
</script>
</body>
</html>