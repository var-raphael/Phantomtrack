
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
    
    // Handle errors gracefully
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
 