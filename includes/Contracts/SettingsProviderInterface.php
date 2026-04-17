<?php

declare(strict_types=1);
/**
 * Contract for plugin settings access.
 *
 * Decouples callers from the static Settings class so tests can inject a
 * fake implementation and the real class can be converted to an injectable
 * DI singleton (t192) without breaking callsites.
 *
 * @package GratisAiAgent\Contracts
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SettingsProviderInterface
 *
 * Minimal read/write contract that mirrors the three most-used Settings
 * static methods.  Provider-key helpers and credential accessors are
 * intentionally excluded — those are secrets-management concerns that
 * should be injected separately.
 */
interface SettingsProviderInterface {

	/**
	 * Get all settings merged with defaults, or a single key.
	 *
	 * @param string|null $key Setting key to retrieve, or null for all settings.
	 * @return mixed All settings array when $key is null, the scalar/array value
	 *               for the requested key, or null when the key is unknown.
	 */
	public function get( ?string $key = null ): mixed;

	/**
	 * Partial-update settings (merge incoming data with existing stored values).
	 *
	 * Unknown keys are silently ignored.
	 *
	 * @param array<string, mixed> $data Key-value pairs to update.
	 * @return bool True when the option was updated.
	 */
	public function update( array $data ): bool;

	/**
	 * Return the default value for every known setting key.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public function get_defaults(): array;
}
