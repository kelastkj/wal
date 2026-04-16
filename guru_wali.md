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
