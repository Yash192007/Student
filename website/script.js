document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.querySelector('.menu-toggle');
  const siteNav = document.getElementById('site-nav');

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

  const roleButtons = document.querySelectorAll('.role-select-button');
  const loginModal = document.getElementById('login-modal');
  const loginRole = document.getElementById('login-role');
  const loginForm = document.getElementById('login-form');
  const loginError = document.getElementById('login-error');
  const loginClose = document.getElementById('login-close');
  const loginBackdrop = document.querySelector('.login-modal-backdrop');
  const roleMessage = document.getElementById('role-message');

  const roleLabels = {
    student: 'Student',
    faculty: 'Faculty',
    admin: 'Admin',
  };

  const credentials = {
    student: { id: 'student01', password: 'student123', redirect: 'student-dashboard.html' },
    faculty: { id: 'faculty01', password: 'faculty123', redirect: 'faculty-dashboard.html' },
    admin: { id: 'admin01', password: 'admin123', redirect: 'admin-dashboard.html' },
  };

  if (!roleButtons.length || !loginModal || !loginRole || !loginForm || !roleMessage) {
    return;
  }

  let currentRole = 'student';

  function setActiveRoleButton(activeRole) {
    roleButtons.forEach(button => {
      button.classList.toggle('active-role', button.dataset.role === activeRole);
    });
  }

  function openLoginModal(role) {
    currentRole = role;
    loginRole.textContent = roleLabels[role];
    loginError.textContent = '';
    loginForm.reset();
    loginModal.classList.add('visible');
    loginModal.setAttribute('aria-hidden', 'false');
    roleMessage.textContent = `Login as ${roleLabels[role]} with your campus credentials.`;
    setActiveRoleButton(role);
  }

  function closeLoginModal() {
    loginModal.classList.remove('visible');
    loginModal.setAttribute('aria-hidden', 'true');
  }

  roleButtons.forEach(button => {
    button.addEventListener('click', () => {
      openLoginModal(button.dataset.role);
    });
  });

  if (loginClose) {
    loginClose.addEventListener('click', closeLoginModal);
  }

  if (loginBackdrop) {
    loginBackdrop.addEventListener('click', closeLoginModal);
  }

  loginForm.addEventListener('submit', event => {
    event.preventDefault();
    const id = event.target.loginId.value.trim();
    const password = event.target.loginPassword.value.trim();
    const expected = credentials[currentRole];

    if (id === expected.id && password === expected.password) {
      window.location.href = expected.redirect;
      return;
    }

    loginError.textContent = 'Invalid ID or password. Please try again.';
  });
});
