<style>
.htmx-indicator {
  display: none;
}

/* Show indicator when HTMX is making a request */
.htmx-request .htmx-indicator,
.htmx-request.htmx-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
}

/* Spinner positioning and styling */
#spinner-pages {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #6366F1;
  font-size: 32px;
  z-index: 10;
}


</style>


<div class="data-grid">
          <!-- Top Pages -->
          <div class="data-card">
            <div class="data-header">
              <h3 class="data-title">Top Pages</h3>
                      <button 
      class="view-all-btn"
      hx-get="api/pages?type=pages&view=all"
      hx-target="#modal-content"
      data-modal-title="	Page Views">
      View All <i class="fas fa-chevron-right"></i>
    </button>
            </div>
            
            <ul class="data-list"
              hx-get="api/pages?type=pages" 
   				 hx-trigger="load" 
   				 hx-swap="outerHTML"
    				 hx-indicator="#spinner-pages">
              <span id="spinner-pages" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
            </ul>
          </div>
          