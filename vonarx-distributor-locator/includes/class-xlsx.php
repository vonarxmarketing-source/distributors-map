<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal, dependency-free XLSX reader/writer.
 *
 * An .xlsx file is just a ZIP of XML parts, so this builds/parses that
 * directly instead of pulling in a heavy third-party spreadsheet library.
 * Every cell is written as an inline string (no shared-strings table),
 * which keeps the writer small and stops Excel from "helpfully" reflowing
 * things like phone numbers or ZIP codes into numbers. ZipArchive is used
 * when available; otherwise this falls back to PclZip, which every
 * WordPress install already ships at wp-admin/includes/class-pclzip.php,
 * so the import/export feature works even on hosts without the PHP zip
 * extension.
 */
class Vonarx_Locator_Xlsx {

	/**
	 * Writes a single-sheet workbook to $filepath.
	 *
	 * @param string $filepath Destination path.
	 * @param array  $headers  Column headings, in order.
	 * @param array  $rows     Each row is a list of cell values, same order as $headers.
	 * @return true|WP_Error
	 */
	public static function write( $filepath, array $headers, array $rows ) {
		$parts = array(
			'[Content_Types].xml'          => self::content_types_xml(),
			'_rels/.rels'                  => self::root_rels_xml(),
			'xl/workbook.xml'              => self::workbook_xml(),
			'xl/_rels/workbook.xml.rels'   => self::workbook_rels_xml(),
			'xl/styles.xml'                => self::styles_xml(),
			'xl/worksheets/sheet1.xml'     => self::sheet_xml( $headers, $rows ),
		);

		return self::write_zip( $filepath, $parts );
	}

	/**
	 * Reads the first sheet of an .xlsx file.
	 *
	 * @param string $filepath Path to the uploaded file.
	 * @return array[]|WP_Error List of rows, each a zero-indexed list of cell strings.
	 */
	public static function read( $filepath ) {
		$workbook_xml = self::read_zip_entry( $filepath, 'xl/workbook.xml' );
		if ( null === $workbook_xml ) {
			return new WP_Error( 'vonarx_invalid_xlsx', __( 'That file doesn\'t look like a valid .xlsx workbook.', 'vonarx-distributor-locator' ) );
		}

		$sheet_path = self::resolve_first_sheet_path( $filepath, $workbook_xml );
		$sheet_xml  = self::read_zip_entry( $filepath, $sheet_path );
		if ( null === $sheet_xml ) {
			return new WP_Error( 'vonarx_invalid_xlsx', __( 'Couldn\'t find any worksheet data in that file.', 'vonarx-distributor-locator' ) );
		}

		$shared_strings_xml = self::read_zip_entry( $filepath, 'xl/sharedStrings.xml' );
		$shared_strings      = $shared_strings_xml ? self::parse_shared_strings( $shared_strings_xml ) : array();

		return self::parse_sheet( $sheet_xml, $shared_strings );
	}

	// -----------------------------------------------------------------
	// Writing
	// -----------------------------------------------------------------

	private static function write_zip( $filepath, array $parts ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				return new WP_Error( 'vonarx_zip_failed', __( 'Could not create the export file.', 'vonarx-distributor-locator' ) );
			}
			foreach ( $parts as $name => $content ) {
				$zip->addFromString( $name, $content );
			}
			$zip->close();
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$file_list = array();
		foreach ( $parts as $name => $content ) {
			$file_list[] = array(
				PCLZIP_ATT_FILE_NAME    => $name,
				PCLZIP_ATT_FILE_CONTENT => $content,
			);
		}

		if ( file_exists( $filepath ) ) {
			unlink( $filepath );
		}

		$archive = new PclZip( $filepath );
		$result  = $archive->create( $file_list );
		if ( 0 === $result ) {
			return new WP_Error( 'vonarx_zip_failed', __( 'Could not create the export file.', 'vonarx-distributor-locator' ) . ' ' . $archive->errorInfo( true ) );
		}

