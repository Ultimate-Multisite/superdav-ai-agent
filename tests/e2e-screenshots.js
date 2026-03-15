const puppeteer = require('puppeteer');

const BASE_URL = 'http://localhost:8888';
const SCREENSHOT_DIR = '/screenshots';

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
    executablePath: '/usr/bin/chromium-browser'
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1400, height: 900 });

  try {
    // Step 1: Login
    console.log('Step 1: Logging in...');
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'networkidle2', timeout: 15000 });
    await page.screenshot({ path: `${SCREENSHOT_DIR}/01-login-page.png`, fullPage: true });

    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
    console.log('Login successful');
    await page.screenshot({ path: `${SCREENSHOT_DIR}/02-dashboard.png`, fullPage: true });

    // Step 2: Plugins page
    console.log('Step 2: Plugins page...');
    await page.goto(`${BASE_URL}/wp-admin/plugins.php`, { waitUntil: 'networkidle2', timeout: 15000 });
    await page.screenshot({ path: `${SCREENSHOT_DIR}/03-plugins-page.png`, fullPage: true });

    // Step 3: Check for connector settings
    console.log('Step 3: Connector settings...');
    await page.goto(`${BASE_URL}/wp-admin/options-general.php?page=openai-compatible-connector`, { waitUntil: 'networkidle2', timeout: 15000 });
    await page.screenshot({ path: `${SCREENSHOT_DIR}/04-connector-settings.png`, fullPage: true });

    // Step 4: AI Agent Settings
    console.log('Step 4: AI Agent Settings...');
    await page.goto(`${BASE_URL}/wp-admin/tools.php?page=ai-agent-settings`, { waitUntil: 'networkidle2', timeout: 15000 });
    // Wait for React to render
    await new Promise(r => setTimeout(r, 3000));
    await page.screenshot({ path: `${SCREENSHOT_DIR}/05-ai-agent-settings.png`, fullPage: true });

    // Step 5: AI Agent Chat page
    console.log('Step 5: AI Agent Chat page...');
    await page.goto(`${BASE_URL}/wp-admin/tools.php?page=ai-agent`, { waitUntil: 'networkidle2', timeout: 15000 });
    // Wait for React to render
    await new Promise(r => setTimeout(r, 3000));
    await page.screenshot({ path: `${SCREENSHOT_DIR}/06-chat-page.png`, fullPage: true });

    // Check what's rendered
    const chatRoot = await page.evaluate(() => {
      const root = document.getElementById('ai-agent-root');
      return {
        exists: !!root,
        innerHTML: root ? root.innerHTML.substring(0, 500) : 'NOT FOUND',
        childCount: root ? root.children.length : 0
      };
    });
    console.log('Chat root:', JSON.stringify(chatRoot));

    // Step 6: Try to send a message
    console.log('Step 6: Sending test message...');
    
    // Look for textarea or input
    const inputSelector = await page.evaluate(() => {
      const selectors = [
        'textarea',
        'input[type="text"]',
        '[contenteditable="true"]',
        '[role="textbox"]'
      ];
      for (const sel of selectors) {
        const el = document.querySelector(sel);
        if (el && (el.offsetParent !== null || el.offsetHeight > 0)) {
          return sel;
        }
      }
      return null;
    });

    if (inputSelector) {
      console.log(`Found input: ${inputSelector}`);
      await page.type(inputSelector, 'Hello! What model are you?');
      await page.screenshot({ path: `${SCREENSHOT_DIR}/07-message-typed.png`, fullPage: true });

      // Try to find and click send button
      const sendClicked = await page.evaluate(() => {
        const buttons = document.querySelectorAll('button');
        for (const btn of buttons) {
          const text = btn.textContent.toLowerCase();
          const label = (btn.getAttribute('aria-label') || '').toLowerCase();
          if (text.includes('send') || label.includes('send') || btn.type === 'submit') {
            btn.click();
            return true;
          }
        }
        // Try pressing Enter on the textarea
        const textarea = document.querySelector('textarea');
        if (textarea) {
          textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', keyCode: 13, bubbles: true }));
          return 'enter';
        }
        return false;
      });
      console.log(`Send clicked: ${sendClicked}`);

      // Wait for response
      console.log('Waiting for response (20s)...');
      await new Promise(r => setTimeout(r, 20000));
      await page.screenshot({ path: `${SCREENSHOT_DIR}/08-chat-response.png`, fullPage: true });
    } else {
      console.log('No input field found');
      await page.screenshot({ path: `${SCREENSHOT_DIR}/07-no-input-found.png`, fullPage: true });
    }

    // Step 7: Test REST API via console
    console.log('Step 7: Testing REST API...');
    const apiResult = await page.evaluate(async () => {
      try {
        const resp = await fetch('/?rest_route=/ai-agent/v1/providers', {
          headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' }
        });
        return { status: resp.status, data: await resp.json() };
      } catch (e) {
        return { error: e.message };
      }
    });
    console.log('API providers:', JSON.stringify(apiResult).substring(0, 500));

    // Step 8: Check console errors
    console.log('Step 8: Checking for errors...');
    const errors = await page.evaluate(() => {
      // Check for visible PHP errors
      const body = document.body.innerHTML;
      const phpErrors = [];
      for (const pattern of ['Fatal error', 'Warning:', 'Notice:', 'Parse error']) {
        if (body.includes(pattern)) {
          const idx = body.indexOf(pattern);
          phpErrors.push(body.substring(idx, idx + 200));
        }
      }
      return phpErrors;
    });
    console.log(`PHP errors: ${errors.length > 0 ? JSON.stringify(errors) : 'None'}`);

    console.log('\n=== TEST COMPLETE ===');

  } catch (error) {
    console.error('Error:', error.message);
    await page.screenshot({ path: `${SCREENSHOT_DIR}/99-error.png`, fullPage: true }).catch(() => {});
  } finally {
    await browser.close();
  }
})();
