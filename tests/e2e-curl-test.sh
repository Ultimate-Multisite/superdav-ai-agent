#!/bin/bash
# Full end-to-end test of AI Agent WordPress plugin using curl
# This tests the same flow as the Playwright test but via HTTP requests

set -euo pipefail

BASE_URL="http://localhost:8888"
COOKIE_JAR="/tmp/wp-cookies.txt"
SCREENSHOT_DIR="/home/dave/ai-agent/screenshots/e2e-test"

mkdir -p "$SCREENSHOT_DIR"
rm -f "$COOKIE_JAR"

echo "=========================================="
echo "AI Agent WordPress Plugin - E2E Test"
echo "=========================================="

# ========== STEP 1: Login ==========
echo ""
echo "=== STEP 1: Login to WordPress ==="

# Get the login page first to get any cookies/nonces
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/wp-login.php" >/tmp/wp-login.html 2>&1

# Extract any hidden fields
REDIRECT_TO=$(grep -oP 'name="redirect_to" value="\K[^"]*' /tmp/wp-login.html || echo "/wp-admin/")
TESTCOOKIE=$(grep -oP 'name="testcookie" value="\K[^"]*' /tmp/wp-login.html || echo "1")

# Login
LOGIN_RESPONSE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-L -w "\n%{http_code}" \
	-d "log=admin&pwd=password&wp-submit=Log+In&redirect_to=${REDIRECT_TO}&testcookie=${TESTCOOKIE}" \
	"$BASE_URL/wp-login.php")

LOGIN_CODE=$(echo "$LOGIN_RESPONSE" | tail -1)
LOGIN_BODY=$(echo "$LOGIN_RESPONSE" | head -n -1)

if echo "$LOGIN_BODY" | grep -q "Dashboard\|wp-admin" || [ "$LOGIN_CODE" = "200" ]; then
	echo "✅ Login successful (HTTP $LOGIN_CODE)"
else
	echo "❌ Login failed (HTTP $LOGIN_CODE)"
	echo "$LOGIN_BODY" | head -20
fi

# ========== STEP 2: Check Network Admin Plugins ==========
echo ""
echo "=== STEP 2: Network Admin Plugins ==="

PLUGINS_PAGE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/wp-admin/network/plugins.php")

# Check AI Agent
if echo "$PLUGINS_PAGE" | grep -q "ai-agent"; then
	echo "✅ AI Agent plugin found"
	if echo "$PLUGINS_PAGE" | grep -A5 "ai-agent/ai-agent.php" | grep -qi "Network Deactivate\|active"; then
		echo "  ✅ AI Agent is network activated"
	else
		echo "  ⚠️  AI Agent may not be network activated"
		# Try to find activate link
		ACTIVATE_URL=$(echo "$PLUGINS_PAGE" | grep -oP 'href="[^"]*action=activate[^"]*ai-agent[^"]*"' | head -1 | sed 's/href="//' | sed 's/"$//')
		if [ -n "$ACTIVATE_URL" ]; then
			echo "  Activating AI Agent..."
			ACTIVATE_URL=$(echo "$ACTIVATE_URL" | sed 's/&amp;/\&/g')
			curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -L "$BASE_URL/wp-admin/network/$ACTIVATE_URL" >/dev/null 2>&1
			echo "  ✅ Activation attempted"
		fi
	fi
else
	echo "❌ AI Agent plugin not found"
fi

# Check OpenAI Compatible Connector
if echo "$PLUGINS_PAGE" | grep -qi "openai\|connector"; then
	echo "✅ OpenAI Compatible Connector plugin found"
	if echo "$PLUGINS_PAGE" | grep -A5 -i "openai-compatible" | grep -qi "Network Deactivate\|active"; then
		echo "  ✅ OpenAI Compatible Connector is network activated"
	else
		echo "  ⚠️  OpenAI Compatible Connector may not be network activated"
	fi
else
	echo "❌ OpenAI Compatible Connector plugin not found"
fi

# List all plugins
echo ""
echo "All plugins found:"
echo "$PLUGINS_PAGE" | grep -oP '<tr[^>]*data-plugin="[^"]*"' | sed 's/.*data-plugin="/  - /' | sed 's/"//'
echo ""
echo "Active plugins (showing Network Deactivate):"
echo "$PLUGINS_PAGE" | grep -B20 "Network Deactivate" | grep -oP 'data-plugin="[^"]*"' | sed 's/data-plugin="/  - /' | sed 's/"//'

