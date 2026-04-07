/**
 * Shared JSDoc type definitions for the Gratis AI Agent plugin.
 *
 * This file is not imported at runtime — it exists solely to provide
 * type information to editors and documentation tools via JSDoc.
 *
 * @module types
 */

/**
 * A single part of a message (text or other content).
 *
 * @typedef {Object} MessagePart
 * @property {string} [text] - Text content of this part.
 */

/**
 * Debug metadata attached to a model message when debug mode is active.
 *
 * @typedef {Object} MessageDebug
 * @property {number}                               responseTimeMs  - Total response time in milliseconds.
 * @property {{prompt: number, completion: number}} tokenUsage      - Token counts.
 * @property {number}                               tokensPerSecond - Streaming speed in tokens per second.
 * @property {string}                               modelId         - Model identifier used for this response.
 * @property {number}                               costEstimate    - Estimated cost in USD.
 * @property {number}                               iterationsUsed  - Number of agent loop iterations.
 * @property {number}                               toolCallCount   - Number of tool calls made.
 * @property {string[]}                             toolNames       - Unique tool names called.
 */

/**
 * A single chat message.
 *
 * @typedef {Object} Message
 * @property {'user'|'model'|'system'|'function'} role        - Message role.
 * @property {MessagePart[]}                      parts       - Message content parts.
 * @property {ToolCall[]}                         [toolCalls] - Tool calls attached to this message.
 * @property {MessageDebug}                       [debug]     - Debug metadata (debug mode only).
 */

/**
 * A tool call or tool result entry.
 *
 * @typedef {Object} ToolCall
 * @property {'call'|'result'} type       - Whether this is a call or its result.
 * @property {string}          name       - Tool name.
 * @property {Object}          [args]     - Arguments passed to the tool (for calls).
 * @property {string|Object}   [response] - Response from the tool (for results).
 * @property {string}          [id]       - Tool call identifier.
 */

/**
 * A model entry within a provider.
 *
 * @typedef {Object} ProviderModel
 * @property {string} id   - Model identifier.
 * @property {string} name - Human-readable model name.
 */

/**
 * An AI provider configuration.
 *
 * @typedef {Object} Provider
 * @property {string}          id       - Provider identifier.
 * @property {string}          name     - Human-readable provider name.
 * @property {ProviderModel[]} [models] - Available models for this provider.
 */

/**
 * A chat session summary (as returned by the sessions list endpoint).
 *
 * @typedef {Object} Session
 * @property {number|string}                        id            - Session identifier.
 * @property {string}                               [title]       - Session title.
 * @property {string}                               [status]      - Session status: 'active', 'archived', 'trash'.
 * @property {string|number}                        [pinned]      - Non-zero/truthy when pinned.
 * @property {string}                               [folder]      - Folder name the session belongs to.
 * @property {string}                               [updated_at]  - ISO 8601 timestamp (without trailing Z).
 * @property {string}                               [provider_id] - Provider used for this session.
 * @property {string}                               [model_id]    - Model used for this session.
 * @property {{prompt: number, completion: number}} [token_usage] - Cumulative token usage.
 */

/**
 * A memory entry.
 *
 * @typedef {Object} Memory
 * @property {number} id       - Memory identifier.
 * @property {string} category - Memory category (e.g. 'general').
 * @property {string} content  - Memory content text.
 */

/**
 * A skill entry.
 *
 * @typedef {Object} Skill
 * @property {number}  id            - Skill identifier.
 * @property {string}  name          - Skill name / slug.
 * @property {string}  [label]       - Human-readable label.
 * @property {string}  [description] - Skill description.
 * @property {boolean} [builtin]     - Whether this is a built-in skill.
 */

/**
 * Plugin settings object.
 *
 * @typedef {Object} Settings
 * @property {boolean} [onboarding_complete]    - Whether onboarding has been completed.
 * @property {string}  [default_provider]       - Default provider ID.
 * @property {string}  [default_model]          - Default model ID.
 * @property {number}  [context_window_default] - Default context window size in tokens.
 */

