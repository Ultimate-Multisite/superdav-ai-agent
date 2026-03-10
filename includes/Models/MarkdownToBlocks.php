<?php

declare(strict_types=1);
/**
 * Markdown to Gutenberg Blocks converter.
 *
 * Standalone line-by-line state machine that converts markdown text
 * into serialized Gutenberg block HTML compatible with WordPress core's
 * serialize_block() format.
 *
 * @package AiAgent
 */

namespace AiAgent\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MarkdownToBlocks {

	/**
	 * Convert markdown text to serialized Gutenberg block HTML.
	 *
	 * @param string $markdown Raw markdown text.
	 * @return string Serialized block HTML ready for post_content.
	 */
	public static function convert( string $markdown ): string {
		$blocks = self::parse( $markdown );
		$output = '';

		foreach ( $blocks as $block ) {
			$output .= serialize_block( $block ) . "\n\n";
		}

		return trim( $output );
	}

	/**
	 * Parse markdown text into an array of block arrays.
	 *
	 * Each block array is compatible with WordPress core's serialize_block().
	 *
	 * @param string $markdown Raw markdown text.
	 * @return array Array of block arrays.
	 */
	public static function parse( string $markdown ): array {
		$lines  = explode( "\n", $markdown );
		$blocks = [];

		$buffer       = [];
		$state        = 'none'; // none, paragraph, code, list, quote, table
		$code_lang    = '';
		$list_ordered = false;
		$table_rows   = [];

		$line_count = count( $lines );

		for ( $i = 0; $i < $line_count; $i++ ) {
			$line = $lines[ $i ];

			// Fenced code block toggle.
			if ( preg_match( '/^```(\w*)/', $line, $m ) ) {
				if ( $state === 'code' ) {
					// Closing fence.
					$blocks[] = self::make_code( implode( "\n", $buffer ), $code_lang );
					$buffer   = [];
					$state    = 'none';
					continue;
				}

				// Opening fence — flush any current buffer first.
				$blocks    = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer    = [];
				$state     = 'code';
				$code_lang = $m[1] ?? '';
				continue;
			}

			// Inside code block — accumulate.
			if ( $state === 'code' ) {
				$buffer[] = $line;
				continue;
			}

			// Blank line — flush.
			if ( trim( $line ) === '' ) {
				$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer     = [];
				$table_rows = [];
				$state      = 'none';
				continue;
			}

			// Heading.
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $m ) ) {
				$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer     = [];
				$table_rows = [];
				$state      = 'none';

				$level    = strlen( $m[1] );
				$blocks[] = self::make_heading( $m[2], $level );
				continue;
			}

			// Separator.
			if ( preg_match( '/^(\-{3,}|\*{3,}|_{3,})$/', trim( $line ) ) ) {
				$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer     = [];
				$table_rows = [];
				$state      = 'none';

				$blocks[] = self::make_separator();
				continue;
			}

			// Standalone image on its own line.
			if ( preg_match( '/^!\[([^\]]*)\]\(([^)]+)\)$/', trim( $line ), $m ) ) {
				$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer     = [];
				$table_rows = [];
				$state      = 'none';

				$blocks[] = self::make_image( $m[2], $m[1] );
				continue;
			}

			// Unordered list item.
			if ( preg_match( '/^[\-\*]\s+(.+)$/', $line, $m ) ) {
				if ( $state !== 'list' || $list_ordered ) {
					$blocks       = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
					$buffer       = [];
					$table_rows   = [];
					$state        = 'list';
					$list_ordered = false;
				}
				$buffer[] = $m[1];
				continue;
			}

