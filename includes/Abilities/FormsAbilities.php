<?php

declare(strict_types=1);
/**
 * Forms abilities for the AI agent.
 *
 * Provides tools for creating contact forms on WordPress sites.
 * Uses Contact Form 7 when active; falls back to a native Gutenberg
 * HTML block form when CF7 is unavailable.
 *
 * Fallback chain:
 *  1. CF7 active (class_exists WPCF7_ContactForm) → WPCF7_ContactForm::create() + shortcode
 *  2. CF7 absent → Gutenberg <!-- wp:html --> block with raw HTML form
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormsAbilities {

	/**
	 * Register all form-related abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/create-contact-form',
			[
				'label'         => __( 'Create Contact Form', 'gratis-ai-agent' ),
				'description'   => __( 'Create a contact form. Uses Contact Form 7 if active; otherwise inserts a Gutenberg HTML block contact form. Returns the shortcode or block markup and optionally appends it to an existing page.', 'gratis-ai-agent' ),
				'ability_class' => CreateContactFormAbility::class,
			]
		);
	}
}

/**
 * Create Contact Form ability.
 *
 * Registers as `ai-agent/create-contact-form`.
 *
 * Fallback chain:
 *   1. CF7 active → WPCF7_ContactForm::create() returns a [contact-form-7 …] shortcode.
 *   2. No CF7 → raw HTML form wrapped in a <!-- wp:html --> Gutenberg block.
 *
 * Optional `page_id` input causes the shortcode or block to be appended to
 * that page's post_content via wp_update_post().
 *
 * @since 1.2.0
 */
class CreateContactFormAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Create Contact Form', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Create a contact form. Uses Contact Form 7 if active; otherwise inserts a Gutenberg HTML block contact form. Returns the shortcode or block markup and optionally appends it to an existing page.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'title'           => [
					'type'        => 'string',
					'description' => 'Form title (default: "Contact Us").',
				],
				'page_id'         => [
					'type'        => 'integer',
					'description' => 'Page ID to append the form shortcode or block to. Omit to return the markup without inserting it.',
				],
				'recipient_email' => [
					'type'        => 'string',
					'description' => 'Email address for CF7 form submissions. Defaults to the WordPress admin email. Ignored for the HTML block fallback.',
				],
			],
			'required'   => [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'method'       => [ 'type' => 'string' ],
				'form_id'      => [ 'type' => 'integer' ],
				'shortcode'    => [ 'type' => 'string' ],
				'block_markup' => [ 'type' => 'string' ],
				'page_id'      => [ 'type' => 'integer' ],
				'message'      => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Execute the ability: route to CF7 or HTML block fallback.
	 *
	 * @param mixed $input Validated input from the Abilities API.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected function execute_callback( $input ) {
		/** @var array<string,mixed> $input */
		$title   = sanitize_text_field( (string) ( $input['title'] ?? 'Contact Us' ) );
		$page_id = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;

		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			$recipient = sanitize_email(
				(string) ( $input['recipient_email'] ?? get_option( 'admin_email', '' ) )
			);
			return $this->create_cf7_form( $title, $page_id, $recipient );
		}

		return $this->create_html_block_form( $title, $page_id );
	}

	/**
	 * Create a Contact Form 7 form and return its shortcode.
	 *
	 * @param string $title     Form title.
	 * @param int    $page_id   Optional page ID to append shortcode to.
	 * @param string $recipient Email for CF7 mail configuration.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function create_cf7_form( string $title, int $page_id, string $recipient ) {
		// WPCF7_ContactForm::create() accepts title/locale and returns a
		// WPCF7_ContactForm object on success, or a WP_Error on failure.
		// @phpstan-ignore-next-line — WPCF7_ContactForm is a third-party plugin class.
		$form = \WPCF7_ContactForm::create(
			[
				'title'  => $title,
				'locale' => get_locale(),
			]
		);

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		if ( ! is_object( $form ) ) {
			return new \WP_Error(
				'cf7_create_failed',
				__( 'Contact Form 7 returned an unexpected response when creating the form.', 'gratis-ai-agent' )
			);
		}

		// @phpstan-ignore-next-line — WPCF7_ContactForm is a third-party plugin class.
		$form_id   = (int) $form->id();
		$shortcode = sprintf( '[contact-form-7 id="%d" title="%s"]', $form_id, esc_attr( $title ) );

		$inserted_page_id = $this->append_to_page( $page_id, $shortcode );

		return [
			'method'       => 'cf7',
			'form_id'      => $form_id,
			'shortcode'    => $shortcode,
			'block_markup' => '',
			'page_id'      => $inserted_page_id,
			'message'      => $inserted_page_id > 0
				? sprintf(
					/* translators: 1: page ID, 2: CF7 shortcode */
					__( 'Contact Form 7 form created and appended to page %1$d. Shortcode: %2$s', 'gratis-ai-agent' ),
					$inserted_page_id,
					$shortcode
				)
				: sprintf(
					/* translators: %s: CF7 shortcode */
					__( 'Contact Form 7 form created. Insert this shortcode into any page or post: %s', 'gratis-ai-agent' ),
					$shortcode
				),
		];
	}

	/**
	 * Create a raw HTML contact form wrapped in a Gutenberg HTML block.
	 *
	 * Note: The HTML block provides markup only. Form submission handling
	 * requires a server-side plugin or custom theme code.
	 *
	 * @param string $title   Form heading text.
	 * @param int    $page_id Optional page ID to append block to.
	 * @return array<string,mixed>
	 */
	private function create_html_block_form( string $title, int $page_id ) {
		$block_markup = $this->build_html_block( $title );

		$inserted_page_id = $this->append_to_page( $page_id, $block_markup );

		return [
			'method'       => 'html-block',
			'form_id'      => 0,
			'shortcode'    => '',
			'block_markup' => $block_markup,
			'page_id'      => $inserted_page_id,
			'message'      => $inserted_page_id > 0
				? sprintf(
					/* translators: %d: page ID */
					__( 'HTML contact form block appended to page %d. Note: form submission handling requires a server-side plugin or custom code.', 'gratis-ai-agent' ),
					$inserted_page_id
				)
				: __( 'HTML contact form block markup generated. Paste into any page or post content to display the form. Note: form submission handling requires a server-side plugin or custom code.', 'gratis-ai-agent' ),
		];
	}

	/**
	 * Build a Gutenberg HTML block wrapping a plain HTML contact form.
	 *
	 * @param string $title Form heading text.
	 * @return string Serialised Gutenberg HTML block.
	 */
	private function build_html_block( string $title ): string {
		$form_html  = "<!-- wp:html -->\n";
		$form_html .= "<div class=\"wp-contact-form\">\n";
		$form_html .= '  <h2>' . esc_html( $title ) . "</h2>\n";
		$form_html .= "  <form method=\"post\" action=\"\">\n";
		$form_html .= "    <input type=\"hidden\" name=\"gratis_contact_form\" value=\"1\" />\n";

		$form_html .= "    <p>\n";
		$form_html .= '      <label for="cf-name">' . esc_html__( 'Your Name (required)', 'gratis-ai-agent' ) . "</label><br />\n";
		$form_html .= "      <input type=\"text\" id=\"cf-name\" name=\"your-name\" required />\n";
		$form_html .= "    </p>\n";

		$form_html .= "    <p>\n";
		$form_html .= '      <label for="cf-email">' . esc_html__( 'Your Email (required)', 'gratis-ai-agent' ) . "</label><br />\n";
		$form_html .= "      <input type=\"email\" id=\"cf-email\" name=\"your-email\" required />\n";
		$form_html .= "    </p>\n";

		$form_html .= "    <p>\n";
		$form_html .= '      <label for="cf-subject">' . esc_html__( 'Subject', 'gratis-ai-agent' ) . "</label><br />\n";
		$form_html .= "      <input type=\"text\" id=\"cf-subject\" name=\"your-subject\" />\n";
		$form_html .= "    </p>\n";

		$form_html .= "    <p>\n";
		$form_html .= '      <label for="cf-message">' . esc_html__( 'Your Message', 'gratis-ai-agent' ) . "</label><br />\n";
		$form_html .= "      <textarea id=\"cf-message\" name=\"your-message\" rows=\"10\" cols=\"40\"></textarea>\n";
		$form_html .= "    </p>\n";

		$form_html .= "    <p>\n";
		$form_html .= '      <input type="submit" value="' . esc_attr__( 'Send', 'gratis-ai-agent' ) . "\" />\n";
		$form_html .= "    </p>\n";

		$form_html .= "  </form>\n";
		$form_html .= "</div>\n";
		$form_html .= '<!-- /wp:html -->';

		return $form_html;
	}

	/**
	 * Append content (shortcode or block) to a page's post_content.
	 *
	 * @param int    $page_id Page ID. 0 means no insertion.
	 * @param string $content Content to append.
	 * @return int The page ID if insertion succeeded, 0 otherwise.
	 */
	private function append_to_page( int $page_id, string $content ): int {
		if ( $page_id <= 0 ) {
			return 0;
		}

		$post = get_post( $page_id );
		if ( ! $post ) {
			return 0;
		}

		$result = wp_update_post(
			[
				'ID'           => $page_id,
				'post_content' => $post->post_content . "\n\n" . $content,
			]
		);

		return is_wp_error( $result ) ? 0 : $page_id;
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}
