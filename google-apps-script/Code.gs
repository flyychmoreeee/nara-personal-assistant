/**
 * ============================================================================
 * NARA ACADEMIC ASSISTANT - GOOGLE SPREADSHEET KAS INTEGRATION
 * ============================================================================
 * 
 * Script ini digunakan untuk menerima data Kas Kelas dari sistem NARA (Laravel)
 * dan otomatis memperbarui 2 Tab di Google Spreadsheet:
 * 1. "Ringkasan & Pengeluaran": Saldo kas, pemasukan (Cash & QRIS), pengeluaran,
 *    dan rincian tiap nota/transaksi pengeluaran.
 * 2. "Rekap Pembayaran Siswa": Tabel matrix seluruh siswa per minggu/iuran,
 *    status lunas, belum bayar, atau "DOUBLE (2x) ⚠️", serta total nominal tunggakan.
 *
 * CARA PEMASANGAN (HANYA 3 MENIT):
 * 1. Buka Google Spreadsheet baru / yang sudah ada untuk kas kelasmu.
 * 2. Klik menu "Ekstensi" (Extensions) > "Apps Script".
 * 3. Hapus semua kode default di editor, lalu PASTE SELURUH KODE DI FILE INI.
 * 4. (Opsional) Jika ingin menggunakan token keamanan, ubah variabel SECRET_TOKEN di bawah.
 * 5. Klik tombol "Terapkan" (Deploy) di kanan atas > "Deployment Baru" (New deployment).
 * 6. Pilih jenis: "Aplikasi Web" (Web App).
 *    - Deskripsi: NARA Kas Sync Webhook
 *    - Jalankan sebagai: "Saya" (Me)
 *    - Yang memiliki akses: "Siapa saja" (Anyone) -> PENTING agar Laravel bisa POST data!
 * 7. Klik "Terapkan" (Deploy) & berikan izin akun Google jika diminta.
 * 8. Salin "URL Aplikasi Web" (Web App URL) yang dihasilkan.
 * 9. Tempelkan URL tersebut di file `.env` Laravel:
 *    KAS_SPREADSHEET_WEBHOOK_URL=https://script.google.com/macros/s/.../exec
 *    KAS_SPREADSHEET_PUBLIC_URL=https://docs.google.com/spreadsheets/d/.../edit
 *
 * Selesai! Kas akan otomatis tersinkron saat bendahara mencatat pembayaran / pengeluaran.
 * ============================================================================
 */

// Ganti jika ingin menggunakan secret token verifikasi (kosongkan jika tidak perlu)
var SECRET_TOKEN = "";

/**
 * Handle HTTP GET Request (Untuk cek status webhook di browser)
 */
function doGet(e) {
  var output = {
    status: "online",
    service: "NARA Kas Spreadsheet Webhook",
    message: "Webhook aktif dan siap menerima sinkronisasi data dari NARA Laravel!",
    timestamp: new Date().toISOString()
  };

  return ContentService.createTextOutput(JSON.stringify(output, null, 2))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * Handle HTTP POST Request (Menerima data JSON dari Laravel)
 */
function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return errorResponse("Payload data tidak ditemukan dalam request POST.", 400);
    }

    var payload = JSON.parse(e.postData.contents);

    // Verifikasi secret token jika disetel
    if (SECRET_TOKEN && SECRET_TOKEN.trim() !== "") {
      var clientSecret = (payload.secret || (e.parameter && e.parameter.secret) || "");
      if (clientSecret !== SECRET_TOKEN) {
        return errorResponse("Akses ditolak: Secret token tidak cocok.", 403);
      }
    }

    // Jalankan pembaruan spreadsheet
    var result = updateKasSpreadsheet(payload);

    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: "Spreadsheet kas berhasil disinkronkan secara otomatis!",
      updated_at: new Date().toISOString(),
      details: result
    }, null, 2)).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return errorResponse("Terjadi kesalahan pada Google Apps Script: " + err.toString(), 500);
  }
}

/**
 * Update kedua lembar kerja (Tab Ringkasan & Tab Rekap Siswa)
 */
