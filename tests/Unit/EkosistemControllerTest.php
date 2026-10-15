<?php

namespace Tests\Unit;

use App\Http\Controllers\EkosistemController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EkosistemControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-10-15 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reads_ecosystem_sheets_and_summarises_area(): void
    {
        $this->fakeSheets();
        $this->actingAs(new User(['pn' => 'test-ekosistem-area', 'name' => 'Area']));

        $data = (new EkosistemController)->index(Request::create('/ekosistem'))->getData();

        $this->assertNull($data['error']);
        $this->assertFalse($data['isLocked']);
        $this->assertSame('all', $data['selectedKc']);
        $this->assertSame(2, $data['summary']['ekosistem']);
        $this->assertSame(3, $data['summary']['prospek']);
        $this->assertSame(2, $data['summary']['berjalan']);
        $this->assertSame(1, $data['summary']['terlambat']);
        $this->assertSame(0, $data['summary']['cek_satuan']);
        $this->assertEqualsWithDelta(22000.0, $data['summary']['potensi_juta'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $data['summary']['realisasi_juta'], 0.001);

        $p001 = collect($data['prospects'])->firstWhere('ID_Prospek', 'P001');
        $this->assertSame('2026-10-22', $p001['Target_Selesai']);
        $this->assertSame('Akuisisi', $p001['Arah']);
        $this->assertFalse($p001['Terlambat']);

        $p002 = collect($data['prospects'])->firstWhere('ID_Prospek', 'P002');
        $this->assertSame('2026-10-01', $p002['Target_Selesai']);
        $this->assertTrue($p002['Terlambat']);

        $rsud = collect($data['ecosystems'])->firstWhere('ID_Ekosistem', 'RSUD');
        $this->assertSame(2, $rsud['total']);
        $this->assertSame(100, $rsud['progress']);
        $this->assertSame(['L1' => 2, 'L2' => 0, 'L3' => 0], $rsud['ring']);

        $this->assertSame('P002', $data['activity'][0]['ID_Prospek']);
    }

    public function test_branch_scoped_user_is_locked_to_own_kc(): void
    {
        $this->fakeSheets();
        $this->actingAs(new User(['pn' => '0049', 'name' => 'KC Magetan']));

        $data = (new EkosistemController)->index(Request::create('/ekosistem', 'GET', ['kc' => 'madiun']))->getData();

        $this->assertTrue($data['isLocked']);
        $this->assertSame('magetan', $data['selectedKc']);
        $this->assertSame(['LANUD'], array_column($data['ecosystems'], 'ID_Ekosistem'));
        $this->assertSame(['P003'], array_column($data['prospects'], 'ID_Prospek'));
        $this->assertSame([], $data['activity']);
    }

    public function test_nominal_entered_in_rupiah_is_flagged_and_excluded_from_totals(): void
    {
        $this->fakeSheets(realisasiP003: '1,250,000,000');
        $this->actingAs(new User(['pn' => 'test-ekosistem-area', 'name' => 'Area']));

        $data = (new EkosistemController)->index(Request::create('/ekosistem'))->getData();

        $p003 = collect($data['prospects'])->firstWhere('ID_Prospek', 'P003');
        $this->assertNull($p003['Realisasi_Nominal_Juta']);
        $this->assertSame([['label' => 'Realisasi', 'value' => 1_250_000_000.0]], $p003['Cek_Satuan']);
        $this->assertSame(1, $data['summary']['cek_satuan']);
        $this->assertEqualsWithDelta(1000.0, $data['summary']['realisasi_juta'], 0.001);
    }

    public function test_failed_fetch_shows_error_and_is_not_cached(): void
    {
        Http::fake(fn () => Http::response('<html>login</html>', 200));
        $this->actingAs(new User(['pn' => 'test-ekosistem-area', 'name' => 'Area']));

        $data = (new EkosistemController)->index(Request::create('/ekosistem'))->getData();

        $this->assertNotNull($data['error']);
        $this->assertSame([], $data['prospects']);
        $this->assertFalse(Cache::has('ekosistem:source:v1:1bssXQGe2PVUQYuNQVfiXuo5TDLuidO6TeU1cYR9iHZI'));
    }

    public function test_view_renders_with_menu_route(): void
    {
        $this->fakeSheets();
        $this->actingAs(new User(['pn' => 'test-ekosistem-area', 'name' => 'Area']));

        $html = (new EkosistemController)->index(Request::create('/ekosistem'))->render();

        $this->assertStringContainsString('Peta Prospek Ekosistem', $html);
        $this->assertStringContainsString('RSUD Caruban &amp; RSUD Dolopo', $html);
        $this->assertTrue(str_contains($html, 'Rp22,0 M'), 'Ringkasan simpanan harus menjumlahkan kedua prospek, bukan menampilkan satu baris detail.');
        $this->assertStringContainsString('Lewat target', $html);
        $this->assertSame(url('/ekosistem'), route('ekosistem.index'));
    }

    private function fakeSheets(string $realisasiP003 = ''): void
    {
        $sheets = [
            'Ekosistem' => implode("\n", [
                '"ID_Ekosistem","Nama_Ekosistem","KC","Jenis","Lokasi","Posisi_BRI","Bank_Pesaing","Fakta_Kunci","Sumber"',
                '"RSUD","RSUD Caruban & RSUD Dolopo","Madiun","Rumah sakit daerah","Mejayan","Giro 8 M","Bank Jatim","1.221 karyawan","File RM"',
                '"LANUD","Lanud Iswahjudi","Magetan","Pangkalan TNI AU","Maospati","CASA 20 M","BNI","Wing Udara 3","File RM"',
            ]),
            'Prospek' => implode("\n", [
                '"ID_Prospek","ID_Ekosistem","Ekosistem","KC","Jalur_RM","Prospek","Produk","Jenis_Potensi","Est_Min_Juta","Est_Max_Juta","Est_Nasabah","Nama_PIC","Tgl_Mulai","Target_Selesai","Tahap","Status","Realisasi_Nominal_Juta","Realisasi_Nasabah","Kendala","Catatan","Ring","Simpul","Status_BRI","Bank_Lain"',
                '"P001","RSUD","RSUD Caruban & RSUD Dolopo","Madiun","RMFT","Pindahkan CASA RSUD Caruban","Giro","Simpanan","15,000","15,000","1","Fitri","2026-10-08","2026-10-22","Pendataan","Berjalan","1,000","","","","L1","RSUD Caruban","Belum BRI","Bank Jatim"',
                '"P002","RSUD","RSUD Caruban & RSUD Dolopo","Madiun","RMFT","Pindahkan CASA RSUD Dolopo","Giro","Simpanan","7,000","7,000","1","Fitri","2026-09-01","10/1/2026 17:00:00","Penawaran","Berjalan","","","","","L1","RSUD Dolopo","Sudah BRI",""',
                '"P003","LANUD","Lanud Iswahjudi","Magetan","RM Konsumer","Briguna personel","Briguna","Kredit","","","100","","","","Pendataan","","'.$realisasiP003.'","","","","L1","Personel","Belum diketahui",""',
                '"","","","","","","","","","","","","","","","","","","","","","","",""',
            ]),
            'Log' => implode("\n", [
                '"Waktu","Pengguna","ID_Prospek","Kolom","Nilai_Lama","Nilai_Baru"',
                '"10/9/2026","Pengguna","P001","Status","Belum mulai","Berjalan"',
                '"10/10/2026","Pengguna","P002","Tahap","Pendataan","Penawaran"',
            ]),
        ];

        Http::fake(function ($request) use ($sheets) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $sheet = $query['sheet'] ?? '';

            return isset($sheets[$sheet]) ? Http::response($sheets[$sheet], 200) : Http::response('', 404);
        });
    }
}
