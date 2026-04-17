# Analisis Pengembangan Sistem Berdasarkan Regulasi Guru Wali

Sumber: Pasal 9 & Pasal 18 (Peraturan tentang Guru Wali)

---

## Apa yang Sudah Ada di Sistem

| Fitur | Status |
|---|---|
| Input absensi per sesi pembinaan | ✅ Ada |
| Input sholat Zuhur & Ashar | ✅ Ada |
| Input kegiatan mingguan (Upacara/Olga) | ✅ Ada |
| Laporan per guru wali | ✅ Ada |
| Dashboard rekap semua guru | ✅ Ada |
| Rekap bulanan pivot table | ✅ Ada |
| Deteksi siswa kritis (banyak alpa) | ✅ Ada |

---

## Yang Bisa Dikembangkan (Berdasarkan Regulasi)

### 1. Dimensi Pendampingan Akademik *(Pasal 9 ayat 2)*
> "paling sedikit melaksanakan pendampingan akademik, pengembangan kompetensi, keterampilan, dan karakter murid dampingannya"

Sistem saat ini hanya mencatat **kehadiran & ibadah**. Perlu ditambah:

- **Catatan akademik per siswa** — nilai rapor, mata pelajaran yang perlu perhatian
- **Dimensi pembinaan** — toggle/checklist: Akademik / Kompetensi / Keterampilan / Karakter per sesi
- **Catatan naratif per siswa** — kolom `tambahan` yang ada bisa diperluas menjadi catatan terstruktur
- **Target & progres** — sasaran pembinaan per siswa (misal: tingkatkan kehadiran, perbaiki nilai X)

---

### 2. Histori Longitudinal Siswa *(Pasal 9 ayat 3)*
> "sejak yang bersangkutan terdaftar sebagai murid hingga menyelesaikan pendidikannya"

Sistem saat ini hanya menampilkan data per sesi/per bulan. Perlu:

- **Halaman profil siswa** — riwayat pembinaan dari kelas X sampai XII (atau masuk hingga lulus)
- **Grafik tren per siswa** — absensi & sholat dari waktu ke waktu
- **Status akademik** — kelas naik/tidak, status aktif/alumni/pindah/keluar
- **Rekap per semester/per tahun ajaran** — bukan hanya per bulan

---

### 3. Kolaborasi dengan Guru BK & Wali Kelas *(Pasal 9 ayat 5)*
> "berkolaborasi dengan Guru bimbingan dan konseling dan Guru wali kelas"

Fitur yang bisa ditambah:

- **Flag/referral ke BK** — tandai siswa yang perlu ditangani Guru BK dari dalam form pembinaan
- **Catatan kolaborasi** — log diskusi/tindak lanjut bersama Guru BK
- **Notifikasi** — jika siswa X sudah N kali alpa, otomatis muncul saran "Rujuk ke BK"

---

### 4. Manajemen Penugasan Guru Wali *(Pasal 18 ayat 1 & 2)*
> "Penetapan Guru wali dilaksanakan dengan mempertimbangkan jumlah murid dibagi dengan jumlah Guru mata pelajaran"

Saat ini guru wali hanya diinput sebagai nama teks bebas. Perlu:

- **Halaman manajemen guru wali** — CRUD data guru (nama, NIP, mata pelajaran yang diampu, kelas binaan)
- **Rasio siswa per guru** — tampilkan jumlah siswa vs kapasitas ideal di dashboard
- **Riwayat penugasan** — tahun ajaran berapa guru ini memegang kelas mana

---

### 5. Monitoring Beban Kerja Guru *(Pasal 18 ayat 4)*
> "Guru yang tidak dapat memenuhi beban kerja dalam pelaksanaan pembelajaran atau pembimbingan"

- **Indikator beban kerja** — jumlah siswa binaan, frekuensi sesi per bulan vs target minimal
- **Alert guru tidak aktif** — jika guru belum input sesi lebih dari N minggu, tampil di dashboard
- **Perbandingan antar guru** — siapa yang rajin, siapa yang jarang input (sudah ada di dashboard, tapi bisa diperkaya)

---

### 6. Laporan Formal untuk Kepala Sekolah *(Pasal 18 ayat 4 & 5)*
> "kepala satuan pendidikan melaporkan kepada Dinas sesuai dengan kewenangannya"

- **Ekspor laporan resmi** — format yang bisa dicetak/PDF dengan kop sekolah, tanda tangan
- **Rekap per semester** — laporan akumulatif yang bisa dilampirkan ke laporan ke Dinas
- **Rekapitulasi semua guru** — satu halaman ringkasan total sesi, total siswa, rata-rata kehadiran seluruh sekolah

---

## Rencana Teknis: Dimensi Pembinaan

Berdasarkan Pasal 9 ayat 2, sesi pembinaan harus mencakup minimal 4 dimensi:
**Akademik · Kompetensi · Keterampilan · Karakter**

Saat ini kolom yang tersedia di `pembinaan_detail` hanya `tambahan VARCHAR(500)` yang tidak terstruktur.
Berikut rencana implementasi bertahap:

---

### Tahap 1 — Perubahan Database Schema

Tambahkan kolom baru di tabel `pembinaan_detail`:

```sql
ALTER TABLE pembinaan_detail
    ADD COLUMN dim_akademik   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=dibahas sesi ini',
    ADD COLUMN dim_kompetensi TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN dim_keterampilan TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN dim_karakter   TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN catatan_akademik VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'catatan khusus per siswa per sesi';
```

