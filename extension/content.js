// Content script to detect page load completion
(function() {
  'use strict';
  
  console.log('Content script loaded for:', window.location.href);
  
  // Check if page is fully loaded and valid
  function checkPageLoad() {
    if (document.readyState === 'complete') {
      console.log('Page load complete, checking validity...');
      
      // Verify this is not an error page
      const title = document.title.toLowerCase();
      const bodyText = document.body ? document.body.innerText.toLowerCase() : '';
      
      // Check for error indicators
      const errorIndicators = [
        '404', 'not found', 'page not found',
        '500', 'internal server error',
        'connection failed', 'server error',
        'access denied', 'forbidden',
        'service unavailable', 'timeout'
      ];
      
      const isErrorPage = errorIndicators.some(indicator => 
        title.includes(indicator) || bodyText.includes(indicator)
      );
      
      // Only track if it's not an error page and has real content
      if (!isErrorPage && document.body && document.body.children.length > 0) {
        console.log('Valid page detected, sending to background script');
        chrome.runtime.sendMessage({
          action: 'pageLoaded',
          url: window.location.href,
          title: document.title
        }, (response) => {
          if (chrome.runtime.lastError) {
            console.error('Error sending message:', chrome.runtime.lastError);
          } else {
            console.log('Message sent successfully');
          }
        });
      } else {
        console.log('Invalid page, not tracking:', { isErrorPage, hasBody: !!document.body, childrenCount: document.body?.children.length });
      }
    }
  }
  
  // Check immediately if page is already loaded
  checkPageLoad();
  
  // Also check when page finishes loading
  if (document.readyState !== 'complete') {
    window.addEventListener('load', checkPageLoad);
    document.addEventListener('DOMContentLoaded', checkPageLoad);
  }
  
  // Check for dynamic content changes (SPA applications)
  let lastUrl = window.location.href;
  const observer = new MutationObserver(() => {
    if (window.location.href !== lastUrl) {
      console.log('URL changed from', lastUrl, 'to', window.location.href);
      lastUrl = window.location.href;
      setTimeout(checkPageLoad, 1000); // Wait for content to load
    }
  });
  
  if (document.body) {
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  } else {
    // If body not ready yet, wait for it
    document.addEventListener('DOMContentLoaded', () => {
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    });
  }
})();
