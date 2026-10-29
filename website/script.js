document.addEventListener('DOMContentLoaded', () => {
  const siteNav = document.querySelector('.site-nav');
  if (siteNav && siteNav.dataset.serverNav !== 'true') {
    const brand = document.querySelector('.brand');
    const siteRoot = brand ? new URL(brand.getAttribute('href'), window.location.href) : new URL('.', window.location.href);
    const appRoot = new URL('studenthub/', siteRoot);
    const themeToggle = document.createElement('button');
    themeToggle.type = 'button';
    themeToggle.className = 'theme-toggle';
    themeToggle.dataset.themeToggle = '';
    themeToggle.setAttribute('aria-label', 'Dark theme');
    themeToggle.setAttribute('aria-pressed', 'false');
    themeToggle.innerHTML = '<span class="theme-icon" aria-hidden="true">&#9790;</span><span>Dark theme</span>';

    function renderNavigation(user = null) {
      const links = [
        ['Home', new URL('index.html', siteRoot)],
      ];
      if (user) {
        links.push(['Dashboard', new URL(user.dashboard, appRoot)]);
      }
      links.push(
        ['Academic', new URL('academic.html', siteRoot)],
        ['Events', new URL('events.php', appRoot)],
        ['Contact', new URL('support.html', siteRoot)],
        ['Data explorer', new URL('practical 6/practical6.html', siteRoot)],
        ['Gallery', new URL('photo-slider.html', siteRoot)],
      );
      if (user?.role === 'admin') {
        links.push(
          ['Students', new URL('students.php', appRoot)],
          ['Faculty accounts', new URL('faculty.php', appRoot)],
        );
      }
      if (!user) {
        links.push(
          ['Login', new URL('login.php', appRoot)],
          ['Register', new URL('register.php', appRoot)],
        );
      }

      const items = links.map(([label, url]) => {
        const link = document.createElement('a');
        link.textContent = label;
        link.href = url.href;
        if (url.pathname === window.location.pathname) {
          link.setAttribute('aria-current', 'page');
        }
        return link;
      });

      if (user) {
        const logoutForm = document.createElement('form');
        logoutForm.className = 'nav-logout';
        logoutForm.method = 'post';
        logoutForm.action = user.logout_url;
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = user.csrf_token;
        const logoutButton = document.createElement('button');
        logoutButton.type = 'submit';
        logoutButton.textContent = 'Log out';
        logoutForm.append(csrf, logoutButton);
        items.push(logoutForm);
      }

      siteNav.replaceChildren(...items, themeToggle);
    }

    renderNavigation();
    fetch(new URL('navigation.php', appRoot), { credentials: 'same-origin' })
      .then(response => {
        if (!response.ok) throw new Error(`Navigation request failed (${response.status})`);
        return response.json();
      })
      .then(result => renderNavigation(result.user || null))
      .catch(error => console.error('Could not refresh the portal navigation.', error));
  }

  const menuToggle = document.querySelector('.menu-toggle');

  if (menuToggle && siteNav) {
    menuToggle.addEventListener('click', () => {
      const isOpen = siteNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
      menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    });

    siteNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        siteNav.classList.remove('open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open navigation');
      });
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && siteNav.classList.contains('open')) {
        siteNav.classList.remove('open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open navigation');
        menuToggle.focus();
      }
    });
  }

  const themeToggleButtons = document.querySelectorAll('[data-theme-toggle]');
  let savedTheme = 'light';

  try {
    savedTheme = localStorage.getItem('campus-portal-theme') || 'light';
  } catch (error) {
    savedTheme = 'light';
  }

  function setTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    themeToggleButtons.forEach(button => {
      button.setAttribute('aria-pressed', String(isDark));
      const icon = button.querySelector('.theme-icon');
      if (icon) icon.textContent = isDark ? '\u2600' : '\u263e';
    });

    try {
      localStorage.setItem('campus-portal-theme', isDark ? 'dark' : 'light');
    } catch (error) {
      return;
    }
  }

  setTheme(savedTheme);

  themeToggleButtons.forEach(button => {
    button.addEventListener('click', () => {
      const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      setTheme(nextTheme);
    });
  });

  document.querySelectorAll('.notification-close').forEach(button => {
    button.addEventListener('click', () => {
      const banner = button.closest('.notification-banner');
      if (banner) banner.hidden = true;
    });
  });
  const resultTab = document.querySelector('[data-open-result]');
  const resultModalOverlay = document.getElementById('result-modal-overlay');
  const closeResultButton = document.getElementById('close-result');

  if (resultTab && resultModalOverlay) {
    const resultModal = resultModalOverlay.querySelector('.result-modal');

    const openResultModal = () => {
      resultModalOverlay.classList.add('visible');
      resultModalOverlay.setAttribute('aria-hidden', 'false');
      resultTab.setAttribute('aria-expanded', 'true');
      resultModal.focus();
    };

    const closeResultModal = () => {
      resultModalOverlay.classList.remove('visible');
      resultModalOverlay.setAttribute('aria-hidden', 'true');
      resultTab.setAttribute('aria-expanded', 'false');
      resultTab.focus();
    };

    resultTab.addEventListener('click', openResultModal);

    if (closeResultButton) {
      closeResultButton.addEventListener('click', closeResultModal);
    }

    resultModalOverlay.addEventListener('click', event => {
      if (event.target === resultModalOverlay) {
        closeResultModal();
      }
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && resultModalOverlay.classList.contains('visible')) {
        closeResultModal();
      }
    });
  }
  const faqQuestions = document.querySelectorAll('.faq-question');

  faqQuestions.forEach(button => {
    button.addEventListener('click', () => {
      const item = button.closest('.faq-item');
      const isExpanded = button.getAttribute('aria-expanded') === 'true';

      faqQuestions.forEach(question => {
        const faqItem = question.closest('.faq-item');
        faqItem.classList.remove('active');
        question.setAttribute('aria-expanded', 'false');
      });

      if (!isExpanded) {
        item.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
      }
    });
  });

  const roleMessage = document.getElementById('role-message');
  document.querySelectorAll('.role-select-button').forEach(button => {
    button.addEventListener('click', () => {
      const role = button.dataset.role;
      if (!['student', 'faculty', 'admin'].includes(role)) return;
      const brand = document.querySelector('.brand');
      const siteRoot = brand ? new URL(brand.getAttribute('href'), window.location.href) : new URL('.', window.location.href);
      const loginUrl = new URL('studenthub/login.php', siteRoot);
      loginUrl.searchParams.set('role', role);
      window.location.href = loginUrl.href;
    });
  });
});
