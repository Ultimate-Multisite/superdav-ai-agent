<?php

declare(strict_types=1);
/**
 * Document parser — extract text from various file formats.
 *
 * Supports PDF (via smalot/pdfparser), DOCX, TXT, Markdown, and HTML.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Models;

use WP_Error;

class DocumentParser {

	/**
	 * Extract text from a WordPress attachment.
	 *
	 * @param int $attachment_id The attachment post ID.
	 * @return string|WP_Error Extracted text or error.
	 */
	public static function extract_from_attachment( int $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'file_not_found', __( 'Attachment file not found.', 'gratis-ai-agent' ) );
		}

		$mime = get_post_mime_type( $attachment_id );
		return self::extract_from_file( $file, $mime );
	}

	/**
	 * Extract text from a file path.
	 *
	 * @param string $path File path.
	 * @param string $mime Optional MIME type. Auto-detected if empty.
	 * @return string|WP_Error Extracted text or error.
	 */
	public static function extract_from_file( string $path, string $mime = '' ) {
		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found.', 'gratis-ai-agent' ) );
		}

		if ( empty( $mime ) ) {
			$mime = self::detect_mime( $path );
		}

		switch ( $mime ) {
			case 'application/pdf':
				return self::parse_pdf( $path );

			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				return self::parse_docx( $path );

			case 'text/plain':
			case 'text/markdown':
				return self::parse_text( $path );

			case 'text/html':
				return self::parse_html( $path );

			default:
				// Try text-based fallback for unknown types.
				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, [ 'txt', 'md', 'markdown', 'csv', 'log', 'json', 'xml', 'yaml', 'yml' ], true ) ) {
					return self::parse_text( $path );
				}

				return new WP_Error(
					'unsupported_format',
					sprintf(
						/* translators: %s: MIME type */
						__( 'Unsupported file format: %s', 'gratis-ai-agent' ),
						$mime
					)
				);
		}
	}

	/**
	 * Parse a PDF file using smalot/pdfparser.
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	private static function parse_pdf( string $path ) {
		if ( ! class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'PDF parsing requires the smalot/pdfparser library. Run composer install.', 'gratis-ai-agent' )
			);
		}

		try {
			$parser = new \Smalot\PdfParser\Parser();
			$pdf    = $parser->parseFile( $path );
			$text   = $pdf->getText();

			if ( empty( trim( $text ) ) ) {
				return new WP_Error( 'empty_content', __( 'No text content found in PDF.', 'gratis-ai-agent' ) );
			}

			return self::clean_text( $text );
		} catch ( \Exception $e ) {
			return new WP_Error( 'parse_error', $e->getMessage() );
		}
	}

	/**
	 * Parse a DOCX file using ZipArchive + XML.
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	private static function parse_docx( string $path ) {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return new WP_Error( 'missing_extension', __( 'PHP ZipArchive extension is required for DOCX parsing.', 'gratis-ai-agent' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'zip_error', __( 'Could not open DOCX file.', 'gratis-ai-agent' ) );
		}

		$content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $content ) {
			return new WP_Error( 'invalid_docx', __( 'Could not find document content in DOCX.', 'gratis-ai-agent' ) );
		}

		// Parse XML and extract text nodes.
		$xml = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NOERROR );

		if ( false === $xml ) {
			return new WP_Error( 'xml_error', __( 'Could not parse DOCX XML content.', 'gratis-ai-agent' ) );
		}

		$xml->registerXPathNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		$paragraphs = [];
		$p_nodes    = $xml->xpath( '//w:p' );

		if ( $p_nodes ) {
			foreach ( $p_nodes as $p ) {
				$p->registerXPathNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
				$texts = $p->xpath( './/w:t' );
				$line  = '';
				if ( $texts ) {
					foreach ( $texts as $t ) {
						$line .= (string) $t;
					}
				}
				if ( ! empty( trim( $line ) ) ) {
					$paragraphs[] = trim( $line );
				}
			}
		}

		$text = implode( "\n\n", $paragraphs );

		if ( empty( trim( $text ) ) ) {
			return new WP_Error( 'empty_content', __( 'No text content found in DOCX.', 'gratis-ai-agent' ) );
		}

		return self::clean_text( $text );
	}

	/**
	 * Parse a plain text or markdown file.
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	private static function parse_text( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$text = file_get_contents( $path );

		if ( false === $text ) {
			return new WP_Error( 'read_error', __( 'Could not read file.', 'gratis-ai-agent' ) );
		}

		return self::clean_text( $text );
	}

	/**
	 * Parse an HTML file by stripping tags.
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	private static function parse_html( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$html = file_get_contents( $path );

		if ( false === $html ) {
			return new WP_Error( 'read_error', __( 'Could not read file.', 'gratis-ai-agent' ) );
		}

		$text = wp_strip_all_tags( $html );
		return self::clean_text( $text );
	}

	/**
	 * Detect MIME type from file extension.
	 *
	 * @param string $path File path.
	 * @return string MIME type.
	 */
	private static function detect_mime( string $path ): string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		$map = [
			'pdf'      => 'application/pdf',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'      => 'text/plain',
			'md'       => 'text/markdown',
			'markdown' => 'text/markdown',
			'html'     => 'text/html',
			'htm'      => 'text/html',
		];

		return $map[ $ext ] ?? 'application/octet-stream';
	}

	/**
	 * Clean extracted text: normalize whitespace and encoding.
	 *
	 * @param string $text Raw text.
	 * @return string Cleaned text.
	 */
	private static function clean_text( string $text ): string {
		// Normalize line endings.
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		// Collapse excessive blank lines to double newline.
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		// Remove null bytes and other control characters (keep newlines/tabs).
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );

		return trim( $text );
	}
}
