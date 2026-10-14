<?php

namespace App\Console\Commands;

use App\Services\KasSpreadsheetService;
use Illuminate\Console\Command;

class SyncKasSpreadsheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kas:sync-spreadsheet {--url= : Override Webhook URL} {--dry-run : Print payload preview without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize class treasury data (balance, expenses, and student payment matrix) to Google Spreadsheet';

    /**
     * Execute the console command.
     */
    public function handle(KasSpreadsheetService $service): int
    {
        $this->info('Mengambil data kas kelas...');

        $payload = $service->buildPayload();
        $summary = $payload['summary'];

        $this->table(
            ['Metrik Kas', 'Nilai'],
            [
                ['Saldo Kas Saat Ini', 'Rp ' . number_format($summary['balance'], 0, ',', '.')],
                ['Total Pemasukan', 'Rp ' . number_format($summary['total_income'], 0, ',', '.') . ' (Cash: ' . number_format($summary['total_cash_income'], 0, ',', '.') . ' | QRIS: ' . number_format($summary['total_qris_income'], 0, ',', '.') . ')'],
                ['Total Pengeluaran', 'Rp ' . number_format($summary['total_expense'], 0, ',', '.')],
                ['Total Siswa Aktif', $summary['total_students']],
                ['Siswa Lunas', $summary['all_paid_students_count']],
                ['Siswa Nunggak', $summary['arrears_students_count'] . ' (Double: ' . $summary['double_arrears_count'] . ')'],
                ['Total Tunggakan', 'Rp ' . number_format($summary['total_arrears_amount'], 0, ',', '.')],
                ['Total Catatan Pengeluaran', count($payload['expenses'])],
                ['Total Minggu/Iuran', count($payload['weeks'])],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Mode --dry-run aktif. Data tidak dikirimkan ke Google Spreadsheet.');
            return Command::SUCCESS;
        }

        $url = $this->option('url') ?: config('services.kas_spreadsheet.webhook_url');

        if (empty($url)) {
            $this->error('Google Spreadsheet Webhook URL belum disetel! Harap isi KAS_SPREADSHEET_WEBHOOK_URL di file .env');
            return Command::FAILURE;
        }

        $this->info("Mengirim data ke Google Spreadsheet Webhook: {$url}...");

        $result = $service->sync($url);

        if ($result['success'] ?? false) {
            $this->info('✓ ' . ($result['message'] ?? 'Sinkronisasi berhasil!'));
            return Command::SUCCESS;
        }

        $this->error('✗ ' . ($result['message'] ?? 'Sinkronisasi gagal!'));
        if (!empty($result['error_detail'])) {
            $this->line(json_encode($result['error_detail'], JSON_PRETTY_PRINT));
        }

        return Command::FAILURE;
    }
}