/**
 * Token usage counters.
 *
 * @typedef {Object} TokenUsage
 * @property {number} prompt     - Input/prompt tokens consumed.
 * @property {number} completion - Output/completion tokens generated.
 */

/**
 * Pending tool confirmation payload.
 *
 * @typedef {Object} PendingConfirmation
 * @property {string}     jobId - Job identifier awaiting confirmation.
 * @property {ToolCall[]} tools - Tools pending user approval.
 */

/**
 * The Redux store state shape.
 *
 * @typedef {Object} StoreState
 * @property {Provider[]}               providers               - Available AI providers.
 * @property {boolean}                  providersLoaded         - Whether providers have been fetched.
 * @property {Session[]}                sessions                - Session list.
 * @property {boolean}                  sessionsLoaded          - Whether sessions have been fetched.
 * @property {number|null}              currentSessionId        - Active session ID.
 * @property {Message[]}                currentSessionMessages  - Messages in the active session.
 * @property {ToolCall[]}               currentSessionToolCalls - Tool calls in the active session.
 * @property {boolean}                  sending                 - Whether a message is in-flight.
 * @property {string|null}              currentJobId            - Active polling job ID.
 * @property {string}                   selectedProviderId      - Currently selected provider ID.
 * @property {string}                   selectedModelId         - Currently selected model ID.
 * @property {boolean}                  floatingOpen            - Whether the floating panel is open.
 * @property {boolean}                  floatingMinimized       - Whether the floating panel is minimized.
 * @property {string|Object}            pageContext             - Structured page context for the AI.
 * @property {string}                   sessionFilter           - Active session filter tab.
 * @property {string}                   sessionFolder           - Active folder filter.
 * @property {string}                   sessionSearch           - Active search query.
 * @property {string[]}                 folders                 - Available folder names.
 * @property {boolean}                  foldersLoaded           - Whether folders have been fetched.
 * @property {Settings|null}            settings                - Plugin settings.
 * @property {boolean}                  settingsLoaded          - Whether settings have been fetched.
 * @property {Memory[]}                 memories                - Memory entries.
 * @property {boolean}                  memoriesLoaded          - Whether memories have been fetched.
 * @property {Skill[]}                  skills                  - Skill entries.
 * @property {boolean}                  skillsLoaded            - Whether skills have been fetched.
 * @property {TokenUsage}               tokenUsage              - Cumulative token usage for the session.
 * @property {PendingConfirmation|null} pendingConfirmation     - Pending tool confirmation.
 * @property {boolean}                  debugMode               - Whether debug mode is active.
 * @property {number}                   sendTimestamp           - Timestamp of the last send (ms since epoch).
 * @property {string}                   streamingText           - Accumulated streaming text buffer.
 * @property {boolean}                  isStreaming             - Whether an SSE stream is active.
 * @property {AbortController|null}     [streamAbortController] - Controller for the active stream.
 */

/**
 * A slash command definition.
 *
 * @typedef {Object} SlashCommand
 * @property {string} name        - Command name including leading slash (e.g. '/new').
 * @property {string} description - Human-readable description.
 * @property {string} action      - Action key used in the handler switch.
 */

/**
 * A keyboard shortcut definition.
 *
 * @typedef {Object} KeyboardShortcut
 * @property {string} combo - Key combination string (e.g. 'mod+k', 'escape').
 * @property {string} label - Human-readable label for the shortcut.
 */

/**
 * A 2D position (pixels from top-left of viewport).
 *
 * @typedef {Object} Position
 * @property {number} x - Horizontal offset in pixels.
 * @property {number} y - Vertical offset in pixels.
 */

/**
 * Return value of the useDrag hook.
 *
 * @typedef {Object} UseDragReturn
 * @property {Position|null} position        - Current custom position, or null for CSS default.
 * @property {boolean}       isDragging      - Whether a drag is in progress.
 * @property {Function}      handleMouseDown - mousedown event handler to attach to the drag handle.
 * @property {Function}      resetPosition   - Resets position to CSS default and clears storage.
 */