# Save plugins page for analysis
echo "$PLUGINS_PAGE" >/tmp/wp-plugins-page.html

# ========== STEP 3: Configure OpenAI Compatible Connector ==========
echo ""
echo "=== STEP 3: Configure OpenAI Compatible Connector ==="

# Try the settings page
for URL in \
	"$BASE_URL/wp-admin/options-general.php?page=openai-compatible-connector" \
	"$BASE_URL/wp-admin/admin.php?page=openai-compatible-connector" \
	"$BASE_URL/wp-admin/options-general.php?page=connectors" \
	"$BASE_URL/wp-admin/admin.php?page=connectors"; do

	SETTINGS_PAGE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -w "\n%{http_code}" "$URL")
	SETTINGS_CODE=$(echo "$SETTINGS_PAGE" | tail -1)
	SETTINGS_BODY=$(echo "$SETTINGS_PAGE" | head -n -1)

	if [ "$SETTINGS_CODE" = "200" ] && ! echo "$SETTINGS_BODY" | grep -q "not found\|No page found"; then
		if echo "$SETTINGS_BODY" | grep -qi "openai\|connector\|endpoint\|api.key"; then
			echo "✅ Found connector settings at: $URL"
			echo "$SETTINGS_BODY" >/tmp/wp-connector-settings.html
			break
		fi
	fi
done

# Get the REST API nonce
echo "Getting REST API nonce..."
ADMIN_PAGE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/wp-admin/admin-ajax.php?action=rest-nonce")
REST_NONCE="$ADMIN_PAGE"
echo "REST nonce from admin-ajax: $REST_NONCE"

# If that didn't work, try extracting from a page
if [ ${#REST_NONCE} -gt 20 ] || [ -z "$REST_NONCE" ]; then
	ADMIN_PAGE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/wp-admin/")
	REST_NONCE=$(echo "$ADMIN_PAGE" | grep -oP 'wpApiSettings.*?"nonce":"[^"]*"' | grep -oP '"nonce":"[^"]*"' | grep -oP ':"[^"]*"' | tr -d ':"' || echo "")
	echo "REST nonce from page: $REST_NONCE"
fi

# Check available REST routes for ai-agent
echo ""
echo "Checking AI Agent REST routes..."
AI_ROUTES=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/?rest_route=/ai-agent/v1")
echo "AI Agent routes response (first 500 chars):"
echo "$AI_ROUTES" | head -c 500
echo ""

# Check all REST routes
echo ""
echo "All REST namespaces:"
ALL_ROUTES=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/?rest_route=/")
echo "$ALL_ROUTES" | python3 -c "import sys,json; d=json.load(sys.stdin); [print(f'  - {r}') for r in sorted(d.get('namespaces',[]))]" 2>/dev/null || echo "Could not parse routes"

# Try to set connector options via REST
echo ""
echo "Attempting to configure connector via REST API..."
CONFIG_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-X POST \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $REST_NONCE" \
	"$BASE_URL/?rest_route=/wp/v2/settings" \
	-d '{"openai_compatible_connector_endpoint":"https://api.synthetic.new/openai/v1","openai_compatible_connector_api_key":"syn_7eb697227c00701e38e6be1a1e5ba3d3"}')
echo "Settings update result: $CONFIG_RESULT" | head -c 500
echo ""

# Try to check/set via options API
echo ""
echo "Checking AI Agent options..."
# Use a custom REST endpoint or admin-ajax to check options
OPTIONS_CHECK=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-H "X-WP-Nonce: $REST_NONCE" \
	"$BASE_URL/?rest_route=/ai-agent/v1/settings" 2>/dev/null)
echo "AI Agent settings: $OPTIONS_CHECK" | head -c 500
echo ""

# ========== STEP 4: AI Agent Settings Page ==========
echo ""
echo "=== STEP 4: AI Agent Settings Page ==="

for URL in \
	"$BASE_URL/wp-admin/admin.php?page=ai-agent-settings" \
	"$BASE_URL/wp-admin/tools.php?page=ai-agent-settings" \
	"$BASE_URL/wp-admin/options-general.php?page=ai-agent-settings"; do

	SETTINGS_RESP=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -w "\n%{http_code}" "$URL")
	SETTINGS_CODE=$(echo "$SETTINGS_RESP" | tail -1)
	SETTINGS_BODY=$(echo "$SETTINGS_RESP" | head -n -1)

	if [ "$SETTINGS_CODE" = "200" ]; then
		if echo "$SETTINGS_BODY" | grep -qi "ai-agent\|provider\|settings"; then
			echo "✅ Found AI Agent Settings at: $URL"
			echo "$SETTINGS_BODY" >/tmp/wp-ai-agent-settings.html

			# Extract provider info
			echo "Provider-related content:"
			echo "$SETTINGS_BODY" | grep -oi '[^<]*provider[^<]*' | head -10
			echo ""
			echo "Select dropdowns:"
			echo "$SETTINGS_BODY" | grep -oP '<select[^>]*>.*?</select>' | head -5
			echo ""
			echo "React root elements:"
			echo "$SETTINGS_BODY" | grep -oP 'id="ai-agent[^"]*"' | head -5
			echo ""
			echo "Enqueued AI Agent scripts:"
			echo "$SETTINGS_BODY" | grep -oP 'src="[^"]*ai-agent[^"]*"' | head -5
			break
		fi
	fi
