<?php

declare(strict_types=1);
/**
 * Transitional adapter: exposes the static Database class as an injectable
 * instance implementing SessionRepositoryInterface.
 *
 * Remove this class once t189 creates includes/Models/SessionRepository.php —
 * at that point, just update the Plugin::configure() binding to point to
 * SessionRepository::class.
 *
 * @package SdAiAgent\Infrastructure\Adapters
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Infrastructure\Adapters;

use SdAiAgent\Contracts\SessionRepositoryInterface;
use SdAiAgent\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper that satisfies SessionRepositoryInterface by delegating every
 * call to the existing static Database methods.
 *
 * This bridge exists so code can depend on the interface (and receive a fake
 * in tests) while the underlying storage layer stays unchanged until t189
 * extracts a dedicated SessionRepository from Database.php.
 */
class DatabaseSessionAdapter implements SessionRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function create_session( array $data ): int|false {
		return Database::create_session( $data );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_session( int $session_id ): ?object {
		$row = Database::get_session( $session_id );
		return is_object( $row ) ? $row : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_sessions( int $user_id, array $filters = [] ): ?array {
		return Database::list_sessions( $user_id, $filters );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_session( int $session_id, array $data ): bool {
		return Database::update_session( $session_id, $data );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_session( int $session_id ): bool {
		return Database::delete_session( $session_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function save_paused_state( int $session_id, array $state ): bool {
		return Database::save_paused_state( $session_id, $state );
	}

	/**
	 * {@inheritdoc}
	 */
	public function load_and_clear_paused_state( int $session_id ): ?array {
		return Database::load_and_clear_paused_state( $session_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function append_to_session( int $session_id, array $messages, array $tool_calls = [] ): bool {
		return Database::append_to_session( $session_id, $messages, $tool_calls );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_session_tokens( int $session_id, int $prompt_tokens, int $completion_tokens ): bool {
		return Database::update_session_tokens( $session_id, $prompt_tokens, $completion_tokens );
	}
}
