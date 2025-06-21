// Popup functionality
let trackingData = [];
let sessionInfo = {};

// DOM elements (will be initialized in init function)
let employeeNameInput, exportBtn, clearBtn, messageEl;

// Initialize popup
async function init() {
  console.log('Popup initializing...');
    // Initialize DOM elements
  employeeNameInput = document.getElementById('employeeName');
  exportBtn = document.getElementById('exportBtn');
  clearBtn = document.getElementById('clearBtn');
  messageEl = document.getElementById('message');
  
  console.log('DOM elements initialized:', {
    employeeNameInput, exportBtn, clearBtn, messageEl
  });
  
  try {
    await loadTrackingData();
    updateUI();
    console.log('Popup initialized successfully. Data loaded:', trackingData.length, 'visits');
  } catch (error) {
    console.error('Error initializing popup:', error);
    // Show error message but continue
    trackingData = [];
    sessionInfo = { total_websites: 0 };
    updateUI();
    showMessage('Error loading data', 'error');  }
  
  // Event listeners
  console.log('Setting up event listeners...');
  console.log('Export button:', exportBtn);
  console.log('Clear button:', clearBtn);
  
  exportBtn.addEventListener('click', exportToExcel);
  clearBtn.addEventListener('click', clearAllData);
  
  console.log('Event listeners attached successfully');
  
  // Load saved employee name
  const savedName = localStorage.getItem('employeeName');
  if (savedName) {
    employeeNameInput.value = savedName;
  }
  
  employeeNameInput.addEventListener('input', () => {
    localStorage.setItem('employeeName', employeeNameInput.value);
  });
}

// Load tracking data from extension storage
async function loadTrackingData() {
  return new Promise((resolve) => {
    try {
      chrome.runtime.sendMessage({ action: 'getTrackingData' }, (response) => {
        if (chrome.runtime.lastError) {
          console.error('Error getting tracking data:', chrome.runtime.lastError);
          trackingData = [];
          sessionInfo = { total_websites: 0 };
        } else if (response) {
          trackingData = response.trackingData || [];
          sessionInfo = response.sessionInfo || { total_websites: 0 };
        } else {
          trackingData = [];
          sessionInfo = { total_websites: 0 };
        }
        resolve();
      });
    } catch (error) {
      console.error('Error in loadTrackingData:', error);
      trackingData = [];
      sessionInfo = { total_websites: 0 };
      resolve();
    }
  });
}

// Update UI with current data
function updateUI() {
  console.log('Updating UI with data:', { trackingDataLength: trackingData.length, sessionInfo });
  // UI simplified - no stats display needed for employees
}

// Show message to user
function showMessage(text, type = 'success') {
  messageEl.textContent = text;
  messageEl.className = `message ${type}`;
  messageEl.style.display = 'block';
  
  setTimeout(() => {
    messageEl.style.display = 'none';
  }, 3000);
}

// Encrypt Excel file with password
function encryptExcelFile(data, password) {
  try {
    console.log('Encrypting file. Original data size:', data.length);
    
    // Create a password-protected container
    const excelData = new Uint8Array(data);
    
    // Simple XOR encryption with password
    const passwordBytes = new TextEncoder().encode(password);
    const encryptedData = new Uint8Array(excelData.length);
    
    for (let i = 0; i < excelData.length; i++) {
      encryptedData[i] = excelData[i] ^ passwordBytes[i % passwordBytes.length];
    }
      // Add a signature to identify encrypted files
    const signatureText = 'ENC_XLSX_nomi311:';
    const signature = new TextEncoder().encode(signatureText);
    const result = new Uint8Array(signature.length + encryptedData.length);
    result.set(signature, 0);
    result.set(encryptedData, signature.length);
    
    console.log('Encryption complete. Signature text:', signatureText);
    console.log('Signature length:', signature.length);
    console.log('Final encrypted size:', result.length);
    console.log('First 20 bytes:', Array.from(result.slice(0, 20)));
    console.log('Signature as string:', new TextDecoder().decode(result.slice(0, signature.length)));
    
    return result;
  } catch (error) {
    console.error('Encryption error:', error);
    return data;
  }
}

