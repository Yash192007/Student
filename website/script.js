document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.querySelector('.menu-toggle');
  const siteNav = document.getElementById('site-nav');

  if (menuToggle && siteNav) {
    menuToggle.addEventListener('click', () => {
      const isOpen = siteNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
    });

    siteNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        siteNav.classList.remove('open');
        menuToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

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
