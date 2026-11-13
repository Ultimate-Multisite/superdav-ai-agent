<?php
/**
 * PHPStan stubs for the Ninja Forms APIs used by sd-ai-agent/create-contact-form.
 *
 * Ninja Forms is an optional third-party plugin with no published stubs. Only
 * the model and factory methods this plugin calls are declared, so PHPStan can
 * type-check those calls. At runtime every use is guarded by function_exists()
 * and class_exists().
 *
 * @package SdAiAgent
 */

// phpcs:disable

/**
 * Shared shape of Ninja Forms database models (form, field and action).
 */
abstract class NF_Abstracts_Model {
	/**
	 * @param array<string, mixed> $data
	 * @return $this
	 */
	public function update_settings( $data ) {}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return $this
	 */
	public function update_setting( $key, $value ) {}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function get_setting( $key ) {}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {}

	/**
	 * @return int
	 */
	public function get_id() {}

	public function save() {}
}

final class NF_Database_Models_Form extends NF_Abstracts_Model {
	/**
	 * @param mixed      $db WordPress database handle ($wpdb).
	 * @param int|string $id
	 */
	public function __construct( $db, $id = '' ) {}
}

final class NF_Database_Models_Field extends NF_Abstracts_Model {}

final class NF_Database_Models_Action extends NF_Abstracts_Model {}

/**
 * A Ninja Forms field type definition (Ninja_Forms()->fields[$type]).
 */
abstract class NF_Abstracts_Field {
	/**
	 * @return array<int|string, mixed>
	 */
	public function get_settings() {}
}

/**
 * Returned by Ninja_Forms()->form(): builds and loads a form's models.
 */
final class NF_Database_ModelFactory {
	/**
	 * @param int|string $id
	 * @return NF_Database_ModelFactory
	 */
	public function field( $id = '' ) {}

	/**
	 * @param int|string $id
	 * @return NF_Database_ModelFactory
	 */
	public function action( $id = '' ) {}

	/**
	 * @return NF_Database_Models_Form|NF_Database_Models_Field|NF_Database_Models_Action
	 */
	public function get() {}

	/**
	 * @return array<int, NF_Database_Models_Field>
	 */
	public function get_fields() {}

	/**
	 * @return array<int, NF_Database_Models_Action>
	 */
	public function get_actions() {}
}

final class Ninja_Forms {
	/**
	 * @var array<string, NF_Abstracts_Field>
	 */
	public $fields = [];

	/**
	 * @param int|string $id
	 * @return NF_Database_ModelFactory
	 */
	public function form( $id = '' ) {}
}

final class WPN_Helper {
	/**
	 * @param int                  $form_id
	 * @param array<string, mixed> $form_cache
	 * @return void
	 */
	public static function update_nf_cache( $form_id, $form_cache ) {}
}

/**
 * @return Ninja_Forms
 */
function Ninja_Forms() {}
