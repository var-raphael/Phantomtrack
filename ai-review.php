<style>
.ai-review-item {
  padding: 0.75rem 0;
  border-bottom: 1px solid #f3f4f6;
}

.ai-review-item:last-child {
  border-bottom: none;
}

.review-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.review-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.review-message {
  font-size: 0.8rem;
  color: var(--text);
  line-height: 1.4;
  padding-left: 1.25rem;
}

#review-loading {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
  font-size: 0.875rem;
}

.htmx-indicator {
  display: none;
}

.htmx-request .htmx-indicator {
  display: block;
}

.htmx-request.view-all-btn {
  opacity: 0.6;
  pointer-events: none;
}

</style>

<div class="data-card">
  <div class="data-header">
    <h3 class="data-title">AI Review</h3>
    <button 
      class="view-all-btn"
      hx-get="api/ai-review?action=generate"
      hx-target="#ai-review-content"
      hx-swap="innerHTML"
      hx-indicator="#review-loading">
      Update Review <i class="fas fa-redo-alt"></i>
    </button>
  </div>
  <div id="ai-review-content" 
       hx-get="api/ai-review?action=load"
       hx-trigger="load"
       hx-swap="innerHTML">
    <div id="review-loading" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i> Generating insights...
    </div>
  </div>
</div>