function updateKasSpreadsheet(data) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();

  // 1. Update Tab Ringkasan & Pengeluaran
  renderOverviewAndExpensesSheet(ss, data);

  // 2. Update Tab Rekap Pembayaran Siswa
  renderStudentMatrixSheet(ss, data);

  return {
    expenses_count: (data.expenses || []).length,
    students_count: (data.students || []).length,
    weeks_count: (data.weeks || []).length
  };
}

/**
 * ----------------------------------------------------------------------------
 * 1. TAB RINGKASAN & PENGELUARAN KAS
 * ----------------------------------------------------------------------------
 */
function renderOverviewAndExpensesSheet(ss, data) {
  var sheetName = "Ringkasan & Pengeluaran";
  var sheet = ss.getSheetByName(sheetName);

  if (!sheet) {
    sheet = ss.insertSheet(sheetName, 0);
  }
  sheet.setTabColor("#10B981"); // Hijau Emerald
  sheet.clear();

  var summary = data.summary || {};
  var expenses = data.expenses || [];
  var generatedAt = data.generated_at || Utilities.formatDate(new Date(), "Asia/Jakarta", "dd MMMM yyyy, HH:mm") + " WIB";

  // --- HEADER BANNER ---
  sheet.getRange("A1:F1").merge()
    .setValue("💰 LAPORAN KEUANGAN KAS KELAS")
    .setBackground("#064E3B")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(14)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(1, 38);

  sheet.getRange("A2:F2").merge()
    .setValue("📅 Pembaruan Terakhir: " + generatedAt + " (Sinkronisasi Otomatis NARA Assistant)")
    .setFontColor("#6B7280")
    .setFontStyle("italic")
    .setFontSize(9)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(2, 22);

  // --- KPI CARDS (KOTAK RINGKASAN) ---
  // Card 1: Saldo Kas (A4:B5)
  sheet.getRange("A4:B4").merge().setValue("SALDO KAS SAAT INI")
    .setBackground("#D1FAE5").setFontColor("#065F46").setFontWeight("bold").setFontSize(9).setHorizontalAlignment("center");
  sheet.getRange("A5:B5").merge().setValue(summary.balance || 0)
    .setBackground("#ECFDF5").setFontColor("#047857").setFontWeight("bold").setFontSize(14).setHorizontalAlignment("center").setNumberFormat("Rp #,##0");
  sheet.getRange("A6:B6").merge().setValue("Uang Kas Bersih Siap Pakai")
    .setBackground("#ECFDF5").setFontColor("#059669").setFontStyle("italic").setFontSize(8).setHorizontalAlignment("center");

  // Card 2: Total Pemasukan (C4:D5)
  sheet.getRange("C4:D4").merge().setValue("TOTAL KAS MASUK")
    .setBackground("#DBEAFE").setFontColor("#1E40AF").setFontWeight("bold").setFontSize(9).setHorizontalAlignment("center");
  sheet.getRange("C5:D5").merge().setValue(summary.total_income || 0)
    .setBackground("#EFF6FF").setFontColor("#1D4ED8").setFontWeight("bold").setFontSize(14).setHorizontalAlignment("center").setNumberFormat("Rp #,##0");
  var cashFormatted = formatRupiahSimple(summary.total_cash_income || 0);
  var qrisFormatted = formatRupiahSimple(summary.total_qris_income || 0);
  sheet.getRange("C6:D6").merge().setValue("Tunai: " + cashFormatted + " | QRIS: " + qrisFormatted)
    .setBackground("#EFF6FF").setFontColor("#3B82F6").setFontStyle("italic").setFontSize(8).setHorizontalAlignment("center");

  // Card 3: Total Pengeluaran (E4:F5)
  sheet.getRange("E4:F4").merge().setValue("TOTAL PENGELUARAN")
    .setBackground("#FEE2E2").setFontColor("#991B1B").setFontWeight("bold").setFontSize(9).setHorizontalAlignment("center");
  sheet.getRange("E5:F5").merge().setValue(summary.total_expense || 0)
    .setBackground("#FEF2F2").setFontColor("#B91C1C").setFontWeight("bold").setFontSize(14).setHorizontalAlignment("center").setNumberFormat("Rp #,##0");
  sheet.getRange("E6:F6").merge().setValue((expenses.length) + " Catatan Pengeluaran")
    .setBackground("#FEF2F2").setFontColor("#EF4444").setFontStyle("italic").setFontSize(8).setHorizontalAlignment("center");

  // Set card borders
  sheet.getRange("A4:B6").setBorder(true, true, true, true, null, null, "#A7F3D0", SpreadsheetApp.BorderStyle.SOLID);
  sheet.getRange("C4:D6").setBorder(true, true, true, true, null, null, "#BFDBFE", SpreadsheetApp.BorderStyle.SOLID);
  sheet.getRange("E4:F6").setBorder(true, true, true, true, null, null, "#FECACA", SpreadsheetApp.BorderStyle.SOLID);

  // --- TABEL PENGELUARAN KAS ---
  sheet.getRange("A8:F8").merge().setValue("📋 RINCIAN & PENGGUNAAN UANG KAS")
    .setFontColor("#1F2937").setFontWeight("bold").setFontSize(11).setVerticalAlignment("middle");
  sheet.setRowHeight(8, 28);

  var expHeaders = ["No", "Tanggal", "Keperluan / Pengeluaran", "Nominal (Rp)", "Catatan / Keterangan", "Bukti Nota"];
  var headerRange = sheet.getRange(9, 1, 1, 6);
  headerRange.setValues([expHeaders])
    .setBackground("#1E293B")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(10)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(9, 28);

  var startRow = 10;
  if (expenses.length > 0) {
    var expenseRows = [];
    for (var i = 0; i < expenses.length; i++) {
      var exp = expenses[i];
      var notaCell = "-";
      if (exp.receipt_url && exp.receipt_url.trim() !== "") {
        notaCell = '=HYPERLINK("' + exp.receipt_url + '"; "Lihat Nota 📄")';
      }

      expenseRows.push([
        i + 1,
        exp.formatted_date || exp.date || "-",
        exp.title || "-",
        exp.amount || 0,
        exp.notes || "-",
        notaCell
      ]);
    }

    var dataRange = sheet.getRange(startRow, 1, expenseRows.length, 6);
    dataRange.setValues(expenseRows);
    dataRange.setFontSize(9).setVerticalAlignment("middle");

    // Alignments & formats
    sheet.getRange(startRow, 1, expenseRows.length, 1).setHorizontalAlignment("center"); // No
    sheet.getRange(startRow, 2, expenseRows.length, 1).setHorizontalAlignment("center"); // Tanggal
    sheet.getRange(startRow, 3, expenseRows.length, 1).setHorizontalAlignment("left");   // Keperluan
    sheet.getRange(startRow, 4, expenseRows.length, 1).setHorizontalAlignment("right").setNumberFormat("Rp #,##0"); // Nominal
    sheet.getRange(startRow, 5, expenseRows.length, 1).setHorizontalAlignment("left");   // Catatan
    sheet.getRange(startRow, 6, expenseRows.length, 1).setHorizontalAlignment("center"); // Nota

    // Alternating row colors
    for (var r = 0; r < expenseRows.length; r++) {
      var rowColor = (r % 2 === 0) ? "#FFFFFF" : "#F8FAFC";
      sheet.getRange(startRow + r, 1, 1, 6).setBackground(rowColor);
      sheet.setRowHeight(startRow + r, 24);
    }

    // Border data
    dataRange.setBorder(true, true, true, true, true, true, "#E2E8F0", SpreadsheetApp.BorderStyle.SOLID);

    // Total Row
    var totalExpRow = startRow + expenseRows.length;
    sheet.getRange(totalExpRow, 1, 1, 3).merge().setValue("TOTAL PENGELUARAN")
      .setFontWeight("bold").setHorizontalAlignment("right").setBackground("#F1F5F9");
    sheet.getRange(totalExpRow, 4).setFormula("=SUM(D" + startRow + ":D" + (totalExpRow - 1) + ")")
      .setFontWeight("bold").setHorizontalAlignment("right").setNumberFormat("Rp #,##0").setBackground("#F1F5F9");
    sheet.getRange(totalExpRow, 5, 1, 2).merge().setValue("")
      .setBackground("#F1F5F9");

    sheet.getRange(totalExpRow, 1, 1, 6).setBorder(true, true, true, true, null, null, "#94A3B8", SpreadsheetApp.BorderStyle.DOUBLE);
    sheet.setRowHeight(totalExpRow, 26);
  } else {
    sheet.getRange("A10:F10").merge().setValue("Belum ada data pengeluaran kas yang tercatat.")
      .setFontStyle("italic").setFontColor("#94A3B8").setHorizontalAlignment("center").setVerticalAlignment("middle");
    sheet.setRowHeight(10, 30);
  }

  // Set column widths
  sheet.setColumnWidth(1, 45);  // No
  sheet.setColumnWidth(2, 95);  // Tanggal
  sheet.setColumnWidth(3, 230); // Keperluan
  sheet.setColumnWidth(4, 120); // Nominal
  sheet.setColumnWidth(5, 200); // Catatan
  sheet.setColumnWidth(6, 110); // Bukti Nota
}

