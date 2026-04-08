/**
 * Jalankan fungsi ini SATU KALI untuk menyiapkan struktur Spreadsheet
 * Klik tombol "Run" pada fungsi setupSpreadsheet di editor Apps Script
 */
function setupSpreadsheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName("Sheet1");
  
  if (!sheet) {
    sheet = ss.insertSheet("Sheet1");
  }
  
  // Header sesuai dengan urutan pengiriman data di HTML
  const headers = [
    "Timestamp", "Tanggal", "Guru Wali", "Nama Siswa", "NISN", 
    "Kelas", "Absen", "Zuhur", "Ashar", "Kegiatan Mingguan", "Tambahan"
  ];
  
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  
  // Format Header
  const headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setBackground("#1a73e8")
             .setFontColor("white")
             .setFontWeight("bold")
             .setHorizontalAlignment("center");

  sheet.setFrozenRows(1);

  // Atur Lebar Kolom
  sheet.setColumnWidth(1, 150); // Timestamp
  sheet.setColumnWidth(2, 100); // Tanggal
  sheet.setColumnWidth(3, 180); // Guru
  sheet.setColumnWidth(4, 200); // Nama Siswa
  
  // Format Bersyarat: Merah jika Alpa (A)
  const rangeAbsen = sheet.getRange("G2:K1000"); 
  const rule = SpreadsheetApp.newConditionalFormatRule()
      .whenTextEqualTo("A")
      .setBackground("#F4CCCC")
      .setFontColor("#CC0000")
      .setRanges([rangeAbsen])
      .build();
  
  const rules = sheet.getConditionalFormatRules();
  rules.push(rule);
  sheet.setConditionalFormatRules(rules);

  SpreadsheetApp.getUi().alert("✅ Spreadsheet SMKN 2 Marabahan siap digunakan!");
}

/**
 * Mengirim data laporan ke halaman HTML (doGet)
 * Params: ?action=getReport&from=YYYY-MM-DD&to=YYYY-MM-DD&guru=NamaGuru (opsional)
 */
function doGet(e) {
  const params = e.parameter;
  const action = params.action || '';

  if (action === 'getReport') {
    const from = params.from ? new Date(params.from) : null;
    const to   = params.to   ? new Date(params.to)   : null;
    if (to) to.setHours(23, 59, 59, 999);
    const guruFilter = (params.guru || '').toLowerCase().trim();

    const ss    = SpreadsheetApp.getActiveSpreadsheet();
    const sheet = ss.getSheetByName("Sheet1");
    const data  = sheet.getDataRange().getValues();

    const rows = [];
    // Mulai dari baris ke-2 (index 1) → skip header
    for (let i = 1; i < data.length; i++) {
      const row     = data[i];
      const tglCell = row[1]; // kolom Tanggal (string YYYY-MM-DD)
      const tgl     = tglCell ? new Date(tglCell) : null;

      // Filter tanggal
      if (from && tgl && tgl < from) continue;
      if (to   && tgl && tgl > to)   continue;

      // Filter guru (opsional)
      const namaGuru = (row[2] || '').toString();
      if (guruFilter && !namaGuru.toLowerCase().includes(guruFilter)) continue;

      // Format tanggal ke YYYY-MM-DD dengan aman (tglCell bisa berupa Date object atau string)
      let tanggalStr = '';
      if (tgl instanceof Date && !isNaN(tgl)) {
        tanggalStr = Utilities.formatDate(tgl, Session.getScriptTimeZone(), 'yyyy-MM-dd');
      } else if (tglCell) {
        tanggalStr = tglCell.toString().substring(0, 10);
      }

      rows.push({
        tanggal  : tanggalStr,
        guru     : namaGuru,
        nama     : row[3] || '',
        nisn     : row[4] ? row[4].toString() : '',
        kelas    : row[5] || '',
        absen    : row[6] || '-',
        zuhur    : row[7] || '-',
        ashar    : row[8] || '-',
        mingguan : row[9] || '-',
        tambahan : row[10] || '-'
      });
    }

    const output = ContentService.createTextOutput(JSON.stringify({ status: 'success', data: rows }))
      .setMimeType(ContentService.MimeType.JSON);
    return output;
  }

  // Default: kembalikan pesan OK
  return ContentService.createTextOutput(JSON.stringify({ status: 'ok' }))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * Menerima data dari Form HTML
 */
function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sheet = ss.getSheetByName("Sheet1");
    
    // Ambil data array siswa dari kiriman
    const responses = data.responses;
    
    responses.forEach(row => {
      sheet.appendRow([
        new Date(),       // Timestamp
        data.tanggal,     // Tanggal Form
        data.guru,        // Nama Guru Wali
        row.nama,         // Nama Siswa
        row.nisn,         // NISN
        row.kelas,        // Kelas
        row.absen,        // Kolom Absen
        row.zuhur,        // Kolom Zuhur
        row.ashar,        // Kolom Ashar
        row.mingguan,     // Kolom Kegiatan (Upacara/Olga)
        row.tambahan      // Kolom Tambahan
      ]);
    });

    return ContentService.createTextOutput(JSON.stringify({"status": "success"}))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({"status": "error", "error": err.toString()}))
      .setMimeType(ContentService.MimeType.JSON);
  }
}