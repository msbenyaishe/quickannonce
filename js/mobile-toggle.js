// Mobile navigation toggle functionality
(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  
  function initMobileToggle() {
    const toggle = $('.mobile-toggle');
    const nav = $('.nav');
    if (toggle && nav) {
      // Remove any existing listeners by cloning
      const newToggle = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(newToggle, toggle);
      
      // Add click event listener
      newToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        nav.classList.toggle('open');
      });
    }
  }
  
  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileToggle);
  } else {
    // If DOM is already ready, wait a bit to ensure all scripts have run
    setTimeout(initMobileToggle, 100);
  }
})();