/**
 * ----------------------------------------------------------------------------
 * 2. TAB REKAP PEMBAYARAN SISWA (MATRIX KAS KELAS)
 * ----------------------------------------------------------------------------
 */
function renderStudentMatrixSheet(ss, data) {
  var sheetName = "Rekap Pembayaran Siswa";
  var sheet = ss.getSheetByName(sheetName);

  if (!sheet) {
    sheet = ss.insertSheet(sheetName, 1);
  }
  sheet.setTabColor("#2563EB"); // Biru Modern
  sheet.clear();

  var weeks = data.weeks || [];
  var students = data.students || [];
  var summary = data.summary || {};
  var generatedAt = data.generated_at || Utilities.formatDate(new Date(), "Asia/Jakarta", "dd MMMM yyyy, HH:mm") + " WIB";

  var totalWeeks = weeks.length;
  // Columns: No Absen (1), Nama (2), Panggilan (3), [Weeks 1..N], Status (4+N), Sisa Tunggakan (5+N), Minggu Nunggak (6+N)
  var totalCols = 3 + totalWeeks + 3;

  // --- HEADER BANNER ---
  sheet.getRange(1, 1, 1, totalCols).merge()
    .setValue("👥 REKAP PEMBAYARAN KAS SISWA")
    .setBackground("#1E3A8A")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(14)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(1, 38);

  sheet.getRange(2, 1, 1, totalCols).merge()
    .setValue("📌 Cek status iuran kasmu: Lunas, Belum Bayar, atau Double (Nunggak). | Terakhir Diperbarui: " + generatedAt)
    .setFontColor("#4B5563")
    .setFontStyle("italic")
    .setFontSize(9)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(2, 22);

  // --- TABLE HEADERS (ROW 4 & 5) ---
  var row4 = ["No", "Nama Siswa", "Panggilan"];
  var row5 = ["-", "-", "-"];

  for (var w = 0; w < totalWeeks; w++) {
    row4.push(weeks[w].title || ("Minggu " + (w + 1)));
    row5.push(formatRupiahSimple(weeks[w].amount || 0));
  }

  row4.push("Status Pembayaran");
  row5.push("(Lunas / Double)");

  row4.push("Sisa Tunggakan");
  row5.push("(Nominal)");

  row4.push("Minggu Belum Bayar");
  row5.push("(Daftar Tunggakan)");

  // Row 4
  var headerRange1 = sheet.getRange(4, 1, 1, totalCols);
  headerRange1.setValues([row4])
    .setBackground("#1E40AF")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(10)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(4, 26);

  // Row 5
  var headerRange2 = sheet.getRange(5, 1, 1, totalCols);
  headerRange2.setValues([row5])
    .setBackground("#3B82F6")
    .setFontColor("#EFF6FF")
    .setFontWeight("normal")
    .setFontSize(8)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle");
  sheet.setRowHeight(5, 20);

  // --- POPULATE STUDENTS DATA ---
  var startRow = 6;
  if (students.length > 0) {
    var studentValues = [];
    var backgroundMatrix = [];
    var fontColorMatrix = [];
    var fontWeightMatrix = [];
    var alignmentMatrix = [];

    for (var s = 0; s < students.length; s++) {
      var stu = students[s];
      var rowData = [
        stu.attendance_number || (s + 1),
        stu.name || "-",
        stu.nickname || "-"
      ];

      var bgRow = ["#FFFFFF", "#FFFFFF", "#FFFFFF"];
      var fcRow = ["#1F2937", "#1F2937", "#4B5563"];
      var fwRow = ["normal", "medium", "normal"];
      var alignRow = ["center", "left", "center"];

      // Process each week
      for (var k = 0; k < totalWeeks; k++) {
        var weekId = weeks[k].id;
        var paymentInfo = stu.weeks ? stu.weeks[weekId] : null;
        var isPaid = paymentInfo ? paymentInfo.is_paid : false;

        if (isPaid) {
          var methodText = paymentInfo.method === "qris" ? "QRIS" : "Cash";
          rowData.push("LUNAS");
          bgRow.push("#D1E7DD"); // Soft Green
          fcRow.push("#0F5132"); // Dark Green
          fwRow.push("bold");
        } else {
          rowData.push("BELUM");
          bgRow.push("#F8D7DA"); // Soft Red
          fcRow.push("#842029"); // Dark Red
          fwRow.push("bold");
        }
        alignRow.push("center");
      }

      // Status column
      var statusText = stu.status_label || (stu.unpaid_count === 0 ? "LUNAS ✅" : "BELUM");
      rowData.push(statusText);
      alignRow.push("center");

      if (stu.unpaid_count === 0) {
        bgRow.push("#D1E7DD"); // Hijau
        fcRow.push("#0F5132");
        fwRow.push("bold");
      } else if (stu.unpaid_count === 1) {
        bgRow.push("#FFF3CD"); // Kuning
        fcRow.push("#664D03");
        fwRow.push("bold");
      } else if (stu.unpaid_count === 2) {
        bgRow.push("#F8D7DA"); // Merah muda (DOUBLE)
        fcRow.push("#842029");
        fwRow.push("bold");
      } else {
        bgRow.push("#FCE7F3"); // Pink/Merah pekat
        fcRow.push("#9D174D");
        fwRow.push("bold");
      }

      // Sisa Tunggakan
      rowData.push(stu.total_arrears || 0);
      alignRow.push("right");
      bgRow.push(stu.total_arrears > 0 ? "#FFF1F2" : "#FFFFFF");
      fcRow.push(stu.total_arrears > 0 ? "#BE123C" : "#10B981");
      fwRow.push(stu.total_arrears > 0 ? "bold" : "normal");

      // Daftar Minggu Belum Bayar
      rowData.push(stu.unpaid_weeks_text || "-");
      alignRow.push("left");
      bgRow.push("#FFFFFF");
      fcRow.push("#4B5563");
      fwRow.push("normal");

      studentValues.push(rowData);
      backgroundMatrix.push(bgRow);
      fontColorMatrix.push(fcRow);
      fontWeightMatrix.push(fwRow);
      alignmentMatrix.push(alignRow);
    }

    // Set Values in batch
    var matrixRange = sheet.getRange(startRow, 1, studentValues.length, totalCols);
    matrixRange.setValues(studentValues)
      .setBackgrounds(backgroundMatrix)
      .setFontColors(fontColorMatrix)
      .setFontWeights(fontWeightMatrix)
      .setHorizontalAlignments(alignmentMatrix)
      .setFontSize(9)
      .setVerticalAlignment("middle");

    // Format Sisa Tunggakan column as Currency
    var arrearsColIndex = 3 + totalWeeks + 2;
    sheet.getRange(startRow, arrearsColIndex, studentValues.length, 1).setNumberFormat("Rp #,##0");

    // Row heights & borders
    for (var r = 0; r < studentValues.length; r++) {
      sheet.setRowHeight(startRow + r, 24);
    }
    matrixRange.setBorder(true, true, true, true, true, true, "#E5E7EB", SpreadsheetApp.BorderStyle.SOLID);

    // --- SUMMARY ROW DI BAWAH MATRIX ---
    var totalRowIndex = startRow + studentValues.length;
    sheet.getRange(totalRowIndex, 1, 1, 3).merge().setValue("TOTAL KESELURUHAN")
      .setFontWeight("bold").setHorizontalAlignment("right").setBackground("#F1F5F9");

    // Total per minggu
    for (var wCol = 0; wCol < totalWeeks; wCol++) {
      var colLetter = getColumnLetter(4 + wCol);
      sheet.getRange(totalRowIndex, 4 + wCol).setValue("")
        .setBackground("#F1F5F9");
    }

    // Status summary
    sheet.getRange(totalRowIndex, 3 + totalWeeks + 1).setValue(
      "Lunas: " + (summary.all_paid_students_count || 0) + " | Nunggak: " + (summary.arrears_students_count || 0)
    ).setFontWeight("bold").setFontSize(8).setHorizontalAlignment("center").setBackground("#F1F5F9");

    // Total Tunggakan formula
    var arrearsColLetter = getColumnLetter(arrearsColIndex);
    sheet.getRange(totalRowIndex, arrearsColIndex)
      .setFormula("=SUM(" + arrearsColLetter + startRow + ":" + arrearsColLetter + (totalRowIndex - 1) + ")")
      .setFontWeight("bold").setHorizontalAlignment("right").setNumberFormat("Rp #,##0").setBackground("#F1F5F9");

    // Catatan
    sheet.getRange(totalRowIndex, arrearsColIndex + 1).setValue("")
      .setBackground("#F1F5F9");

    sheet.getRange(totalRowIndex, 1, 1, totalCols).setBorder(true, true, true, true, null, null, "#94A3B8", SpreadsheetApp.BorderStyle.DOUBLE);
    sheet.setRowHeight(totalRowIndex, 26);
  }

  // Freeze Headers & Student identification columns
  sheet.setFrozenRows(5);
  sheet.setFrozenColumns(3);

  // Column Widths
  sheet.setColumnWidth(1, 45);  // No Absen
  sheet.setColumnWidth(2, 175); // Nama Siswa
  sheet.setColumnWidth(3, 90);  // Panggilan

  for (var wIdx = 0; wIdx < totalWeeks; wIdx++) {
    sheet.setColumnWidth(4 + wIdx, 80); // Tiap Minggu
  }

  sheet.setColumnWidth(3 + totalWeeks + 1, 135); // Status
  sheet.setColumnWidth(3 + totalWeeks + 2, 115); // Total Tunggakan
  sheet.setColumnWidth(3 + totalWeeks + 3, 200); // Minggu Belum Bayar
}

/**
 * ----------------------------------------------------------------------------
 * HELPER FUNCTIONS
 * ----------------------------------------------------------------------------
 */

function formatRupiahSimple(num) {
  if (num === null || num === undefined) return "Rp 0";
  return "Rp " + Number(num).toLocaleString("id-ID");
}

function getColumnLetter(columnNumber) {
  var temp = 0;
  var letter = "";
  while (columnNumber > 0) {
    temp = (columnNumber - 1) % 26;
    letter = String.fromCharCode(temp + 65) + letter;
    columnNumber = (columnNumber - temp - 1) / 26;
  }
  return letter;
}

function errorResponse(message, statusCode) {
  var output = {
    success: false,
    error: message,
    code: statusCode,
    timestamp: new Date().toISOString()
  };

  return ContentService.createTextOutput(JSON.stringify(output, null, 2))
    .setMimeType(ContentService.MimeType.JSON);
}
