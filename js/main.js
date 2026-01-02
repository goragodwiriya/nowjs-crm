/**
 * CRM System - Main Application
 * Now.js Framework
 */

// Permission Helper Functions
const isAdmin = (user) => user?.status === 1;
const isSuperAdmin = (user) => user?.status === 1 && user?.id === 1;
const hasPermission = (user, permission) => {
  if (isAdmin(user)) return true; // Admin ทำได้ทุกอย่าง
  return user?.permission?.includes(permission);
};

// Route Guards
const requireAdmin = async (params, current, authManager) => {
  const user = authManager.getUser();
  if (!isAdmin(user)) return '/403';
  return true;
};

const requirePermission = (permission) => async (params, current, authManager) => {
  const user = authManager.getUser();
  if (!hasPermission(user, permission)) return '/403';
  return true;
};

document.addEventListener('DOMContentLoaded', async () => {
  try {
    // Detect current directory path
    const currentPath = window.location.pathname;
    const currentDir = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);

    // Initialize framework
    await Now.init({
      // Environment mode: 'development' or 'production'
      environment: 'production',

      // Path configuration for templates and resources
      paths: {
        components: `${currentDir}components`,
        plugins: `${currentDir}plugins`,
        templates: `${currentDir}templates`,
        translations: `${currentDir}language`
      },

      // Enable framework-level auth so AuthManager will initialize before RouterManager
      auth: {
        enabled: true,
        autoInit: true,
        endpoints: {
          verify: 'api/index/auth/verify', // Used to check if the Token/Cookie sent by Client (such as Authorization Header or Cookie) is still correct/not expired or not.
          me: 'api/index/auth/me', // Restore the current user (Profile)
          login: 'api/index/auth/login', // Get a Creitedial (Email/Password) and reply token/session + user info.
          logout: 'api/index/auth/logout', // Cancel session /invalidates token at Server
          refresh: 'api/index/auth/refresh' // Used to ask for a new Token when the TOKEN is currently expired (if using JWT or Token-based Author)
        },

        token: {
          storageKey: 'auth_user'
        },

        redirects: {
          afterLogin: '/',
          afterLogout: '/login',
          unauthorized: '/login',
          forbidden: '/403'
        }
      },
      // Security configuration (CSRF token endpoint configurable here)
      security: {
        csrf: {
          enabled: true,
          tokenName: '_token',
          headerName: 'X-CSRF-Token',
          cookieName: 'XSRF-TOKEN',
          metaName: 'csrf-token',
          tokenUrl: 'api/index/auth/csrf-token' // CSRF endpoin
        }
      },

      // Internationalization settings
      i18n: {
        enabled: true,
        availableLocales: ['en', 'th']
      },

      theme: {
        enabled: true,
        defaultTheme: 'light',
        storageKey: 'crm_theme',
        systemPreference: false, // Not use system color scheme preference

        // Smooth transitions
        transition: {
          enabled: true,
          duration: 300,
          hideOnSwitch: true
        },

        // API config - auto-load from server on init
        api: {
          enabled: true,
          configUrl: 'api/index/config/theme-settings',  // Automatically loads during init
          cacheResponse: true
        }
      },

      router: {
        enabled: true,
        base: currentDir,
        mode: 'history', // 'hash' or 'history'

        // Auth Configuration for Router
        auth: {
          enabled: true,
          autoGuard: true,
          defaultRequireAuth: true,
          publicPaths: ['/login', '/404'],
          guestOnlyPaths: ['/login'],
          redirects: {
            unauthenticated: '/login',
            unauthorized: '/login',
            forbidden: '/403',
            afterLogin: '/',
            afterLogout: '/login'
          }
        },

        notFound: {
          behavior: 'render',
          template: '404.html',
          title: 'Page Not Found'
        },

        routes: {
          '/': {
            template: '/crm/dashboard.html',
            title: '{LNG_Dashboard} - CRM',
            requireAuth: true
          },
          '/login': {
            template: 'login.html',
            title: '{LNG_Login} - CRM',
            requireGuest: true,
            requireAuth: false
          },
          '/forgot': {
            template: 'forgot.html',
            title: '{LNG_Forgot Password} - CRM',
            requireGuest: true,
            requireAuth: false
          },
          '/register': {
            template: 'register.html',
            title: '{LNG_Register} - CRM',
            requireGuest: true,
            requireAuth: false
          },
          '/reset-password': {
            template: 'reset-password.html',
            title: '{LNG_Reset Password} - CRM',
            requireGuest: true,
            requireAuth: false
          },
          '/activate': {
            template: 'activate.html',
            title: '{LNG_Activate Account} - CRM',
            requireGuest: true,
            requireAuth: false
          },
          '/logout': {
            requireAuth: false,
            beforeEnter: async (params, current, authManager) => {
              await authManager.logout();
              return '/login';
            }
          },
          '/pipeline': {
            template: '/crm/pipeline.html',
            title: '{LNG_Sales Pipeline} - CRM',
            requireAuth: true
          },
          '/deals': {
            template: '/crm/deals.html',
            title: '{LNG_Deals} - CRM',
            requireAuth: true
          },
          '/deal': {
            template: '/crm/deal.html',
            title: '{LNG_Deal} - CRM',
            menuPath: '/deals',
            requireAuth: true
          },
          '/customers': {
            template: '/crm/customers.html',
            title: '{LNG_Customers} - CRM',
            requireAuth: true
          },
          '/customer': {
            template: '/crm/customer.html',
            title: '{LNG_Customer} - CRM',
            menuPath: '/customers',
            requireAuth: true
          },
          '/contacts': {
            template: '/crm/contacts.html',
            title: '{LNG_Contacts} - CRM',
            requireAuth: true
          },
          '/contact': {
            template: '/crm/contact.html',
            title: '{LNG_Contact} - CRM',
            menuPath: '/contacts',
            requireAuth: true
          },
          '/activities': {
            template: '/crm/activities.html',
            title: '{LNG_Activities} - CRM',
            requireAuth: true
          },
          '/activity': {
            template: '/crm/activity.html',
            title: '{LNG_Activity} - CRM',
            menuPath: '/activities',
            requireAuth: true
          },
          '/campaigns': {
            template: '/crm/campaigns.html',
            title: '{LNG_Campaigns} - CRM',
            requireAuth: true
          },
          '/campaign': {
            template: '/crm/campaign.html',
            title: '{LNG_Campaign} - CRM',
            menuPath: '/campaigns',
            requireAuth: true
          },
          '/editprofile': {
            template: 'editprofile.html',
            title: '{LNG_Edit Profile} - CRM',
            menuPath: '/users',
            requireAuth: true
          },
          '/profile': {
            template: 'profile.html',
            title: '{LNG_Profile} - CRM',
            menuPath: '/users',
            requireAuth: true
          },
          '/users': {
            template: 'users.html',
            title: '{LNG_Users} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/user-status': {
            template: 'settings/userstatus.html',
            title: '{LNG_Member status} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/permission': {
            template: 'settings/permission.html',
            title: '{LNG_Permissions} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/general-settings': {
            template: 'settings/general.html',
            title: '{LNG_General Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/email-settings': {
            template: 'settings/email.html',
            title: '{LNG_Email Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/api-settings': {
            template: 'settings/api.html',
            title: '{LNG_API Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/theme-settings': {
            template: 'settings/theme.html',
            title: '{LNG_Theme Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/line-settings': {
            template: 'settings/line.html',
            title: '{LNG_Line Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/telegram-settings': {
            template: 'settings/telegram.html',
            title: '{LNG_Telegram Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/sms-settings': {
            template: 'settings/sms.html',
            title: '{LNG_SMS Settings} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/cookie-policy': {
            template: 'settings/cookie-policy.html',
            title: '{LNG_Cookie Policy} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/languages': {
            template: 'settings/languages.html',
            title: '{LNG_Manage languages} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/language': {
            template: 'settings/language.html',
            title: '{LNG_Add}/{LNG_Edit} {LNG_Language} - CRM',
            menuPath: '/languages',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/usage': {
            template: 'settings/usage.html',
            title: '{LNG_Usage history} - CRM',
            requireAuth: true,
            beforeEnter: requireAdmin
          },
          '/403': {
            template: '403.html',
            title: '{LNG_Access Denied} - CRM',
            requireAuth: true
          },
          '/404': {
            template: '404.html',
            title: '{LNG_Page Not Found}'
          }
        }
      },

      scroll: {
        enabled: false,
        selectors: {
          content: '.content',
        }
      }
    }).then(() => {
      // Load application components after framework initialization
      const scripts = [
        `${currentDir}js/components/sidebar.js`,
        `${currentDir}js/components/topbar.js`,
        `${currentDir}js/components/SocialLogin.js`,
      ];

      // Dynamically load all component scripts
      scripts.forEach(src => {
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
      });
    });

    // Create application instance
    const app = await Now.createApp({
      name: 'Now.js',
      version: '1.0.0'
    });

  } catch (error) {
    console.error('Application initialization failed:', error);
  }
});

function initProfile(element, data) {
  const input = element.querySelector('#birthday');
  const display = element.querySelector('.dropdown-display');

  const updateAge = () => {
    if (input.value) {
      const birth = new Date(input.value);
      const age = Math.floor((Date.now() - birth) / 31557600000);

      // Format date with standard pattern (YYYY uses locale-based year: BE for Thai, CE for others)
      const formattedDate = Utils.date.format(input.value, 'D MMMM YYYY');

      display.textContent = `${formattedDate} (${age} ${Now.translate('years')})`;
    } else {
      display.textContent = '';
    }
  };

  input.addEventListener('change', updateAge);
  updateAge();

  // Return cleanup function (optional)
  return () => {
    input.removeEventListener('change', updateAge);
  };
}

function initGeneralSettings(element, data) {
  const timezone = element.querySelector('#timezone');
  const server_time = element.querySelector('#server_time');
  const local_time = element.querySelector('#local_time');
  let intervalId = 0;

  const updateTimes = () => {
    // Update local time with selected timezone
    if (local_time && timezone?.value) {
      const options = {
        timeZone: timezone.value,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      };
      local_time.textContent = new Date().toLocaleString('en-GB', options).replace(',', '');
    }

    // Update server time (add elapsed time to initial server time)
    if (server_time) {
      // Parse d/m/Y H:i:s format
      const parts = server_time.textContent.match(/(\d+)\/(\d+)\/(\d+)\s+(\d+):(\d+):(\d+)/);
      if (parts) {
        const seconds = parseInt(parts[6]) + 1;
        const serverStartTime = new Date(parts[3], parts[2] - 1, parts[1], parts[4], parts[5], seconds);
        const currentServerTime = new Date(serverStartTime.getTime());
        server_time.textContent = Utils.date.format(currentServerTime, 'DD/MM/YYYY HH:mm:ss', 'th-CE');
      }

    }
  };

  if (timezone && server_time && local_time) {
    updateTimes();
    intervalId = window.setInterval(updateTimes, 1000);
  }

  // Return cleanup function (optional)
  return () => {
    window.clearInterval(intervalId);
  };
}

function initEmailSettings(element, data) {
  const email_SMTPAuth = element.querySelector('#email_SMTPAuth');
  const test_email = element.querySelector('#test_email');

  const smtpAuthChange = () => {
    element.querySelector('#email_SMTPSecure').disabled = !email_SMTPAuth.checked;
    element.querySelector('#email_Username').disabled = !email_SMTPAuth.checked;
    element.querySelector('#email_Password').disabled = !email_SMTPAuth.checked;
  };
  email_SMTPAuth.addEventListener('change', smtpAuthChange);
  smtpAuthChange();

  // Test email button handler - sends to logged-in user's email
  const testEmailClick = async () => {
    // Disable button during request
    test_email.disabled = true;
    const originalText = test_email.innerHTML;
    test_email.innerHTML = '<span class="spinner"></span> ' + Now.translate('Sending...');

    try {
      const response = await ApiService.post('api/index/settings/testEmail');

      if (response.success) {
        NotificationManager.success(response.message || Now.translate('Test sent successfully'));
      } else {
        NotificationManager.error(response.message || Now.translate('Failed to send test'));
      }
    } catch (error) {
      NotificationManager.error(Now.translate('Failed to send test'));
    } finally {
      test_email.disabled = false;
      test_email.innerHTML = originalText;
    }
  };

  if (test_email) {
    test_email.addEventListener('click', testEmailClick);
  }

  // Return cleanup function
  return () => {
    email_SMTPAuth.removeEventListener('change', smtpAuthChange);
    if (test_email) {
      test_email.removeEventListener('click', testEmailClick);
    }
  };
}

function initTelegramSettings(element, data) {
  const telegram_bot_token = element.querySelector('#telegram_bot_token');
  const telegram_chat_id = element.querySelector('#telegram_chat_id');
  const test_telegram = element.querySelector('#test_telegram');

  const testTelegramClick = async () => {
    if (!telegram_bot_token.value || !telegram_chat_id.value) {
      NotificationManager.error('{LNG_Please fill in} Bot Username {LNG_and} Chat ID');
      return;
    }

    // Disable button during request
    test_telegram.disabled = true;
    const originalText = test_telegram.innerHTML
    test_telegram.innerHTML = '<span class="spinner"></span> ' + Now.translate('Sending...');

    try {
      const response = await ApiService.post('api/index/settings/testTelegram', {
        bot_token: telegram_bot_token.value,
        chat_id: telegram_chat_id.value
      });

      if (response.success) {
        NotificationManager.success(response.message || Now.translate('Test sent successfully'));
      } else {
        NotificationManager.error(response.message || Now.translate('Failed to send test'));
      }
    } catch (error) {
      NotificationManager.error(Now.translate('Failed to send test'));
    } finally {
      test_telegram.disabled = false;
      test_telegram.innerHTML = originalText;
    }
  };

  if (test_telegram) {
    test_telegram.addEventListener('click', testTelegramClick);
  }

  // Return cleanup function
  return () => {
    if (test_telegram) {
      test_telegram.removeEventListener('click', testTelegramClick);
    }
  };
}

function initCrmPipeline(element, data) {
  const updateCounters = () => {
    document.querySelectorAll('.kanban-column').forEach(column => {
      const count = column.querySelectorAll('.kanban-card').length;
      const counter = column.querySelector('.column-count');
      if (counter) counter.textContent = count;
    });
  };

  // Update kanban column counters when cards are moved
  document.addEventListener('sortable:end', updateCounters);

  // Return cleanup function
  return () => {
    document.removeEventListener('sortable:end', updateCounters);
  };
}

function formatDealStage(cell, rawValue, rowData, attributes) {
  const stages = ['qualified', 'won', 'negotiation', 'lost', 'lead', 'proposal'];
  cell.innerHTML = `<span class="status${stages.indexOf(rawValue)}">${Utils.string.humanize(rawValue)}</span>`;
}

function formatCustomerStatus(cell, rawValue, rowData, attributes) {
  const statuses = ['lead', 'prospect', 'customer', 'inactive', 'churned'];
  const humanized = Utils.string.humanize(rawValue);
  cell.innerHTML = `<span class="status${statuses.indexOf(rawValue)}" data-i18n="${humanized}">${Now.translate(humanized)}</span>`;
}

function formatCustomerSource(cell, rawValue, rowData, attributes) {
  const icons = {
    website: 'icon-world',
    referral: 'icon-link',
    cold_call: 'icon-phone',
    advertisement: 'icon-ads',
    trade_show: 'icon-product',
    social_media: 'icon-share',
    other: 'icon-file'
  };
  const humanized = Utils.string.humanize(rawValue);
  const icon = icons[rawValue] ?? 'icon-file';
  cell.innerHTML = `<span class="${icon}" title="${Now.translate(humanized)}"></span>`;
}

function formatContactPrimary(cell, rawValue, rowData, attributes) {

  const className = rawValue ? 'icon-star2 color-blue' : 'icon-star0 color-silver';
  cell.innerHTML = `<span class="${className}"></span>`;
}

function formatContactStatus(cell, rawValue, rowData, attributes) {
  const className = rawValue === 'active' ? 'icon-valid color-red' : 'icon-invalid color-silver';
  const humanized = Utils.string.humanize(rawValue);
  cell.innerHTML = `<span class="${className}" title="${Now.translate(humanized)}"></span>`;
}

function formatCampaignStatus(cell, rawValue, rowData, attributes) {
  const statuses = ['scheduled', 'paused', 'active', 'draft', 'cancelled', 'completed'];
  const humanized = Utils.string.humanize(rawValue);
  cell.innerHTML = `<span class="status${statuses.indexOf(rawValue)}" data-i18n="${humanized}">${Now.translate(humanized)}</span>`;
}

function formatCampaignIcon(cell, rawValue, rowData, attributes) {
  const icons = {
    email: 'icon-email',
    social: 'icon-share',
    event: 'icon-event',
    webinar: 'icon-published1',
    advertisement: 'icon-ads',
    other: 'icon-file'
  };
  const humanized = Utils.string.humanize(rawValue);
  const icon = icons[rawValue] ?? 'icon-file';
  cell.innerHTML = `<span class="${icon}" title="${Now.translate(humanized)}"></span>`;
}

function formatActivityStatus(cell, rawValue, rowData, attributes) {
  const statuses = {
    scheduled: 'status0',
    completed: 'status2',
    cancelled: 'status3',
    no_show: 'status4'
  };
  const humanized = Utils.string.humanize(rawValue);
  const status = statuses[rawValue] ?? statuses.cancelled;
  cell.innerHTML = `<span class="${status}" data-i18n="${humanized}">${Now.translate(humanized)}</span>`;
}

function formatActivityPriority(cell, rawValue, rowData, attributes) {
  const statuses = {
    low: 'status3',
    medium: 'status1',
    high: 'status4'
  };
  const humanized = Utils.string.humanize(rawValue);
  const status = statuses[rawValue] ?? statuses.low;
  cell.innerHTML = `<span class="${status}" data-i18n="${humanized}">${Now.translate(humanized)}</span>`;
}

function formatActivityIcon(cell, rawValue, rowData, attributes) {
  const icons = {
    call: 'icon-phone',
    meeting: 'icon-event',
    email: 'icon-email',
    task: 'icon-list',
    note: 'icon-file',
    lunch: 'icon-food',
    demo: 'icon-published1',
    follow_up: 'icon-clock'
  };
  const humanized = Utils.string.humanize(rawValue);
  const icon = icons[rawValue] ?? 'icon-list';
  cell.innerHTML = `<span class="${icon}" title="${Now.translate(humanized)}"></span>`;
}