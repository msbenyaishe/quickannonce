// QuickAnnonce UI interactions - PHP FILE VERSION
(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  // ======== AUTHENTICATION SYSTEM (localStorage-based, works with file://) ========
  
  const TEST_ACCOUNTS = {
    'admin@quickannonce.com': { password: 'admin123', name: 'Admin User', role: 'admin' },
    'user@quickannonce.com': { password: 'user123', name: 'Regular User', role: 'user' }
  };

  function initializeAccounts() {
    try {
      const existingAccounts = localStorage.getItem('accounts');
      if (!existingAccounts) {
        localStorage.setItem('accounts', JSON.stringify(TEST_ACCOUNTS));
        console.log('✓ Test accounts initialized');
      } else {
        const accounts = JSON.parse(existingAccounts);
        const merged = { ...TEST_ACCOUNTS, ...accounts };
        localStorage.setItem('accounts', JSON.stringify(merged));
        console.log('✓ Test accounts verified');
      }
    } catch (e) {
      console.error('LocalStorage error:', e);
      alert('Please enable localStorage in your browser settings');
    }
  }
  
  initializeAccounts();

  function getCurrentUser() {
    try {
      const userStr = localStorage.getItem('currentUser');
      return userStr ? JSON.parse(userStr) : null;
    } catch (e) {
      console.error('Error reading user:', e);
      return null;
    }
  }

  function login(email, password) {
    try {
      const accounts = JSON.parse(localStorage.getItem('accounts') || '{}');
      const account = accounts[email];
      
      console.log('🔐 Login attempt:', email);
      
      if (account && account.password === password) {
        const user = { email, name: account.name, role: account.role };
        localStorage.setItem('currentUser', JSON.stringify(user));
        console.log('✓ Login successful:', user.name, `(${user.role})`);
        return { success: true, user };
      }
      
      console.log('✗ Login failed: Invalid credentials');
      return { success: false, message: 'Invalid email or password' };
    } catch (e) {
      console.error('Login error:', e);
      return { success: false, message: 'Login failed. Check console.' };
    }
  }

  function register(email, password, name) {
    try {
      const accounts = JSON.parse(localStorage.getItem('accounts') || '{}');
      
      if (accounts[email]) {
        return { success: false, message: 'Email already registered' };
      }
      
      accounts[email] = { password, name, role: 'user' };
      localStorage.setItem('accounts', JSON.stringify(accounts));
      
      const user = { email, name, role: 'user' };
      localStorage.setItem('currentUser', JSON.stringify(user));
      console.log('✓ Registration successful:', name);
      return { success: true, user };
    } catch (e) {
      console.error('Registration error:', e);
      return { success: false, message: 'Registration failed. Check console.' };
    }
  }

  function logout() {
    try {
      localStorage.removeItem('currentUser');
      console.log('✓ Logged out');
      window.location.replace('index.php'); // updated to .php
    } catch (e) {
      console.error('Logout error:', e);
      window.location.href = 'index.php'; // updated to .php
    }
  }

  function updateNavbar() {
    const nav = $('.nav');
    const ctaContainer = nav?.nextElementSibling;
    const user = getCurrentUser();
    
    if (!nav) return;

    let currentPage = window.location.pathname.split('/').pop();
    if (!currentPage || currentPage === '') currentPage = 'index.php';
    
    if (currentPage === 'login.php' || currentPage === 'register.php') {
      if (ctaContainer) {
        const cta = ctaContainer.querySelector('.cta');
        if (cta) cta.style.display = 'none';
      }
      return;
    }
    
    nav.innerHTML = '';
    
    if (!user) {
      nav.innerHTML = `
        <a ${currentPage === 'index.php' ? 'class="active"' : ''} href="index.php">Home</a>
        <a ${currentPage === 'user-consult.php' ? 'class="active"' : ''} href="user-consult.php">Announcements</a>
        <a ${currentPage === 'login.php' ? 'class="active"' : ''} href="login.php">Login</a>
        <a ${currentPage === 'register.php' ? 'class="active"' : ''} href="register.php">Register</a>
        <a ${currentPage === 'contact.php' ? 'class="active"' : ''} href="contact.php">Contact</a>
      `;
      if (ctaContainer) {
        const cta = ctaContainer.querySelector('.cta');
        if (cta) cta.style.display = 'none';
      }
      
    } else if (user.role === 'admin') {
      nav.innerHTML = `
        <a ${currentPage === 'index.php' ? 'class="active"' : ''} href="index.php">Home</a>
        <a ${currentPage === 'user-consult.php' ? 'class="active"' : ''} href="user-consult.php">Announcements</a>
        <a ${currentPage === 'admin-console.php' ? 'class="active"' : ''} href="admin-console.php">Admin</a>
        <a href="#" onclick="event.preventDefault(); window.logout();">Logout</a>
      `;
      if (ctaContainer) {
        const cta = ctaContainer.querySelector('.cta');
        if (cta) cta.style.display = 'inline-block';
      }
      
    } else {
      nav.innerHTML = `
        <a ${currentPage === 'index.php' ? 'class="active"' : ''} href="index.php">Home</a>
        <a ${currentPage === 'user-consult.php' ? 'class="active"' : ''} href="user-consult.php">Announcements</a>
        <a ${currentPage === 'contact.php' ? 'class="active"' : ''} href="contact.php">Contact</a>
        <a href="#" onclick="event.preventDefault(); window.logout();">Logout</a>
      `;
      if (ctaContainer) {
        const cta = ctaContainer.querySelector('.cta');
        if (cta) cta.style.display = 'inline-block';
      }
    }
  }

  window.login = login;
  window.register = register;
  window.logout = logout;
  window.getCurrentUser = getCurrentUser;

  updateNavbar();

  const viewSwitchers = $$('.view-switch [data-view]');
  viewSwitchers.forEach(btn => {
    btn.addEventListener('click', () => {
      const container = btn.closest('[data-view-container]');
      if (!container) return;
      container.classList.remove('grid','list');
      container.classList.add(btn.dataset.view);
      $$('.view-switch [data-view]', container).forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  const list = $('[data-validate-list]');
  const counter = $('[data-pending-count]');
  const emptyState = $('[data-empty-state]');
  const toast = $('[data-toast]');

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toast.classList.remove('show'), 1800);
  }

  function updateState() {
    if (!list || !counter) return;
    const items = $$('.card', list);
    counter.textContent = items.length;
    if (items.length === 0) {
      list.classList.add('hidden');
      emptyState?.classList.remove('hidden');
    }
  }

  if (list) {
    list.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const article = btn.closest('article.card');
      const id = article?.getAttribute('data-id') || '';
      const action = btn.getAttribute('data-action');
      if (article && (action === 'approve' || action === 'reject')) {
        article.remove();
        updateState();
        showToast(`${id} ${action === 'approve' ? 'approved' : 'rejected'}`);
      }
    });
  }

  $$('[data-tabs]').forEach(container => {
    const tabs = $$('.tab', container);
    const panels = $$('.tab-panel', container);
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.getAttribute('data-target');
        tabs.forEach(t => t.classList.remove('active'));
        panels.forEach(p => p.classList.add('hidden'));
        tab.classList.add('active');
        if (target) {
          const panel = document.getElementById(target);
          if (panel) panel.classList.remove('hidden');
        }
      });
    });
  });

  $$('.pagination a').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      $$('.pagination a').forEach(x => x.classList.remove('active'));
      a.classList.add('active');
    });
  });

  const postForm = $('#post-form');
  if (postForm) {
    const fileInput = $('#photos');
    postForm.addEventListener('submit', (e) => {
      const price = parseFloat($('#price')?.value || '0');
      if (isNaN(price) || price < 0) {
        e.preventDefault();
        alert('Please enter a valid non-negative price.');
        return;
      }
      if (fileInput && fileInput.files.length) {
        for (const f of fileInput.files) {
          const isJpg = /jpe?g$/i.test(f.name);
          const under100kb = f.size <= 100 * 1024;
          if (!isJpg || !under100kb) {
            e.preventDefault();
            alert('Photos must be JPG and <= 100KB each.');
            return;
          }
        }
      }
    });
  }

  $$('form[data-validate]')?.forEach(form => {
    form.addEventListener('submit', (e) => {
      const required = $$('[required]', form);
      const invalid = required.find(inp => !inp.value?.trim());
      if (invalid) {
        e.preventDefault();
        alert('Please fill all required fields.');
      }
    });
  });

})();