// Export data to Excel with password protection
async function exportToExcel() {
  const employeeName = employeeNameInput.value.trim();
  
  if (!employeeName) {
    showMessage('Please enter your name', 'error');
    return;
  }
  
  if (trackingData.length === 0) {
    showMessage('No data to export', 'error');
    return;
  }
  
  // Show loading
  exportBtn.disabled = true;
  exportBtn.querySelector('.btn-text').style.display = 'none';
  exportBtn.querySelector('.loading').style.display = 'inline';
  
  try {
    // Prepare data for Excel
    const excelData = trackingData.map((item, index) => ({
      'Sr. No.': index + 1,
      'Employee Name': employeeName,
      'Website URL': item.url,
      'Domain': new URL(item.url).hostname,
      'Date': item.date,
      'Time': item.time,
      'Timestamp': item.timestamp,
      'IP Address': item.ip_address,
      'Session ID': item.session_id
    }));
    
    // Create workbook
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(excelData);
    
    // Auto-size columns
    const colWidths = [
      { wch: 8 },   // Sr. No.
      { wch: 20 },  // Employee Name
      { wch: 50 },  // Website URL
      { wch: 25 },  // Domain
      { wch: 12 },  // Date
      { wch: 12 },  // Time
      { wch: 20 },  // Timestamp
      { wch: 15 },  // IP Address
      { wch: 15 }   // Session ID
    ];
    ws['!cols'] = colWidths;
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Website Tracking Report');
      // Generate current date for filename
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0].replace(/:/g, '-');
    const filename = `Website_Tracking_${employeeName}_${dateStr}_${timeStr}.encrypted.xlsx`;// Write the file and encrypt it
    const wbout = XLSX.write(wb, {
      bookType: 'xlsx',
      type: 'array'
    });
    
    // Encrypt the Excel file data
    const encryptedData = encryptExcelFile(wbout, 'nomi311');    // Create blob and download
    const blob = new Blob([encryptedData], { type: 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
      // Use Chrome's download API
    chrome.downloads.download({
      url: url,
      filename: filename,
      saveAs: false
    }, (downloadId) => {
      if (chrome.runtime.lastError) {
        console.error('Download error:', chrome.runtime.lastError);
        showMessage('Export failed: ' + chrome.runtime.lastError.message, 'error');      } else {
        showMessage(`Encrypted report exported: ${filename}. Use the decryption tool to open.`, 'success');
        
        // Update last export time
        chrome.runtime.sendMessage({
          action: 'updateLastExport',
          timestamp: new Date().toISOString()
        });
      }
      
      // Clean up
      URL.revokeObjectURL(url);
    });
    
  } catch (error) {
    console.error('Export error:', error);
    showMessage('Export failed: ' + error.message, 'error');
  } finally {
    // Hide loading
    exportBtn.disabled = false;
    exportBtn.querySelector('.btn-text').style.display = 'inline';
    exportBtn.querySelector('.loading').style.display = 'none';
  }
}

// Clear all tracking data and restart tracking
async function clearAllData() {
  console.log('Clear data button clicked');
  
  if (confirm('Are you sure you want to clear all tracking data? This will reset tracking and start fresh. This cannot be undone.')) {
    console.log('User confirmed clear data action');
    
    try {
      // Add timeout to prevent hanging
      const response = await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error('Message timeout'));
        }, 5000); // 5 second timeout
        
        chrome.runtime.sendMessage({ action: 'clearData' }, (response) => {
          clearTimeout(timeout);
          
          if (chrome.runtime.lastError) {
            reject(chrome.runtime.lastError);
          } else {
            resolve(response);
          }
        });
      });
      
      console.log('Clear data response:', response);
      
      if (response && response.success) {
        trackingData = [];
        sessionInfo = { total_websites: 0 };
        updateUI();
        showMessage('All data cleared successfully. Tracking restarted.', 'success');
        
        // Restart tracking session (optional, don't fail if this doesn't work)
        try {
          const restartResponse = await new Promise((resolve) => {
            const timeout = setTimeout(() => resolve({ success: false }), 2000);
            
            chrome.runtime.sendMessage({ action: 'restartTracking' }, (response) => {
              clearTimeout(timeout);
              resolve(response || { success: false });
            });
          });
          
          console.log('Restart tracking response:', restartResponse);
        } catch (restartError) {
          console.warn('Restart tracking failed (non-critical):', restartError);
        }
      } else {
        console.error('Clear data failed:', response);
        showMessage('Failed to clear data: ' + (response?.error || 'Unknown error'), 'error');
      }
    } catch (error) {
      console.error('Clear data error:', error);
      showMessage('Error clearing data: ' + error.message, 'error');
    }
  } else {
    console.log('User cancelled clear data action');
  }
}

// Initialize when popup loads
document.addEventListener('DOMContentLoaded', init);