Kolom `tambahan` yang ada tetap dipertahankan (tidak di-drop) agar data lama tidak hilang.
`catatan_akademik` menggantikan peran `tambahan` secara bertahap.

---

### Tahap 2 — UI di `index.html`

Mekanisme serupa toggle **Kegiatan Tambahan** yang sudah ada (`#toggleTambahan`).

**Tambah toggle baru di panel kontrol:**
```
[ ] Dimensi Pembinaan
```
Jika dicentang → muncul panel di atas tabel (bukan kolom per siswa) berisi 4 checkbox sesi:

```
Sesi ini membahas:
[✓] Akademik   [ ] Kompetensi   [ ] Keterampilan   [✓] Karakter
```

Ini adalah **dimensi per sesi** (1 sesi = 1 kombinasi dimensi yang sama untuk semua siswa).
Disimpan ke tabel `pembinaan` (level sesi), bukan `pembinaan_detail` (level siswa).

Untuk itu tambahkan kolom di `pembinaan`:
```sql
ALTER TABLE pembinaan
    ADD COLUMN dim_akademik     TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN dim_kompetensi   TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN dim_keterampilan TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN dim_karakter     TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN tema_sesi        VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'tema/topik sesi pembinaan';
```

**Catatan per siswa** (opsional, jika ada siswa yang butuh catatan khusus):
Kolom `catatan_akademik` di `pembinaan_detail` diisi via modal yang sudah ada (`#modalStatus`).
Tambahkan field teks di modal:
```
[Catatan untuk siswa ini (opsional)________________]
```

---

### Tahap 3 — Perubahan `save.php`

Tambah field baru ke body JSON yang diterima:
```json
{
  "tanggal": "2026-04-16",
  "guru": "Nama Guru",
  "dimensi": {
    "akademik": true,
    "kompetensi": false,
    "keterampilan": false,
    "karakter": true,
    "tema": "Persiapan UAS"
  },
  "responses": [
    { "nama": "...", "catatan_akademik": "Nilai matematika turun" }
  ]
}
```

Validasi di `save.php`:
```php
$dimAkademik    = !empty($body['dimensi']['akademik'])    ? 1 : 0;
$dimKompetensi  = !empty($body['dimensi']['kompetensi'])  ? 1 : 0;
$dimKeterampilan= !empty($body['dimensi']['keterampilan'])? 1 : 0;
$dimKarakter    = !empty($body['dimensi']['karakter'])    ? 1 : 0;
$temaSesi       = substr(trim($body['dimensi']['tema'] ?? ''), 0, 200);
```

Update upsert `pembinaan` menyertakan kolom dimensi.
Per siswa: tambahkan `catatan_akademik` ke `$stmtDetail`.

---

### Tahap 4 — Perubahan `report.php`

**MODE 2** (laporan per guru) — tambahkan field ke SELECT:
```sql
p.dim_akademik, p.dim_kompetensi, p.dim_keterampilan, p.dim_karakter, p.tema_sesi,
pd.catatan_akademik
```

**MODE baru (MODE 5) — Rekap Dimensi per Guru:**
```
GET /api/report.php?dimensi=1&guru=NamaGuru&from=...&to=...
```
Response: berapa sesi membahas akademik, kompetensi, keterampilan, karakter dalam rentang waktu.
Berguna untuk laporan kepala sekolah.

---

### Tahap 5 — Tampilan di Halaman Laporan & Rekap Bulanan

**`laporan.html`** — tambahkan di baris per sesi:
- Ikon dimensi kecil: `📚 A  🔧 K  ⚡ Ktr  💎 Kar` (yang dicentang tampil berwarna, yang tidak abu)
- Tema sesi tampil sebagai subtitle di header per sesi

**`rekap_bulanan.html`** — tambahkan baris footer per tanggal:
- Baris dimensi: icon A/K/Ktr/Kar yang aktif per kolom tanggal

**Halaman baru `dimensi_rekap.html`** (jangka panjang):
- Bar chart: distribusi dimensi per bulan/per guru
- "Bulan ini: 80% sesi bahas Akademik, 40% Karakter, 20% Kompetensi"

---

### Ringkasan Perubahan File

| File | Perubahan |
|---|---|
| `schema.sql` | `ALTER TABLE pembinaan` + `ALTER TABLE pembinaan_detail` |
| `api/save.php` | Terima & simpan field `dimensi` dan `catatan_akademik` |
| `api/report.php` | MODE 2 tambah kolom dimensi; tambah MODE 5 rekap dimensi |
| `index.html` | Toggle + panel checkbox dimensi sesi; field catatan di modal |
| `laporan.html` | Tampilkan ikon dimensi per sesi |
| `rekap_bulanan.html` | Baris dimensi aktif per kolom tanggal |

---

## Prioritas Pengembangan (Usulan)

| Prioritas | Fitur | Kompleksitas |
|---|---|---|
| 🔴 Tinggi | Profil & histori longitudinal siswa | Sedang |
| 🔴 Tinggi | Ekspor laporan formal (PDF/cetak) | Rendah |
| 🟡 Sedang | Dimensi pembinaan (Akademik/Karakter/dll) | Sedang |
| 🟡 Sedang | Flag/referral siswa ke BK | Rendah |
| 🟢 Rendah | Manajemen penugasan guru wali | Tinggi |
| 🟢 Rendah | Monitoring beban kerja & alert | Sedang |

---

*Dokumen ini dibuat berdasarkan analisis Pasal 9 & Pasal 18 peraturan Guru Wali — April 2026*