		return true;
	}

	private static function content_types_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function root_rels_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbook_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Distributor Locations" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function workbook_rels_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	/**
	 * Two cell styles: 0 = default, 1 = bold (used for the header row).
	 */
	private static function styles_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}

	private static function sheet_xml( array $headers, array $rows ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$xml .= '<cols>';
		foreach ( $headers as $index => $header ) {
			$width = min( 40, max( 10, strlen( $header ) + 4 ) );
			$xml  .= sprintf( '<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $index + 1, $width );
		}
		$xml .= '</cols>';
		$xml .= '<sheetData>';

		$xml .= self::row_xml( 1, $headers, 1 );
		foreach ( $rows as $row_index => $row ) {
			$xml .= self::row_xml( $row_index + 2, $row, 0 );
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	private static function row_xml( $row_number, array $cells, $style_id ) {
		$xml = sprintf( '<row r="%d">', $row_number );
		foreach ( array_values( $cells ) as $col_index => $value ) {
			$ref   = self::column_letter( $col_index + 1 ) . $row_number;
			$style = $style_id ? sprintf( ' s="%d"', $style_id ) : '';
			$xml  .= sprintf(
				'<c r="%1$s"%2$s t="inlineStr"><is><t xml:space="preserve">%3$s</t></is></c>',
				$ref,
				$style,
				self::xml_escape( (string) $value )
			);
		}
		$xml .= '</row>';
		return $xml;
	}

	private static function column_letter( $index ) {
		$letter = '';
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) ( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private static function xml_escape( $text ) {
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );
		return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	// -----------------------------------------------------------------
	// Reading
	// -----------------------------------------------------------------

	private static function read_zip_entry( $filepath, $entry_name ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $filepath ) ) {
				return null;
			}
			$data = $zip->getFromName( $entry_name );
			$zip->close();
			return false === $data ? null : $data;
		}

		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$archive   = new PclZip( $filepath );
		$extracted = $archive->extract( PCLZIP_OPT_BY_NAME, array( $entry_name ), PCLZIP_OPT_EXTRACT_AS_STRING );
		if ( ! is_array( $extracted ) || empty( $extracted[0]['content'] ) ) {
			return null;
		}
		return $extracted[0]['content'];
	}

	private static function load_xml( $xml_string ) {
		$internal_errors = libxml_use_internal_errors( true );
		$xml             = simplexml_load_string( $xml_string );
		libxml_use_internal_errors( $internal_errors );
		return false === $xml ? null : $xml;
	}

	/**
	 * Finds the zip path of the workbook's first declared sheet by
	 * following its r:id through xl/_rels/workbook.xml.rels — sheet file
	 * names aren't guaranteed to be "sheet1.xml" once a workbook has been
	 * resaved by Excel/Google Sheets.
	 */
	private static function resolve_first_sheet_path( $filepath, $workbook_xml ) {
		$default = 'xl/worksheets/sheet1.xml';

		$workbook = self::load_xml( $workbook_xml );
		if ( ! $workbook ) {
			return $default;
		}

		$namespaces = $workbook->getNamespaces( true );
		$r_ns       = isset( $namespaces['r'] ) ? $namespaces['r'] : 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

		$first_sheet = null;
		foreach ( $workbook->sheets->sheet as $sheet ) {
			$attrs       = $sheet->attributes( $r_ns );
			$first_sheet = isset( $attrs['id'] ) ? (string) $attrs['id'] : null;
			break;
		}

		if ( ! $first_sheet ) {
			return $default;
		}

		$rels_xml = self::read_zip_entry( $filepath, 'xl/_rels/workbook.xml.rels' );
		$rels     = $rels_xml ? self::load_xml( $rels_xml ) : null;
		if ( ! $rels ) {
			return $default;
		}

		foreach ( $rels->Relationship as $rel ) {
			if ( (string) $rel['Id'] === $first_sheet ) {
				$target = (string) $rel['Target'];
				return ( 0 === strpos( $target, '/' ) ) ? ltrim( $target, '/' ) : 'xl/' . $target;
			}
		}

		return $default;
	}

	private static function parse_shared_strings( $xml_string ) {
		$xml = self::load_xml( $xml_string );
		if ( ! $xml ) {
			return array();
		}

		$strings = array();
		foreach ( $xml->si as $si ) {
			if ( isset( $si->t ) ) {
				$strings[] = (string) $si->t;
				continue;
			}
			$text = '';
			foreach ( $si->r as $run ) {
				$text .= (string) $run->t;
			}
			$strings[] = $text;
		}
		return $strings;
	}

	private static function parse_sheet( $xml_string, array $shared_strings ) {
		$xml = self::load_xml( $xml_string );
		if ( ! $xml || ! isset( $xml->sheetData ) ) {
			return array();
		}

		$rows = array();
		foreach ( $xml->sheetData->row as $row ) {
			$cells   = array();
			$max_col = -1;
			foreach ( $row->c as $c ) {
				$col = self::column_index_from_ref( (string) $c['r'] );
				$type = (string) $c['t'];

				if ( 'inlineStr' === $type ) {
					$value = isset( $c->is->t ) ? (string) $c->is->t : '';
				} elseif ( 's' === $type ) {
					$idx   = (int) $c->v;
					$value = isset( $shared_strings[ $idx ] ) ? $shared_strings[ $idx ] : '';
				} else {
					$value = isset( $c->v ) ? (string) $c->v : '';
				}

				$cells[ $col ] = $value;
				$max_col       = max( $max_col, $col );
			}

			$dense = array();
			for ( $i = 0; $i <= $max_col; $i++ ) {
				$dense[] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
			}
			$rows[] = $dense;
		}

		return $rows;
	}

	private static function column_index_from_ref( $ref ) {
		preg_match( '/^([A-Z]+)/', $ref, $matches );
		$letters = isset( $matches[1] ) ? $matches[1] : 'A';
		$index   = 0;
		for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return $index - 1;
	}
}
