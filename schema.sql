-- ================================================================
-- Schema Database: smkn2_wali
-- Sistem Pembinaan Guru Wali SMKN 2 Marabahan
-- Migrasi dari Google Apps Script + Sheets
-- ================================================================
-- CARA IMPORT DI WEBHOSTING (cPanel):
--   1. Buat database baru di cPanel → MySQL Databases
--   2. Buat user & assign ke database tersebut (ALL PRIVILEGES)
--   3. Buka phpMyAdmin, pilih database yang sudah dibuat
--   4. Tab Import → pilih file ini → klik Go
-- JANGAN jalankan CREATE DATABASE / USE di webhosting shared.
-- ================================================================

-- ----------------------------------------------------------------
-- Tabel master guru wali
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guru_wali (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(200) NOT NULL,
    kelas      VARCHAR(50)  NOT NULL DEFAULT '',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Tabel master siswa
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS siswa (
    id           MEDIUMINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nisn         VARCHAR(20)  DEFAULT NULL,
    nama         VARCHAR(200) NOT NULL,
    kelas        VARCHAR(50)  NOT NULL,
    guru_wali_id SMALLINT UNSIGNED NOT NULL,
    KEY idx_nisn (nisn),
    KEY idx_guru (guru_wali_id),
    CONSTRAINT fk_siswa_guru FOREIGN KEY (guru_wali_id)
        REFERENCES guru_wali(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Tabel sesi pembinaan (1 sesi = 1 guru, 1 tanggal)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembinaan (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tanggal      DATE     NOT NULL,
    guru_wali_id SMALLINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tgl_guru (tanggal, guru_wali_id),
    KEY idx_tanggal (tanggal),
    CONSTRAINT fk_pem_guru FOREIGN KEY (guru_wali_id)
        REFERENCES guru_wali(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Tabel detail rekam per siswa per sesi
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembinaan_detail (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pembinaan_id INT UNSIGNED      NOT NULL,
    siswa_id     MEDIUMINT UNSIGNED NOT NULL,
    absen        CHAR(1)      NOT NULL DEFAULT 'H' COMMENT 'H=Hadir T=Tepat L=Terlambat A=Alpa S=Sakit I=Izin',
    zuhur        VARCHAR(5)   NOT NULL DEFAULT 'H' COMMENT 'H=Hadir S=Sakit I=Izin A=Alpa Haid=Haid',
    ashar        VARCHAR(5)   NOT NULL DEFAULT 'H' COMMENT 'H=Hadir S=Sakit I=Izin A=Alpa Haid=Haid',
    mingguan     VARCHAR(5)   NOT NULL DEFAULT '-' COMMENT 'H/X/I untuk Upacara-Olga (X=Tidak Hadir), - jika tidak ada',
    tambahan     VARCHAR(500) NOT NULL DEFAULT '',
    UNIQUE KEY uq_pem_siswa (pembinaan_id, siswa_id),
    KEY idx_siswa (siswa_id),
    CONSTRAINT fk_det_pem   FOREIGN KEY (pembinaan_id) REFERENCES pembinaan(id) ON DELETE CASCADE,
    CONSTRAINT fk_det_siswa FOREIGN KEY (siswa_id)     REFERENCES siswa(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
