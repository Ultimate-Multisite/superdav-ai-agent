# Outbound MCP connections

Administrators configure remote MCP servers from **Settings → Tools → MCP servers**.

1. Select **Add server**, then enter the server URL and, optionally, a friendly name. When no name is provided, the endpoint hostname is used.
2. Select **No authentication**, **Access token**, or **Sign in (OAuth)**. API-key and custom-header options are available under **Advanced options** for servers that require them.
3. Select **Connect**. The plugin saves a disabled connection, discovers its tools once, and enables it only after discovery succeeds.

A failed setup leaves a new connection disabled and editable. Existing servers show a plain-language status and the number of discovered tools. **Refresh tools** fetches the current list; enablement checks an expired snapshot before making its tools available.

Connecting a server does not grant its tools additional permissions. Existing capability checks and confirmation policies apply to the real current WordPress user. Remote read-only/idempotent hints never bypass approval. The administrator configures site-wide connections, available only to users who pass the existing tool capability gates (administrators by default).

## Sign in to an OAuth server

Use **Sign in**, approve access on the server's authorization page, and return to **Settings → Tools**. Access and refresh tokens are managed on the server; never paste them into the chat. Sign in again after consent expires or is revoked. **Disconnect sign-in** removes local authorization; **Remove** also deletes the connection.

OAuth requires HTTPS on the WordPress site (HTTP localhost callbacks are the development exception). Automatic setup uses a Client ID Metadata Document served by this site, so the authorization server must support that standard and be able to reach the HTTPS identity URL. If it does not, register a client with that server and enter its client ID and, where required, secret under **Advanced**. The manager provides the redirect URI. Dynamic client registration and other OAuth grants are not supported.

Discovery follows MCP authorization specification **2026-07-28**: protected-resource metadata from a Bearer challenge or well-known location, OAuth/OpenID authorization-server discovery, exact issuer validation and resource binding, authorization-code + S256 PKCE, one-time administrator/site-bound state, and request-time refresh. A server with unsupported metadata gets an actionable setup error rather than an unsafe fallback.

## Supported servers and limits

- Modern **Streamable HTTP**, with JSON or bounded SSE response bodies. Protocol revisions 2026-07-28, 2025-11-25 and 2025-06-18 are accepted through initialization negotiation.
- Text and structured tool results; this initial version does not consume prompts/resources, embedded media or client actions.
- WordPress REST-compatible input schemas. Unsupported schema constructs, external references and unsafe descriptions/results are rejected; no external schema is fetched.
- Discovery is bounded to fewer than 100 tools and at most 10 pages. HTTP calls have a 15-second timeout and 256 KiB response limit; tool arguments/results have a 64 KiB limit and bounded depth.
- Snapshots expire after 15 minutes and refresh once on demand or through the explicit Refresh action. Failed refresh keeps the prior list for diagnosis but does not execute unverified stale tools. No persistent listeners or background discovery service run.
- Long canonical MCP ability IDs use the existing ability-search/ability-call bridge rather than invalidating an AI provider's function-name limit. The connection IDs and WordPress ability names are not renamed.

Legacy HTTP+SSE transport and stdio/command execution are not supported. Import accepts the existing `mcpServers` URL format, never executes commands, excludes credentials and saves servers disabled for review.

## Safety and troubleshooting

Private/loopback/metadata endpoints are blocked by the existing SSRF policy. Credentials require HTTPS; OAuth metadata/token redirects are rejected instead of forwarding credentials. Tokens stay in the private credential store, encrypted and excluded from generic option tools, exports, provider traces and operational logs. Known locally configured credential values are scrubbed if a remote tool echoes them.

Remote descriptions/results are untrusted data. Size/schema checks, known forged-role/instruction detection and the normal permission gates are defense in depth, not a guarantee that all prompt injection can be detected. Only connect servers you trust with the data and access you grant.

Tool calls are never automatically replayed after an ambiguous timeout, interruption or invalid response. If the result says **outcome unknown**, check the remote server before repeating the operation. Authentication recovery can retry a rejected non-tool request once; it does not silently repeat a tool mutation.
