<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Debug - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .debug-box {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 1.5rem;
      margin: 1rem 0;
      font-family: monospace;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .debug-section {
      margin-bottom: 2rem;
    }
    .btn-group {
      display: flex;
      gap: 10px;
      margin: 1rem 0;
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php">
        <span class="brand-logo">QA</span>
        <span class="brand-name">QuickAnnonce</span>
      </a>
      <nav class="nav">
        <a href="index.php">Home</a>
      </nav>
      <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
    </div>
  </header>

  <main class="container content">
    <h1>Authentication Debug Page</h1>
    <p>This page helps you debug authentication issues.</p>

    <div class="debug-section">
      <h2>Current User</h2>
      <div class="debug-box" id="current-user"></div>
    </div>

    <div class="debug-section">
      <h2>All Accounts</h2>
      <div class="debug-box" id="all-accounts"></div>
    </div>

    <div class="debug-section">
      <h2>Actions</h2>
      <div class="btn-group">
        <button class="btn" onclick="refreshData()">Refresh Data</button>
        <button class="btn secondary" onclick="clearAll()">Clear All Data</button>
        <button class="btn ghost" onclick="resetAccounts()">Reset Test Accounts</button>
      </div>
    </div>

    <div class="debug-section">
      <h2>Quick Login Test</h2>
      <div class="btn-group">
        <button class="btn" onclick="testAdminLogin()">Test Admin Login</button>
        <button class="btn" onclick="testUserLogin()">Test User Login</button>
        <button class="btn ghost" onclick="testLogout()">Test Logout</button>
      </div>
      <div id="test-result" class="debug-box" style="display: none; margin-top: 1rem;"></div>
    </div>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
    </div>
  </footer>

  <script src="js/main.js"></script>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();

    function refreshData() {
      const currentUser = localStorage.getItem('currentUser');
      const accounts = localStorage.getItem('accounts');

      document.getElementById('current-user').textContent = currentUser 
        ? JSON.stringify(JSON.parse(currentUser), null, 2) 
        : 'No user logged in';

      document.getElementById('all-accounts').textContent = accounts 
        ? JSON.stringify(JSON.parse(accounts), null, 2) 
        : 'No accounts found';
    }

    function clearAll() {
      if (confirm('This will clear all authentication data. Continue?')) {
        localStorage.removeItem('currentUser');
        localStorage.removeItem('accounts');
        alert('All data cleared!');
        refreshData();
      }
    }

    function resetAccounts() {
      const TEST_ACCOUNTS = {
        'admin@quickannonce.com': { password: 'admin123', name: 'Admin User', role: 'admin' },
        'user@quickannonce.com': { password: 'user123', name: 'Regular User', role: 'user' }
      };
      localStorage.setItem('accounts', JSON.stringify(TEST_ACCOUNTS));
      alert('Test accounts reset!');
      refreshData();
    }

    function showTestResult(message, success) {
      const resultDiv = document.getElementById('test-result');
      resultDiv.style.display = 'block';
      resultDiv.style.background = success ? '#d1fae5' : '#fee2e2';
      resultDiv.style.color = success ? '#065f46' : '#991b1b';
      resultDiv.textContent = message;
      refreshData();
    }

    function testAdminLogin() {
      const result = window.login('admin@quickannonce.com', 'admin123');
      if (result.success) {
        showTestResult(`Success! Logged in as: ${result.user.name} (${result.user.role}). Redirecting to index.php...`, true);
        setTimeout(() => {
          window.location.replace('index.php');
        }, 1500);
      } else {
        showTestResult(`Failed: ${result.message}`, false);
      }
    }

    function testUserLogin() {
      const result = window.login('user@quickannonce.com', 'user123');
      if (result.success) {
        showTestResult(`Success! Logged in as: ${result.user.name} (${result.user.role}). Redirecting to index.php...`, true);
        setTimeout(() => {
          window.location.replace('index.php');
        }, 1500);
      } else {
        showTestResult(`Failed: ${result.message}`, false);
      }
    }

    function testLogout() {
      localStorage.removeItem('currentUser');
      showTestResult('Logged out successfully! Redirecting...', true);
      setTimeout(() => {
        window.location.replace('index.php');
      }, 1000);
    }

    // Load data on page load
    refreshData();
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
 </html>