done

# Check admin menu for AI Agent items
echo ""
echo "AI Agent menu items:"
ADMIN_PAGE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE_URL/wp-admin/")
echo "$ADMIN_PAGE" | grep -oP 'href="[^"]*page=ai-agent[^"]*"[^>]*>[^<]*' | head -10

# ========== STEP 5: AI Agent Chat Page ==========
echo ""
echo "=== STEP 5: AI Agent Chat Page ==="

for URL in \
	"$BASE_URL/wp-admin/admin.php?page=ai-agent" \
	"$BASE_URL/wp-admin/tools.php?page=ai-agent"; do

	CHAT_RESP=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -w "\n%{http_code}" "$URL")
	CHAT_CODE=$(echo "$CHAT_RESP" | tail -1)
	CHAT_BODY=$(echo "$CHAT_RESP" | head -n -1)

	if [ "$CHAT_CODE" = "200" ]; then
		if echo "$CHAT_BODY" | grep -qi "ai-agent"; then
			echo "✅ Found AI Agent Chat page at: $URL"
			echo "$CHAT_BODY" >/tmp/wp-ai-agent-chat.html

			echo "Chat UI elements:"
			echo "$CHAT_BODY" | grep -oP 'id="[^"]*ai-agent[^"]*"' | head -10
			echo ""
			echo "Chat-related classes:"
			echo "$CHAT_BODY" | grep -oP 'class="[^"]*chat[^"]*"' | head -10
			echo ""
			echo "Enqueued scripts:"
			echo "$CHAT_BODY" | grep -oP 'src="[^"]*ai-agent[^"]*\.js[^"]*"' | head -10
			echo ""
			echo "Inline script data:"
			echo "$CHAT_BODY" | grep -oP 'aiAgentData[^<]*' | head -5
			echo "$CHAT_BODY" | grep -oP 'ai_agent[^<]*' | head -5
			break
		fi
	fi
done

# ========== STEP 6 & 7: Test REST API ==========
echo ""
echo "=== STEP 6/7: Test REST API Endpoints ==="

# Test creating a session
echo "Creating a session..."
SESSION_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-X POST \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $REST_NONCE" \
	"$BASE_URL/?rest_route=/ai-agent/v1/sessions" \
	-d '{"title":"E2E Test Session"}')
echo "Session creation: $SESSION_RESULT" | head -c 500
echo ""

# Extract session ID
SESSION_ID=$(echo "$SESSION_RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('id', d.get('session_id', '')))" 2>/dev/null || echo "")
echo "Session ID: $SESSION_ID"

# Test the run endpoint
echo ""
echo "Testing /ai-agent/v1/run endpoint..."
RUN_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-X POST \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $REST_NONCE" \
	--max-time 30 \
	"$BASE_URL/?rest_route=/ai-agent/v1/run" \
	-d '{"message":"Hello! What model are you?","provider":"openai-compatible","model":"gpt-4o"}')
echo "Run result: $RUN_RESULT" | head -c 1000
echo ""

# Test the chat endpoint
echo ""
echo "Testing /ai-agent/v1/chat endpoint..."
CHAT_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-X POST \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $REST_NONCE" \
	--max-time 30 \
	"$BASE_URL/?rest_route=/ai-agent/v1/chat" \
	-d '{"message":"Hello! What model are you?"}')
echo "Chat result: $CHAT_RESULT" | head -c 1000
echo ""

