/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `bod_boc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bod_boc` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instansi` varchar(255) DEFAULT NULL,
  `bod_boc` varchar(255) DEFAULT NULL,
  `nama_nasabah` varchar(255) DEFAULT NULL,
  `ket_nasabah` varchar(255) DEFAULT NULL,
  `cif` varchar(255) DEFAULT NULL,
  `fasilitas_1` varchar(255) DEFAULT NULL,
  `fasilitas_2` varchar(255) DEFAULT NULL,
  `fasilitas_3` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brilink_web_laporan_summary_transaksi_brilink_web`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brilink_web_laporan_summary_transaksi_brilink_web` (
  `uniqueid_namareport` varchar(50) NOT NULL,
  `periode` varchar(20) DEFAULT NULL,
  `kanwil` varchar(100) DEFAULT NULL,
  `cabang` varchar(100) DEFAULT NULL,
  `uker` varchar(100) DEFAULT NULL,
  `merchant_name` varchar(150) DEFAULT NULL,
  `merchant_code` varchar(50) DEFAULT NULL,
  `outlet_name` varchar(150) DEFAULT NULL,
  `outlet_code` varchar(50) DEFAULT NULL,
  `total_transaksi` bigint(20) DEFAULT NULL,
  `total_nominal` decimal(18,2) DEFAULT NULL,
  `total_fee` decimal(18,2) DEFAULT NULL,
  `total_fee_bri` decimal(18,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `casa_brilink_edc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `casa_brilink_edc` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `periode` date NOT NULL,
  `region` varchar(20) DEFAULT NULL,
  `rgdesc` varchar(150) DEFAULT NULL,
  `mainbr` varchar(20) DEFAULT NULL,
  `mbdesc` varchar(150) DEFAULT NULL,
  `branch` varchar(20) DEFAULT NULL,
  `brdesc` varchar(150) DEFAULT NULL,
  `kode_agen` varchar(30) DEFAULT NULL,
  `mid_code` varchar(50) DEFAULT NULL,
  `account` varchar(50) DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `sumber` varchar(50) DEFAULT NULL,
  `jml_nominal_casa` decimal(20,2) DEFAULT NULL,
  `textbox9` decimal(20,2) DEFAULT NULL,
  `cifno` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `casa_brilink_edc_periode_index` (`periode`),
  KEY `casa_brilink_edc_account_index` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `casa_brilink_web`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `casa_brilink_web` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `periode` date NOT NULL,
  `region` varchar(20) DEFAULT NULL,
  `rgdesc` varchar(150) DEFAULT NULL,
  `mainbr` varchar(20) DEFAULT NULL,
  `mbdesc` varchar(150) DEFAULT NULL,
  `branch` varchar(20) DEFAULT NULL,
  `brdesc` varchar(150) DEFAULT NULL,
  `kode_agen` varchar(30) DEFAULT NULL,
  `mid_code` varchar(50) DEFAULT NULL,
  `account` varchar(50) DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `sumber` varchar(50) DEFAULT NULL,
  `jml_nominal_casa` decimal(20,2) DEFAULT NULL,
  `textbox9` decimal(20,2) DEFAULT NULL,
  `cifno` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `casa_brilink_web_periode_index` (`periode`),
  KEY `casa_brilink_web_account_index` (`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_loan_dinamis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_loan_dinamis` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `periode` date DEFAULT NULL,
  `kode_kanwil1` varchar(100) DEFAULT NULL,
  `kanwil1` varchar(150) DEFAULT NULL,
  `kode_cabang1` varchar(100) DEFAULT NULL,
  `cabang1` varchar(150) DEFAULT NULL,
  `branch1` varchar(100) DEFAULT NULL,
  `unit1` varchar(150) DEFAULT NULL,
  `curtyp` varchar(100) DEFAULT NULL,
  `ao_name` varchar(150) DEFAULT NULL,
  `cifno` varchar(50) DEFAULT NULL,
  `nomor_rekening1` varchar(100) DEFAULT NULL,
  `status_rekening1` varchar(100) DEFAULT NULL,
  `ln_type` varchar(100) DEFAULT NULL,
  `nama_debitur1` varchar(150) DEFAULT NULL,
  `rate` decimal(20,2) DEFAULT NULL,
  `jangka_waktu1` varchar(50) DEFAULT NULL,
  `plafon` decimal(20,2) DEFAULT NULL,
  `baki_debet1` decimal(20,2) DEFAULT NULL,
  `ckpn` decimal(20,2) DEFAULT NULL,
  `nilai_tercatat1` decimal(20,2) DEFAULT NULL,
  `kol_adk1` varchar(100) DEFAULT NULL,
  `kolek_detail` varchar(100) DEFAULT NULL,
  `kolek` varchar(100) DEFAULT NULL,
  `kolektabilitas_lancar` decimal(20,2) DEFAULT NULL,
  `kolektabilitas_dpk` decimal(20,2) DEFAULT NULL,
  `kolektabilitas_kuranglancar` decimal(20,2) DEFAULT NULL,
  `kolektabilitas_diragukan` decimal(20,2) DEFAULT NULL,
  `kolektabilitas_macet` decimal(20,2) DEFAULT NULL,
  `total_kewajiban` decimal(20,2) DEFAULT NULL,
  `tunggakan_pokok` decimal(20,2) DEFAULT NULL,
  `tunggakan_bunga` decimal(20,2) DEFAULT NULL,
  `tunggakan_penalti` decimal(20,2) DEFAULT NULL,
  `umur_tunggakan` int(11) DEFAULT NULL,
  `tgl_realisasi` date DEFAULT NULL,
  `tgl_jatuh_tempo` date DEFAULT NULL,
  `tanggal_menunggak` date DEFAULT NULL,
  `tgl_bayar_terakhir` date DEFAULT NULL,
  `tgl_terminate` date DEFAULT NULL,
  `last_date_maintenance_billing` date DEFAULT NULL,
  `next_pmt_date` date DEFAULT NULL,
  `next_pmt_int_date` date DEFAULT NULL,
  `advance_payment` decimal(20,2) DEFAULT NULL,
  `bap` decimal(20,2) DEFAULT NULL,
  `payment_amount` decimal(20,2) DEFAULT NULL,
  `final_payment_amount` decimal(20,2) DEFAULT NULL,
  `npb_pokok_la` decimal(20,2) DEFAULT NULL,
  `npb_pokok_lf` decimal(20,2) DEFAULT NULL,
  `npb_bunga_la` decimal(20,2) DEFAULT NULL,
  `npb_bunga_lf` decimal(20,2) DEFAULT NULL,
  `jml_angsuran1` decimal(20,2) DEFAULT NULL,
  `jumlah_bayar` decimal(20,2) DEFAULT NULL,
  `deffered_bunga` decimal(20,2) DEFAULT NULL,
  `sai_tunggakan` decimal(20,2) DEFAULT NULL,
  `sai_deffered` decimal(20,2) DEFAULT NULL,
  `sai1` decimal(20,2) DEFAULT NULL,
  `freq_payment` int(11) DEFAULT NULL,
  `freq_int_payment` int(11) DEFAULT NULL,
  `jadwal_gp_pokok` text DEFAULT NULL,
  `pn_pengelola1` text DEFAULT NULL,
  `pn_name1` varchar(150) DEFAULT NULL,
  `pn_pemrakarsa1` text DEFAULT NULL,
  `pn_referral1` text DEFAULT NULL,
  `pn_restruk1` text DEFAULT NULL,
  `pn_pengelola2` text DEFAULT NULL,
  `pn_pemutus1` text DEFAULT NULL,
  `pn_crm1` text DEFAULT NULL,
  `pn_crr` text DEFAULT NULL,
  `pn_referral_naik_kelas1` text DEFAULT NULL,
  `jumlah_pn1` int(11) DEFAULT NULL,
  `jumlah_pn_all1` int(11) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `kecamatan_t_tinggal` varchar(150) DEFAULT NULL,
  `kelurahan_t_tinggal` varchar(150) DEFAULT NULL,
  `kodepos_t_tinggal` varchar(100) DEFAULT NULL,
  `kecamatan_t_usaha` varchar(150) DEFAULT NULL,
  `kelurahan_t_usaha` varchar(150) DEFAULT NULL,
  `kodepos_t_usaha` varchar(100) DEFAULT NULL,
  `segmen_dashboard` varchar(100) DEFAULT NULL,
  `produk_dashboard` varchar(100) DEFAULT NULL,
  `divisi_segmen_dashboard` varchar(100) DEFAULT NULL,
  `npl_method` varchar(100) DEFAULT NULL,
  `restruk_ke1` int(11) DEFAULT NULL,
  `jenis_restruk1` varchar(100) DEFAULT NULL,
  `tgl_akad_restruk` date DEFAULT NULL,
  `flag_restruk` varchar(100) DEFAULT NULL,
  `flag_restruk_covid1` varchar(100) DEFAULT NULL,
  `flag_commodity_chain1` varchar(100) DEFAULT NULL,
  `flag_briguna_digital1` varchar(100) DEFAULT NULL,
  `flag_agf` varchar(100) DEFAULT NULL,
  `flag_aft` varchar(100) DEFAULT NULL,
  `pmtamt` decimal(20,2) DEFAULT NULL,
  `pmtamt_base` decimal(20,2) DEFAULT NULL,
  `offcr` varchar(100) DEFAULT NULL,
  `lbdotu` varchar(100) DEFAULT NULL,
  `keterangan_pn_pengelola` varchar(150) DEFAULT NULL,
  `os_idr` decimal(20,2) DEFAULT NULL,
  `flag_klaim` varchar(100) DEFAULT NULL,
  `os_sebelum_klaim` decimal(20,2) DEFAULT NULL,
  `os_penuh_berjalan` decimal(20,2) DEFAULT NULL,
  `bilprn` decimal(20,2) DEFAULT NULL,
  `bilint` decimal(20,2) DEFAULT NULL,
  `billc` decimal(20,2) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `idx_loan_periode_branch` (`periode`),
  KEY `idx_loan_periode_cif` (`periode`,`cifno`),
  KEY `idx_loan_segmen` (`segmen_dashboard`),
  KEY `idx_loan_cif` (`cifno`),
  KEY `idx_dld_periode_rekening` (`periode`,`nomor_rekening1`),
  KEY `idx_dld_periode_segmen_produk` (`periode`,`segmen_dashboard`,`produk_dashboard`),
  KEY `idx_dld_periode_cabang_unit` (`periode`,`cabang1`,`unit1`),
  KEY `idx_dld_periode_segmen_produk_cabang_unit` (`periode`,`segmen_dashboard`,`produk_dashboard`,`cabang1`,`unit1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_dld_after_ins_invalidate_snapshots
            AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF NEW.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_dld_after_upd_invalidate_snapshots
            AFTER UPDATE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF NEW.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                END IF;

                IF OLD.periode IS NOT NULL AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_dld_after_del_invalidate_snapshots
            AFTER DELETE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF OLD.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `dashboard_pinjaman_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_pinjaman_snapshots` (
  `uniqueid_dps` varchar(191) NOT NULL,
  `periode` date NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `loan_balance` decimal(20,2) NOT NULL DEFAULT 0.00,
  `quality_bucket` varchar(20) DEFAULT NULL,
  `segmen_dashboard` varchar(100) DEFAULT NULL,
  `produk_dashboard` varchar(150) DEFAULT NULL,
  `cabang1` varchar(150) DEFAULT NULL,
  `unit1` varchar(180) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_dps`),
  KEY `idx_dps_period_filter_chain` (`periode`,`segmen_dashboard`,`produk_dashboard`,`cabang1`,`unit1`),
  KEY `idx_dps_period_account` (`periode`,`account_number`),
  KEY `idx_dps_period_branch_unit` (`periode`,`cabang1`,`unit1`),
  KEY `dashboard_pinjaman_snapshots_periode_index` (`periode`),
  KEY `dashboard_pinjaman_snapshots_account_number_index` (`account_number`),
  KEY `dashboard_pinjaman_snapshots_quality_bucket_index` (`quality_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_report` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `folder_path` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'uploaded',
  `total_files` int(11) DEFAULT NULL,
  `total_success` int(10) unsigned NOT NULL DEFAULT 0,
  `total_failed` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `job_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`job_context`)),
  `job_fingerprint` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_jobs_status_updated_at` (`status`,`updated_at`),
  KEY `idx_import_jobs_created_by_status_created_at` (`created_by`,`status`,`created_at`),
  KEY `idx_import_jobs_report_created_at` (`id_report`,`created_at`),
  UNIQUE KEY `idx_import_jobs_job_fingerprint` (`job_fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `input_rekanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `input_rekanan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perusahaan_anak` varchar(255) DEFAULT NULL,
  `rekanan_level_1` varchar(255) DEFAULT NULL,
  `rekanan_level_2` varchar(255) DEFAULT NULL,
  `status_nasabah` varchar(255) DEFAULT NULL,
  `cif` varchar(255) DEFAULT NULL,
  `produk_1` varchar(255) DEFAULT NULL,
  `produk_2` varchar(255) DEFAULT NULL,
  `produk_3` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jumlah_merchant_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jumlah_merchant_detail` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `TAHUN` varchar(255) DEFAULT NULL,
  `PERIODE` varchar(255) DEFAULT NULL,
  `POSISI` datetime DEFAULT NULL,
  `KODE_KANWIL` varchar(255) DEFAULT NULL,
  `NAMA_KANWIL` varchar(255) DEFAULT NULL,
  `KODE_KANCA` varchar(255) DEFAULT NULL,
  `NAMA_KANCA` varchar(255) DEFAULT NULL,
  `KODE_UKER` varchar(255) DEFAULT NULL,
  `NAMA_UKER` varchar(255) DEFAULT NULL,
  `TID` varchar(255) DEFAULT NULL,
  `MID` varchar(255) DEFAULT NULL,
  `NAMA_MERCHANT` varchar(255) DEFAULT NULL,
  `JENIS` varchar(255) DEFAULT NULL,
  `KANWIL_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `KANWIL_NAMA_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `UKER_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `UKER_NAMA_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `KANWIL_IMPLEMENTOR` varchar(255) DEFAULT NULL,
  `KANWIL_NAMA_IMPLEMENTOR` varchar(255) DEFAULT NULL,
  `UKER_IMPLEMENTOR` varchar(255) DEFAULT NULL,
  `UKER_NAMA_IMPLEMENTOR` varchar(255) DEFAULT NULL,
  `PN_USER_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `NAMA_USER_PEMRAKARSA` varchar(255) DEFAULT NULL,
  `LAST_AVAILABLE` datetime DEFAULT NULL,
  `STATUS_AVAILABLE` varchar(255) DEFAULT NULL,
  `LAST_UTILITY` datetime DEFAULT NULL,
  `STATUS_UTILITY` varchar(255) DEFAULT NULL,
  `LAST_TRANSACTIONAL` datetime DEFAULT NULL,
  `STATUS_TRANSACTIONAL` varchar(255) DEFAULT NULL,
  `ALAMAT_MERCHANT` text DEFAULT NULL,
  `KELURAHAN` varchar(255) DEFAULT NULL,
  `KECAMATAN` varchar(255) DEFAULT NULL,
  `KABUPATEN` varchar(255) DEFAULT NULL,
  `PROVINSI` varchar(255) DEFAULT NULL,
  `AKTIF_OR_STAGING` varchar(255) DEFAULT NULL,
  `JML_TRANSAKSI` bigint(20) DEFAULT NULL,
  `SALES_VOLUME` bigint(20) DEFAULT NULL,
  `AKUMULASI_TRANSAKSI` bigint(20) DEFAULT NULL,
  `AKUMULASI_SALES_VOLUME` bigint(20) DEFAULT NULL,
  `KARTU_JML_TRANSAKSI_ON_US` bigint(20) DEFAULT NULL,
  `KARTU_JML_TRANSAKSI_OFF_US` bigint(20) DEFAULT NULL,
  `JML_TRANSAKSI_LAINNYA` bigint(20) DEFAULT NULL,
  `KARTU_SALES_VOLUME_ON_US` bigint(20) DEFAULT NULL,
  `KARTU_SALES_VOLUME_OFF_US` bigint(20) DEFAULT NULL,
  `SALES_VOLUME_LAINNYA` bigint(20) DEFAULT NULL,
  `KET_MCC` varchar(255) DEFAULT NULL,
  `KODE_MCC` varchar(255) DEFAULT NULL,
  `NOREK` varchar(255) DEFAULT NULL,
  `CIFNO` varchar(255) DEFAULT NULL,
  `SALDO_POSISI` bigint(20) DEFAULT NULL,
  `RATAS_SALDO` bigint(20) DEFAULT NULL,
  `SALDO_POSISI_BY_CIF` bigint(20) DEFAULT NULL,
  `RATAS_SALDO_BY_CIF` bigint(20) DEFAULT NULL,
  `TGL_APPROVAL` datetime DEFAULT NULL,
  `NILAI` varchar(255) DEFAULT NULL,
  `SOURCE` varchar(255) DEFAULT NULL,
  `SALES_VOLUME_MID` bigint(20) DEFAULT NULL,
  `FLAGGING` varchar(255) DEFAULT NULL,
  `FLAGGING_BRI_MERCHANT` varchar(255) DEFAULT NULL,
  `TIERING_SALES_VOLUME` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `jmd_periode_tid_index` (`PERIODE`,`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lw325_ph`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lw325_ph` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `periode` date DEFAULT NULL,
  `acctno` varchar(50) DEFAULT NULL,
  `kanwil` varchar(150) DEFAULT NULL,
  `kanca` varchar(150) DEFAULT NULL,
  `unit` varchar(180) DEFAULT NULL,
  `nama_debitur` varchar(255) DEFAULT NULL,
  `cif1` varchar(50) DEFAULT NULL,
  `fksegmen` varchar(30) DEFAULT NULL,
  `segmen_dashboard` varchar(100) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `produk_dashboard` varchar(150) DEFAULT NULL,
  `tgl_ph` date DEFAULT NULL,
  `tgl_realisasi` date DEFAULT NULL,
  `curtyp` varchar(10) DEFAULT NULL,
  `saldo_pertama_ph_pokok` decimal(20,2) DEFAULT NULL,
  `saldo_pertama_ph_bunga` decimal(20,2) DEFAULT NULL,
  `besar_realisasi` decimal(20,2) DEFAULT NULL,
  `plafon` decimal(20,2) DEFAULT NULL,
  `jw` int(10) unsigned DEFAULT NULL,
  `at` int(10) unsigned DEFAULT NULL,
  `cif` varchar(50) DEFAULT NULL,
  `pokok` decimal(20,2) DEFAULT NULL,
  `bunga` decimal(20,2) DEFAULT NULL,
  `angpok` decimal(20,2) DEFAULT NULL,
  `angbung` decimal(20,2) DEFAULT NULL,
  `sisapok` decimal(20,2) DEFAULT NULL,
  `sisabun` decimal(20,2) DEFAULT NULL,
  `clmamt1` decimal(20,2) DEFAULT NULL,
  `clmapr1` decimal(20,2) DEFAULT NULL,
  `os_penuh_berjalan1` decimal(20,2) DEFAULT NULL,
  `kecamatan_t_tinggal` varchar(150) DEFAULT NULL,
  `kelurahan_t_tinggal` varchar(150) DEFAULT NULL,
  `kodepos_t_tinggal` varchar(20) DEFAULT NULL,
  `kecamatan_t_usaha` varchar(150) DEFAULT NULL,
  `kelurahan_t_usaha` varchar(150) DEFAULT NULL,
  `kodepos_t_usaha` varchar(20) DEFAULT NULL,
  `pn_pengelola` varchar(255) DEFAULT NULL,
  `pn_pemrakarsa` varchar(255) DEFAULT NULL,
  `pn_referral` varchar(255) DEFAULT NULL,
  `pn_restruk` varchar(255) DEFAULT NULL,
  `pn_pengelola2` varchar(255) DEFAULT NULL,
  `pn_pemutus` varchar(255) DEFAULT NULL,
  `pn_crm` varchar(255) DEFAULT NULL,
  `pn_crr1` varchar(255) DEFAULT NULL,
  `pn_referral_naik_kelas` varchar(255) DEFAULT NULL,
  `jumlah_pn` int(10) unsigned DEFAULT NULL,
  `jumlah_pn_all` int(10) unsigned DEFAULT NULL,
  `saldo_pertama_kali_charge_off` decimal(20,2) DEFAULT NULL,
  `deffered_bunga` decimal(20,2) DEFAULT NULL,
  `sai_deffered` decimal(20,2) DEFAULT NULL,
  `sai_tunggakan` decimal(20,2) DEFAULT NULL,
  `deffered_bunga_ph` decimal(20,2) DEFAULT NULL,
  `sai_tunggakan_ph` decimal(20,2) DEFAULT NULL,
  `sai_deffered_ph` decimal(20,2) DEFAULT NULL,
  `wcbal` decimal(20,2) DEFAULT NULL,
  `waccint` decimal(20,2) DEFAULT NULL,
  `wadvpmt` decimal(20,2) DEFAULT NULL,
  `wpenint` decimal(20,2) DEFAULT NULL,
  `wmisc` decimal(20,2) DEFAULT NULL,
  `wothchg` decimal(20,2) DEFAULT NULL,
  `wpmtamt` decimal(20,2) DEFAULT NULL,
  `wpstdt` date DEFAULT NULL,
  `wpstdt6` date DEFAULT NULL,
  `wamount` decimal(20,2) DEFAULT NULL,
  `flag_klaim` varchar(10) DEFAULT NULL,
  `clmamt` decimal(20,2) DEFAULT NULL,
  `clmapr` decimal(20,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `report_ph_periode_index` (`periode`),
  KEY `report_ph_acctno_index` (`acctno`),
  KEY `report_ph_kanca_index` (`kanca`),
  KEY `report_ph_cif1_index` (`cif1`),
  KEY `report_ph_cif_index` (`cif`),
  KEY `idx_lw325ph_periode_acctno_pokok` (`periode`,`acctno`,`pokok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merchant_qris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merchant_qris` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `TAHUN` varchar(10) DEFAULT NULL,
  `PERIODE` varchar(20) DEFAULT NULL,
  `POSISI` date DEFAULT NULL,
  `KODE_KANWIL` varchar(50) DEFAULT NULL,
  `NAMA_KANWIL` varchar(100) DEFAULT NULL,
  `KODE_KCI` varchar(50) DEFAULT NULL,
  `NAMA_KCI` varchar(100) DEFAULT NULL,
  `KODE_BRANCH` varchar(50) DEFAULT NULL,
  `NAMA_BRANCH` varchar(150) DEFAULT NULL,
  `JENIS` varchar(50) DEFAULT NULL,
  `SEGMENTASI_JENIS` varchar(100) DEFAULT NULL,
  `NILAI` decimal(20,2) DEFAULT NULL,
  `MERCHANT_QRIS` decimal(20,2) DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merchant_qris_volume`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merchant_qris_volume` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `TAHUN` varchar(10) DEFAULT NULL,
  `PERIODE` varchar(20) DEFAULT NULL,
  `POSISI` date DEFAULT NULL,
  `KODE_KANWIL` varchar(50) DEFAULT NULL,
  `NAMA_KANWIL` varchar(100) DEFAULT NULL,
  `KODE_KCI` varchar(50) DEFAULT NULL,
  `NAMA_KCI` varchar(100) DEFAULT NULL,
  `KODE_BRANCH` varchar(50) DEFAULT NULL,
  `NAMA_BRANCH` varchar(150) DEFAULT NULL,
  `JENIS` varchar(50) DEFAULT NULL,
  `SEGMENTASI_JENIS` varchar(100) DEFAULT NULL,
  `MERCHANT_QRIS_VOLUME` decimal(20,2) DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nama_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nama_report` (
  `id_report` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_report` varchar(255) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_report`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `performance_pis_per_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_pis_per_produk` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `posisi` date DEFAULT NULL,
  `kode_kanwil` varchar(20) DEFAULT NULL,
  `kanwil` varchar(150) DEFAULT NULL,
  `kode_kanca` varchar(20) DEFAULT NULL,
  `kanca` varchar(150) DEFAULT NULL,
  `kode_uker` varchar(20) DEFAULT NULL,
  `uker` varchar(150) DEFAULT NULL,
  `corporate_code` varchar(30) DEFAULT NULL,
  `nama_perusahaan` varchar(255) DEFAULT NULL,
  `jenis_mitra` varchar(100) DEFAULT NULL,
  `jenis_perusahaan` varchar(100) DEFAULT NULL,
  `tipe_produk` varchar(50) DEFAULT NULL,
  `nomor_rekening` varchar(50) DEFAULT NULL,
  `nama_rekening` varchar(255) DEFAULT NULL,
  `saldo_britama_kerjasama` decimal(20,2) DEFAULT NULL,
  `tanggal_pembuatan_rekening` date DEFAULT NULL,
  `pn_rm_dana_brinets` varchar(50) DEFAULT NULL,
  `pn_rm_dana_pis2` varchar(50) DEFAULT NULL,
  `nomor_hp` varchar(50) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `flag_briguna` varchar(10) DEFAULT NULL,
  `flag_cc` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`),
  KEY `performance_pis_per_produk_posisi_index` (`posisi`),
  KEY `performance_pis_per_produk_nomor_rekening_index` (`nomor_rekening`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `performance_new_payroll_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_new_payroll_snapshots` (
  `uniqueid_pnps` varchar(191) NOT NULL,
  `snapshot_posisi` date NOT NULL,
  `branch` varchar(100) NOT NULL,
  `rekening_curr` int(10) unsigned NOT NULL DEFAULT 0,
  `rekening_prev` int(10) unsigned NOT NULL DEFAULT 0,
  `rekening_yoy_prev` int(10) unsigned NOT NULL DEFAULT 0,
  `saldo_curr` decimal(20,2) NOT NULL DEFAULT 0.00,
  `saldo_prev` decimal(20,2) NOT NULL DEFAULT 0.00,
  `saldo_yoy_prev` decimal(20,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_pnps`),
  UNIQUE KEY `uq_pnps_snapshot_branch` (`snapshot_posisi`,`branch`),
  KEY `idx_pnps_snapshot_posisi` (`snapshot_posisi`),
  KEY `idx_pnps_branch` (`branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rasio_casa_debitur_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rasio_casa_debitur_snapshots` (
  `uniqueid_rcds` varchar(191) NOT NULL,
  `loan_period` date NOT NULL,
  `casa_period` date DEFAULT NULL,
  `branch_key` varchar(50) NOT NULL,
  `branch_label` varchar(100) DEFAULT NULL,
  `segment_key` varchar(30) NOT NULL,
  `os_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `casa_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `source_row_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_rcds`),
  UNIQUE KEY `uq_rcds_period_branch_segment` (`loan_period`,`branch_key`,`segment_key`),
  KEY `rasio_casa_debitur_snapshots_loan_period_index` (`loan_period`),
  KEY `rasio_casa_debitur_snapshots_casa_period_index` (`casa_period`),
  KEY `rasio_casa_debitur_snapshots_branch_key_index` (`branch_key`),
  KEY `rasio_casa_debitur_snapshots_segment_key_index` (`segment_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rekening_dormant_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rekening_dormant_snapshots` (
  `uniqueid_rds` varchar(191) NOT NULL,
  `posisi` date NOT NULL,
  `branch_label` varchar(100) NOT NULL,
  `raw_branch` varchar(180) NOT NULL,
  `unit_kerja` varchar(180) NOT NULL DEFAULT '',
  `dormant_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_rds`),
  UNIQUE KEY `uq_rds_posisi_branch_unit` (`posisi`,`raw_branch`,`unit_kerja`),
  KEY `idx_rds_posisi_label_unit` (`posisi`,`branch_label`,`unit_kerja`),
  KEY `rekening_dormant_snapshots_posisi_index` (`posisi`),
  KEY `rekening_dormant_snapshots_branch_label_index` (`branch_label`),
  KEY `rekening_dormant_snapshots_raw_branch_index` (`raw_branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_sync_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_sync_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_job_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(150) DEFAULT NULL,
  `table_name` varchar(120) NOT NULL,
  `period_hint` date DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `status` varchar(30) NOT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `affected_rows` int(10) unsigned DEFAULT NULL,
  `message` text DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_sync_audits_import_job_id_index` (`import_job_id`),
  KEY `report_sync_audits_source_index` (`source`),
  KEY `report_sync_audits_table_name_index` (`table_name`),
  KEY `report_sync_audits_period_hint_index` (`period_hint`),
  KEY `report_sync_audits_action_index` (`action`),
  KEY `report_sync_audits_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simpanan_multipn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `simpanan_multipn` (
  `uniqueid_SMPN` varchar(50) NOT NULL,
  `posisi` date DEFAULT NULL,
  `regional_office` varchar(100) DEFAULT NULL,
  `kantor_cabang` varchar(100) DEFAULT NULL,
  `unit_kerja` varchar(100) DEFAULT NULL,
  `CIFNO` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `jenis_simpanan` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `saldo_idr` decimal(18,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uniqueid_SMPN`),
  KEY `idx_smp_posisi_cif` (`posisi`,`CIFNO`),
  KEY `idx_simp_posisi_jenis` (`posisi`,`jenis_simpanan`),
  KEY `idx_simp_cif` (`CIFNO`),
  KEY `idx_simp_dormant_posisi_status_cabang_rek` (`posisi`,`status`,`kantor_cabang`,`no_rekening`),
  KEY `idx_smp_posisi_status_cab_unit` (`posisi`,`status`,`kantor_cabang`,`unit_kerja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_smp_after_ins_invalidate_snapshots
            AFTER INSERT ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF NEW.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_smp_after_upd_invalidate_snapshots
            AFTER UPDATE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF NEW.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
                END IF;

                IF OLD.posisi IS NOT NULL AND (NEW.posisi IS NULL OR OLD.posisi <> NEW.posisi) THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER trg_smp_after_del_invalidate_snapshots
            AFTER DELETE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF OLD.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `sv_merchant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sv_merchant` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `TAHUN` varchar(10) DEFAULT NULL,
  `PERIODE` varchar(20) DEFAULT NULL,
  `POSISI` varchar(50) DEFAULT NULL,
  `KODE_KANWIL` varchar(50) DEFAULT NULL,
  `NAMA_KANWIL` varchar(100) DEFAULT NULL,
  `KODE_KCI` varchar(50) DEFAULT NULL,
  `NAMA_KCI` varchar(100) DEFAULT NULL,
  `KODE_BRANCH` varchar(50) DEFAULT NULL,
  `NAMA_BRANCH` varchar(150) DEFAULT NULL,
  `JENIS` varchar(50) DEFAULT NULL,
  `SEGMENTASI_JENIS` varchar(100) DEFAULT NULL,
  `SV_MERCHANT` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_brimo_fin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_brimo_fin` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `tahun` varchar(4) DEFAULT NULL,
  `periode` varchar(7) DEFAULT NULL,
  `posisi` date DEFAULT NULL,
  `region` varchar(10) DEFAULT NULL,
  `rgdesc` varchar(100) DEFAULT NULL,
  `mainbr` varchar(10) DEFAULT NULL,
  `mbdesc` varchar(100) DEFAULT NULL,
  `branch` varchar(10) DEFAULT NULL,
  `brdesc` varchar(100) DEFAULT NULL,
  `jenis` varchar(50) DEFAULT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `segmentasi` varchar(50) DEFAULT NULL,
  `jumlah` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_brimo_rpt_v2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_brimo_rpt_v2` (
  `uniqueid_namareport` varchar(255) NOT NULL,
  `tahun` varchar(4) DEFAULT NULL,
  `periode` varchar(7) DEFAULT NULL,
  `posisi` date DEFAULT NULL,
  `region` varchar(5) DEFAULT NULL,
  `rgdesc` varchar(100) DEFAULT NULL,
  `mainbr` varchar(10) DEFAULT NULL,
  `mbdesc` varchar(100) DEFAULT NULL,
  `branch` varchar(10) DEFAULT NULL,
  `brdesc` varchar(100) DEFAULT NULL,
  `jenis` varchar(50) DEFAULT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `jumlah` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`uniqueid_namareport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `pn` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_pn_unique` (`pn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2026_03_30_000001_create_daily_loan_dinamis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2026_04_01_000001_add_baki_debet_to_daily_loan_dinamis_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2026_04_02_000001_add_indexes_for_rasio_casa_performance',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_25_110900_add_role_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_25_114247_create_nama_report_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_25_114346_create_import_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_25_114522_create_import_mappings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_25_114952_create_jumlah_merchant_detail_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_26_071835_remove_timestamps_from_jumlah_merchant_detail_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_30_000002_create_simpanan_multipn_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_30_000003_create_user_brimo_rpt_v2_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_30_000004_create_user_brimo_fin_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_04_03_000001_add_status_to_simpanan_multipn_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_04_03_120000_change_baki_debet_to_decimal_on_daily_loan_dinamis_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_04_02_130000_drop_no_from_brilink_summary_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_04_02_140000_drop_no_from_performance_pis_per_produk_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_04_03_130000_create_performance_pis_per_produk_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_04_03_140100_drop_no_after_create_performance_pis_per_produk_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_04_04_090000_create_casa_brilink_web_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_04_04_090100_create_casa_brilink_edc_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_04_04_090200_add_casa_brilink_reports_to_nama_report_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_04_03_130100_add_performance_pis_per_produk_to_nama_report_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_04_04_120000_drop_row_num_from_casa_brilink_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_04_05_110000_add_periode_tid_index_to_jumlah_merchant_detail_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_04_06_120000_expand_daily_loan_dinamis_columns',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_04_06_150000_align_daily_loan_dinamis_schema_and_backfill',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_04_06_180000_reorder_daily_loan_dinamis_columns',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_04_06_190000_drop_legacy_daily_loan_columns_and_finalize_order',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_04_06_210000_add_dashboard_pinjaman_indexes_to_daily_loan_dinamis_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_04_06_220000_create_report_ph_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_04_06_220100_add_report_ph_to_nama_report_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_04_06_220200_rename_lw325_ph_to_lw321_ph',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_04_06_220300_rename_report_ph_to_lw321_ph',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_04_06_220400_rename_nama_report_to_nominatif_rekening_pinjaman_ph',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_04_06_230000_rename_lw321_ph_to_lw325_ph',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_04_04_000001_create_input_rekanan_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_04_07_000001_rename_uniqueid_simopn_to_smpn_on_simpanan_multipn_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_04_07_010000_add_concurrency_indexes_to_import_jobs_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_04_07_020000_add_rekening_dormant_index_to_simpanan_multipn_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_04_07_030000_create_report_snapshot_tables',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_04_07_040000_create_report_sync_audits_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_04_06_223000_add_input_rekanan_to_nama_report_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_04_07_103100_add_bod_boc_to_nama_report_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_04_07_103000_create_bod_boc_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_04_06_120000_sync_daily_loan_dinamis_schema_with_daily_loan_csv',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_04_07_000001_add_rekening_dormant_indexes_to_simpanan_multipn',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_04_07_150000_create_snapshot_invalidation_delete_triggers',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_04_07_220000_promote_uniqueid_as_primary_for_report_tables',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_04_07_230000_expand_snapshot_invalidation_triggers',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_04_08_000000_recreate_snapshot_tables_with_uniqueids',31);