			// Ordered list item.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $m ) ) {
				if ( $state !== 'list' || ! $list_ordered ) {
					$blocks       = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
					$buffer       = [];
					$table_rows   = [];
					$state        = 'list';
					$list_ordered = true;
				}
				$buffer[] = $m[1];
				continue;
			}

			// Blockquote.
			if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
				if ( $state !== 'quote' ) {
					$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
					$buffer     = [];
					$table_rows = [];
					$state      = 'quote';
				}
				$buffer[] = $m[1];
				continue;
			}

			// Table row.
			if ( preg_match( '/^\|(.+)\|$/', $line ) ) {
				if ( $state !== 'table' ) {
					$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
					$buffer     = [];
					$table_rows = [];
					$state      = 'table';
				}
				$table_rows[] = $line;
				continue;
			}

			// Default: paragraph text.
			if ( $state !== 'paragraph' ) {
				$blocks     = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );
				$buffer     = [];
				$table_rows = [];
				$state      = 'paragraph';
			}
			$buffer[] = $line;
		}

		// Flush remaining buffer.
		$blocks = array_merge( $blocks, self::flush_buffer( $buffer, $state, $list_ordered, $table_rows ) );

		return $blocks;
	}

	/**
	 * Flush the current buffer into blocks based on the current state.
	 *
	 * @param array  $buffer       Accumulated lines.
	 * @param string $state        Current state.
	 * @param bool   $list_ordered Whether the list is ordered.
	 * @param array  $table_rows   Accumulated table rows.
	 * @return array Array of block arrays.
	 */
	private static function flush_buffer( array $buffer, string $state, bool $list_ordered, array $table_rows ): array {
		if ( empty( $buffer ) && empty( $table_rows ) ) {
			return [];
		}

		switch ( $state ) {
			case 'paragraph':
				$text = implode( ' ', $buffer );
				$html = self::parse_inline( $text );
				return [ self::make_paragraph( $html ) ];

			case 'list':
				return [ self::make_list( $buffer, $list_ordered ) ];

			case 'quote':
				$text = implode( "\n", $buffer );
				$html = self::parse_inline( $text );
				return [ self::make_quote( $html ) ];

			case 'code':
				return [ self::make_code( implode( "\n", $buffer ), '' ) ];

			case 'table':
				return self::parse_table( $table_rows );

			default:
				return [];
		}
	}

	/**
	 * Parse inline markdown formatting to HTML.
	 *
	 * @param string $text Raw inline markdown.
	 * @return string HTML with inline formatting applied.
	 */
	public static function parse_inline( string $text ): string {
		// Inline code (must be first to prevent inner formatting).
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		// Bold.
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );

		// Italic.
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
		$text = preg_replace( '/_(.+?)_/', '<em>$1</em>', $text );

		// Images (inline).
		$text = preg_replace( '/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1"/>', $text );

		// Links.
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text );

		return $text;
	}

	/**
	 * Parse table rows into a table block.
	 *
	 * @param array $rows Raw table row lines.
	 * @return array Array containing the table block (or empty).
	 */
	private static function parse_table( array $rows ): array {
		if ( count( $rows ) < 2 ) {
			return [];
		}

		$parsed_rows     = [];
		$separator_index = -1;

		foreach ( $rows as $index => $row ) {
			$cells = array_map( 'trim', explode( '|', trim( $row, '|' ) ) );

			// Detect separator row (e.g. | --- | --- |).
			if ( preg_match( '/^[\s\-:|]+$/', implode( '|', $cells ) ) ) {
				$separator_index = $index;
				continue;
			}

			$parsed_rows[] = [
				'cells' => $cells,
				'index' => $index,
			];
		}

		if ( empty( $parsed_rows ) ) {
			return [];
		}

		$headers = [];
		$body    = [];

		foreach ( $parsed_rows as $row_data ) {
			$row_cells = [];
			foreach ( $row_data['cells'] as $cell ) {
				$row_cells[] = [
					'content' => self::parse_inline( $cell ),
					'tag'     => ( $separator_index > 0 && $row_data['index'] < $separator_index ) ? 'th' : 'td',
				];
			}

			if ( $separator_index > 0 && $row_data['index'] < $separator_index ) {
				$headers[] = [ 'cells' => $row_cells ];
			} else {
				$body[] = [ 'cells' => $row_cells ];
			}
		}

		return [ self::make_table( $headers, $body ) ];
	}

	// ─── Block makers ─────────────────────────────────────────────

	/**
	 * Make a core/paragraph block.
	 *
	 * @param string $html Inner HTML content.
	 * @return array Block array.
	 */
	public static function make_paragraph( string $html ): array {
		$inner = '<p>' . $html . '</p>';
		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/heading block.
	 *
	 * @param string $text  Heading text (may contain inline markdown).
	 * @param int    $level Heading level (1-6).
	 * @return array Block array.
	 */
	public static function make_heading( string $text, int $level = 2 ): array {
		$html  = self::parse_inline( $text );
		$inner = '<h' . $level . ' class="wp-block-heading">' . $html . '</h' . $level . '>';
		$attrs = [];

		if ( $level !== 2 ) {
			$attrs['level'] = $level;
		}

		return [
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/list block with core/list-item inner blocks.
	 *
	 * @param array $items   Array of item text strings.
	 * @param bool  $ordered Whether the list is ordered.
	 * @return array Block array.
	 */
	public static function make_list( array $items, bool $ordered = false ): array {
		$tag           = $ordered ? 'ol' : 'ul';
		$attrs         = $ordered ? [ 'ordered' => true ] : [];
		$inner_blocks  = [];
		$inner_content = [ '<' . $tag . '>' ];

		foreach ( $items as $item ) {
			$item_html       = self::parse_inline( $item );
			$li_html         = '<li>' . $item_html . '</li>';
			$inner_blocks[]  = [
				'blockName'    => 'core/list-item',
				'attrs'        => [],
				'innerBlocks'  => [],
				'innerHTML'    => $li_html,
				'innerContent' => [ $li_html ],
			];
			$inner_content[] = null; // Placeholder for inner block.
		}

		$inner_content[] = '</' . $tag . '>';

		$inner_html = '<' . $tag . '>';
		foreach ( $items as $item ) {
			$inner_html .= '<li>' . self::parse_inline( $item ) . '</li>';
		}
		$inner_html .= '</' . $tag . '>';

		return [
			'blockName'    => 'core/list',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Make a core/quote block.
	 *
	 * @param string $content HTML content for the quote.
	 * @return array Block array.
	 */
	public static function make_quote( string $content ): array {
		$inner = '<blockquote class="wp-block-quote"><p>' . $content . '</p></blockquote>';
		return [
			'blockName'    => 'core/quote',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/code block.
	 *
	 * @param string $code     Raw code content (will be HTML-escaped).
	 * @param string $language Code language identifier.
	 * @return array Block array.
	 */
	public static function make_code( string $code, string $language = '' ): array {
		$escaped = esc_html( $code );
		$inner   = '<pre class="wp-block-code"><code>' . $escaped . '</code></pre>';
		$attrs   = [];

		if ( ! empty( $language ) ) {
			$attrs['language'] = $language;
		}

		return [
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/image block.
	 *
	 * @param string $url Image URL.
	 * @param string $alt Alt text.
	 * @return array Block array.
	 */
	public static function make_image( string $url, string $alt = '' ): array {
		$alt_attr = esc_attr( $alt );
		$url_esc  = esc_url( $url );
		$inner    = '<figure class="wp-block-image"><img src="' . $url_esc . '" alt="' . $alt_attr . '"/></figure>';

		return [
			'blockName'    => 'core/image',
			'attrs'        => [
				'url' => $url,
				'alt' => $alt,
			],
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/separator block.
	 *
	 * @return array Block array.
	 */
	public static function make_separator(): array {
		$inner = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
		return [
			'blockName'    => 'core/separator',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	/**
	 * Make a core/table block.
	 *
	 * @param array $headers Array of header row data with 'cells' arrays.
	 * @param array $body    Array of body row data with 'cells' arrays.
	 * @return array Block array.
	 */
	public static function make_table( array $headers, array $body ): array {
		$attrs = [];

		if ( ! empty( $headers ) ) {
			$head_rows = [];
			foreach ( $headers as $row ) {
				$cells = [];
				foreach ( $row['cells'] as $cell ) {
					$cells[] = [
						'content' => $cell['content'],
						'tag'     => 'th',
					];
				}
				$head_rows[] = [ 'cells' => $cells ];
			}
			$attrs['head'] = $head_rows;
		}

		if ( ! empty( $body ) ) {
			$body_rows = [];
			foreach ( $body as $row ) {
				$cells = [];
				foreach ( $row['cells'] as $cell ) {
					$cells[] = [
						'content' => $cell['content'],
						'tag'     => 'td',
					];
				}
				$body_rows[] = [ 'cells' => $cells ];
			}
			$attrs['body'] = $body_rows;
		}

		// Build innerHTML.
		$html = '<figure class="wp-block-table"><table>';

		if ( ! empty( $headers ) ) {
			$html .= '<thead>';
			foreach ( $headers as $row ) {
				$html .= '<tr>';
				foreach ( $row['cells'] as $cell ) {
					$html .= '<th>' . $cell['content'] . '</th>';
				}
				$html .= '</tr>';
			}
			$html .= '</thead>';
		}

		if ( ! empty( $body ) ) {
			$html .= '<tbody>';
			foreach ( $body as $row ) {
				$html .= '<tr>';
				foreach ( $row['cells'] as $cell ) {
					$html .= '<td>' . $cell['content'] . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody>';
		}

		$html .= '</table></figure>';

		return [
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $html,
			'innerContent' => [ $html ],
		];
	}
}
