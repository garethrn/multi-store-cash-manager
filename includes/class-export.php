<?php
/**
 * Data export functionality.
 *
 * Handles CSV exports and data backup creation.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_Export class.
 *
 * @since 1.0.0
 */
class MSCM_Export {

	/**
	 * Upload directory for exports.
	 *
	 * @var string
	 */
	private $export_dir;

	/**
	 * Upload URL for exports.
	 *
	 * @var string
	 */
	private $export_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->export_dir = $upload_dir['basedir'] . '/mscm-exports';
		$this->export_url = $upload_dir['baseurl'] . '/mscm-exports';

		// Schedule daily backup.
		add_action( 'mscm_daily_backup', array( $this, 'create_backup' ) );

		// Ensure export directory exists.
		if ( ! is_dir( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );
			// Protect with .htaccess (require auth to access export files).
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$this->export_dir . '/.htaccess',
				"Options -Indexes\n<FilesMatch \"\\.csv$\">\n  Require all granted\n</FilesMatch>\n"
			);
		}
	}

	/**
	 * Export data in the specified format.
	 *
	 * @param string $format   Export format (csv).
	 * @param int    $store_id Store ID (0 for all).
	 * @param string $date_from Start date.
	 * @param string $date_to  End date.
	 * @return string|false Export file URL or false on failure.
	 */
	public function export( $format, $store_id, $date_from, $date_to ) {
		switch ( $format ) {
			case 'csv':
				return $this->export_csv( $store_id, $date_from, $date_to );
			default:
				return false;
		}
	}

	/**
	 * Export entries to CSV.
	 *
	 * @param int    $store_id  Store ID (0 for all).
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return string|false File URL or false on failure.
	 */
	public function export_csv( $store_id, $date_from, $date_to ) {
		$args = array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'limit'     => 10000,
		);

		if ( $store_id ) {
			$args['store_id'] = $store_id;
		}

		$entries = MSCM()->db->get_entries( $args );

		if ( empty( $entries ) ) {
			return false;
		}

		$filename = sprintf(
			'mscm-export-%s-%s-%s.csv',
			$store_id ? 'store' . $store_id : 'all',
			$date_from,
			$date_to
		);

		$filepath = $this->export_dir . '/' . $filename;

		$headers = array(
			__( 'ID', 'multi-store-cash-manager' ),
			__( 'Store', 'multi-store-cash-manager' ),
			__( 'Date', 'multi-store-cash-manager' ),
			__( 'R200 Notes', 'multi-store-cash-manager' ),
			__( 'R100 Notes', 'multi-store-cash-manager' ),
			__( 'R50 Notes', 'multi-store-cash-manager' ),
			__( 'R20 Notes', 'multi-store-cash-manager' ),
			__( 'R10 Coins', 'multi-store-cash-manager' ),
			__( 'R5 Coins', 'multi-store-cash-manager' ),
			__( 'R2 Coins', 'multi-store-cash-manager' ),
			__( 'R1 Coins', 'multi-store-cash-manager' ),
			__( '50c Coins', 'multi-store-cash-manager' ),
			__( 'Total Cash', 'multi-store-cash-manager' ),
			__( 'Float', 'multi-store-cash-manager' ),
			__( 'Cash to Bank', 'multi-store-cash-manager' ),
			__( 'Credit Card', 'multi-store-cash-manager' ),
			__( 'EFT', 'multi-store-cash-manager' ),
			__( 'Other Digital', 'multi-store-cash-manager' ),
			__( 'Total Sales', 'multi-store-cash-manager' ),
			__( 'POS Reported', 'multi-store-cash-manager' ),
			__( 'Discrepancy', 'multi-store-cash-manager' ),
			__( 'Net Banking', 'multi-store-cash-manager' ),
			__( 'Status', 'multi-store-cash-manager' ),
			__( 'Notes', 'multi-store-cash-manager' ),
			__( 'Submitted By', 'multi-store-cash-manager' ),
			__( 'Created At', 'multi-store-cash-manager' ),
		);

		$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			return false;
		}

		// Add BOM for Excel UTF-8 compatibility.
		fwrite( $fp, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $fp, $headers );

		foreach ( $entries as $entry ) {
			fputcsv(
				$fp,
				array(
					$entry->id,
					$entry->store_name,
					$entry->entry_date,
					$entry->denom_200,
					$entry->denom_100,
					$entry->denom_50,
					$entry->denom_20,
					$entry->denom_10,
					$entry->denom_5,
					$entry->denom_2,
					$entry->denom_1,
					$entry->denom_50c,
					$entry->total_cash,
					$entry->float_amount,
					$entry->cash_to_bank,
					$entry->credit_card,
					$entry->eft,
					$entry->other_digital,
					$entry->total_sales,
					$entry->pos_reported,
					$entry->discrepancy,
					$entry->net_banking,
					$entry->status,
					$entry->notes,
					$entry->user_name,
					$entry->created_at,
				)
			);
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $this->export_url . '/' . $filename;
	}

	/**
	 * Create a full database backup.
	 */
	public function create_backup() {
		if ( ! get_option( 'mscm_enable_backups', 1 ) ) {
			return;
		}

		$backup_dir = wp_upload_dir()['basedir'] . '/mscm-backups';

		if ( ! is_dir( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			file_put_contents( $backup_dir . '/.htaccess', 'deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		global $wpdb;
		$tables = array_keys( MSCM()->db->tables );
		$backup = "-- MSCM Backup: " . current_time( 'mysql' ) . "\n\n";

		foreach ( $tables as $table_key ) {
			$table_name = MSCM()->db->tables[ $table_key ];
			$rows       = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( empty( $rows ) ) {
				continue;
			}

			$backup .= "-- Table: {$table_name}\n";
			$backup .= "TRUNCATE TABLE `{$table_name}`;\n";

			foreach ( $rows as $row ) {
				$values  = array_map( fn( $v ) => null === $v ? 'NULL' : "'" . esc_sql( $v ) . "'", array_values( $row ) );
				$columns = '`' . implode( '`, `', array_keys( $row ) ) . '`';
				$backup .= "INSERT INTO `{$table_name}` ({$columns}) VALUES (" . implode( ', ', $values ) . ");\n";
			}

			$backup .= "\n";
		}

		$filename = 'mscm-backup-' . wp_date( 'Y-m-d-His' ) . '.sql';
		file_put_contents( $backup_dir . '/' . $filename, $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// Clean old backups.
		$this->clean_old_backups( $backup_dir );
	}

	/**
	 * Remove backup files older than the retention period.
	 *
	 * @param string $backup_dir Backup directory path.
	 */
	private function clean_old_backups( $backup_dir ) {
		$retention_days = absint( get_option( 'mscm_backup_retention', 30 ) );
		$cutoff         = strtotime( "-{$retention_days} days" );

		$files = glob( $backup_dir . '/mscm-backup-*.sql' );

		if ( ! $files ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}
}