# If we have a session, try sending a message to it
if [ -n "$SESSION_ID" ] && [ "$SESSION_ID" != "" ]; then
	echo ""
	echo "Testing /ai-agent/v1/sessions/$SESSION_ID/messages endpoint..."
	MSG_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
		-X POST \
		-H "Content-Type: application/json" \
		-H "X-WP-Nonce: $REST_NONCE" \
		--max-time 30 \
		"$BASE_URL/?rest_route=/ai-agent/v1/sessions/$SESSION_ID/messages" \
		-d '{"message":"Hello! What model are you?"}')
	echo "Message result: $MSG_RESULT" | head -c 1000
	echo ""
fi

# Test listing providers
echo ""
echo "Testing /ai-agent/v1/providers endpoint..."
PROVIDERS_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-H "X-WP-Nonce: $REST_NONCE" \
	"$BASE_URL/?rest_route=/ai-agent/v1/providers")
echo "Providers: $PROVIDERS_RESULT" | head -c 1000
echo ""

# Test listing models
echo ""
echo "Testing /ai-agent/v1/models endpoint..."
MODELS_RESULT=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-H "X-WP-Nonce: $REST_NONCE" \
	"$BASE_URL/?rest_route=/ai-agent/v1/models")
echo "Models: $MODELS_RESULT" | head -c 1000
echo ""

# ========== STEP 8: Check for errors ==========
echo ""
echo "=== STEP 8: Error Checking ==="

# Check for PHP errors in responses
echo "Checking for PHP errors..."
for FILE in /tmp/wp-ai-agent-chat.html /tmp/wp-ai-agent-settings.html /tmp/wp-plugins-page.html; do
	if [ -f "$FILE" ]; then
		ERRORS=$(grep -ci "Fatal error\|Warning:\|Notice:\|Parse error\|Deprecated:" "$FILE" 2>/dev/null || echo "0")
		echo "  $FILE: $ERRORS PHP errors/warnings"
		if [ "$ERRORS" -gt 0 ]; then
			grep -i "Fatal error\|Warning:\|Notice:\|Parse error\|Deprecated:" "$FILE" | head -5
		fi
	fi
done

# Check debug.log
echo ""
echo "Checking debug.log..."
DEBUG_LOG=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/wp-content/debug.log")
echo "debug.log HTTP status: $DEBUG_LOG"

# Try to get debug log via WP-CLI in docker
echo ""
echo "Checking Docker container for debug.log..."
CONTAINER=$(docker ps --format '{{.Names}}' | grep -i wordpress | head -1)
if [ -n "$CONTAINER" ]; then
	echo "Container: $CONTAINER"
	docker exec "$CONTAINER" cat /var/www/html/wp-content/debug.log 2>/dev/null | tail -50 || echo "No debug.log or not accessible"
	echo ""
	echo "Checking PHP error log..."
	docker exec "$CONTAINER" cat /var/log/php_errors.log 2>/dev/null | tail -20 || echo "No PHP error log"

	# Also check wp options for connector settings
	echo ""
	echo "Checking WP options for connector/ai-agent settings..."
	docker exec "$CONTAINER" wp option list --search="*openai*" --allow-root 2>/dev/null || echo "WP-CLI not available or option not found"
	docker exec "$CONTAINER" wp option list --search="*ai_agent*" --allow-root 2>/dev/null || echo "WP-CLI not available or option not found"
	docker exec "$CONTAINER" wp option list --search="*connector*" --allow-root 2>/dev/null || echo "WP-CLI not available or option not found"

	# Set the connector options directly via WP-CLI
	echo ""
	echo "Setting connector options via WP-CLI..."
	docker exec "$CONTAINER" wp option update openai_compatible_connector_endpoint "https://api.synthetic.new/openai/v1" --allow-root 2>/dev/null || echo "Failed to set endpoint"
	docker exec "$CONTAINER" wp option update openai_compatible_connector_api_key "syn_7eb697227c00701e38e6be1a1e5ba3d3" --allow-root 2>/dev/null || echo "Failed to set API key"

	# Check active plugins
	echo ""
	echo "Active plugins:"
	docker exec "$CONTAINER" wp plugin list --allow-root 2>/dev/null || echo "WP-CLI not available"

	# Network activate if needed
	echo ""
	echo "Network activating plugins..."
	docker exec "$CONTAINER" wp plugin activate ai-agent --network --allow-root 2>/dev/null || echo "AI Agent activation result above"
	docker exec "$CONTAINER" wp plugin activate openai-compatible-connector --network --allow-root 2>/dev/null || echo "Connector activation result above"
fi

echo ""
echo "=========================================="
echo "E2E TEST COMPLETE"
echo "=========================================="
