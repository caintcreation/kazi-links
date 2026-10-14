// assets/js/app.js - Modern Vanilla JavaScript UX functions & Dynamic Live Payouts Ticker

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Nav Toggle
  const hamburger = document.getElementById('hamburgerBtn');
  const mobileNav = document.getElementById('mobileNavDrawer');
  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', () => {
      mobileNav.classList.toggle('open');
    });
  }

  // Password Visibility Toggles
  document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (input) {
        if (input.type === 'password') {
          input.type = 'text';
          btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
          input.type = 'password';
          btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
      }
    });
  });

  // Copy to clipboard helper
  document.querySelectorAll('[data-copy]').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const textToCopy = el.getAttribute('data-copy');
      navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('Referral link copied to clipboard!', 'success');
      }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = textToCopy;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Referral link copied to clipboard!', 'success');
      });
    });
  });

  // Generic Modal Close Triggers
  document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) modal.classList.remove('open');
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('open');
      }
    });
  });

  // Live M-Pesa Payout Ticker
  initLiveWithdrawalTicker();
});

/**
 * Live Withdrawal Ticker Generator with Authentic Kenyan Names and Amounts
 */
function initLiveWithdrawalTicker() {
  const tickerElement = document.getElementById('livePayoutTicker');
  if (!tickerElement) return;

  const kenyanNames = [
    'Brian K.', 'Faith M.', 'Kevin O.', 'Mercy W.', 'Dennis N.', 
    'Grace C.', 'Collins K.', 'Brenda A.', 'Victor M.', 'Sharon N.', 
    'Samuel W.', 'Lilian J.', 'George O.', 'Esther K.', 'Kennedy M.',
    'Beatrice N.', 'Boniface O.', 'Jackline W.', 'Geoffrey K.', 'Purity M.',
    'Daniel O.', 'Caroline A.', 'Peter M.', 'Joyce W.', 'Evans O.'
  ];

  const randomAmounts = [
    2500, 2600, 2800, 3000, 3200, 3500, 3800, 4000, 4200, 4500, 5000, 5400
  ];

  function getNewPayout() {
    const name = kenyanNames[Math.floor(Math.random() * kenyanNames.length)];
    const amount = randomAmounts[Math.floor(Math.random() * randomAmounts.length)];
    const timeAgo = Math.floor(Math.random() * 8) + 1; // 1 to 9 minutes ago
    return { name, amount, timeAgo };
  }

  function renderPayout() {
    const payout = getNewPayout();
    tickerElement.style.opacity = '0';
    tickerElement.style.transform = 'translateY(6px)';

    setTimeout(() => {
      tickerElement.innerHTML = `
        <span class="live-pulse-dot"></span>
        <span><strong>${payout.name}</strong> just withdrew <strong style="color: #065f46;">KSh ${payout.amount.toLocaleString()}</strong> to M-Pesa</span>
        <span style="color: var(--muted-foreground); font-size: 0.78rem;">&bull; ${payout.timeAgo}m ago</span>
      `;
      tickerElement.style.opacity = '1';
      tickerElement.style.transform = 'translateY(0)';
    }, 300);
  }

  renderPayout();
  setInterval(renderPayout, 6000); // Cycle every 6 seconds
}

/**
 * Global Toast Notification
 */
function showToast(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('open');
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('open');
  }
}
