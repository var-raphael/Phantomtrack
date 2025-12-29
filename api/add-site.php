<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "add";

switch ($type) {
	case "add":
?>
		<style>
			.input-container {
				display: flex;
				flex-direction: column;
				gap: 16px;
				max-width: 600px;
			}
			
			#input-section {
				display: flex;
				flex-direction: column;
				gap: 12px;
			}
			
			.input-wrapper {
				display: flex;
				flex-direction: column;
				gap: 12px;
				align-items: center;
			}
			
			#website-name, #script-output {
				flex: 1;
				padding: 12px 16px;
				background: var(--card);
				border: 1px solid var(--border);
				border-radius: 8px;
				color: var(--text);
				font-size: 14px;
				transition: all 0.2s ease;
				backdrop-filter: blur(10px);
			}
			
			#website-name::placeholder {
				color: var(--text);
				opacity: 0.5;
			}
			
			#website-name:focus {
				outline: none;
				border-color: var(--accent1);
				background: var(--hover);
				box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
			}
			
			#website-name:hover:not(:disabled) {
				border-color: var(--accent1);
			}
			
			#script-output {
				opacity: 0.7;
				cursor: not-allowed;
				width: 100%;
				display: block;
			}
			
			.submit-btn {
				width: 100%;
				padding: 12px 24px;
				background: linear-gradient(135deg, var(--accent1), var(--accent2));
				color: white;
				border: none;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s ease;
			}
			
			.copy-btn {
				padding: 12px 20px;
				background: linear-gradient(135deg, var(--accent1), var(--accent2));
				color: white;
				border: none;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s ease;
				white-space: nowrap;
				width: 100%;
				display: block;
			}
			
			.submit-btn:hover, .copy-btn:hover {
				transform: translateY(-2px);
				box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
			}
			
			.submit-btn:active, .copy-btn:active {
				transform: translateY(0);
			}
			
			.submit-btn:disabled {
				opacity: 0.6;
				cursor: not-allowed;
				transform: none;
			}
			
			.error-msg {
				color: #ef4444;
				font-size: 13px;
				text-align: center;
				display: none;
			}
			
			.success-msg {
				color: #10b981;
				font-size: 13px;
				text-align: center;
				display: none;
			}
			
			.hidden {
				display: none !important;
			}
		</style>
		
		<div class="input-container">
			<div id="input-section">
				<input type="url" id="website-name" name="web-url" placeholder="https://example.com" required>
				<div class="error-msg" id="error-msg"></div>
				<button type="button" class="submit-btn" id="add-btn">Add Website</button>
			</div>
			
			<div id="output-section" class="hidden">
				<div class="input-wrapper">
					<input type="text" id="script-output" disabled>
					<button type="button" class="copy-btn" id="copy-btn"><i class="fa fa-clipboard"></i> Copy</button>
				</div>
				<div class="success-msg" id="success-msg"></div>
			</div>
		</div>
		
		<script>
		(function() {
			const websiteInput = document.getElementById('website-name');
			const addBtn = document.getElementById('add-btn');
			const errorMsg = document.getElementById('error-msg');
			const inputSection = document.getElementById('input-section');
			const outputSection = document.getElementById('output-section');
			const scriptOutput = document.getElementById('script-output');
			const copyBtn = document.getElementById('copy-btn');
			const successMsg = document.getElementById('success-msg');
			
			function validateUrl(url) {
				if (!url.startsWith('https://')) {
					return { valid: false, error: 'URL must start with https://' };
				}
				
				if (url.includes('localhost') || url.includes('127.0.0.1')) {
					return { valid: false, error: 'Localhost URLs are not allowed' };
				}
				
				try {
					const urlObj = new URL(url);
					if (urlObj.protocol !== 'https:') {
						return { valid: false, error: 'Only HTTPS URLs are allowed' };
					}
					return { valid: true };
				} catch (e) {
					return { valid: false, error: 'Invalid URL format' };
				}
			}
			
			function generateTrackId() {
				return 'track_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
			}
			
			function downloadScript(scriptText, filename) {
				const blob = new Blob([scriptText], { type: 'text/plain' });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = filename;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				window.URL.revokeObjectURL(url);
			}
			
			addBtn.addEventListener('click', function() {
				const url = websiteInput.value.trim();
				
				if (!url) {
					errorMsg.textContent = 'Please enter a URL';
					errorMsg.style.display = 'block';
					return;
				}
				
				const validation = validateUrl(url);
				
				if (!validation.valid) {
					errorMsg.textContent = validation.error;
					errorMsg.style.display = 'block';
					return;
				}
				
				errorMsg.style.display = 'none';
				addBtn.disabled = true;
				addBtn.textContent = 'Adding...';
				
				const trackId = generateTrackId();
				const scriptTag = '<script src="phantom.js?trackid=' + trackId + '"><\/script>';
				
				// Use /api/process to stay in the api folder
				fetch('/phantomtrack/api/process', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'url=' + encodeURIComponent(url) + '&trackid=' + encodeURIComponent(trackId)
				})
				.then(response => {
					console.log('Response status:', response.status);
					console.log('Response URL:', response.url);
					if (!response.ok) {
						throw new Error('Server returned ' + response.status);
					}
					return response.text();
				})
				.then(text => {
					console.log('Raw response:', text);
					try {
						const data = JSON.parse(text);
						console.log('Parsed data:', data);
						if (data.success) {
							scriptOutput.value = scriptTag;
							inputSection.classList.add('hidden');
							outputSection.classList.remove('hidden');
						} else {
							errorMsg.textContent = data.error || 'An error occurred. Please try again.';
							errorMsg.style.display = 'block';
							addBtn.disabled = false;
							addBtn.textContent = 'Add Website';
						}
					} catch (e) {
						console.error('JSON parse error:', e);
						errorMsg.textContent = 'Invalid server response: ' + text.substring(0, 100);
						errorMsg.style.display = 'block';
						addBtn.disabled = false;
						addBtn.textContent = 'Add Website';
					}
				})
				.catch(error => {
					console.error('Fetch error:', error);
					errorMsg.textContent = 'Connection error: ' + error.message;
					errorMsg.style.display = 'block';
					addBtn.disabled = false;
					addBtn.textContent = 'Add Website';
				});
			});
			
			copyBtn.addEventListener('click', function() {
				const scriptText = scriptOutput.value;
				
				navigator.clipboard.writeText(scriptText).then(function() {
					const uniqueId = 'script_' + Date.now();
					downloadScript(scriptText, uniqueId + '.txt');
					
					successMsg.textContent = '✓ Copied and downloaded!';
					successMsg.style.display = 'block';
					
					setTimeout(function() {
						successMsg.style.display = 'none';
					}, 3000);
				}).catch(function(err) {
					alert('Failed to copy to clipboard');
				});
			});
			
			websiteInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					addBtn.click();
				}
			});
		})();
		</script>
<?php
		break;
	 
	default:
		echo "error";
}
?>
