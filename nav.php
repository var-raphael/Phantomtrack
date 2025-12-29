<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
      
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">PhantomTrack</div>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
      <i id="themeIcon" class="fas fa-moon"></i>
    </button>
  </div>
  
  <ul class="nav-menu">
    <li class="nav-item">
      <a href="dashboard" class="nav-link active">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" 
         class="nav-link" 
         hx-get="api/get-websites"
         hx-trigger="click"
         hx-target="#website-list"
         hx-swap="innerHTML"
         hx-indicator="#websites-spinner"
         onclick="event.preventDefault(); toggleWebsiteDropdown(this);">
        <i class="fas fa-globe"></i>
        <span>Websites</span>
        <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.8em;" id="websites-chevron"></i>
        <i class="fas fa-spinner fa-spin htmx-indicator" id="websites-spinner" style="margin-left: 5px; font-size: 0.8em; display: none;"></i>
      </a>
      <ul id="website-dropdown" style="display: none; padding-left: 30px; margin: 0; list-style: none;">
        <li style="padding: 8px 0;">
          <button class="btn-outline" 
                  hx-get="api/add-site?type=add"
                  hx-target="#modal-content"
                  data-modal-title="Add Website">
            Add Website <i class="fa fa-plus-circle"></i>
          </button>
          <br><br>
        </li>
        <div id="website-list">
          <li style="padding: 8px 0; color: var(--text-secondary);">
            <i class="fas fa-circle-notch fa-spin"></i> Loading websites...
          </li>
        </div>
      </ul>
    </li>
    <li class="nav-item">
      <a href="export" class="nav-link">
        <i class="fas fa-door-open"></i>
        <span>Export Data</span>
      </a>
    </li>
    
    <li class="nav-item">
      <a href="settings" class="nav-link">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
    </li>
    
    <li class="nav-item">
      <a href="plan" class="nav-link">
        <i class="fas fa-crown"></i>
        <span>Plans</span>
      </a>
    </li>
    
    <li class="nav-item">
      <a href="documentation" class="nav-link">
        <i class="fas fa-book-open"></i>
        <span>Documentations</span>
      </a>
    </li>
    
    <li class="nav-item">
      <a href="hire-me" class="nav-link">
        <i class="fas fa-laptop-code"></i>
        <span>Hire Me</span>
      </a>
    </li>
  </ul>
</aside>

<script>
function toggleWebsiteDropdown(element) {
  const dropdown = document.getElementById('website-dropdown');
  const chevron = document.getElementById('websites-chevron');
  
  if (dropdown.style.display === 'none' || dropdown.style.display === '') {
    dropdown.style.display = 'block';
    chevron.style.transform = 'rotate(180deg)';
  } else {
    dropdown.style.display = 'none';
    chevron.style.transform = 'rotate(0deg)';
  }
}



function editWebsite(websiteId) {
    const newName = prompt('Enter new website URL:');
    
    if (newName === null) {
        // User cancelled
        return;
    }
    
    if (!newName || newName.trim() === '') {
        alert('Website URL cannot be empty');
        return;
    }
    
    // Validate URL format
    if (!newName.startsWith('https://')) {
        alert('URL must start with https://');
        return;
    }
    
    // Show loading
    const formData = new FormData();
    formData.append('website_id', websiteId);
    formData.append('new_name', newName.trim());
    
    fetch('api/edit-website', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Website updated successfully!');
            // Refresh the website list
            refreshWebsiteList();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the website');
    });
}

function deleteWebsite(websiteId) {
    const confirmed = confirm('Are you sure you want to delete this website? This action cannot be undone.');
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('website_id', websiteId);
    
    fetch('api/delete-website', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
    alert('Website deleted successfully!');
    
    window.location.href = 'dashboard';
} else {
    alert('Error: ' + data.error);
}
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the website');
    });
}

function refreshWebsiteList() {
    // Trigger HTMX to reload the website list
    const websiteLink = document.querySelector('[hx-get="api/get-websites"]');
    if (websiteLink) {
        htmx.trigger(websiteLink, 'click');
    }
}
</script>

<style>
#websites-chevron {
  transition: transform 0.3s ease;
}

.htmx-indicator {
  display: none;
}

.htmx-request .htmx-indicator {
  display: inline-block;
}

.htmx-request.htmx-indicator {
  display: inline-block;
}

#website-list li a {
  color: var(--text);
  text-decoration: none;
  display: block;
  padding: 8px 0;
  transition: color 0.2s;
}

#website-list li a:hover {
  color: var(--accent1);
}

.website-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.website-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.website-badge {
  font-size: 0.7em;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--accent1);
  color: white;
  margin-left: 8px;
}
</style>