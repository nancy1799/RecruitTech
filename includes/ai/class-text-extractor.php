<?php
/**
 * RecruitTech Text Extractor
 *
 * Pulls plain text out of uploaded CVs and company documents so it can be
 * fed to the AI. Kept dependency-free (no Composer libraries) since this is
 * a beginner-level WordPress plugin: it reads the raw file bytes and pulls
 * out readable text with simple, well-known techniques for each format.
 *
 * This will not perfectly extract every PDF (e.g. scanned/image-only PDFs),
 * but it works for the normal text-based PDF and DOCX files job seekers and
 * companies upload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecruitTech_Text_Extractor {

	/**
	 * Extract plain text from a file given its public URL (as stored in the
	 * plugin's database columns, e.g. cvs.file_path).
	 *
	 * @param string $url Public URL of the uploaded file.
	 * @return string Extracted text, or '' if it can't be read.
	 */
	public static function extract_from_url( $url ) {
		$path = self::url_to_path( $url );
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return '';
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		switch ( $extension ) {
			case 'pdf':
				return self::extract_pdf( $path );
			case 'docx':
				return self::extract_docx( $path );
			case 'txt':
				return self::clean_whitespace( (string) file_get_contents( $path ) );
			default:
				return '';
		}
	}

	/**
	 * Convert a WordPress uploads URL back into a local file path.
	 *
	 * @param string $url Uploaded file URL.
	 * @return string
	 */
	protected static function url_to_path( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}

		$upload_dir = wp_get_upload_dir();
		if ( empty( $upload_dir['baseurl'] ) || empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		if ( 0 !== strpos( $url, $upload_dir['baseurl'] ) ) {
			return '';
		}

		$relative_path = substr( $url, strlen( $upload_dir['baseurl'] ) );

		return $upload_dir['basedir'] . $relative_path;
	}

	/**
	 * Extract readable text from a PDF by delegating to pdftotext.exe.
	 *
	 * The extractor intentionally uses a single, explicit path here so the
	 * returned text is the raw output from the PDF text extraction tool rather
	 * than metadata or ad-hoc parsing of PDF streams.
	 *
	 * @param string $path Local file path.
	 * @return string
	 */
	protected static function extract_pdf( $path ) {
		$executable = self::find_pdftotext_executable();
		if ( '' === $executable ) {
			error_log( 'RecruitTech PDF extraction failed: pdftotext executable not found.' );
			return '';
		}

		$command = sprintf(
			'"%s" -enc UTF-8 -nopgbrk "%s" -',
			str_replace( '"', '\\"', $executable ),
			str_replace( '"', '\\"', $path )
		);

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes, dirname( $path ) );
		if ( ! is_resource( $process ) ) {
			error_log( 'RecruitTech PDF extraction failed: unable to start pdftotext process.' );
			return '';
		}

		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		error_log( 'RecruitTech PDF extraction command: ' . $command );
		error_log( 'RecruitTech PDF extraction exit code: ' . $exit_code );
		error_log( 'RecruitTech PDF extraction stdout: ' . $stdout );
		if ( '' !== $stderr ) {
			error_log( 'RecruitTech PDF extraction stderr: ' . $stderr );
		}

		if ( 0 !== $exit_code || '' === $stdout ) {
			return '';
		}

		return $stdout;
	}

	/**
	 * Locate an available pdftotext executable.
	 *
	 * @return string
	 */
	protected static function find_pdftotext_executable() {
		$candidates = array();
		$resolved   = '';
		$path_env   = getenv( 'PATH' );

		$plugin_bin = dirname( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pdftotext.exe';
		$candidates[] = $plugin_bin;

		if ( is_string( $path_env ) && '' !== $path_env ) {
			foreach ( explode( PATH_SEPARATOR, $path_env ) as $dir ) {
				if ( '' === $dir ) {
					continue;
				}
				$candidates[] = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'pdftotext.exe';
				$candidates[] = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'pdftotext';
			}
		}

		$candidates[] = 'pdftotext.exe';
		$candidates[] = 'pdftotext';

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && file_exists( $candidate ) ) {
				$resolved = $candidate;
				break;
			}
		}

		error_log( 'RecruitTech pdftotext executable path: ' . ( '' !== $resolved ? $resolved : 'not found' ) );

		return $resolved;
	}

	/**
	 * Extract readable text from a DOCX file (a zip archive containing
	 * word/document.xml).
	 *
	 * @param string $path Local file path.
	 * @return string
	 */
	protected static function extract_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return '';
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( empty( $xml ) ) {
			return '';
		}

		// Turn paragraph/line breaks into real newlines before stripping tags.
		$xml = preg_replace( '/<\/w:p>|<w:br\s*\/?>/', "\n", $xml );
		$text = wp_strip_all_tags( $xml );

		return self::clean_whitespace( $text );
	}

	/**
	 * Collapse repeated whitespace so cached/extracted text stays compact.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected static function clean_whitespace( $text ) {
		$text = preg_replace( '/[ \t]+/', ' ', (string) $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return trim( $text );
	}
}
