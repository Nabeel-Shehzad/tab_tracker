// Background service worker for tracking website visits
let currentIP = null;
let sessionId = null;

// Generate unique session ID
function generateSessionId() {
  return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Get IP address
async function getIPAddress() {
  if (currentIP) return currentIP;
  
  try {
    const response = await fetch('https://api.ipify.org?format=json');
    const data = await response.json();
    currentIP = data.ip;
    return currentIP;
  } catch (error) {
    // Fallback: try to get local IP
    try {
      const response = await fetch('https://httpbin.org/ip');
      const data = await response.json();
      currentIP = data.origin.split(',')[0].trim();
      return currentIP;
    } catch (fallbackError) {
      currentIP = 'Unknown';
      return currentIP;
    }
  }
}

// Validate if URL is a real website
function isValidWebsite(url) {
  if (!url) return false;
  
  // Exclude chrome internal pages
  if (url.startsWith('chrome://') || 
      url.startsWith('chrome-extension://') || 
      url.startsWith('about:') ||
      url.startsWith('moz-extension://') ||
      url.startsWith('edge://')) {
    return false;
  }
  
  // Only allow http and https
  if (!url.startsWith('http://') && !url.startsWith('https://')) {
    return false;
  }
  
  // Exclude localhost and local IPs
  if (url.includes('localhost') || 
      url.includes('127.0.0.1') ||
      url.includes('192.168.') ||
      url.includes('10.0.') ||
      url.includes('172.16.')) {
    return false;
  }
  
  return true;
}

// Store website visit
async function storeWebsiteVisit(url, tabId) {
  console.log('Attempting to store visit:', url);
  
  if (!isValidWebsite(url)) {
    console.log('Invalid website, not storing:', url);
    return;
  }
  
  try {
    // Get existing data first to check for duplicates
    const result = await chrome.storage.local.get(['tracking_data', 'session_info']);
    const trackingData = result.tracking_data || [];
    
    // Check for recent duplicate (within last 10 seconds)
    const now = Date.now();
    const recentDuplicate = trackingData.find(visit => 
      visit.url === url && 
      (now - new Date(visit.timestamp).getTime()) < 10000 // 10 seconds
    );
    
    if (recentDuplicate) {
      console.log('Duplicate visit detected within 10 seconds, skipping:', url);
      return;
    }
    
    const ip = await getIPAddress();
    const timestamp = new Date().toISOString();
    const date = new Date().toISOString().split('T')[0];
    const time = new Date().toLocaleTimeString('en-US', { hour12: false });
    
    const visitData = {
      id: `visit_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      url: url,
      timestamp: timestamp,
      date: date,
      time: time,
      ip_address: ip,
      session_id: sessionId,
      tab_id: tabId
    };
    
    const sessionInfo = result.session_info || {
      start_time: timestamp,
      total_websites: 0,
      last_export: null
    };
    
    // Add new visit
    trackingData.push(visitData);
    sessionInfo.total_websites = trackingData.length;
    
    // Clean old data if too much (keep last 5000 entries)
    if (trackingData.length > 5000) {
      trackingData.splice(0, trackingData.length - 5000);
    }
    
    // Save to storage
    await chrome.storage.local.set({
      tracking_data: trackingData,
      session_info: sessionInfo
    });
    
    console.log('Website visit tracked successfully:', url, 'Total visits:', trackingData.length);
  } catch (error) {
    console.error('Error storing website visit:', error);
  }
}

// Initialize session
async function initializeSession() {
  sessionId = generateSessionId();
  await getIPAddress();
  console.log('Session initialized:', sessionId, 'IP:', currentIP);
}

chrome.runtime.onStartup.addListener(initializeSession);
chrome.runtime.onInstalled.addListener(initializeSession);

// Initialize immediately
initializeSession();

// Listen for tab updates
chrome.tabs.onUpdated.addListener(async (tabId, changeInfo, tab) => {
  if (changeInfo.status === 'complete' && tab.url) {
    await storeWebsiteVisit(tab.url, tabId);
  }
});

// Listen for tab activation (when user switches tabs)
chrome.tabs.onActivated.addListener(async (activeInfo) => {
  try {
    const tab = await chrome.tabs.get(activeInfo.tabId);
    if (tab.url && tab.status === 'complete') {
      await storeWebsiteVisit(tab.url, activeInfo.tabId);
    }
  } catch (error) {
    console.log('Error getting tab info:', error);
  }
});

// Handle messages from popup and content scripts
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log('Received message:', request.action);
  
  if (request.action === 'getTrackingData') {
    chrome.storage.local.get(['tracking_data', 'session_info']).then(result => {
      sendResponse({
        trackingData: result.tracking_data || [],
        sessionInfo: result.session_info || { total_websites: 0 }
      });
    }).catch(error => {
      console.error('Error getting tracking data:', error);
      sendResponse({ trackingData: [], sessionInfo: { total_websites: 0 } });
    });
    return true; // Keep message channel open for async response
  } 
  
  else if (request.action === 'clearData') {
    chrome.storage.local.clear().then(async () => {
      try {
        // Generate new session ID
        sessionId = generateSessionId();
        currentIP = null; // Reset IP to get fresh IP
        
        // Initialize new session info
        const sessionInfo = {
          start_time: new Date().toISOString(),
          total_websites: 0,
          session_id: sessionId
        };
        
        // Store new session info
        await chrome.storage.local.set({
          session_info: sessionInfo,
          tracking_data: []
        });
        
        console.log('Data cleared and tracking restarted with new session:', sessionId);
        sendResponse({ success: true });
      } catch (error) {
        console.error('Error during clear data setup:', error);
        sendResponse({ success: false, error: error.message });
      }
    }).catch(error => {
      console.error('Error clearing data:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true; // Keep message channel open for async response
  } 
  
  else if (request.action === 'restartTracking') {
    try {
      // Additional restart tracking handler if needed
      sessionId = generateSessionId();
      currentIP = null;
      sendResponse({ success: true, sessionId: sessionId });
    } catch (error) {
      console.error('Error restarting tracking:', error);
      sendResponse({ success: false, error: error.message });
    }
    return true; // Keep message channel open for async response
  } 
  
  else if (request.action === 'pageLoaded') {
    // Handle page loaded message from content script
    storeWebsiteVisit(request.url, sender.tab?.id).then(() => {
      sendResponse({ received: true });
    }).catch(error => {
      console.error('Error storing page visit:', error);
      sendResponse({ received: false, error: error.message });
    });
    return true; // Keep message channel open for async response
  }
  
  else if (request.action === 'updateLastExport') {
    chrome.storage.local.get(['session_info']).then(result => {
      const sessionInfo = result.session_info || {};
      sessionInfo.last_export = request.timestamp;
      return chrome.storage.local.set({ session_info: sessionInfo });
    }).then(() => {
      sendResponse({ success: true });
    }).catch(error => {
      console.error('Error updating last export:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true; // Keep message channel open for async response
  }
  
  else {
    // Unknown action
    console.warn('Unknown action:', request.action);
    sendResponse({ success: false, error: 'Unknown action' });
  }
});
