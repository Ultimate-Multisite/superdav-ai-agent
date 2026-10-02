# Outbound MCP connections

Administrators configure remote MCP servers from **Settings → Tools → MCP servers**.

1. Select **Add server**, then enter the server URL and, optionally, a friendly name. When no name is provided, the endpoint hostname is used.
2. Select **No authentication** or **Access token**. API-key and custom-header options are available under **Advanced options** for servers that require them.
3. Select **Connect**. The plugin saves a disabled connection, discovers its tools once, and enables it only after discovery succeeds.

A failed discovery leaves the connection disabled and editable. Existing servers show a plain-language connection status and the number of discovered tools. **Reconnect** and **Refresh tools** both perform one discovery request; the initial connect flow does not run both actions.

Connecting a server does not grant its tools additional permissions. Existing capability checks and tool confirmation policies still apply. Credentials are submitted only with the form, cleared after the request finishes, and omitted from connection details and exported JSON. Imported servers are disabled until reviewed and connected.
