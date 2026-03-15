/**
 * Full end-to-end test of AI Agent WordPress plugin
 * Tests: login, plugin activation, connector config, AI Agent settings, chat UI, API
 */
import { chromium } from 'playwright';
import { writeFileSync } from 'fs';

const BASE_URL = 'http://localhost:8888';
const SCREENSHOT_DIR = '/home/dave/ai-agent/screenshots/e2e-test';
const USERNAME = 'admin';
const PASSWORD = 'password';

let stepNum = 0;
const results = [];

function log(msg) {
  console.log(`[STEP ${stepNum}] ${msg}`);
  results.push(`Step ${stepNum}: ${msg}`);
}

async function screenshot(page, name) {
  const path = `${SCREENSHOT_DIR}/${String(stepNum).padStart(2, '0')}-${name}.png`;
  await page.screenshot({ path, fullPage: true });
  console.log(`  📸 Screenshot: ${path}`);
  return path;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1400, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  // Collect console errors
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });

  try {
    // ========== STEP 1: Login ==========
    stepNum = 1;
    log('Logging in to WordPress admin...');
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'networkidle', timeout: 15000 });
    await page.fill('#user_login', USERNAME);
    await page.fill('#user_pass', PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
    log('Login successful - redirected to wp-admin');
    await screenshot(page, 'login-success');

    // ========== STEP 2: Network Admin Plugins ==========
    stepNum = 2;
    log('Navigating to Network Admin Plugins...');
    await page.goto(`${BASE_URL}/wp-admin/network/plugins.php`, { waitUntil: 'networkidle', timeout: 15000 });
    await screenshot(page, 'network-plugins-before');

    // Check plugin status
    const pageContent = await page.content();

    // Check AI Agent plugin
    const aiAgentActive = pageContent.includes('ai-agent/ai-agent.php') &&
      (pageContent.includes('Network Deactivate') || pageContent.includes('network-deactivate'));
    log(`AI Agent plugin network active: ${aiAgentActive}`);

    // Check OpenAI Compatible Connector
    const connectorActive = pageContent.includes('openai-compatible') &&
      (pageContent.includes('Network Deactivate') || pageContent.includes('network-deactivate'));
    log(`OpenAI Compatible Connector network active: ${connectorActive}`);

    // Try to activate plugins if not active
    if (!aiAgentActive) {
      log('Attempting to network activate AI Agent...');
      // Look for the activate link for ai-agent
      const activateLink = page.locator('tr[data-plugin="ai-agent/ai-agent.php"] .activate a, tr#ai-agent a[href*="action=activate"]');
      if (await activateLink.count() > 0) {
        await activateLink.first().click();
        await page.waitForLoadState('networkidle');
        log('AI Agent activated');
      } else {
        // Try finding by text
        const rows = page.locator('tr');
        const count = await rows.count();
        for (let i = 0; i < count; i++) {
          const text = await rows.nth(i).textContent();
          if (text && text.includes('AI Agent') && !text.includes('Network Deactivate')) {
            const activateBtn = rows.nth(i).locator('a[href*="action=activate"]');
            if (await activateBtn.count() > 0) {
              await activateBtn.first().click();
              await page.waitForLoadState('networkidle');
              log('AI Agent activated via row search');
              break;
            }
          }
        }
      }
    }

    if (!connectorActive) {
      log('Attempting to network activate OpenAI Compatible Connector...');
      const rows = page.locator('tr');
      const count = await rows.count();
      for (let i = 0; i < count; i++) {
        const text = await rows.nth(i).textContent();
        if (text && (text.includes('OpenAI') || text.includes('openai-compatible')) && !text.includes('Network Deactivate')) {
          const activateBtn = rows.nth(i).locator('a[href*="action=activate"]');
          if (await activateBtn.count() > 0) {
            await activateBtn.first().click();
            await page.waitForLoadState('networkidle');
            log('OpenAI Compatible Connector activated via row search');
            break;
          }
        }
      }
    }

    // Reload and take final screenshot
    await page.goto(`${BASE_URL}/wp-admin/network/plugins.php`, { waitUntil: 'networkidle', timeout: 15000 });
    await screenshot(page, 'network-plugins-after');

    // Get full plugin list for report
    const pluginRows = page.locator('table.plugins tr');
    const pluginCount = await pluginRows.count();
    log(`Total plugin rows: ${pluginCount}`);
    for (let i = 0; i < pluginCount; i++) {
      const text = await pluginRows.nth(i).textContent();
      if (text && (text.includes('AI Agent') || text.includes('OpenAI') || text.includes('Connector') || text.includes('openai'))) {
        log(`Plugin row: ${text.substring(0, 200).replace(/\s+/g, ' ').trim()}`);
      }
    }

    // ========== STEP 3: Configure OpenAI Compatible Connector ==========
    stepNum = 3;
    log('Looking for OpenAI Compatible Connector settings...');

    // Try various settings pages
    const settingsUrls = [
      `${BASE_URL}/wp-admin/options-general.php?page=openai-compatible-connector`,
      `${BASE_URL}/wp-admin/options-general.php?page=connectors`,
      `${BASE_URL}/wp-admin/admin.php?page=openai-compatible-connector`,
      `${BASE_URL}/wp-admin/admin.php?page=connectors`,
      `${BASE_URL}/wp-admin/options-general.php`,
    ];

    let foundSettingsPage = false;
    for (const url of settingsUrls) {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
      const content = await page.content();
      if (content.includes('openai') || content.includes('OpenAI') || content.includes('Connector') || content.includes('endpoint') || content.includes('API Key')) {
        log(`Found settings at: ${url}`);
        await screenshot(page, 'connector-settings-page');
        foundSettingsPage = true;
        break;
      }
    }

    if (!foundSettingsPage) {
      log('No dedicated settings page found. Checking admin menu...');
      // Check all admin menu items
      await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });
      const menuLinks = await page.locator('#adminmenu a').evaluateAll(els =>
        els.map(el => ({ text: el.textContent.trim(), href: el.href }))
      );
      const relevantMenus = menuLinks.filter(m =>
        m.text.toLowerCase().includes('connector') ||
        m.text.toLowerCase().includes('openai') ||
        m.text.toLowerCase().includes('ai ')
      );
      log(`Relevant menu items: ${JSON.stringify(relevantMenus)}`);
    }

    // Try to set options via REST API
    log('Attempting to configure connector via REST API / wp-options...');

    // First, get the nonce
    await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });

    // Try setting options via admin-ajax or REST
    const configResult = await page.evaluate(async () => {
      const results = {};

      // Try to read current options via REST
      try {
        const resp = await fetch('/?rest_route=/wp/v2/settings', {
          headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' }
        });
        if (resp.ok) {
          const settings = await resp.json();
          results.currentSettings = Object.keys(settings).filter(k =>
            k.includes('openai') || k.includes('connector') || k.includes('ai_agent')
          );
        }
      } catch (e) {
        results.settingsError = e.message;
      }

      // Try to update options via REST
      try {
        const resp = await fetch('/?rest_route=/wp/v2/settings', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify({
            openai_compatible_connector_endpoint: 'https://api.synthetic.new/openai/v1',
            openai_compatible_connector_api_key: 'syn_7eb697227c00701e38e6be1a1e5ba3d3'
          })
        });
        results.updateStatus = resp.status;
        results.updateBody = await resp.json();
      } catch (e) {
        results.updateError = e.message;
      }

      return results;
    });
    log(`REST API config result: ${JSON.stringify(configResult).substring(0, 500)}`);

    // Also try setting via wp_options directly using admin-ajax
    const ajaxResult = await page.evaluate(async () => {
      // Try using the options.php approach
      try {
        const formData = new FormData();
        formData.append('action', 'update_option');
        formData.append('option_name', 'openai_compatible_connector_endpoint');
        formData.append('option_value', 'https://api.synthetic.new/openai/v1');

        const resp = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          body: formData
        });
        return { status: resp.status, body: await resp.text() };
      } catch (e) {
        return { error: e.message };
      }
    });
    log(`AJAX config result: ${JSON.stringify(ajaxResult).substring(0, 300)}`);

    // Try WP-CLI via the REST API to set options
    // Let's check what the connector plugin actually stores
    const optionCheck = await page.evaluate(async () => {
      try {
        // Check if there's a connector-specific REST endpoint
        const resp = await fetch('/?rest_route=/');
        const routes = await resp.json();
        const connectorRoutes = Object.keys(routes.routes || {}).filter(r =>
          r.includes('connector') || r.includes('openai') || r.includes('ai-agent')
        );
        return { connectorRoutes };
      } catch (e) {
        return { error: e.message };
      }
    });
    log(`Available REST routes: ${JSON.stringify(optionCheck)}`);
    await screenshot(page, 'connector-config-attempt');

    // ========== STEP 4: AI Agent Settings ==========
    stepNum = 4;
    log('Navigating to AI Agent Settings...');

    // Try Tools > AI Agent Settings
    const settingsPages = [
      `${BASE_URL}/wp-admin/admin.php?page=ai-agent-settings`,
      `${BASE_URL}/wp-admin/tools.php?page=ai-agent-settings`,
      `${BASE_URL}/wp-admin/options-general.php?page=ai-agent-settings`,
    ];

    let foundAgentSettings = false;
    for (const url of settingsPages) {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
      const title = await page.title();
      const content = await page.content();
      if (!content.includes('not found') && !content.includes('You do not have sufficient permissions') &&
          (content.includes('ai-agent') || content.includes('AI Agent') || content.includes('Provider'))) {
        log(`Found AI Agent Settings at: ${url}`);
        foundAgentSettings = true;
        await screenshot(page, 'ai-agent-settings');

        // Check for provider dropdown
        const selects = await page.locator('select').evaluateAll(els =>
          els.map(el => ({
            name: el.name || el.id,
            options: Array.from(el.options).map(o => ({ value: o.value, text: o.textContent.trim() }))
          }))
        );
        log(`Select dropdowns found: ${JSON.stringify(selects)}`);

        // Also check React-rendered dropdowns
        const reactContent = await page.locator('#ai-agent-settings-root, #ai-agent-root, [data-wp-component], .components-select-control').evaluateAll(els =>
          els.map(el => el.innerHTML.substring(0, 500))
        );
        log(`React content: ${JSON.stringify(reactContent).substring(0, 500)}`);
        break;
      }
    }

    if (!foundAgentSettings) {
      log('AI Agent Settings page not found at expected URLs. Checking admin menu...');
      await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });
      const allMenus = await page.locator('#adminmenu a').evaluateAll(els =>
        els.map(el => ({ text: el.textContent.trim(), href: el.href }))
          .filter(m => m.text.toLowerCase().includes('ai') || m.text.toLowerCase().includes('agent'))
      );
      log(`AI-related menu items: ${JSON.stringify(allMenus)}`);

      // Try each found menu item
      for (const menu of allMenus) {
        if (menu.href && menu.text.toLowerCase().includes('setting')) {
          await page.goto(menu.href, { waitUntil: 'networkidle', timeout: 10000 });
          await screenshot(page, 'ai-agent-settings-from-menu');
          foundAgentSettings = true;
          break;
        }
      }
    }

    // ========== STEP 5: AI Agent Chat Page ==========
    stepNum = 5;
    log('Navigating to AI Agent chat page...');

    const chatPages = [
      `${BASE_URL}/wp-admin/admin.php?page=ai-agent`,
      `${BASE_URL}/wp-admin/tools.php?page=ai-agent`,
    ];

    let foundChatPage = false;
    for (const url of chatPages) {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
      const content = await page.content();
      if (content.includes('ai-agent') || content.includes('AI Agent') || content.includes('chat')) {
        log(`Found AI Agent chat page at: ${url}`);
        foundChatPage = true;

        // Wait a bit for React to render
        await page.waitForTimeout(3000);
        await screenshot(page, 'chat-page-initial');

        // Check what's rendered
        const chatUI = await page.evaluate(() => {
          const root = document.querySelector('#ai-agent-root, #ai-agent-app, [class*="chat"], [class*="agent"]');
          return {
            rootFound: !!root,
            rootId: root?.id,
            rootClass: root?.className,
            innerHTML: root?.innerHTML?.substring(0, 1000),
            bodyClasses: document.body.className,
            scripts: Array.from(document.querySelectorAll('script[src]')).map(s => s.src).filter(s => s.includes('ai-agent')),
          };
        });
        log(`Chat UI state: ${JSON.stringify(chatUI).substring(0, 800)}`);
        break;
      }
    }

    if (!foundChatPage) {
      log('Chat page not found. Checking all admin menu items...');
      await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });
      const allMenus = await page.locator('#adminmenu a').evaluateAll(els =>
        els.map(el => ({ text: el.textContent.trim(), href: el.href }))
          .filter(m => m.text.toLowerCase().includes('ai') || m.text.toLowerCase().includes('agent'))
      );
      log(`AI-related menu items: ${JSON.stringify(allMenus)}`);

      if (allMenus.length > 0) {
        // Navigate to the first AI Agent menu item
        for (const menu of allMenus) {
          if (menu.href && !menu.text.toLowerCase().includes('setting')) {
            await page.goto(menu.href, { waitUntil: 'networkidle', timeout: 15000 });
            await page.waitForTimeout(3000);
            await screenshot(page, 'chat-page-from-menu');
            foundChatPage = true;
            break;
          }
        }
      }
    }

    // ========== STEP 6: Send a test message via UI ==========
    stepNum = 6;
    log('Attempting to send a test message via chat UI...');

    if (foundChatPage) {
      // Look for input field
      const inputSelectors = [
        'textarea[placeholder*="message"]',
        'textarea[placeholder*="Message"]',
        'textarea[placeholder*="type"]',
        'textarea[placeholder*="Type"]',
        'textarea',
        'input[type="text"][placeholder*="message"]',
        'input[type="text"][placeholder*="Message"]',
        '[contenteditable="true"]',
        '.chat-input textarea',
        '.message-input textarea',
        '[class*="chat"] textarea',
        '[class*="chat"] input[type="text"]',
      ];

      let inputFound = false;
      for (const selector of inputSelectors) {
        const input = page.locator(selector);
        if (await input.count() > 0) {
          log(`Found input with selector: ${selector}`);
          await input.first().fill('Hello! What model are you?');
          await screenshot(page, 'chat-message-typed');

          // Look for send button
          const sendSelectors = [
            'button[type="submit"]',
            'button[aria-label*="send"]',
            'button[aria-label*="Send"]',
            'button:has-text("Send")',
            'button:has-text("send")',
            '[class*="send"] button',
            'form button',
          ];

          for (const btnSelector of sendSelectors) {
            const btn = page.locator(btnSelector);
            if (await btn.count() > 0) {
              log(`Found send button: ${btnSelector}`);
              await btn.first().click();
              inputFound = true;

              // Wait for response (up to 30 seconds)
              log('Waiting for AI response (up to 30s)...');
              await page.waitForTimeout(5000);
              await screenshot(page, 'chat-response-5s');

              // Check if there's a response
              const responseCheck = await page.evaluate(() => {
                const messages = document.querySelectorAll('[class*="message"], [class*="Message"], [class*="response"], [class*="Response"], [class*="bubble"], [class*="chat-item"]');
                return {
                  messageCount: messages.length,
                  lastMessage: messages.length > 0 ? messages[messages.length - 1].textContent?.substring(0, 500) : null,
                  allText: document.querySelector('#ai-agent-root, #ai-agent-app, [class*="chat"]')?.textContent?.substring(0, 1000),
                };
              });
              log(`Response check: ${JSON.stringify(responseCheck).substring(0, 500)}`);

              if (!responseCheck.lastMessage || responseCheck.messageCount < 2) {
                // Wait longer
                await page.waitForTimeout(15000);
                await screenshot(page, 'chat-response-20s');

                const responseCheck2 = await page.evaluate(() => {
                  const messages = document.querySelectorAll('[class*="message"], [class*="Message"], [class*="response"], [class*="Response"], [class*="bubble"], [class*="chat-item"]');
                  return {
                    messageCount: messages.length,
                    lastMessage: messages.length > 0 ? messages[messages.length - 1].textContent?.substring(0, 500) : null,
                    allText: document.querySelector('#ai-agent-root, #ai-agent-app, [class*="chat"]')?.textContent?.substring(0, 1000),
                  };
                });
                log(`Response check (20s): ${JSON.stringify(responseCheck2).substring(0, 500)}`);
              }
              break;
            }
          }

          if (!inputFound) {
            // Try pressing Enter instead
            await input.first().press('Enter');
            inputFound = true;
            log('Pressed Enter to send message');
            await page.waitForTimeout(10000);
            await screenshot(page, 'chat-response-enter');
          }
          break;
        }
      }

      if (!inputFound) {
        log('Could not find chat input field');
        // Dump the page structure
        const pageStructure = await page.evaluate(() => {
          return document.querySelector('#wpbody-content')?.innerHTML?.substring(0, 2000);
        });
        log(`Page structure: ${pageStructure?.substring(0, 800)}`);
      }
    }

    // ========== STEP 7: Test REST API directly ==========
    stepNum = 7;
    log('Testing REST API directly via browser console...');

    // Make sure we're on a wp-admin page to have the nonce
    await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });

    const apiResult = await page.evaluate(async () => {
      const results = {};

      // First check what nonce we have
      results.hasNonce = !!window.wpApiSettings?.nonce;
      results.nonce = window.wpApiSettings?.nonce?.substring(0, 10) + '...';

      // Test the AI Agent run endpoint
      try {
        const resp = await fetch('/?rest_route=/ai-agent/v1/run', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify({
            message: 'Hello! What model are you?',
            provider: 'openai-compatible',
            model: 'gpt-4o'
          })
        });
        results.runStatus = resp.status;
        results.runBody = await resp.text();
        try {
          results.runJson = JSON.parse(results.runBody);
          delete results.runBody; // Don't duplicate
        } catch (e) {
          // Keep as text
        }
      } catch (e) {
        results.runError = e.message;
      }

      // Also try the chat endpoint
      try {
        const resp = await fetch('/?rest_route=/ai-agent/v1/chat', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify({
            message: 'Hello! What model are you?'
          })
        });
        results.chatStatus = resp.status;
        results.chatBody = await resp.text();
        try {
          results.chatJson = JSON.parse(results.chatBody);
          delete results.chatBody;
        } catch (e) {
          // Keep as text
        }
      } catch (e) {
        results.chatError = e.message;
      }

      // Try session creation
      try {
        const resp = await fetch('/?rest_route=/ai-agent/v1/sessions', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify({
            title: 'E2E Test Session'
          })
        });
        results.sessionStatus = resp.status;
        results.sessionBody = await resp.text();
        try {
          results.sessionJson = JSON.parse(results.sessionBody);
          delete results.sessionBody;
        } catch (e) {
          // Keep as text
        }
      } catch (e) {
        results.sessionError = e.message;
      }

      // List available routes
      try {
        const resp = await fetch('/?rest_route=/ai-agent/v1');
        results.routeStatus = resp.status;
        if (resp.ok) {
          const data = await resp.json();
          results.availableRoutes = Object.keys(data.routes || {});
        }
      } catch (e) {
        results.routeError = e.message;
      }

      return results;
    });

    log(`API test results: ${JSON.stringify(apiResult, null, 2).substring(0, 2000)}`);
    await screenshot(page, 'api-test-results');

    // ========== STEP 7b: Try with session-based approach ==========
    if (apiResult.sessionJson?.id || apiResult.sessionJson?.session_id) {
      const sessionId = apiResult.sessionJson.id || apiResult.sessionJson.session_id;
      log(`Got session ID: ${sessionId}. Trying to send message with session...`);

      const sessionMsgResult = await page.evaluate(async (sid) => {
        try {
          const resp = await fetch(`/?rest_route=/ai-agent/v1/sessions/${sid}/messages`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': window.wpApiSettings?.nonce || ''
            },
            body: JSON.stringify({
              message: 'Hello! What model are you?'
            })
          });
          const status = resp.status;
          const body = await resp.text();
          try {
            return { status, json: JSON.parse(body) };
          } catch (e) {
            return { status, body: body.substring(0, 1000) };
          }
        } catch (e) {
          return { error: e.message };
        }
      }, sessionId);
      log(`Session message result: ${JSON.stringify(sessionMsgResult).substring(0, 1000)}`);
    }

    // ========== STEP 8: Check for errors ==========
    stepNum = 8;
    log('Checking for errors...');

    // Console errors collected throughout
    log(`Browser console errors: ${consoleErrors.length}`);
    for (const err of consoleErrors.slice(0, 20)) {
      log(`  Console error: ${err.substring(0, 200)}`);
    }

    // Check for PHP errors on the page
    const phpErrors = await page.evaluate(() => {
      const body = document.body.innerHTML;
      const errorPatterns = [
        /Fatal error/i,
        /Warning:/i,
        /Notice:/i,
        /Parse error/i,
        /Deprecated:/i,
      ];
      const found = [];
      for (const pattern of errorPatterns) {
        const match = body.match(pattern);
        if (match) {
          const idx = body.indexOf(match[0]);
          found.push(body.substring(idx, idx + 200));
        }
      }
      return found;
    });
    if (phpErrors.length > 0) {
      log(`PHP errors found: ${JSON.stringify(phpErrors)}`);
    } else {
      log('No PHP errors found on page');
    }

    // Check debug.log if accessible
    try {
      const debugResp = await page.goto(`${BASE_URL}/wp-content/debug.log`, { timeout: 5000 });
      if (debugResp && debugResp.status() === 200) {
        const debugContent = await page.textContent('body');
        log(`debug.log content (last 500 chars): ${debugContent?.slice(-500)}`);
      }
    } catch (e) {
      log('debug.log not accessible (expected)');
    }

    // Navigate back to admin for final screenshot
    await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 10000 });
    await screenshot(page, 'final-admin-dashboard');

  } catch (error) {
    log(`ERROR: ${error.message}`);
    console.error(error.stack);
    await screenshot(page, 'error-state').catch(() => {});
  } finally {
    // Print summary
    console.log('\n' + '='.repeat(80));
    console.log('TEST RESULTS SUMMARY');
    console.log('='.repeat(80));
    for (const r of results) {
      console.log(r);
    }
    console.log('='.repeat(80));

    await browser.close();
  }
}

main().catch(console.error);
