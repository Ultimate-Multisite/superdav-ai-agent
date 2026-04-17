<?php

declare(strict_types=1);
/**
 * Contract for session storage operations.
 *
 * Decouples callers (AgentLoop, REST controllers, CLI commands) from the
 * concrete storage implementation so that tests can inject a fake and the
 * real implementation can be swapped when t189 splits Database.php into
 * domain repositories.
 *
 * @package GratisAiAgent\Contracts
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SessionRepositoryInterface
 *
 * Defines the CRUD contract for AI agent conversation sessions.
 * All methods correspond directly to the existing Database static methods
 * so the adapter is a thin delegation wrapper until t189 delivers
 * includes/Models/SessionRepository.php.
 */
interface SessionRepositoryInterface {

	/**
	 * Create a new session and return its ID.
	 *
	 * @param array<string, mixed> $data Session fields (user_id, title, provider_id, model_id).
	 * @return int|false New session ID on success, false on failure.
	 */
	public function create_session( array $data ): int|false;

	/**
	 * Get a single session by ID.
	 *
	 * @param int $session_id Session ID.
	 * @return object|null Session row object, or null when not found.
	 */
	public function get_session( int $session_id ): ?object;

	/**
	 * List sessions for a user.
	 *
	 * @param int                  $user_id WordPress user ID.
	 * @param array<string, mixed> $filters Optional filters: status, folder, search, pinned.
	 * @return list<object>|null Array of session summary objects, or null on failure.
	 */
	public function list_sessions( int $user_id, array $filters = [] ): ?array;

	/**
	 * Update fields on an existing session.
	 *
	 * @param int                  $session_id Session ID.
	 * @param array<string, mixed> $data       Fields to update.
	 * @return bool True on success.
	 */
	public function update_session( int $session_id, array $data ): bool;

	/**
	 * Delete a session and its associated data.
	 *
	 * @param int $session_id Session ID.
	 * @return bool True on success.
	 */
	public function delete_session( int $session_id ): bool;

	/**
	 * Persist the paused agent-loop state for a session.
	 *
	 * @param int                  $session_id Session ID.
	 * @param array<string, mixed> $state      Serializable loop state.
	 * @return bool True on success.
	 */
	public function save_paused_state( int $session_id, array $state ): bool;

	/**
	 * Load and atomically clear the paused agent-loop state for a session.
	 *
	 * Returns the state and clears the column so a second resume attempt
	 * cannot replay the same state.
	 *
	 * @param int $session_id Session ID.
	 * @return array<string, mixed>|null Paused state, or null if none.
	 */
	public function load_and_clear_paused_state( int $session_id ): ?array;

	/**
	 * Append messages and tool calls to a session.
	 *
	 * @param int   $session_id Session ID.
	 * @param array $messages   New message arrays to append.
	 * @param array $tool_calls New tool call log entries to append.
	 * @return bool True on success.
	 *
	 * @phpstan-param list<mixed>                $messages
	 * @phpstan-param list<array<string, mixed>> $tool_calls
	 */
	public function append_to_session( int $session_id, array $messages, array $tool_calls = [] ): bool;

	/**
	 * Accumulate prompt and completion token counts for a session.
	 *
	 * @param int $session_id        Session ID.
	 * @param int $prompt_tokens     Prompt tokens to add.
	 * @param int $completion_tokens Completion tokens to add.
	 * @return bool True on success.
	 */
	public function update_session_tokens( int $session_id, int $prompt_tokens, int $completion_tokens ): bool;
}
