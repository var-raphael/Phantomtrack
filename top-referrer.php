<style>

/* Spinner positioning and styling */
#spinner-referrer {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #6366F1;
  font-size: 32px;
  z-index: 10;
}


.data-item-detailed {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
}

.data-item-detailed .data-label {
  font-weight: 600;
  color: var(--text);
}

.data-metrics {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.data-metric {
  font-size: 0.8rem;
  color: #6b7280;
}

.data-metric small {
  color: #9ca3af;
  font-size: 0.75rem;
}

</style>


<div class="data-card">
            <div class="data-header">
              <h3 class="data-title">Top Referrers</h3>
                         <button 
      class="view-all-btn"
      hx-get="api/pages?type=referrers&view=all"
      hx-target="#modal-content"
      data-modal-title="	Top Referrer">
      View All <i class="fas fa-chevron-right"></i>
    </button>
            </div>
             <ul class="data-list"
              hx-get="api/pages?type=referrers" 
   				 hx-trigger="load" 
   				 hx-swap="outerHTML"
    				 hx-indicator="#spinner-referrer">
              <span id="spinner-referrer" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
            </ul>
          </div>