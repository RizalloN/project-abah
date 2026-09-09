<?php

namespace App\Support;

use App\Services\Reports\RunOffReportService;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LandingMicroPerformanceService
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';

    private const PERFORMANCE_RM_SNAPSHOT_TABLE = 'performance_rm_snapshots';

    private const CACHE_VERSION = 'v28-micro-cache-coherent-brihc-productivity';

    private const PERIOD_LOOKUP_INDEXES = [
        'idx_snapshot_filter_optimized',
        'idx_dld_nonptp_monthly_lookup',
        'idx_loan_periode_segmen',
        'idx_loan_periode_rek',
    ];

    private const PAYLOAD_CACHE_HOURS = 6;

    private const STABLE_CACHE_DAYS = 2;

    private const BUILD_LOCK_SECONDS = 180;

    /** @var array<string, array<int, string>> */
    private array $columnListingMemo = [];

    /** @var array<string, bool> */
    private array $tableExistsMemo = [];

    public function __construct(private readonly ReportIndexHintResolver $indexHintResolver) {}

    private const AREA_BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];

    /** @var array<string, array{name:string, branch:string}> */
    private const AREA_MBM_REFERENCE = [
        '24600' => ['name' => 'Trimo Agung Yunianto', 'branch' => 'KC MADIUN'],
        '20458' => ['name' => 'Nur Elfiana', 'branch' => 'KC MADIUN'],
        '64850' => ['name' => 'Hendry Nurwahyudi', 'branch' => 'KC MADIUN'],
        '22008' => ['name' => 'Rudhi Nur Subijanto', 'branch' => 'KC MAGETAN'],
        '22263' => ['name' => 'Muko Hendrasworo', 'branch' => 'KC MAGETAN'],
        '61165' => ['name' => 'Rita Awaliasari', 'branch' => 'KC MAGETAN'],
        '22271' => ['name' => 'Dian Febriantari', 'branch' => 'KC NGAWI'],
        '22461' => ['name' => 'Tri Handayani', 'branch' => 'KC NGAWI'],
        '22666' => ['name' => 'Soni Sanjaya', 'branch' => 'KC NGAWI'],
        '21668' => ['name' => 'Iwan Wahyudi', 'branch' => 'KC PONOROGO'],
        '20496' => ['name' => 'Indra Hananto', 'branch' => 'KC PONOROGO'],
        '23379' => ['name' => 'Kun Harianto', 'branch' => 'KC PONOROGO'],
    ];

    /**
     * Referensi limit Stop & Go PDWK dari workbook
     * "PDWK MBM & KEPALA UNIT_2026 07 31.xlsx" tab Nominatif.
     *
     * @var array<string, array{role:string, limit:int, name:string, unit:string, branch:string}>
     */
    private const PDWK_LIMIT_REFERENCE = [
        '20496' => ['role' => 'mbm', 'limit' => 40, 'name' => 'Indra Hananto', 'unit' => 'KC Ponorogo', 'branch' => 'KC Ponorogo'],
        '22263' => ['role' => 'mbm', 'limit' => 40, 'name' => 'Muko Hendrasworo', 'unit' => 'KC Magetan', 'branch' => 'KC Magetan'],
        '22271' => ['role' => 'mbm', 'limit' => 40, 'name' => 'Dian Febriantari', 'unit' => 'KC Ngawi', 'branch' => 'KC Ngawi'],
        '24600' => ['role' => 'mbm', 'limit' => 40, 'name' => 'Trimo Agung Yunianto', 'unit' => 'KC Madiun', 'branch' => 'KC Madiun'],
        '20458' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Nur Elfiana', 'unit' => 'KC Madiun', 'branch' => 'KC Madiun'],
        '21668' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Iwan Wahyudi', 'unit' => 'KC Ponorogo', 'branch' => 'KC Ponorogo'],
        '22008' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Rudhi Nur Subijanto', 'unit' => 'KC Magetan', 'branch' => 'KC Magetan'],
        '22461' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Tri Handayani', 'unit' => 'KC Ngawi', 'branch' => 'KC Ngawi'],
        '22666' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Soni Sanjaya', 'unit' => 'KC Ngawi', 'branch' => 'KC Ngawi'],
        '64850' => ['role' => 'mbm', 'limit' => 75, 'name' => 'Hendri Windianarko', 'unit' => 'KC Madiun', 'branch' => 'KC Madiun'],
        '22781' => ['role' => 'mbm', 'limit' => 100, 'name' => 'Suprijono Edi Widodo', 'unit' => 'KC Magetan', 'branch' => 'KC Magetan'],
        '23379' => ['role' => 'mbm', 'limit' => 100, 'name' => 'Kun Harianto', 'unit' => 'KC Ponorogo', 'branch' => 'KC Ponorogo'],
        '55365' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sutini', 'unit' => 'UNIT SUKOREJO PONOROGO', 'branch' => 'KC Ponorogo'],
        '25142' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Hari Basuki', 'unit' => 'UNIT SLAHUNG PONOROGO', 'branch' => 'KC Ponorogo'],
        '235757' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Kristian Eko Laksono', 'unit' => 'UNIT SAWOO PONOROGO', 'branch' => 'KC Ponorogo'],
        '56279' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Siti Zuhrotus Sholikhah', 'unit' => 'UNIT SAMPUNG PONOROGO', 'branch' => 'KC Ponorogo'],
        '159081' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Gesang Saifullah Rachman', 'unit' => 'UNIT PASAR NGUMPUL PONOROGO', 'branch' => 'KC Ponorogo'],
        '206932' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Riyan Mey Abadi', 'unit' => 'UNIT PASAR CONDONG PONOROGO', 'branch' => 'KC Ponorogo'],
        '23211' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Adin Darmawan', 'unit' => 'UNIT NAILAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '117642' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ika Kumala Pramitasari', 'unit' => 'UNIT MLARAK PONOROGO', 'branch' => 'KC Ponorogo'],
        '159891' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Anton Dwi Susilo', 'unit' => 'UNIT KOTA III PONOROGO', 'branch' => 'KC Ponorogo'],
        '207710' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Shandy Firdausy', 'unit' => 'UNIT KOTA II PONOROGO', 'branch' => 'KC Ponorogo'],
        '57092' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Catur Ribut Hendriawan', 'unit' => 'UNIT KOTA I PONOROGO', 'branch' => 'KC Ponorogo'],
        '58001' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Fauzia Widya Nur Darmanto', 'unit' => 'UNIT KESUGIHAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '225105' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Wisnu Wardana', 'unit' => 'UNIT JETIS PONOROGO', 'branch' => 'KC Ponorogo'],
        '117679' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Firdaus Amin Zulkarnain', 'unit' => 'UNIT JENANGAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '56274' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Farida Nur Laily', 'unit' => 'UNIT JAMBON PONOROGO', 'branch' => 'KC Ponorogo'],
        '172498' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Aprilia Dwi Jayanti', 'unit' => 'UNIT DENGOK PONOROGO', 'branch' => 'KC Ponorogo'],
        '51635' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Dian Arief Rachman', 'unit' => 'UNIT BUNGKAL PONOROGO', 'branch' => 'KC Ponorogo'],
        '55613' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Fatih Muzaqqi', 'unit' => 'UNIT BRAHU PONOROGO', 'branch' => 'KC Ponorogo'],
        '57089' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Arini Endah Wahyuningsih', 'unit' => 'UNIT BABADAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '61549' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Donny Bagus Trianto', 'unit' => 'UNIT WALIKUKUN NGAWI', 'branch' => 'KC Ngawi'],
        '53167' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Raditya Prima S', 'unit' => 'UNIT TEGUHAN NGAWI', 'branch' => 'KC Ngawi'],
        '117678' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Fajar Kharisma', 'unit' => 'UNIT TAMBAKROMO NGAWI', 'branch' => 'KC Ngawi'],
        '53170' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Doni Setiawan', 'unit' => 'UNIT SOOKO NGAWI', 'branch' => 'KC Ngawi'],
        '130004' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Evik Dwi Purnanto', 'unit' => 'UNIT SINE NGAWI', 'branch' => 'KC Ngawi'],
        '157241' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Arifta Widyatama', 'unit' => 'UNIT SIDOLAJU NGAWI', 'branch' => 'KC Ngawi'],
        '22962' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Esti Setiyawijayanti', 'unit' => 'UNIT SAMBIREJO NGAWI', 'branch' => 'KC Ngawi'],
        '53191' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ratna Kumalawati', 'unit' => 'UNIT RONGGOWARSITO NGAWI', 'branch' => 'KC Ngawi'],
        '53160' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Nurcahyono Setia Budi', 'unit' => 'UNIT POJOK NGAWI', 'branch' => 'KC Ngawi'],
        '229799' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Joko Sutrisno', 'unit' => 'UNIT PB SUDIRMAN NGAWI', 'branch' => 'KC Ngawi'],
        '65512' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Nur Effendi', 'unit' => 'UNIT PASAR BESAR NGAWI', 'branch' => 'KC Ngawi'],
        '22828' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Eko Putranto', 'unit' => 'UNIT PARON NGAWI', 'branch' => 'KC Ngawi'],
        '56685' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Adiarto Priyo Wicaksono', 'unit' => 'UNIT PANGKUR NGAWI', 'branch' => 'KC Ngawi'],
        '22843' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sri Utami', 'unit' => 'UNIT PADAS NGAWI', 'branch' => 'KC Ngawi'],
        '172501' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Roni Savrori', 'unit' => 'UNIT NGRAMBE NGAWI', 'branch' => 'KC Ngawi'],
        '57415' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Luthfi Ardhani', 'unit' => 'UNIT MANTINGAN NGAWI', 'branch' => 'KC Ngawi'],
        '22960' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Tri Wahyu Artati', 'unit' => 'UNIT KWADUNGAN NGAWI', 'branch' => 'KC Ngawi'],
        '164999' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Metti Yuana', 'unit' => 'UNIT KERASWETAN NGAWI', 'branch' => 'KC Ngawi'],
        '199575' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Bayu Sulistyo Pamungkas Sunoto', 'unit' => 'UNIT KENDAL NGAWI', 'branch' => 'KC Ngawi'],
        '52433' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Albertus Novianto Sulistiawan', 'unit' => 'UNIT KEDUNGPRAHU NGAWI', 'branch' => 'KC Ngawi'],
        '56308' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Harnaning Trimuji Hastuti', 'unit' => 'UNIT KEDUNGGALAR NGAWI', 'branch' => 'KC Ngawi'],
        '149780' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Arisma Cahya Kurniawan', 'unit' => 'UNIT KARANG JATI NGAWI', 'branch' => 'KC Ngawi'],
        '172523' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Rahendra Ragil Priyo Nugroho', 'unit' => 'UNIT JOGOROGO NGAWI', 'branch' => 'KC Ngawi'],
        '55331' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Puput Indrasari', 'unit' => 'UNIT CEPOKO NGAWI', 'branch' => 'KC Ngawi'],
        '22830' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Andik Tri Prasetyanto', 'unit' => 'UNIT AHMAD YANI NGAWI', 'branch' => 'KC Ngawi'],
        '56498' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Anthon Purbaya', 'unit' => 'UNIT TAKERAN MAGETAN', 'branch' => 'KC Magetan'],
        '22118' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sugianto', 'unit' => 'UNIT SUKOMORO MAGETAN', 'branch' => 'KC Magetan'],
        '130049' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Fitra Ardi Yunianto', 'unit' => 'UNIT SIDOREJO MAGETAN', 'branch' => 'KC Magetan'],
        '160348' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Veri Dwi Septian', 'unit' => 'UNIT SELOSARI MAGETAN', 'branch' => 'KC Magetan'],
        '64737' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Agung Margo Prayitno', 'unit' => 'UNIT REJOSARI MAGETAN', 'branch' => 'KC Magetan'],
        '22227' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Yudi Handayatno', 'unit' => 'UNIT PONCOL MAGETAN', 'branch' => 'KC Magetan'],
        '22124' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Teguh Purwanto', 'unit' => 'UNIT PLAOSAN MAGETAN', 'branch' => 'KC Magetan'],
        '156829' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Elida Pujiadi', 'unit' => 'UNIT PARANG MAGETAN', 'branch' => 'KC Magetan'],
        '52941' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Agus Pujianto', 'unit' => 'UNIT PANEKAN MAGETAN', 'branch' => 'KC Magetan'],
        '61308' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Toni Efendi', 'unit' => 'UNIT NGARIBOYO MAGETAN', 'branch' => 'KC Magetan'],
        '264773' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Alvian Eka Hardian', 'unit' => 'UNIT MT HARYONO MAGETAN', 'branch' => 'KC Magetan'],
        '173908' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Pituk Puspito Nugroho', 'unit' => 'UNIT MAOSPATI MAGETAN', 'branch' => 'KC Magetan'],
        '22201' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Joko Purwanto', 'unit' => 'UNIT LAMBEYAN MAGETAN', 'branch' => 'KC Magetan'],
        '56282' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Mohamad Roesli Effendi', 'unit' => 'UNIT KENONGOMULYO MAGETAN', 'branch' => 'KC Magetan'],
        '22185' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sudarmi', 'unit' => 'UNIT KAWEDANAN MAGETAN', 'branch' => 'KC Magetan'],
        '173898' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Dian Proboyektik Sholichin', 'unit' => 'UNIT KARTOHARJO MAGETAN', 'branch' => 'KC Magetan'],
        '22108' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Agung Gianto', 'unit' => 'UNIT KARANGSONO MAGETAN', 'branch' => 'KC Magetan'],
        '52952' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ony Nur Mahendra', 'unit' => 'UNIT KARANGREJO MAGETAN', 'branch' => 'KC Magetan'],
        '55833' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Teguh Susanto', 'unit' => 'UNIT JUNGKE MAGETAN', 'branch' => 'KC Magetan'],
        '161468' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Syarifudin Bhaktiar', 'unit' => 'UNIT BENDO MAGETAN', 'branch' => 'KC Magetan'],
        '52946' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Dewi Tritjahjani', 'unit' => 'UNIT A. YANI MAGETAN', 'branch' => 'KC Magetan'],
        '21954' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sumanto', 'unit' => 'UNIT WUNGU MADIUN', 'branch' => 'KC Madiun'],
        '24797' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Wiwik Kuswantiningsih', 'unit' => 'UNIT WONOASRI MADIUN', 'branch' => 'KC Madiun'],
        '57958' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Tri Andani Ayupi', 'unit' => 'UNIT UTERAN MADIUN', 'branch' => 'KC Madiun'],
        '21949' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Tommy Kuswantoro', 'unit' => 'UNIT SLEKO MADIUN', 'branch' => 'KC Madiun'],
        '22087' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Yuliawan', 'unit' => 'UNIT SAWAHAN MADIUN', 'branch' => 'KC Madiun'],
        '22023' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Puji Suryanto', 'unit' => 'UNIT PURWOREJO MADIUN', 'branch' => 'KC Madiun'],
        '51654' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Tri Wahyudi Budi Setiawan', 'unit' => 'UNIT PILANGKENCENG MADIUN', 'branch' => 'KC Madiun'],
        '50612' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Agus Chandra Wahyudi', 'unit' => 'UNIT PERINTIS KEMERDEKAAN MADI', 'branch' => 'KC Madiun'],
        '57431' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'I\'ing Menawati', 'unit' => 'UNIT MUNENG MADIUN', 'branch' => 'KC Madiun'],
        '52520' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Erwin Hendratmoko', 'unit' => 'UNIT MLILIR MADIUN', 'branch' => 'KC Madiun'],
        '21948' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ruswida Amiranti', 'unit' => 'UNIT MEJAYAN MADIUN', 'branch' => 'KC Madiun'],
        '55629' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Indra Gunawan Aryanto', 'unit' => 'UNIT KEBONSARI MADIUN', 'branch' => 'KC Madiun'],
        '187098' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Yudha Rofika Adi Diyanto', 'unit' => 'UNIT KARE MADIUN', 'branch' => 'KC Madiun'],
        '21955' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Dwi Karyawati', 'unit' => 'UNIT JIWAN MADIUN', 'branch' => 'KC Madiun'],
        '197162' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Bayu Adi Kusuma', 'unit' => 'UNIT GEMARANG MADIUN', 'branch' => 'KC Madiun'],
        '22081' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Hadi Kuncoro', 'unit' => 'UNIT GANTRUNG MADIUN', 'branch' => 'KC Madiun'],
        '57216' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Catur Widyastutik', 'unit' => 'UNIT DUNGUS MADIUN', 'branch' => 'KC Madiun'],
        '52501' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Danang Kurniawan', 'unit' => 'UNIT DOLOPO MADIUN', 'branch' => 'KC Madiun'],
        '61624' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ardiyanto Tri Nugroho', 'unit' => 'UNIT DIPONEGORO MADIUN', 'branch' => 'KC Madiun'],
        '224883' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Wahyu Priyo Widyastomo', 'unit' => 'UNIT DAGANGAN MADIUN', 'branch' => 'KC Madiun'],
        '52523' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Ary Legowo', 'unit' => 'UNIT CARUBAN MADIUN', 'branch' => 'KC Madiun'],
        '22076' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Dwi Gatot Prasetyo', 'unit' => 'UNIT BALEREJO MADIUN', 'branch' => 'KC Madiun'],
        '87357' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Puguh Dwi Prasetyo', 'unit' => 'UNIT BALE LINTANG MADIUN', 'branch' => 'KC Madiun'],
        '61446' => ['role' => 'ka_unit', 'limit' => 100, 'name' => 'Sigit Susanto', 'unit' => 'UNIT ALOON - ALOON MADIUN', 'branch' => 'KC Madiun'],
        '52987' => ['role' => 'ka_unit', 'limit' => 40, 'name' => 'Widhodho Haribowo', 'unit' => 'UNIT SAMBIT PONOROGO', 'branch' => 'KC Ponorogo'],
        '154634' => ['role' => 'ka_unit', 'limit' => 40, 'name' => 'Syaiful Abidin', 'unit' => 'UNIT BADEGAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '154633' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Nanda Satria Bhakti', 'unit' => 'UNIT SOOKO PONOROGO', 'branch' => 'KC Ponorogo'],
        '57094' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Aditya Wisnu Wardana', 'unit' => 'UNIT PULUNG PONOROGO', 'branch' => 'KC Ponorogo'],
        '167228' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Risma Lestinasari', 'unit' => 'UNIT PASAR PON PONOROGO', 'branch' => 'KC Ponorogo'],
        '56277' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Muhammad Zaifar Rahman', 'unit' => 'UNIT NGRAYUN PONOROGO', 'branch' => 'KC Ponorogo'],
        '119095' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Pratristo Teguh Yuniar', 'unit' => 'UNIT KAUMAN PONOROGO', 'branch' => 'KC Ponorogo'],
        '209521' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Dimas Kristianto', 'unit' => 'UNIT BALONG PONOROGO', 'branch' => 'KC Ponorogo'],
        '57952' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Bisri Efendi', 'unit' => 'UNIT KARANGMOJO MAGETAN', 'branch' => 'KC Magetan'],
        '224262' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Satriyo Nugroho', 'unit' => 'UNIT ISWAHYUDI MAGETAN', 'branch' => 'KC Magetan'],
        '64262' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Sunandar Eko Kriswiyanto', 'unit' => 'UNIT SARADAN MADIUN', 'branch' => 'KC Madiun'],
        '199564' => ['role' => 'ka_unit', 'limit' => 75, 'name' => 'Hana Binti Muyasaroh', 'unit' => 'UNIT NGLAMES MADIUN', 'branch' => 'KC Madiun'],
    ];

    private const PDWK_ROLE_OVERRIDES = [
        '66855' => 'BOH',
        '57671' => 'BOH',
        '58377' => 'BOH',
        '69909' => 'BOH',
        '76935' => 'SBOH',
        '131794' => 'SBOH',
        '141028' => 'SBOH',
        '23711' => 'SBOH',
    ];

    /**
     * Kunci PDWK per rekening untuk kasus penugasan/otorisasi khusus yang sudah
     * dikonfirmasi. Mapping ini sengaja dievaluasi sebelum PN dan BRIHC, sehingga
     * import Daily Loan Dinamis berikutnya tidak dapat mengubah klasifikasinya.
     *
     * @var array<string, array{role: string, note: string, is_override: bool}>
     */
    private const PDWK_ACCOUNT_OVERRIDES = [
        '634101020247101' => ['role' => 'BOH', 'note' => 'BOH override — PGS Pinca Madiun (standar MBM).', 'is_override' => true],
        '388701046142106' => ['role' => 'BOH', 'note' => 'BOH override — PGS Pinca Madiun (standar MBM).', 'is_override' => true],
        '388701046143102' => ['role' => 'BOH', 'note' => 'BOH override — PGS Pinca Madiun (standar MBM).', 'is_override' => true],
        '635201038330107' => ['role' => 'BOH', 'note' => 'BOH — diputus PGS Pinca Madiun.', 'is_override' => false],
        '55201008397109' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
        '55201008400106' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
        '55201008410101' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
        '55201008414105' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
        '210901000316104' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
        '388501022076105' => ['role' => 'KA UNIT', 'note' => 'KA Unit override — diputus PGS Pinca Madiun.', 'is_override' => true],
        '364101032802102' => ['role' => 'BOH', 'note' => 'BOH override — Tito RSBH (standar MBM).', 'is_override' => true],
        '375801028495108' => ['role' => 'BOH', 'note' => 'BOH override — Tito RSBH (standar MBM).', 'is_override' => true],
        '220401000458108' => ['role' => 'SBOH', 'note' => 'SBOH — klasifikasi dikonfirmasi.', 'is_override' => false],
    ];

    private const NATIONAL_HOLIDAYS = [
        2025 => [
            '2025-01-01', '2025-01-27', '2025-01-29', '2025-03-29', '2025-03-31',
            '2025-04-01', '2025-04-18', '2025-04-20', '2025-05-01', '2025-05-12',
            '2025-05-29', '2025-06-01', '2025-06-06', '2025-06-27', '2025-08-17',
            '2025-09-05', '2025-12-25',
        ],
        2026 => [
            '2026-01-01', '2026-01-16', '2026-02-17', '2026-03-19', '2026-03-21',
            '2026-03-22', '2026-04-03', '2026-04-05', '2026-05-01', '2026-05-14',
            '2026-05-27', '2026-05-31', '2026-06-01', '2026-06-16', '2026-08-17',
            '2026-08-25', '2026-12-25',
        ],
    ];

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function payload(?string $requestedPeriod, ?array $branchScope = null, bool $forceRefresh = false): array
    {
        $empty = $this->emptyPayload($branchScope);
        if (! $this->hasTable(self::SOURCE_TABLE)) {
            return $empty;
        }

        try {
            $period = $this->resolvePeriod($requestedPeriod, $branchScope);
            if ($period === null) {
                return $empty;
            }

            $previousPeriod = $this->resolvePeriodBefore(
                Carbon::parse($period)->startOfMonth()->toDateString(),
                $branchScope
            );
            $ytdPeriod = $this->resolvePeriodBefore(
                Carbon::parse($period)->startOfYear()->toDateString(),
                $branchScope
            );
            $dataVersion = implode('.', [
                ReportCacheVersion::get('pinjaman'),
                ReportCacheVersion::get('harian'),
            ]);
            $cacheKey = implode(':', [
                'landing',
                'micro-performance',
                self::CACHE_VERSION,
                $dataVersion,
                $period,
                $previousPeriod ?? 'none',
                $ytdPeriod ?? 'none',
                $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
            ]);
            $stableCacheKey = implode(':', [
                'landing',
                'micro-performance',
                self::CACHE_VERSION,
                'stable',
                $dataVersion,
                $period,
                $previousPeriod ?? 'none',
                $ytdPeriod ?? 'none',
                $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
            ]);

            if ($forceRefresh) {
                Cache::forget($cacheKey);
                Cache::forget($stableCacheKey);
            }

            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            $stable = Cache::get($stableCacheKey);
            if (! $forceRefresh && is_array($stable)) {
                Cache::put($cacheKey, $stable, now()->addSeconds(30));
                $this->deferPayloadRefresh(
                    $cacheKey,
                    $stableCacheKey,
                    $period,
                    $previousPeriod,
                    $ytdPeriod,
                    $branchScope
                );

                return $stable;
            }

            return $this->buildAndCachePayload(
                $cacheKey,
                $stableCacheKey,
                $period,
                $previousPeriod,
                $ytdPeriod,
                $branchScope
            );
        } catch (Throwable $exception) {
            Log::warning('Landing Mikro gagal dihitung.', [
                'period' => $requestedPeriod,
                'scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                'error' => $exception->getMessage(),
            ]);

            $empty['meta']['error'] = $exception->getMessage();

            return $empty;
        }
    }

    /**
     * Ringkasan putusan ringan untuk landing page. Resolver dan deduplikasi
     * sengaja memakai jalur yang sama dengan trigger Mikro agar PGS, BRIHC,
     * dan override rekening tetap menghasilkan klasifikasi yang identik.
     *
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function decisionSummary(
        ?string $requestedPeriod,
        ?array $branchScope = null,
        bool $forceRefresh = false
    ): array {
        $period = $this->resolvePeriod($requestedPeriod, $branchScope);
        if ($period === null) {
            return [
                'available' => false,
                'period' => null,
                'period_label' => '-',
                'total' => ['deb' => 0, 'amount' => 0.0],
                'decisions' => [],
            ];
        }

        $cacheKey = implode(':', [
            'landing',
            'micro-decision-summary',
            self::CACHE_VERSION,
            ReportCacheVersion::composite(['pinjaman']),
            $period,
            $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
        ]);
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHours(self::PAYLOAD_CACHE_HOURS), function () use ($period, $branchScope): array {
            $realization = $this->aggregatePlafondRealization(
                $this->fetchPlafondRealizationRows($period, $branchScope),
                $branchScope
            );

            return [
                'available' => (bool) data_get($realization, 'available', false),
                'period' => $period,
                'period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
                'scope_label' => $branchScope['label'] ?? 'Area 6',
                'total' => (array) data_get($realization, 'total', []),
                'decisions' => (array) data_get($realization, 'decisions', []),
                'source' => 'Daily Loan Dinamis - resolver PDWK Mikro',
            ];
        });
    }

    /** @param array<string, mixed>|null $branchScope */
    private function buildAndCachePayload(
        string $cacheKey,
        string $stableCacheKey,
        string $period,
        ?string $previousPeriod,
        ?string $ytdPeriod,
        ?array $branchScope
    ): array {
        $lock = Cache::lock($cacheKey.':build-lock', self::BUILD_LOCK_SECONDS);

        try {
            return $lock->block(60, function () use (
                $cacheKey,
                $stableCacheKey,
                $period,
                $previousPeriod,
                $ytdPeriod,
                $branchScope
            ): array {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $payload = $this->buildPayload($period, $previousPeriod, $ytdPeriod, $branchScope);
                Cache::put($cacheKey, $payload, now()->addHours(self::PAYLOAD_CACHE_HOURS));
                Cache::put($stableCacheKey, $payload, now()->addDays(self::STABLE_CACHE_DAYS));

                return $payload;
            });
        } catch (LockTimeoutException) {
            $stable = Cache::get($stableCacheKey);

            return is_array($stable) ? $stable : $this->emptyPayload($branchScope);
        }
    }

    /** @param array<string, mixed>|null $branchScope */
    private function deferPayloadRefresh(
        string $cacheKey,
        string $stableCacheKey,
        string $period,
        ?string $previousPeriod,
        ?string $ytdPeriod,
        ?array $branchScope
    ): void {
        $pendingKey = $cacheKey.':refresh-pending';
        if (! Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(5))) {
            return;
        }

        defer(function () use (
            $cacheKey,
            $stableCacheKey,
            $period,
            $previousPeriod,
            $ytdPeriod,
            $branchScope,
            $pendingKey
        ): void {
            $lock = Cache::lock($cacheKey.':build-lock', self::BUILD_LOCK_SECONDS);
            $locked = false;

            try {
                $locked = $lock->get();
                if (! $locked) {
                    return;
                }

                $payload = $this->buildPayload($period, $previousPeriod, $ytdPeriod, $branchScope);
                Cache::put($cacheKey, $payload, now()->addHours(self::PAYLOAD_CACHE_HOURS));
                Cache::put($stableCacheKey, $payload, now()->addDays(self::STABLE_CACHE_DAYS));
            } catch (Throwable $exception) {
                Log::warning('Refresh cache landing Mikro gagal.', [
                    'period' => $period,
                    'scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                if ($locked) {
                    $lock->release();
                }
                Cache::forget($pendingKey);
            }
        }, 'landing-micro-performance:'.md5($cacheKey), true);
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    private function buildPayload(string $period, ?string $previousPeriod, ?string $ytdPeriod, ?array $branchScope): array
    {
        $plafondRows = $this->fetchPlafondRealizationRows($period, $branchScope);
        $netRows = $previousPeriod === null
            ? []
            : $this->fetchNetRealizationRows($period, $previousPeriod, $branchScope, $plafondRows);
        $roster = $this->mantriRoster($branchScope, $period);
        $realization = $this->decorateRealizationWithMantri(
            $this->aggregatePlafondRealization($plafondRows, $branchScope),
            $plafondRows,
            $roster
        );
        $netDisbursement = $this->aggregateNetRealization($netRows, $branchScope);
        $burden = $this->buildBurdenPayload($period, $previousPeriod, $ytdPeriod, $branchScope);
        $mantriPerformance = $this->buildMantriPerformance(
            $period,
            $plafondRows,
            $netRows,
            $branchScope,
            $roster
        );
        $unproductiveMantri = $this->buildUnproductiveMantri($period, $branchScope, $roster);

        return [
            'meta' => [
                'available' => true,
                'period' => $period,
                'period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
                'previous_period' => $previousPeriod,
                'previous_period_label' => $previousPeriod ? Carbon::parse($previousPeriod)->translatedFormat('d M Y') : '-',
                'ytd_period' => $ytdPeriod,
                'ytd_period_label' => $ytdPeriod ? Carbon::parse($ytdPeriod)->translatedFormat('d M Y') : '-',
                'scope' => $branchScope === null ? UserBranchScope::AREA_SCOPE : 'branch',
                'scope_label' => $branchScope['label'] ?? 'Area 6',
                'is_area' => $branchScope === null,
                'source' => 'Daily Loan Dinamis',
                'generated_at' => now()->toDateTimeString(),
                'error' => '',
            ],
            'products_position' => $this->productPositions($period, $branchScope),
            'realization' => $realization,
            'net_disbursement' => $netDisbursement,
            'pdwk_limits' => $this->buildPdwkLimitSummary($plafondRows, $branchScope),
            'decision_ranking' => $this->buildMbmDecisionRanking($plafondRows, $netRows),
            'realization_need' => $this->buildRealizationNeed($period, $branchScope, $mantriPerformance),
            'ph' => $this->buildMicroPhSummary($period, $branchScope),
            'mantri_performance' => $mantriPerformance,
            'burden' => $burden,
            'unproductive_mantri' => $unproductiveMantri,
            'billing' => $this->billingSchedule($branchScope, $period, $previousPeriod),
        ];
    }

    /**
     * @param  array<int, object|array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function aggregatePlafondRealization(array $rows, ?array $branchScope = null): array
    {
        $normalizedRows = array_map(static function (object|array $sourceRow): object {
            $row = (array) $sourceRow;
            $amount = max(0.0, (float) ($row['amount'] ?? $row['plafon'] ?? 0.0));

            return (object) array_merge($row, [
                'current_os' => max(0.0, (float) ($row['current_os'] ?? $amount)),
                'previous_os' => 0.0,
                'net_amount' => $amount,
                'realization_type' => 'baru',
            ]);
        }, $rows);

        $payload = $this->aggregateNetRealization($normalizedRows, $branchScope);
        $payload['types'] = [];

        return $payload;
    }

    /**
     * @param  array<int, object|array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function aggregateNetRealization(array $rows, ?array $branchScope = null): array
    {
        $branchTotals = [];
        $products = [];
        $patterns = [
            'musiman' => [
                'key' => 'musiman',
                'label' => 'Musiman',
                'deb' => 0,
                'amount' => 0.0,
                // Rincian tetap berbasis rekening/akad agar sejalan dengan total plafon baru.
                'details' => [
                    'satu_kali' => ['key' => 'satu_kali', 'label' => '1x Angsuran', 'deb' => 0, 'amount' => 0.0],
                    'periodik' => ['key' => 'periodik', 'label' => 'Periodik', 'deb' => 0, 'amount' => 0.0],
                    'lainnya' => ['key' => 'lainnya', 'label' => 'Lainnya', 'deb' => 0, 'amount' => 0.0],
                ],
            ],
            'non_musiman' => ['key' => 'non_musiman', 'label' => 'Non Musiman', 'deb' => 0, 'amount' => 0.0],
        ];
        $periodicFrequencies = collect(range(3, 12))
            ->mapWithKeys(fn (int $frequency): array => [$frequency => [
                'frequency' => $frequency,
                'label' => 'Freq '.$frequency,
                'customers' => [],
                'accounts' => [],
                'os' => 0.0,
            ]])
            ->all();
        $oneTimeTerms = [];
        $types = [
            'baru' => ['key' => 'baru', 'label' => 'Realisasi Baru', 'deb' => 0, 'amount' => 0.0],
            'suplesi' => ['key' => 'suplesi', 'label' => 'Suplesi', 'deb' => 0, 'amount' => 0.0],
        ];
        $decisions = collect(['boh', 'sboh', 'mbm', 'ka_unit'])
            ->mapWithKeys(fn (string $role): array => [$role => [
                'key' => $role,
                'label' => match ($role) {
                    'boh' => 'BOH',
                    'sboh' => 'SBOH',
                    'mbm' => 'MBM',
                    default => 'KA Unit',
                },
                'branches' => [],
            ]])
            ->all();

        $totalDeb = 0;
        $totalAmount = 0.0;
        foreach ($rows as $rowIndex => $sourceRow) {
            $row = (array) $sourceRow;
            $current = max(0.0, (float) ($row['current_os'] ?? 0.0));
            $previous = max(0.0, (float) ($row['previous_os'] ?? 0.0));
            $amount = array_key_exists('net_amount', $row)
                ? max(0.0, (float) $row['net_amount'])
                : max(0.0, $current - $previous);
            if ($amount <= 0.0) {
                continue;
            }

            $totalDeb++;
            $totalAmount += $amount;
            $branch = $this->branchLabel((string) ($row['branch'] ?? ($branchScope['upper_label'] ?? '')));
            $branchTotals[$branch] ??= ['deb' => 0, 'amount' => 0.0];
            $branchTotals[$branch]['deb']++;
            $branchTotals[$branch]['amount'] += $amount;

            $typeKey = in_array(($row['realization_type'] ?? null), ['baru', 'suplesi'], true)
                ? (string) $row['realization_type']
                : ($previous > 0.0 ? 'suplesi' : 'baru');
            $types[$typeKey]['deb']++;
            $types[$typeKey]['amount'] += $amount;

            $product = $this->microProductLabel((string) ($row['product'] ?? ''));
            $productKey = $this->normaliseToken($product);
            $products[$productKey] ??= ['key' => $productKey, 'label' => $product, 'deb' => 0, 'amount' => 0.0];
            $products[$productKey]['deb']++;
            $products[$productKey]['amount'] += $amount;

            $patternKey = $this->isMusiman(
                (string) ($row['payment_pattern'] ?? ''),
                (int) ($row['payment_frequency'] ?? 0)
            ) ? 'musiman' : 'non_musiman';
            $patterns[$patternKey]['deb']++;
            $patterns[$patternKey]['amount'] += $amount;
            if ($patternKey === 'musiman') {
                $paymentFrequency = (int) ($row['payment_frequency'] ?? 0);
                $detailKey = $this->musimanPaymentDetailKey(
                    (string) ($row['payment_pattern'] ?? ''),
                    $paymentFrequency
                );
                $patterns[$patternKey]['details'][$detailKey]['deb']++;
                $patterns[$patternKey]['details'][$detailKey]['amount'] += $amount;

                if ($detailKey === 'satu_kali') {
                    $term = $this->normaliseLoanTerm($row['loan_term'] ?? null);
                    $customerKey = strtoupper(trim((string) ($row['cif_key'] ?? '')));
                    $accountKey = strtoupper(trim((string) ($row['account_key'] ?? '')));
                    if ($customerKey === '') {
                        $customerKey = $accountKey !== '' ? 'REK:'.$accountKey : 'ROW:'.$rowIndex;
                    }

                    $oneTimeTerms[$term['key']] ??= [
                        ...$term,
                        'customers' => [],
                        'accounts' => [],
                        'os' => 0.0,
                    ];
                    $oneTimeTerms[$term['key']]['customers'][$customerKey] = true;
                    if ($accountKey !== '') {
                        $oneTimeTerms[$term['key']]['accounts'][$accountKey] = true;
                    }
                    $oneTimeTerms[$term['key']]['os'] += max(0.0, (float) ($row['current_os'] ?? 0.0));
                }

                if ($detailKey === 'periodik' && $paymentFrequency >= 3 && $paymentFrequency <= 12) {
                    $customerKey = strtoupper(trim((string) ($row['cif_key'] ?? '')));
                    $accountKey = strtoupper(trim((string) ($row['account_key'] ?? '')));
                    if ($customerKey === '') {
                        $customerKey = $accountKey !== '' ? 'REK:'.$accountKey : 'ROW:'.$rowIndex;
                    }

                    $periodicFrequencies[$paymentFrequency] ??= [
                        'frequency' => $paymentFrequency,
                        'label' => 'Freq '.$paymentFrequency,
                        'customers' => [],
                        'accounts' => [],
                        'os' => 0.0,
                        'term_details' => [],
                    ];
                    $periodicFrequencies[$paymentFrequency]['customers'][$customerKey] = true;
                    if ($accountKey !== '') {
                        $periodicFrequencies[$paymentFrequency]['accounts'][$accountKey] = true;
                    }
                    $periodicFrequencies[$paymentFrequency]['os'] += max(0.0, (float) ($row['current_os'] ?? 0.0));

                    // Accumulate per-term breakdown within this frequency
                    $freqTerm = $this->normaliseLoanTerm($row['loan_term'] ?? null);
                    $periodicFrequencies[$paymentFrequency]['term_details'][$freqTerm['key']] ??= [
                        ...$freqTerm,
                        'customers' => [],
                        'accounts' => [],
                        'os' => 0.0,
                    ];
                    $periodicFrequencies[$paymentFrequency]['term_details'][$freqTerm['key']]['customers'][$customerKey] = true;
                    if ($accountKey !== '') {
                        $periodicFrequencies[$paymentFrequency]['term_details'][$freqTerm['key']]['accounts'][$accountKey] = true;
                    }
                    $periodicFrequencies[$paymentFrequency]['term_details'][$freqTerm['key']]['os'] += max(0.0, (float) ($row['current_os'] ?? 0.0));
                }

            }

            $roleKey = $this->decisionRoleKey((string) ($row['decision_role'] ?? ''));
            $decisions[$roleKey]['branches'][$branch] ??= [
                'branch' => $branch,
                'deb' => 0,
                'amount' => 0.0,
                'primary_deb' => 0,
                'primary_amount' => 0.0,
                'override_deb' => 0,
                'override_amount' => 0.0,
            ];
            $decisionRow = &$decisions[$roleKey]['branches'][$branch];
            $decisionRow['deb']++;
            $decisionRow['amount'] += $amount;

            $plafon = max(0.0, (float) ($row['plafon'] ?? 0.0));
            $isOverride = array_key_exists('decision_is_override', $row)
                ? (bool) $row['decision_is_override']
                : (($roleKey === 'boh' && $plafon <= 250_000_000)
                    || ($roleKey === 'mbm' && $plafon < 100_000_000));
            if ($isOverride) {
                $decisionRow['override_deb']++;
                $decisionRow['override_amount'] += $amount;
            } else {
                $decisionRow['primary_deb']++;
                $decisionRow['primary_amount'] += $amount;
            }
            unset($decisionRow);
        }

        $branchOrder = array_flip(array_map(fn (string $branch): string => 'KC '.$branch, self::AREA_BRANCHES));
        $displayBranches = $branchScope !== null
            ? [$this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''))]
            : array_keys($branchOrder);
        foreach ($decisions as $roleKey => &$decision) {
            $decision['primary_label'] = match ($roleKey) {
                'boh' => '> 250 jt',
                'mbm' => '100 - 250 jt',
                default => 'Sesuai PDWK',
            };
            $decision['override_label'] = match ($roleKey) {
                'boh' => '<= 250 jt (Override)',
                'mbm' => '< 100 jt (Override)',
                default => 'Override',
            };
            foreach ($displayBranches as $displayBranch) {
                $decision['branches'][$displayBranch] ??= [
                    'branch' => $displayBranch,
                    'deb' => 0,
                    'amount' => 0.0,
                    'primary_deb' => 0,
                    'primary_amount' => 0.0,
                    'override_deb' => 0,
                    'override_amount' => 0.0,
                ];
            }
            $decision['branches'] = collect($decision['branches'])
                ->map(function (array $item) use ($branchTotals): array {
                    $branchAmount = (float) data_get($branchTotals, $item['branch'].'.amount', 0.0);
                    $item['share'] = $branchAmount > 0.0 ? ((float) $item['amount'] / $branchAmount) * 100 : 0.0;

                    return $item;
                })
                ->sortBy(fn (array $item): string => str_pad((string) ($branchOrder[$item['branch']] ?? 99), 2, '0', STR_PAD_LEFT).'|'.$item['branch'])
                ->values()
                ->all();
            $decision['deb'] = (int) collect($decision['branches'])->sum('deb');
            $decision['amount'] = (float) collect($decision['branches'])->sum('amount');
            $decision['share'] = $totalAmount > 0.0 ? ($decision['amount'] / $totalAmount) * 100 : 0.0;
        }
        unset($decision);

        ksort($periodicFrequencies, SORT_NUMERIC);
        $patterns['musiman']['details']['periodik']['frequency_details'] = collect($periodicFrequencies)
            ->map(static function (array $item): array {
                $termDetails = collect($item['term_details'] ?? [])
                    ->sortBy(static fn (array $t): string => str_pad((string) ($t['months'] ?? 9999), 5, '0', STR_PAD_LEFT))
                    ->map(static fn (array $t): array => [
                        'key'       => (string) $t['key'],
                        'label'     => (string) $t['label'],
                        'months'    => $t['months'],
                        'customers' => count((array) $t['customers']),
                        'deb'       => count((array) $t['accounts']),
                        'os'        => (float) $t['os'],
                    ])
                    ->filter(static fn (array $t): bool => $t['customers'] > 0)
                    ->values()
                    ->all();

                return [
                    'frequency'    => (int) $item['frequency'],
                    'label'        => (string) $item['label'],
                    'customers'    => count((array) $item['customers']),
                    'deb'          => count((array) $item['accounts']),
                    'os'           => (float) $item['os'],
                    'term_details' => $termDetails,
                ];
            })
            ->filter(static fn (array $item): bool => $item['customers'] > 0)
            ->values()
            ->all();
        $patterns['musiman']['details']['satu_kali']['term_details'] = collect($oneTimeTerms)
            ->sortBy(fn (array $item): string => str_pad((string) ($item['months'] ?? 9999), 5, '0', STR_PAD_LEFT))
            ->map(static function (array $item): array {
                return [
                    'key' => (string) $item['key'],
                    'label' => (string) $item['label'],
                    'months' => $item['months'],
                    'customers' => count((array) $item['customers']),
                    'deb' => count((array) $item['accounts']),
                    'os' => (float) $item['os'],
                ];
            })
            ->values()
            ->all();

        $decorateShare = static function (array $item) use ($totalAmount): array {
            $item['share'] = $totalAmount > 0.0 ? ((float) $item['amount'] / $totalAmount) * 100 : 0.0;

            return $item;
        };

        return [
            'available' => $totalDeb > 0,
            'total' => ['deb' => $totalDeb, 'amount' => $totalAmount],
            'types' => array_values(array_map($decorateShare, $types)),
            'products' => collect($products)->map($decorateShare)->sortByDesc('amount')->values()->all(),
            'patterns' => array_values(array_map($decorateShare, $patterns)),
            'decisions' => array_values($decisions),
        ];
    }

    /**
     * @param  array<string, mixed>  $realization
     * @param  array<int, object|array<string, mixed>>  $rows
     * @param  Collection<int, array<string, string>>  $roster
     * @return array<string, mixed>
     */
    private function decorateRealizationWithMantri(array $realization, array $rows, Collection $roster): array
    {
        $realizedByProduct = collect($rows)
            ->groupBy(fn (object|array $row): string => $this->normaliseToken($this->microProductLabel((string) data_get($row, 'product', ''))))
            ->map(fn (Collection $productRows): array => $productRows
                ->map(fn (object|array $row): string => $this->normalisePn((string) data_get($row, 'manager_pn', data_get($row, 'manager_raw', ''))))
                ->filter()
                ->unique()
                ->values()
                ->all());

        $realization['products'] = collect((array) ($realization['products'] ?? []))
            ->map(function (array $product) use ($roster, $realizedByProduct): array {
                $productKey = $this->normaliseToken((string) ($product['label'] ?? ''));
                $eligible = $productKey === 'briguna-mikro'
                    ? $roster->where('category', 'briguna')
                    : $roster->whereIn('category', ['pt', 'contract']);
                $eligiblePns = $eligible->pluck('pn')->filter()->unique();
                $realizedPns = collect($realizedByProduct->get($productKey, []))->intersect($eligiblePns)->unique();
                $product['mantri_eligible'] = $eligiblePns->count();
                $product['mantri_realized'] = $realizedPns->count();
                $product['mantri_not_realized'] = max(0, $eligiblePns->count() - $realizedPns->count());

                return $product;
            })
            ->all();

        $kupedesRoster = $roster->whereIn('category', ['pt', 'contract'])->values();
        $kupedesEligiblePns = $kupedesRoster->pluck('pn')->filter()->unique();
        $kupedesRealizedPns = collect($realizedByProduct->get('kupedes', []))
            ->intersect($kupedesEligiblePns)
            ->filter()
            ->unique();
        $realization['kupedes_not_realized'] = $kupedesRoster
            ->reject(fn (array $person): bool => $kupedesRealizedPns->contains((string) ($person['pn'] ?? '')))
            ->map(static fn (array $person): array => [
                'pn' => (string) ($person['pn'] ?? ''),
                'name' => trim((string) ($person['name'] ?? '')),
                'branch' => (string) ($person['branch'] ?? ''),
                'unit' => trim((string) ($person['unit'] ?? '')),
                'category' => (string) ($person['category'] ?? ''),
            ])
            ->sortBy(static fn (array $person): string => strtoupper(
                $person['branch'].'|'.$person['unit'].'|'.$person['name'].'|'.$person['pn']
            ))
            ->values()
            ->all();
        $realization['mantri_roster'] = [
            'total_active' => $roster->count(),
            'pt' => $roster->where('category', 'pt')->count(),
            'contract' => $roster->where('category', 'contract')->count(),
            'briguna' => $roster->where('category', 'briguna')->count(),
            'kupedes_eligible' => $kupedesEligiblePns->count(),
            'kupedes_realized' => $kupedesRealizedPns->count(),
            'kupedes_not_realized' => max(0, $kupedesEligiblePns->count() - $kupedesRealizedPns->count()),
            'daily_placement_matched' => $roster->where('roster_source', 'daily_loan_current_assignment')->count(),
            'branches' => $roster
                ->groupBy('branch')
                ->map(static fn (Collection $people, string $branch): array => [
                    'branch' => $branch,
                    'total_active' => $people->count(),
                    'pt' => $people->where('category', 'pt')->count(),
                    'contract' => $people->where('category', 'contract')->count(),
                    'briguna' => $people->where('category', 'briguna')->count(),
                ])
                ->sortBy('branch')
                ->values()
                ->all(),
            'source' => 'BRIHC aktif dipadukan dengan penempatan dan realisasi Daily Loan Dinamis',
        ];

        $eligiblePns = $roster->pluck('pn')->filter()->unique();
        $realizedPns = collect($rows)
            ->map(fn (object|array $row): string => $this->normalisePn((string) data_get($row, 'manager_pn', data_get($row, 'manager_raw', ''))))
            ->filter()
            ->intersect($eligiblePns)
            ->unique();
        $realization['mantri'] = [
            'eligible' => $eligiblePns->count(),
            'realized' => $realizedPns->count(),
            'not_realized' => max(0, $eligiblePns->count() - $realizedPns->count()),
        ];

        return $realization;
    }

    /** @param array<string, mixed>|null $branchScope */
    private function buildMicroPhSummary(string $period, ?array $branchScope): array
    {
        $required = ['periode', 'acctno', 'kanca', 'unit', 'segmen_dashboard', 'pokok'];
        if (! $this->hasTable('lw325_ph') || ! collect($required)->every(fn (string $column): bool => $this->hasColumn('lw325_ph', $column))) {
            return $this->emptyPhSummary();
        }

        $currentPeriod = DB::table('lw325_ph')->where('periode', '<=', $period)->max('periode');
        if (! $currentPeriod) {
            return $this->emptyPhSummary();
        }

        $currentPeriod = Carbon::parse($currentPeriod)->toDateString();
        $previousPeriod = Carbon::parse($currentPeriod)->startOfMonth()->subDay()->toDateString();
        if (! DB::table('lw325_ph')->where('periode', $previousPeriod)->exists()) {
            return $this->emptyPhSummary($currentPeriod, $previousPeriod);
        }

        $tupok = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($currentPeriod, $previousPeriod): void {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->whereRaw('n.periode = ?', [$currentPeriod])
                    ->whereRaw('o.periode = ?', [$previousPeriod]);
            })
            ->whereRaw("UPPER(TRIM(COALESCE(o.segmen_dashboard, ''))) = 'MICRO'")
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');
        $this->applyBranchFilter($tupok, 'o.kanca', $branchScope);
        $tupokMetric = $tupok
            ->selectRaw('COUNT(DISTINCT o.acctno) as deb')
            ->selectRaw('COALESCE(SUM(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)), 0) as amount')
            ->first();

        $lunas = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($currentPeriod): void {
                $join->on('o.acctno', '=', 'n.acctno')
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->whereRaw('n.periode = ?', [$currentPeriod]);
            })
            ->where('o.periode', $previousPeriod)
            ->whereRaw("UPPER(TRIM(COALESCE(o.segmen_dashboard, ''))) = 'MICRO'")
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');
        $this->applyBranchFilter($lunas, 'o.kanca', $branchScope);
        $lunasMetric = $lunas
            ->selectRaw('COUNT(DISTINCT o.acctno) as deb')
            ->selectRaw('COALESCE(SUM(o.pokok), 0) as amount')
            ->first();

        return [
            'available' => true,
            'period' => $currentPeriod,
            'period_label' => Carbon::parse($currentPeriod)->translatedFormat('d M Y'),
            'comparison_period' => $previousPeriod,
            'comparison_period_label' => Carbon::parse($previousPeriod)->translatedFormat('d M Y'),
            'lunas' => ['deb' => (int) ($lunasMetric->deb ?? 0), 'amount' => (float) ($lunasMetric->amount ?? 0.0)],
            'turun_pokok' => ['deb' => (int) ($tupokMetric->deb ?? 0), 'amount' => (float) ($tupokMetric->amount ?? 0.0)],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyPhSummary(?string $period = null, ?string $previousPeriod = null): array
    {
        return [
            'available' => false,
            'period' => $period,
            'period_label' => $period ? Carbon::parse($period)->translatedFormat('d M Y') : '-',
            'comparison_period' => $previousPeriod,
            'comparison_period_label' => $previousPeriod ? Carbon::parse($previousPeriod)->translatedFormat('d M Y') : '-',
            'lunas' => ['deb' => 0, 'amount' => 0.0],
            'turun_pokok' => ['deb' => 0, 'amount' => 0.0],
        ];
    }

    /**
     * @param  array<int, object|array<string, mixed>>  $plafondRows
     * @param  array<int, object|array<string, mixed>>  $netRows
     * @return array<string, mixed>
     */
    private function buildMbmDecisionRanking(array $plafondRows, array $netRows): array
    {
        $people = [];
        foreach ($plafondRows as $sourceRow) {
            $row = (array) $sourceRow;
            if (! $this->isMbmRankingRow($row)) {
                continue;
            }

            $pn = $this->normalisePn((string) ($row['decision_pn'] ?? ''));
            $name = trim((string) ($row['decision_name'] ?? ''));
            $official = self::AREA_MBM_REFERENCE[$pn] ?? null;
            if ($official === null) {
                continue;
            }
            $name = $official['name'];
            $key = $pn !== '' ? 'PN:'.$pn : 'NAME:'.$this->normaliseToken($name);
            if ($key === 'NAME:') {
                continue;
            }

            $people[$key] ??= [
                'pn' => $pn !== '' ? $pn : '-',
                'name' => $name !== '' ? $name : ($pn !== '' ? 'PN '.$pn : 'Tidak Terpetakan'),
                'branch' => (string) ($official['branch'] ?? $this->branchLabel((string) ($row['branch'] ?? ''))),
            ];
        }

        $rankMetric = function (array $metricRows, string $amountColumn) use ($people): array {
            $metrics = collect($people)
                ->map(fn (array $person): array => $person + ['amount' => 0.0, 'accounts' => []])
                ->all();

            foreach ($metricRows as $sourceRow) {
                $row = (array) $sourceRow;
                if (! $this->isMbmRankingRow($row)) {
                    continue;
                }

                $pn = $this->normalisePn((string) ($row['decision_pn'] ?? ''));
                $name = trim((string) ($row['decision_name'] ?? ''));
                $key = $pn !== '' ? 'PN:'.$pn : 'NAME:'.$this->normaliseToken($name);
                if (! isset($metrics[$key])) {
                    continue;
                }

                $metrics[$key]['amount'] += max(0.0, (float) ($row[$amountColumn] ?? 0.0));
                $account = strtoupper(trim((string) ($row['account_key'] ?? '')));
                if ($account !== '') {
                    $metrics[$key]['accounts'][$account] = true;
                }
            }

            $items = collect($metrics)
                ->map(static function (array $item): array {
                    $item['deb'] = count((array) $item['accounts']);
                    unset($item['accounts']);

                    return $item;
                })
                ->values();
            $sortKey = static fn (array $item): string => str_pad(
                number_format((float) $item['amount'], 2, '.', ''),
                30,
                '0',
                STR_PAD_LEFT
            ).'|'.strtoupper((string) $item['name']);

            return [
                'available' => $items->isNotEmpty(),
                'top' => $items->sortByDesc($sortKey)->take(3)->values()->all(),
                'bottom' => $items->sortBy($sortKey)->take(3)->values()->all(),
            ];
        };

        return [
            'available' => $people !== [],
            'default_metric' => 'plafond',
            'metrics' => [
                'plafond' => ['key' => 'plafond', 'label' => 'Plafon', ...$rankMetric($plafondRows, 'amount')],
                'net' => ['key' => 'net', 'label' => 'Nett Disbursement', ...$rankMetric($netRows, 'net_amount')],
            ],
        ];
    }

    /**
     * @param  array<int, object|array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    private function buildPdwkLimitSummary(array $rows, ?array $branchScope = null): array
    {
        $statusDefinitions = [
            'full' => ['key' => 'full', 'label' => '100% x PDWK', 'limit' => 100, 'tone' => 'green'],
            'three_quarter' => ['key' => 'three_quarter', 'label' => '75% x PDWK', 'limit' => 75, 'tone' => 'yellow'],
            'limited' => ['key' => 'limited', 'label' => '40% x PDWK', 'limit' => 40, 'tone' => 'orange'],
            'stop' => ['key' => 'stop', 'label' => 'Stop', 'limit' => 0, 'tone' => 'red'],
        ];
        $roleDefinitions = [
            'mbm' => ['key' => 'mbm', 'label' => 'MBM', 'statuses' => []],
            'ka_unit' => ['key' => 'ka_unit', 'label' => 'KA Unit', 'statuses' => []],
        ];

        foreach ($roleDefinitions as &$role) {
            $role['statuses'] = collect($statusDefinitions)
                ->map(fn (array $status): array => $status + [
                    'people' => [],
                    'reference_people' => [],
                    'accounts' => [],
                    'amount' => 0.0,
                ])
                ->all();
        }
        unset($role);

        $scopeBranch = $branchScope === null
            ? ''
            : $this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''));

        foreach (self::PDWK_LIMIT_REFERENCE as $pn => $reference) {
            if ($scopeBranch !== '' && $this->branchLabel((string) $reference['branch']) !== $scopeBranch) {
                continue;
            }

            $statusKey = match (true) {
                $reference['limit'] <= 0 => 'stop',
                $reference['limit'] <= 40 => 'limited',
                $reference['limit'] <= 75 => 'three_quarter',
                default => 'full',
            };
            $roleDefinitions[$reference['role']]['statuses'][$statusKey]['reference_people'][$pn] = [
                'pn' => $pn,
                'name' => $reference['name'],
                'unit' => $reference['unit'],
                'branch' => $reference['branch'],
            ];
        }

        foreach ($rows as $rowIndex => $sourceRow) {
            $row = (array) $sourceRow;
            $pn = $this->normalisePn((string) ($row['decision_pn'] ?? ''));
            if ($pn === '') {
                continue;
            }

            $reference = self::PDWK_LIMIT_REFERENCE[$pn] ?? null;
            // Stop & Go PDWK is intentionally isolated from BRIHC. A Daily Loan
            // decision is counted only when its PN exists in the supplied workbook.
            if ($reference === null) {
                continue;
            }

            $roleKey = (string) $reference['role'];
            $limit = (int) $reference['limit'];
            $statusKey = match (true) {
                $limit <= 0 => 'stop',
                $limit <= 40 => 'limited',
                $limit <= 75 => 'three_quarter',
                default => 'full',
            };
            $name = (string) $reference['name'];
            $unit = (string) $reference['unit'];
            $account = strtoupper(trim((string) ($row['account_key'] ?? '')));
            if ($account === '') {
                $account = 'ROW:'.$rowIndex;
            }

            $status = &$roleDefinitions[$roleKey]['statuses'][$statusKey];
            $status['people'][$pn] = ['pn' => $pn, 'name' => $name, 'unit' => $unit];
            $status['accounts'][$account] = true;
            $status['amount'] += max(0.0, (float) ($row['amount'] ?? $row['plafon'] ?? 0.0));
            unset($status);
        }

        $roles = collect($roleDefinitions)->map(function (array $role): array {
            $totalPeople = collect($role['statuses'])->sum(fn (array $status): int => count($status['people']));
            $totalAccounts = collect($role['statuses'])->sum(fn (array $status): int => count($status['accounts']));
            $totalAmount = (float) collect($role['statuses'])->sum('amount');

            $role['statuses'] = collect($role['statuses'])->map(function (array $status) use ($totalPeople, $totalAccounts, $totalAmount): array {
                $people = collect($status['people'])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
                $referencePeople = collect($status['reference_people'])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
                $accounts = count($status['accounts']);
                unset($status['accounts'], $status['reference_people']);

                return array_merge($status, [
                    'pemutus' => count($people),
                    'pemutus_share' => $totalPeople > 0 ? (count($people) / $totalPeople) * 100 : 0.0,
                    'putus_deb' => $accounts,
                    'putus_share' => $totalAccounts > 0 ? ($accounts / $totalAccounts) * 100 : 0.0,
                    'amount_share' => $totalAmount > 0.0 ? ((float) $status['amount'] / $totalAmount) * 100 : 0.0,
                    'people' => $people,
                    'reference_people' => $referencePeople,
                ]);
            })->values()->all();
            $role['available'] = $totalPeople > 0;
            $role['total'] = [
                'pemutus' => $totalPeople,
                'putus_deb' => $totalAccounts,
                'amount' => $totalAmount,
            ];

            return $role;
        })->values()->all();

        $defaultRole = collect($roles)->first(fn (array $role): bool => $role['available']);

        return [
            'available' => collect($roles)->contains(fn (array $role): bool => $role['available']),
            'default_role' => (string) ($defaultRole['key'] ?? 'mbm'),
            'roles' => $roles,
            'source' => 'PDWK MBM & KEPALA UNIT_2026 07 31.xlsx',
        ];
    }

    /** @param array<string, mixed> $row */
    private function isMbmRankingRow(array $row): bool
    {
        $referenceRole = strtoupper(trim((string) ($row['decision_reference_role'] ?? '')));
        $effectiveRole = strtoupper(trim((string) ($row['decision_role'] ?? '')));

        return ($referenceRole === 'MBM' && $effectiveRole !== 'SBOH')
            || ($referenceRole === '' && $effectiveRole === 'MBM');
    }

    /**
     * Mengambil akad Mikro bulan berjalan per rekening. Seluruh agregasi realisasi
     * (produk, pola angsuran, dan PDWK) wajib memakai plafon akad ini, bukan delta OS.
     *
     * @param  array<string, mixed>|null  $branchScope
     * @return array<int, object>
     */
    private function fetchPlafondRealizationRows(string $period, ?array $branchScope): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $columns = array_values(array_filter([
            'uniqueid_namareport', 'cifno', 'nomor_rekening1', 'plafon', 'baki_debet1',
            'tgl_realisasi', 'cabang1', 'kode_cabang1', 'unit1', 'produk_dashboard',
            'ln_type', 'freq_payment', 'pn_pemutus1', 'pn_pengelola1',
            $this->hasColumn(self::SOURCE_TABLE, 'nama_debitur1') ? 'nama_debitur1' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'jangka_waktu1') ? 'jangka_waktu1' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'cifno_clean') ? 'cifno_clean' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'pn_pemutus_normalized') ? 'pn_pemutus_normalized' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'cabang_normalized') ? 'cabang_normalized' : null,
        ]));

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->select($columns);
        $this->applyDailyMicroFilter($query);
        $this->applyDailyBranchFilter($query, '', $branchScope);

        $paymentPatterns = $this->loanPaymentPatternMap();
        $decisionReferences = $this->decisionReferenceMap();
        $decisionRoles = collect($decisionReferences)
            ->mapWithKeys(fn (array $reference, string $pn): array => [$pn => (string) ($reference['role'] ?? '')])
            ->filter()
            ->all();
        $loanRows = $this->deduplicateLoanAccounts($query->get());
        $decisionProfiles = $this->inferDecisionProfiles($loanRows, $decisionRoles);

        return $loanRows
            ->map(function ($row) use ($paymentPatterns, $decisionProfiles, $decisionReferences, $decisionRoles): object {
                $plafon = max(0.0, (float) ($row->plafon ?? 0.0));
                $accountKey = $this->loanAccountKey($row);
                $loanTypeKey = strtoupper(trim((string) ($row->ln_type ?? '')));
                $rawDecisionPn = trim((string) ($row->pn_pemutus_normalized ?? ''));
                if ($rawDecisionPn === '') {
                    $rawDecisionPn = trim((string) ($row->pn_pemutus1 ?? ''));
                }
                $decisionPn = $this->normalisePn($rawDecisionPn);
                $decision = $this->resolveDecisionRole(
                    $accountKey,
                    $decisionPn,
                    $plafon,
                    $decisionRoles,
                    $decisionProfiles
                );

                return (object) [
                    'account_key' => $accountKey,
                    'cif_key' => $this->loanCifKey($row),
                    'amount' => $plafon,
                    'plafon' => $plafon,
                    'current_os' => max(0.0, (float) ($row->baki_debet1 ?? 0.0)),
                    'realization_date' => (string) ($row->tgl_realisasi ?? ''),
                    'branch' => (string) ($row->cabang_normalized ?? $row->cabang1 ?? ''),
                    'branch_code' => trim((string) ($row->kode_cabang1 ?? '')),
                    'unit' => (string) ($row->unit1 ?? ''),
                    'product' => (string) ($row->produk_dashboard ?? ''),
                    'customer_name' => trim((string) ($row->nama_debitur1 ?? '')),
                    'loan_term' => $row->jangka_waktu1 ?? null,
                    'payment_pattern' => (string) ($paymentPatterns[$loanTypeKey] ?? ''),
                    'payment_frequency' => (int) ($row->freq_payment ?? 0),
                    'decision_pn' => $decisionPn,
                    'decision_name' => (string) data_get(
                        $decisionReferences,
                        $decisionPn.'.name',
                        $this->decisionNameFromRaw((string) ($row->pn_pemutus1 ?? ''), $decisionPn)
                    ),
                    'decision_reference_role' => (string) data_get($decisionReferences, $decisionPn.'.role', ''),
                    'decision_role' => $decision['role'],
                    'decision_role_source' => $decision['source'],
                    'decision_role_confidence' => $decision['confidence'],
                    'decision_expected_role' => $decision['expected_role'],
                    'decision_is_override' => $decision['is_override'],
                    'decision_override_note' => $decision['note'],
                    'manager_pn' => $this->normalisePn((string) ($row->pn_pengelola1 ?? '')),
                    'manager_raw' => trim((string) ($row->pn_pengelola1 ?? '')),
                ];
            })
            ->filter(fn (object $row): bool => $row->account_key !== '' && $row->amount > 0.0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function oneTimePaymentNominatives(
        ?string $requestedPeriod,
        ?array $branchScope,
        string $productKey,
        string $termKey
    ): array {
        $period = $this->resolvePeriod($requestedPeriod, $branchScope);
        if ($period === null) {
            return [
                'period' => null,
                'period_label' => '-',
                'scope_label' => (string) ($branchScope['label'] ?? 'Area 6'),
                'product_label' => '-',
                'term_label' => '-',
                'customers' => 0,
                'accounts' => 0,
                'total_os' => 0.0,
                'rows' => [],
            ];
        }

        $allowedProducts = collect([
            ['key' => 'all', 'label' => 'Semua Produk'],
            ...$this->microNeedProductDefinitions(),
        ])->keyBy('key');
        if (! $allowedProducts->has($productKey)) {
            throw new \InvalidArgumentException('Produk Mikro tidak valid.');
        }
        if ($termKey !== 'unknown' && preg_match('/^m\d+$/', $termKey) !== 1) {
            throw new \InvalidArgumentException('Jangka waktu tidak valid.');
        }

        $rows = collect($this->fetchPlafondRealizationRows($period, $branchScope))
            ->filter(function (object $row) use ($productKey, $termKey): bool {
                $isOneTime = $this->isMusiman($row->payment_pattern, $row->payment_frequency)
                    && $this->musimanPaymentDetailKey($row->payment_pattern, $row->payment_frequency) === 'satu_kali';
                $rowProductKey = $this->normaliseToken($this->microProductLabel((string) $row->product));
                $rowTerm = $this->normaliseLoanTerm($row->loan_term);

                return $isOneTime
                    && ($productKey === 'all' || $rowProductKey === $productKey)
                    && $rowTerm['key'] === $termKey;
            })
            ->sortBy(fn (object $row): string => implode('|', [
                $this->branchLabel((string) $row->branch),
                strtoupper(trim((string) $row->unit)),
                strtoupper(trim((string) $row->customer_name)),
                strtoupper(trim((string) $row->account_key)),
            ]))
            ->values();
        $term = $this->normaliseLoanTerm($termKey === 'unknown' ? null : substr($termKey, 1));
        $productLabel = (string) data_get($allowedProducts->get($productKey), 'label', $productKey);

        return [
            'period' => $period,
            'period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
            'scope_label' => (string) ($branchScope['label'] ?? 'Area 6'),
            'product_key' => $productKey,
            'product_label' => $productLabel,
            'term_key' => $termKey,
            'term_label' => $term['label'],
            'customers' => $rows->pluck('cif_key')->filter()->unique()->count(),
            'accounts' => $rows->pluck('account_key')->filter()->unique()->count(),
            'total_os' => (float) $rows->sum('current_os'),
            'rows' => $rows->map(function (object $row): array {
                return [
                    'customer_name' => $row->customer_name !== '' ? $row->customer_name : '-',
                    'cif' => $row->cif_key !== '' ? $row->cif_key : '-',
                    'account' => $row->account_key !== '' ? $row->account_key : '-',
                    'branch' => $this->branchLabel((string) $row->branch),
                    'unit' => trim((string) $row->unit) !== '' ? trim((string) $row->unit) : '-',
                    'product' => $this->microProductLabel((string) $row->product),
                    'term' => $this->normaliseLoanTerm($row->loan_term)['label'],
                    'realization_date' => $row->realization_date !== ''
                        ? Carbon::parse($row->realization_date)->translatedFormat('d M Y')
                        : '-',
                    'plafon' => (float) $row->plafon,
                    'os' => (float) $row->current_os,
                ];
            })->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @param  array<int, object>|null  $realizationRows
     * @return array<int, object>
     */
    private function fetchNetRealizationRows(
        string $period,
        string $previousPeriod,
        ?array $branchScope,
        ?array $realizationRows = null
    ): array {
        $realizationRows ??= $this->fetchPlafondRealizationRows($period, $branchScope);
        if ($realizationRows === []) {
            return [];
        }

        $realizations = collect($realizationRows);
        $candidateCifs = $realizations
            ->map(fn (object $row): string => str_starts_with($row->cif_key, 'REK:') ? '' : $row->cif_key)
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();
        $fallbackAccounts = $realizations
            ->filter(fn (object $row): bool => str_starts_with($row->cif_key, 'REK:'))
            ->map(fn (object $row): string => $row->account_key)
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        // Scope cabang tidak diterapkan pada exposure pembanding agar rekening lintas
        // cabang dengan CIF sama tidak keliru dianggap sudah tutup.
        $currentRows = $this->fetchCandidateLoanRows($period, $candidateCifs, $fallbackAccounts, null);
        $previousRows = $this->fetchCandidateLoanRows($previousPeriod, $candidateCifs, $fallbackAccounts, null);
        $currentAccounts = $this->deduplicateLoanAccounts($currentRows);
        $previousAccounts = $this->deduplicateLoanAccounts($previousRows);
        $currentByCif = $currentAccounts->groupBy(fn ($row): string => $this->loanCifKey($row));
        $previousByCif = $previousAccounts->groupBy(fn ($row): string => $this->loanCifKey($row));

        return $realizations
            ->groupBy('cif_key')
            ->flatMap(function (Collection $group, string $cifKey) use ($currentByCif, $previousByCif): array {
                $currentCifAccounts = collect($currentByCif[$cifKey] ?? []);
                $previousCifAccounts = collect($previousByCif[$cifKey] ?? []);
                $currentAccountKeys = $currentCifAccounts
                    ->mapWithKeys(fn ($row): array => [$this->loanAccountKey($row) => true]);
                $previousAccountOs = $previousCifAccounts
                    ->mapWithKeys(fn ($row): array => [
                        $this->loanAccountKey($row) => max(0.0, (float) ($row->baki_debet1 ?? 0.0)),
                    ]);
                $closedExposure = (float) $previousCifAccounts
                    ->filter(fn ($row): bool => ! $currentAccountKeys->has($this->loanAccountKey($row)))
                    ->sum(fn ($row): float => max(0.0, (float) ($row->baki_debet1 ?? 0.0)));

                $sameAccountRows = $group->filter(fn (object $row): bool => $previousAccountOs->has($row->account_key));
                $newAccountRows = $group->reject(fn (object $row): bool => $previousAccountOs->has($row->account_key));
                $result = [];

                foreach ($sameAccountRows as $row) {
                    $previousOs = (float) $previousAccountOs->get($row->account_key, 0.0);
                    $netAmount = max(0.0, (float) $row->plafon - $previousOs);
                    if ($netAmount > 0.0) {
                        $result[] = $this->decorateNetRow($row, $netAmount, $previousOs, 'suplesi');
                    }
                }

                $newPlafon = (float) $newAccountRows->sum('plafon');
                $newNetTotal = max(0.0, $newPlafon - $closedExposure);
                foreach ($newAccountRows as $row) {
                    $netAmount = $newPlafon > 0.0
                        ? $newNetTotal * ((float) $row->plafon / $newPlafon)
                        : 0.0;
                    if ($netAmount > 0.0) {
                        $allocatedPrevious = max(0.0, (float) $row->plafon - $netAmount);
                        $result[] = $this->decorateNetRow(
                            $row,
                            $netAmount,
                            $allocatedPrevious,
                            $closedExposure > 0.0 ? 'suplesi' : 'baru'
                        );
                    }
                }

                return $result;
            })
            ->values()
            ->all();
    }

    private function decorateNetRow(
        object $row,
        float $netAmount,
        float $previousOs,
        string $type
    ): object {
        return (object) array_merge((array) $row, [
            'current_os' => (float) $row->plafon,
            'previous_os' => $previousOs,
            'net_amount' => $netAmount,
            'realization_type' => $type,
        ]);
    }

    /**
     * @param  Collection<int, string>  $candidateCifs
     * @param  Collection<int, string>  $fallbackAccounts
     * @param  array<string, mixed>|null  $branchScope
     * @return Collection<int, object>
     */
    private function fetchCandidateLoanRows(
        string $period,
        Collection $candidateCifs,
        Collection $fallbackAccounts,
        ?array $branchScope
    ): Collection {
        $columns = array_values(array_filter([
            'uniqueid_namareport', 'cifno', 'nomor_rekening1', 'baki_debet1', 'plafon',
            'cabang1', 'unit1', 'produk_dashboard', 'ln_type', 'freq_payment', 'pn_pemutus1',
            $this->hasColumn(self::SOURCE_TABLE, 'tgl_realisasi') ? 'tgl_realisasi' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'kode_cabang1') ? 'kode_cabang1' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'pn_pengelola1') ? 'pn_pengelola1' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'cifno_clean') ? 'cifno_clean' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'pn_pemutus_normalized') ? 'pn_pemutus_normalized' : null,
            $this->hasColumn(self::SOURCE_TABLE, 'cabang_normalized') ? 'cabang_normalized' : null,
        ]));
        $cifColumn = $this->hasColumn(self::SOURCE_TABLE, 'cifno_clean') ? 'cifno_clean' : 'cifno';

        $rows = collect();
        foreach ($candidateCifs->chunk(800) as $chunk) {
            $query = DB::table(self::SOURCE_TABLE)
                ->where('periode', $period)
                ->whereIn($cifColumn, $chunk->all())
                ->select($columns);
            $this->applyDailyMicroFilter($query);
            $this->applyDailyBranchFilter($query, '', $branchScope);
            $rows = $rows->concat($query->get());
        }
        foreach ($fallbackAccounts->chunk(800) as $chunk) {
            $query = DB::table(self::SOURCE_TABLE)
                ->where('periode', $period)
                ->whereIn('nomor_rekening1', $chunk->all())
                ->select($columns);
            $this->applyDailyMicroFilter($query);
            $this->applyDailyBranchFilter($query, '', $branchScope);
            $rows = $rows->concat($query->get());
        }

        return $rows->values();
    }

    /** @return Collection<int, object> */
    private function deduplicateLoanAccounts(Collection $rows): Collection
    {
        return $rows
            ->groupBy(function ($row): string {
                return $this->loanCifKey($row).'|'.$this->loanAccountKey($row);
            })
            ->map(fn (Collection $duplicates) => $duplicates
                ->sortByDesc(fn ($row): float => max(
                    (float) ($row->plafon ?? 0.0),
                    (float) ($row->baki_debet1 ?? 0.0)
                ))
                ->first())
            ->values();
    }

    private function loanAccountKey(object $row): string
    {
        return strtoupper(trim((string) ($row->nomor_rekening1 ?? $row->uniqueid_namareport ?? '')));
    }

    private function loanCifKey(object $row): string
    {
        $cif = strtoupper(trim((string) ($row->cifno_clean ?? '')));
        $cif = $cif !== '' ? $cif : strtoupper(trim((string) ($row->cifno ?? '')));
        if ($cif !== '') {
            return $cif;
        }

        return 'REK:'.strtoupper(trim((string) ($row->nomor_rekening1 ?? $row->uniqueid_namareport ?? '')));
    }

    /** @return array<string, string> */
    private function loanPaymentPatternMap(): array
    {
        if (! $this->hasTable('loan_type')) {
            return [];
        }

        return DB::table('loan_type')
            ->select('loan_type', 'pola_pembayaran')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                strtoupper(trim((string) ($row->loan_type ?? ''))) => strtoupper(trim((string) ($row->pola_pembayaran ?? ''))),
            ])
            ->filter(fn (string $value, string $key): bool => $key !== '')
            ->all();
    }

    /** @return array<string, string> */
    private function decisionRoleMap(): array
    {
        return collect($this->decisionReferenceMap())
            ->mapWithKeys(fn (array $reference, string $pn): array => [$pn => (string) ($reference['role'] ?? '')])
            ->filter()
            ->all();
    }

    /** @return array<string, array{role:string, name:string}> */
    private function decisionReferenceMap(): array
    {
        if (! $this->hasTable('brihc')) {
            return [];
        }

        $availableColumns = array_fill_keys(
            array_map('strtolower', Schema::getColumnListing('brihc')),
            true
        );
        $nameColumn = collect(['nama', 'completename', 'name'])
            ->first(fn (string $column): bool => isset($availableColumns[$column]));
        $columns = array_values(array_filter(['pn', 'jabatan', $nameColumn]));

        return DB::table('brihc')
            ->select($columns)
            ->get()
            ->groupBy(fn ($row): string => $this->normalisePn((string) ($row->pn ?? '')))
            ->map(function (Collection $rows) use ($nameColumn): array {
                $roles = $rows
                    ->map(fn ($row): ?string => $this->canonicalDecisionRole((string) ($row->jabatan ?? '')))
                    ->filter()
                    ->unique()
                    ->values();
                $name = $nameColumn === null
                    ? ''
                    : (string) $rows
                        ->map(fn ($row): string => trim((string) ($row->{$nameColumn} ?? '')))
                        ->first(fn (string $value): bool => $value !== '', '');

                return [
                    'role' => (string) ($roles->first() ?? ''),
                    'name' => $name,
                ];
            })
            ->filter(fn (array $value, string $key): bool => $key !== '' && $value['role'] !== '')
            ->all();
    }

    private function decisionNameFromRaw(string $rawValue, string $pn): string
    {
        $parts = array_map('trim', explode('-', $rawValue, 2));
        $name = (string) ($parts[1] ?? '');

        return $name !== '' ? $name : ($pn !== '' ? 'PN '.$pn : 'Tidak Terpetakan');
    }

    private function normalisePn(string $value): string
    {
        $firstPart = trim(explode('-', $value, 2)[0] ?? '');
        $digits = preg_replace('/\D+/', '', $firstPart) ?? '';
        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : strtoupper($firstPart);
    }

    /**
     * Mendeteksi kewenangan aktual dari pola transaksi satu periode. Kandidat BOH
     * harus menjangkau beberapa unit dalam satu cabang dan memutus plafon MBM serta
     * BOH. Kandidat SBOH harus konsisten pada satu KCP dan punya transaksi berulang
     * atau plafon di atas kewenangan MBM. Pola ambigu tetap memakai referensi awal.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string, string>  $roleMap
     * @return array<string, array{role: string, source: string, confidence: string, note: string}>
     */
    private function inferDecisionProfiles(Collection $rows, array $roleMap): array
    {
        return $rows
            ->groupBy(function ($row): string {
                $rawPn = trim((string) ($row->pn_pemutus_normalized ?? ''));
                if ($rawPn === '') {
                    $rawPn = trim((string) ($row->pn_pemutus1 ?? ''));
                }

                return $this->normalisePn($rawPn);
            })
            ->map(function (Collection $pnRows, string $pn) use ($roleMap): ?array {
                if ($pn === '' || ($roleMap[$pn] ?? '') !== 'MBM') {
                    return null;
                }

                $validRows = $pnRows
                    ->filter(fn ($row): bool => max(0.0, (float) ($row->plafon ?? 0.0)) > 0.0)
                    ->values();
                $branches = $validRows
                    ->map(fn ($row): string => $this->branchLabel((string) ($row->cabang_normalized ?? $row->cabang1 ?? '')))
                    ->filter(fn (string $branch): bool => $branch !== '' && $branch !== 'TIDAK TERPETAKAN')
                    ->unique()
                    ->values();
                $units = $validRows
                    ->map(fn ($row): string => $this->normaliseToken((string) ($row->unit1 ?? '')))
                    ->filter(fn (string $unit): bool => $unit !== '')
                    ->unique()
                    ->values();
                $hasMbmAuthority = $validRows->contains(function ($row): bool {
                    $plafon = max(0.0, (float) ($row->plafon ?? 0.0));

                    return $plafon >= 100_000_000 && $plafon <= 250_000_000;
                });
                $hasBohAuthority = $validRows->contains(
                    fn ($row): bool => max(0.0, (float) ($row->plafon ?? 0.0)) > 250_000_000
                );

                if ($validRows->count() >= 3
                    && $branches->count() === 1
                    && $units->count() >= 2
                    && $hasMbmAuthority
                    && $hasBohAuthority) {
                    return [
                        'role' => 'BOH',
                        'source' => 'inferred_acting_boh',
                        'confidence' => 'high',
                        'note' => 'Terdeteksi sebagai pejabat pelaksana BOH dari pola kewenangan lintas unit pada periode berjalan.',
                    ];
                }

                $singleUnit = (string) ($units->first() ?? '');
                $looksLikeKcpHead = $branches->count() === 1
                    && $units->count() === 1
                    && preg_match('/(?:^|-)KCP(?:-|$)/i', $singleUnit) === 1
                    && ($validRows->count() >= 3 || $hasBohAuthority);
                if ($looksLikeKcpHead) {
                    return [
                        'role' => 'SBOH',
                        'source' => 'inferred_kcp_sboh',
                        'confidence' => 'high',
                        'note' => 'Terdeteksi sebagai pemutus SBOH dari pola kewenangan yang konsisten pada satu KCP.',
                    ];
                }

                return null;
            })
            ->filter()
            ->all();
    }

    /**
     * @param  array<string, string>  $roleMap
     * @param  array<string, array{role: string, source: string, confidence: string, note: string}>  $profiles
     * @return array{role: string, source: string, confidence: string, expected_role: string, is_override: bool, note: string}
     */
    private function resolveDecisionRole(
        string $accountKey,
        string $pn,
        float $plafon,
        array $roleMap,
        array $profiles = []
    ): array {
        $expectedRole = $this->fallbackRole($plafon);

        if (isset(self::PDWK_ACCOUNT_OVERRIDES[$accountKey])) {
            $override = self::PDWK_ACCOUNT_OVERRIDES[$accountKey];

            return [
                'role' => $override['role'],
                'source' => 'manual_account_override',
                'confidence' => 'confirmed',
                'expected_role' => $expectedRole,
                'is_override' => $override['is_override'],
                'note' => $override['note'],
            ];
        }
        if ($pn !== '' && isset($profiles[$pn])) {
            $profile = $profiles[$pn];
            if ($profile['role'] === 'BOH' && $expectedRole === 'KA UNIT') {
                return [
                    'role' => 'KA UNIT',
                    'source' => 'inferred_acting_boh_nominal_guard',
                    'confidence' => $profile['confidence'],
                    'expected_role' => $expectedRole,
                    'is_override' => true,
                    'note' => 'Ditandatangani pejabat pelaksana BOH; plafon di bawah Rp100 juta tetap dikelompokkan sebagai KA Unit override.',
                ];
            }

            return [
                'role' => $profile['role'],
                'source' => $profile['source'],
                'confidence' => $profile['confidence'],
                'expected_role' => $expectedRole,
                'is_override' => $profile['role'] !== $expectedRole,
                'note' => $profile['note'],
            ];
        }
        if ($pn !== '' && isset(self::PDWK_ROLE_OVERRIDES[$pn])) {
            $role = self::PDWK_ROLE_OVERRIDES[$pn];

            return [
                'role' => $role,
                'source' => 'pn_override',
                'confidence' => 'confirmed',
                'expected_role' => $expectedRole,
                'is_override' => $this->isDecisionOverride($role, $expectedRole),
                'note' => '',
            ];
        }
        if ($pn !== '' && isset($roleMap[$pn])) {
            $role = $roleMap[$pn];

            return [
                'role' => $role,
                'source' => 'brihc',
                'confidence' => 'high',
                'expected_role' => $expectedRole,
                'is_override' => $this->isDecisionOverride($role, $expectedRole),
                'note' => '',
            ];
        }

        return [
            'role' => $expectedRole,
            'source' => 'nominal_fallback',
            'confidence' => 'low',
            'expected_role' => $expectedRole,
            'is_override' => false,
            'note' => '',
        ];
    }

    private function isDecisionOverride(string $role, string $expectedRole): bool
    {
        $authorityRole = $role === 'SBOH' ? 'BOH' : $role;

        return $authorityRole !== $expectedRole;
    }

    private function canonicalDecisionRole(string $job): ?string
    {
        $job = strtoupper(trim($job));

        return match (true) {
            str_contains($job, 'SBOH'),
            str_contains($job, 'PINCAPEM'),
            str_contains($job, 'PIMPINAN CABANG PEMBANTU') => 'SBOH',
            str_contains($job, 'RSBH'), str_contains($job, 'RMBH'),
            str_contains($job, 'BOH'), str_contains($job, 'PINCA'),
            str_contains($job, 'PIMPINAN CABANG') => 'BOH',
            str_contains($job, 'MBM') => 'MBM',
            str_contains($job, 'KAUNIT'), str_contains($job, 'KEPALA UNIT') => 'KA UNIT',
            default => null,
        };
    }

    private function applyDailyMicroFilter(Builder $query, string $alias = ''): void
    {
        $prefix = $alias === '' ? '' : rtrim($alias, '.').'.';
        if ($this->hasColumn(self::SOURCE_TABLE, 'segmen_kinerja')) {
            $query->where($prefix.'segmen_kinerja', 'MICRO');

            return;
        }

        if ($this->hasColumn(self::SOURCE_TABLE, 'segmen_dashboard')) {
            $query->whereRaw("UPPER(TRIM(COALESCE({$prefix}segmen_dashboard, ''))) LIKE '%MICRO%'");

            return;
        }

        if ($this->hasColumn(self::SOURCE_TABLE, 'segmen')) {
            $query->whereRaw("UPPER(TRIM(COALESCE({$prefix}segmen, ''))) LIKE '%MICRO%'");

            return;
        }
    }

    private function microProductLabel(string $product): string
    {
        $product = trim($product);

        return match ($this->normaliseToken($product)) {
            'briguna-mikro' => 'Briguna Mikro',
            'kupedes' => 'Kupedes',
            'kur-mikro' => 'KUR Mikro',
            'kur-kecil', 'kur-small' => 'KUR Kecil',
            // Pada data sumber Mikro, produk KPP masih dikirim sebagai "KPR".
            // Segmentasi MICRO sudah memisahkannya dari KPR Consumer.
            'kpp', 'kur-kpp', 'kpr' => 'KUR KPP',
            'cashcoll', 'cash-collateral', 'cashcollateral' => 'Cash Collateral',
            default => $product !== '' ? $product : 'Tidak Terpetakan',
        };
    }

    /** @param array<string, mixed>|null $branchScope */
    private function applyDailyBranchFilter(Builder $query, string $alias, ?array $branchScope): void
    {
        $prefix = $alias === '' ? '' : rtrim($alias, '.').'.';
        if ($this->hasColumn(self::SOURCE_TABLE, 'cabang_normalized')) {
            $branches = $branchScope !== null
                ? array_filter([
                    strtoupper(trim((string) ($branchScope['upper_label'] ?? ''))),
                    strtoupper(trim((string) ($branchScope['label'] ?? ''))),
                    strtoupper(trim((string) ($branchScope['plain_label'] ?? ''))),
                    'KC '.strtoupper(trim((string) ($branchScope['plain_label'] ?? ''))),
                ])
                : collect(self::AREA_BRANCHES)->flatMap(fn (string $branch): array => ['KC '.$branch, $branch])->all();
            $query->whereIn($prefix.'cabang_normalized', array_values(array_unique($branches)));

            return;
        }

        $this->applyBranchFilter($query, $prefix.'cabang1', $branchScope);
    }

    /** @param array<string, mixed>|null $branchScope */
    private function productPositions(string $period, ?array $branchScope): array
    {
        if (! $this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return ['available' => false, 'total' => 0.0, 'items' => []];
        }

        $products = [
            'briguna_mikro_os' => 'Briguna Mikro',
            'kupedes_os' => 'Kupedes',
            'kur_mikro_os' => 'KUR Mikro',
            'kur_kecil_os' => 'KUR Kecil',
            'kur_kpp_os' => 'KUR KPP',
        ];
        $columns = array_keys(array_filter(
            $products,
            fn (string $label, string $column): bool => $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, $column),
            ARRAY_FILTER_USE_BOTH
        ));
        if ($columns === []) {
            return ['available' => false, 'total' => 0.0, 'items' => []];
        }

        $rows = $this->harianSnapshotQuery($period, $branchScope, true)
            ->select($columns)
            ->get();
        $items = collect($columns)->map(function (string $column) use ($products, $rows): array {
            return [
                'key' => $column,
                'label' => $products[$column],
                'amount' => (float) $rows->sum(fn ($row): float => (float) ($row->{$column} ?? 0.0)),
            ];
        });
        $total = (float) $items->sum('amount');

        return [
            'available' => $rows->isNotEmpty() && $items->isNotEmpty(),
            'total' => $total,
            'items' => $items
                ->map(function (array $item) use ($total): array {
                    $item['share'] = $total > 0.0 ? ((float) $item['amount'] / $total) * 100 : 0.0;

                    return $item;
                })
                ->sortByDesc('amount')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, object>  $realizationRows
     * @param  array<int, object>  $netRows
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    private function buildMantriPerformance(
        string $period,
        array $realizationRows,
        array $netRows,
        ?array $branchScope,
        ?Collection $roster = null
    ): array {
        $roster ??= $this->mantriRoster($branchScope);
        $eligiblePns = $roster->pluck('pn')
            ->filter()
            ->mapWithKeys(static fn (string $pn): array => [$pn => true]);
        $belongsToActiveRoster = fn (object $row): bool => $eligiblePns->has(
            $this->normalisePn((string) ($row->manager_pn ?? $row->manager_raw ?? ''))
        );
        $rosterRealizationRows = array_values(array_filter($realizationRows, $belongsToActiveRoster));
        $rosterNetRows = array_values(array_filter($netRows, $belongsToActiveRoster));
        $excludedRealizationRows = array_values(array_filter($realizationRows, fn (object $row): bool => ! $belongsToActiveRoster($row)));
        $excludedNetRows = array_values(array_filter($netRows, fn (object $row): bool => ! $belongsToActiveRoster($row)));
        $dailyRealizationRows = array_values(array_filter(
            $rosterRealizationRows,
            fn (object $row): bool => substr(trim((string) ($row->realization_date ?? '')), 0, 10) === $period
        ));
        $dailyNetRows = array_values(array_filter(
            $rosterNetRows,
            fn (object $row): bool => substr(trim((string) ($row->realization_date ?? '')), 0, 10) === $period
        ));
        $workingDays = $this->workingDays(
            Carbon::parse($period)->startOfMonth()->toDateString(),
            $period
        );
        $netByManager = $this->managerMetrics($rosterNetRows, 'net_amount');
        $dailyRealizationByBranch = $this->branchMetrics($dailyRealizationRows);
        $dailyNetByBranch = $this->branchMetrics($dailyNetRows, 'net_amount');
        $realizationByBranch = $this->branchMetrics($rosterRealizationRows);
        $netByBranch = $this->branchMetrics($rosterNetRows, 'net_amount');
        $branchCodes = $this->branchCodeMap($period, $branchScope);
        $branchLabels = $branchScope !== null
            ? [$this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''))]
            : array_map(fn (string $branch): string => 'KC '.$branch, self::AREA_BRANCHES);

        $rows = collect($branchLabels)->map(function (string $branch) use (
            $roster,
            $workingDays,
            $dailyRealizationByBranch,
            $dailyNetByBranch,
            $realizationByBranch,
            $netByBranch,
            $netByManager,
            $branchCodes
        ): array {
            $people = $roster->where('branch', $branch)->values();

            return $this->mantriPerformanceRow(
                $branch,
                (string) ($branchCodes[$branch] ?? '-'),
                $people,
                $dailyRealizationByBranch[$branch] ?? ['amount' => 0.0, 'accounts' => []],
                $dailyNetByBranch[$branch] ?? ['amount' => 0.0, 'accounts' => []],
                $realizationByBranch[$branch] ?? ['amount' => 0.0, 'accounts' => []],
                $netByBranch[$branch] ?? ['amount' => 0.0, 'accounts' => []],
                $netByManager,
                $workingDays
            );
        })->values();

        $total = $this->mantriPerformanceRow(
            $branchScope === null ? 'AREA 6' : $this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? '')),
            '-',
            $roster,
            $this->loanMetrics($dailyRealizationRows),
            $this->loanMetrics($dailyNetRows, 'net_amount'),
            $this->loanMetrics($rosterRealizationRows),
            $this->loanMetrics($rosterNetRows, 'net_amount'),
            $netByManager,
            $workingDays
        );
        $excludedRealization = $this->loanMetrics($excludedRealizationRows);
        $excludedNet = $this->loanMetrics($excludedNetRows, 'net_amount');

        return [
            'available' => $roster->isNotEmpty() || $rosterRealizationRows !== [] || $rosterNetRows !== [],
            'working_days' => $workingDays,
            'daily_period' => $period,
            'daily_period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
            'accumulation_start' => Carbon::parse($period)->startOfMonth()->toDateString(),
            'accumulation_label' => Carbon::parse($period)->startOfMonth()->translatedFormat('d M').' - '.Carbon::parse($period)->translatedFormat('d M Y'),
            'rows' => $rows->all(),
            'total' => $total,
            'excluded_non_roster' => [
                'realization' => [
                    'deb' => count($excludedRealization['accounts']),
                    'amount' => (float) $excludedRealization['amount'],
                ],
                'net_disbursement' => [
                    'deb' => count($excludedNet['accounts']),
                    'amount' => (float) $excludedNet['amount'],
                ],
            ],
            'source' => 'Daily Loan Dinamis khusus pengelola yang terdaftar sebagai Mantri aktif BRIHC',
        ];
    }

    /**
     * @param  Collection<int, array<string, string>>  $people
     * @param  array{amount:float, accounts:array<string, bool>}  $dailyRealizationMetric
     * @param  array{amount:float, accounts:array<string, bool>}  $dailyNetMetric
     * @param  array{amount:float, accounts:array<string, bool>}  $realizationMetric
     * @param  array{amount:float, accounts:array<string, bool>}  $netMetric
     * @param  array<string, array{amount:float, accounts:array<string, bool>}>  $netByManager
     * @return array<string, mixed>
     */
    private function mantriPerformanceRow(
        string $branch,
        string $branchCode,
        Collection $people,
        array $dailyRealizationMetric,
        array $dailyNetMetric,
        array $realizationMetric,
        array $netMetric,
        array $netByManager,
        int $workingDays
    ): array {
        $metric = static function (array $source) use ($workingDays): array {
            $amount = (float) ($source['amount'] ?? 0.0);
            $accounts = count((array) ($source['accounts'] ?? []));

            return [
                'deb' => $accounts,
                'amount' => $amount,
                'average_per_hke' => $workingDays > 0 ? $amount / $workingDays : 0.0,
            ];
        };

        return [
            'branch_code' => $branchCode !== '' ? $branchCode : '-',
            'branch' => $branch,
            'headcount' => [
                'pt_non_briguna' => $people->where('category', 'pt')->count(),
                'contract' => $people->where('category', 'contract')->count(),
                'briguna' => $people->where('category', 'briguna')->count(),
            ],
            'daily_realization' => $metric($dailyRealizationMetric),
            'daily_net_disbursement' => $metric($dailyNetMetric),
            'realization' => $metric($realizationMetric),
            'net_disbursement' => $metric($netMetric),
            'tiers' => [
                'pt' => $this->mantriTierPayload($people->where('category', 'pt')->values(), $netByManager, 'pt'),
                'contract' => $this->mantriTierPayload($people->where('category', 'contract')->values(), $netByManager, 'contract'),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, string>>  $people
     * @param  array<string, array{amount:float, accounts:array<string, bool>}>  $netByManager
     * @return array<string, mixed>
     */
    private function mantriTierPayload(Collection $people, array $netByManager, string $category): array
    {
        $definitions = $category === 'contract'
            ? [
                'none' => 'Belum Realisasi',
                'extreme_low' => 'Extreme Low < 350 jt',
                'low' => 'Low 350 - < 600 jt',
                'mid' => 'Mid 600 - 800 jt',
                'high' => 'High > 800 jt',
            ]
            : [
                'none' => 'Belum Realisasi',
                'extreme_low' => 'Extreme Low < 400 jt',
                'low' => 'Low 400 - < 800 jt',
                'mid' => 'Mid 800 jt - 1 M',
                'high' => 'High > 1 M',
            ];
        $buckets = collect($definitions)->map(fn (string $label, string $key): array => [
            'key' => $key,
            'label' => $label,
            'mantri' => 0,
            'share' => 0.0,
            'people' => [],
        ])->all();

        foreach ($people as $person) {
            $managerMetric = $netByManager[(string) ($person['pn'] ?? '')] ?? ['amount' => 0.0, 'accounts' => []];
            $amount = (float) ($managerMetric['amount'] ?? 0.0);
            $key = match (true) {
                $amount <= 0.0 => 'none',
                $category === 'contract' && $amount < 350_000_000 => 'extreme_low',
                $category === 'contract' && $amount < 600_000_000 => 'low',
                $category === 'contract' && $amount <= 800_000_000 => 'mid',
                $category === 'contract' => 'high',
                $amount < 400_000_000 => 'extreme_low',
                $amount < 800_000_000 => 'low',
                $amount <= 1_000_000_000 => 'mid',
                default => 'high',
            };
            $buckets[$key]['mantri']++;
            $buckets[$key]['people'][] = [
                'pn' => (string) ($person['pn'] ?? ''),
                'name' => (string) ($person['name'] ?? ''),
                'branch' => (string) ($person['branch'] ?? ''),
                'unit' => (string) ($person['unit'] ?? ''),
                'net_amount' => $amount,
                'net_deb' => count((array) ($managerMetric['accounts'] ?? [])),
            ];
        }

        $total = $people->count();
        foreach ($buckets as &$bucket) {
            $bucket['share'] = $total > 0 ? ((int) $bucket['mantri'] / $total) * 100 : 0.0;
            usort($bucket['people'], static function (array $left, array $right): int {
                $byAmount = (float) $right['net_amount'] <=> (float) $left['net_amount'];

                return $byAmount !== 0
                    ? $byAmount
                    : strcmp($left['name'].'|'.$left['pn'], $right['name'].'|'.$right['pn']);
            });
        }
        unset($bucket);

        return ['total' => $total, 'buckets' => array_values($buckets)];
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<string, array{amount:float, accounts:array<string, bool>}>
     */
    private function managerMetrics(array $rows, string $amountColumn = 'amount'): array
    {
        $metrics = [];
        foreach ($rows as $row) {
            $pn = $this->normalisePn((string) ($row->manager_pn ?? $row->manager_raw ?? ''));
            $account = strtoupper(trim((string) ($row->account_key ?? '')));
            if ($pn === '' || $account === '') {
                continue;
            }

            $metrics[$pn] ??= ['amount' => 0.0, 'accounts' => []];
            $metrics[$pn]['amount'] += max(0.0, (float) ($row->{$amountColumn} ?? 0.0));
            $metrics[$pn]['accounts'][$account] = true;
        }

        return $metrics;
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<string, array{amount:float, accounts:array<string, bool>}>
     */
    private function branchMetrics(array $rows, string $amountColumn = 'amount'): array
    {
        $metrics = [];
        foreach ($rows as $row) {
            $branch = $this->branchLabel((string) ($row->branch ?? ''));
            $account = strtoupper(trim((string) ($row->account_key ?? '')));
            if ($account === '') {
                continue;
            }

            $metrics[$branch] ??= ['amount' => 0.0, 'accounts' => []];
            $metrics[$branch]['amount'] += max(0.0, (float) ($row->{$amountColumn} ?? 0.0));
            $metrics[$branch]['accounts'][$account] = true;
        }

        return $metrics;
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{amount:float, accounts:array<string, bool>}
     */
    private function loanMetrics(array $rows, string $amountColumn = 'amount'): array
    {
        $metric = ['amount' => 0.0, 'accounts' => []];
        foreach ($rows as $row) {
            $account = strtoupper(trim((string) ($row->account_key ?? '')));
            if ($account === '') {
                continue;
            }

            $metric['amount'] += max(0.0, (float) ($row->{$amountColumn} ?? 0.0));
            $metric['accounts'][$account] = true;
        }

        return $metric;
    }

    /**
     * Daily Loan Dinamis menetapkan penempatan Mantri periode berjalan.
     * BRIHC hanya melengkapi kategori, nama cadangan, dan tanggal mulai jabatan
     * untuk kebutuhan pembacaan histori produktivitas.
     *
     * @param  array<string, mixed>|null  $branchScope
     * @return Collection<int, array<string, string>>
     */
    private function mantriRoster(?array $branchScope, ?string $period = null): Collection
    {
        $references = $this->mantriReferenceRoster($branchScope);
        $dailyAssignments = $period === null ? collect() : $this->dailyMantriAssignments($period, $branchScope);
        if ($dailyAssignments->isEmpty()) {
            return $references;
        }

        $referencesByIdentity = $references->groupBy(fn (array $person): string => $this->mantriRosterIdentityKey($person));
        $dailyIdentities = $dailyAssignments
            ->mapWithKeys(fn (array $person): array => [$this->mantriRosterIdentityKey($person) => true]);
        $dailyRoster = $dailyAssignments
            ->map(function (array $assignment) use ($referencesByIdentity): ?array {
                $candidates = $referencesByIdentity->get($this->mantriRosterIdentityKey($assignment), collect());
                $reference = $candidates->first(
                    fn (array $person): bool => $this->mantriAssignmentKey($person) === $this->mantriAssignmentKey($assignment)
                );
                if (! is_array($reference) && $candidates->count() === 1) {
                    $reference = $candidates->first();
                }
                if (! is_array($reference)) {
                    return null;
                }

                $dailyName = trim((string) ($assignment['name'] ?? ''));

                return array_merge($reference, [
                    'name' => $dailyName !== '' ? $dailyName : (string) ($reference['name'] ?? ''),
                    'branch' => (string) $assignment['branch'],
                    'unit' => (string) $assignment['unit'],
                    'roster_source' => 'daily_loan_current_assignment',
                ]);
            })
            ->filter()
            ->values();

        // Mantri aktif yang belum memegang rekening pada snapshot Daily Loan
        // tetap hadir sebagai roster BRIHC, tetapi histori mereka dijaga oleh TMT jabatan.
        return $dailyRoster
            ->concat($references->reject(fn (array $person): bool => $dailyIdentities->has($this->mantriRosterIdentityKey($person))))
            ->sortBy(fn (array $row): string => $row['branch'].'|'.$row['pn'])
            ->unique(fn (array $row): string => $this->mantriRosterIdentityKey($row))
            ->values();
    }

    /** @param array<string, mixed>|null $branchScope @return Collection<int, array<string, string>> */
    private function mantriReferenceRoster(?array $branchScope): Collection
    {
        if (! $this->hasTable('brihc_pemasar')) {
            return collect();
        }

        $available = array_flip($this->columnListing('brihc_pemasar'));
        $columns = array_values(array_filter([
            isset($available['pernr']) ? 'pernr' : null,
            isset($available['pn_mantri']) ? 'pn_mantri' : null,
            isset($available['completename']) ? 'completename' : null,
            isset($available['esgdesc']) ? 'esgdesc' : null,
            isset($available['positiondesc']) ? 'positiondesc' : null,
            isset($available['orgdesc']) ? 'orgdesc' : null,
            isset($available['psadesc']) ? 'psadesc' : null,
            isset($available['tmt_jabatan']) ? 'tmt_jabatan' : null,
            isset($available['tmt_masuk']) ? 'tmt_masuk' : null,
        ]));
        if (! in_array('positiondesc', $columns, true) || ! in_array('psadesc', $columns, true)) {
            return collect();
        }

        return DB::table('brihc_pemasar')
            ->select($columns)
            ->get()
            ->map(function ($row): ?array {
                $position = strtoupper(trim((string) ($row->positiondesc ?? '')));
                $employment = strtoupper(trim((string) ($row->esgdesc ?? '')));
                $organization = strtoupper(trim((string) ($row->orgdesc ?? '')));
                $branch = $this->branchLabel((string) ($row->psadesc ?? ''));
                $rawPn = trim((string) ($row->pernr ?? ''));
                if ($rawPn === '') {
                    $rawPn = trim((string) ($row->pn_mantri ?? ''));
                }
                $pn = $this->normalisePn($rawPn);
                if ($pn === '' || ! in_array($branch, array_map(fn (string $item): string => 'KC '.$item, self::AREA_BRANCHES), true)) {
                    return null;
                }

                $category = match (true) {
                    $position === 'MANTRI BRIGUNA' => 'briguna',
                    $position === 'MANTRI' && str_contains($employment, 'KONTRAK') => 'contract',
                    $position === 'MANTRI' && str_contains($employment, 'PT') => 'pt',
                    default => null,
                };
                if ($category === null || ($category !== 'briguna' && ! str_starts_with($organization, 'UNIT'))) {
                    return null;
                }

                return [
                    'pn' => $pn,
                    'name' => trim((string) ($row->completename ?? '')),
                    'branch' => $branch,
                    'unit' => trim((string) ($row->orgdesc ?? '')),
                    'category' => $category,
                    'history_start' => $this->mantriHistoryStartDate(
                        $row->tmt_jabatan ?? null,
                        $row->tmt_masuk ?? null
                    ),
                    'roster_source' => 'brihc_active_reference',
                ];
            })
            ->filter()
            ->when($branchScope !== null, function (Collection $rows) use ($branchScope): Collection {
                $branch = $this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''));

                return $rows->where('branch', $branch);
            })
            ->sortBy(fn (array $row): string => $row['branch'].'|'.$row['pn'])
            ->unique('pn')
            ->values();
    }

    /** @param array<string, mixed>|null $branchScope @return Collection<int, array<string, string>> */
    private function dailyMantriAssignments(string $period, ?array $branchScope): Collection
    {
        if (! $this->hasColumn(self::SOURCE_TABLE, 'pn_pengelola1')) {
            return collect();
        }

        $columns = ['pn_pengelola1', 'cabang1', 'unit1'];
        if ($this->hasColumn(self::SOURCE_TABLE, 'cabang_normalized')) {
            $columns[] = 'cabang_normalized';
        }
        if ($this->hasColumn(self::SOURCE_TABLE, 'pn_name1')) {
            $columns[] = 'pn_name1';
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->select($columns);
        $this->applyDailyMicroFilter($query);
        $this->applyDailyBranchFilter($query, '', $branchScope);

        return $query->get()
            ->map(function (object $row): ?array {
                $pn = $this->normalisePn((string) ($row->pn_pengelola1 ?? ''));
                $branch = $this->branchLabel((string) ($row->cabang_normalized ?? $row->cabang1 ?? ''));
                $unit = trim((string) ($row->unit1 ?? ''));
                if ($pn === '' || $branch === '' || $unit === '') {
                    return null;
                }

                return [
                    'pn' => $pn,
                    'name' => $this->dailyMantriName((string) ($row->pn_name1 ?? ''), $pn),
                    'branch' => $branch,
                    'unit' => $unit,
                ];
            })
            ->filter()
            ->groupBy(fn (array $row): string => $row['branch'].'|'.$row['pn'])
            ->map(function (Collection $rows): array {
                return $rows
                    ->sortBy(fn (array $row): string => $this->mantriAssignmentKey($row).'|'.strtoupper($row['name']))
                    ->first();
            })
            ->values();
    }

    /** @param array<string, string> $person */
    private function mantriAssignmentKey(array $person): string
    {
        $branch = $this->branchLabel((string) ($person['branch'] ?? ''));
        $unit = preg_replace('/[^A-Z0-9]+/', '', strtoupper((string) ($person['unit'] ?? ''))) ?? '';

        return $branch.'|'.$unit;
    }

    /** @param array<string, string> $person */
    private function mantriRosterIdentityKey(array $person): string
    {
        return $this->branchLabel((string) ($person['branch'] ?? '')).'|'.(string) ($person['pn'] ?? '');
    }

    private function dailyMantriName(string $rawName, string $pn): string
    {
        $name = trim($rawName);
        if (preg_match('/^\s*0*'.preg_quote($pn, '/').'\s*-+\s*(.+)$/u', $name, $matches) === 1) {
            return trim($matches[1]);
        }

        return preg_match('/^\s*0*\d+\s*$/', $name) === 1 ? '' : $name;
    }

    private function mantriHistoryStartDate(mixed $tmtJabatan, mixed $tmtMasuk): string
    {
        foreach ([$tmtJabatan, $tmtMasuk] as $value) {
            $date = trim((string) $value);
            if ($date === '') {
                continue;
            }

            try {
                return Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return '';
    }

    /**
     * Mengikuti definisi landing SME: hanya bulan closing yang dihitung dan
     * ketidakproduktifan harus terjadi berturut-turut dari closing terbaru.
     * Snapshot RM dipakai sebagai agregat ringan; sumbernya tetap Daily Loan
     * Dinamis. BRIHC hanya membatasi histori sebelum TMT jabatan Mantri aktif.
     *
     * @param  array<string, mixed>|null  $branchScope
     * @param  Collection<int, array<string, string>>  $roster
     * @return array<string, mixed>
     */
    private function buildUnproductiveMantri(
        string $period,
        ?array $branchScope,
        Collection $roster
    ): array {
        $closedPeriods = $this->closedMicroPeriods($period, $branchScope, 6);
        $definitions = [
            'month_1' => ['label' => '1 bulan', 'minimum' => 1],
            'month_3' => ['label' => '3 bulan berturut-turut', 'minimum' => 3],
            'month_6' => ['label' => '6 bulan berturut-turut', 'minimum' => 6],
        ];
        $emptyMetrics = collect($definitions)->mapWithKeys(
            static fn (array $definition, string $key): array => [$key => [
                'key' => $key,
                'label' => $definition['label'],
                'count' => 0,
                'percentage' => 0.0,
                'monitored' => 0,
                'enabled' => false,
                'mantri' => [],
            ]]
        )->all();

        if (
            $closedPeriods->isEmpty()
            || $roster->isEmpty()
            || ! $this->hasTable(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            || ! collect(['periode', 'cabang', 'unit', 'rm', 'segmen', 'realisasi_os'])
                ->every(fn (string $column): bool => $this->hasColumn(self::PERFORMANCE_RM_SNAPSHOT_TABLE, $column))
        ) {
            return [
                'available' => false,
                'period_label' => '-',
                'total_mantri' => $roster->count(),
                'closed_periods' => [],
                'totals' => $emptyMetrics,
                'branches' => [],
                'source' => 'Performance RM Snapshot - Daily Loan Dinamis',
            ];
        }

        $snapshotQuery = DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->whereIn('periode', $closedPeriods->all())
            ->whereRaw("UPPER(TRIM(COALESCE(segmen, ''))) = 'MICRO'");
        $this->applyBranchFilter($snapshotQuery, 'cabang', $branchScope);

        $snapshotRows = $snapshotQuery
            ->select('periode', 'cabang', 'rm')
            ->selectRaw('SUM(COALESCE(realisasi_os, 0)) as realization_amount')
            ->groupBy('periode', 'cabang', 'rm')
            ->get();
        $coveredPeriods = $snapshotRows
            ->pluck('periode')
            ->map(static fn ($value): string => Carbon::parse((string) $value)->toDateString())
            ->unique()
            ->flip();
        $consecutivePeriods = collect();
        foreach ($closedPeriods as $closedPeriod) {
            if (! $coveredPeriods->has($closedPeriod)) {
                break;
            }
            $consecutivePeriods->push($closedPeriod);
        }
        $closedPeriods = $consecutivePeriods;
        if ($closedPeriods->isEmpty()) {
            return [
                'available' => false,
                'period_label' => '-',
                'total_mantri' => $roster->count(),
                'closed_periods' => [],
                'totals' => $emptyMetrics,
                'branches' => [],
                'source' => 'Performance RM Snapshot - Daily Loan Dinamis',
            ];
        }

        $coverage = $snapshotRows->reduce(function (array $carry, object $row): array {
            $periodKey = Carbon::parse((string) ($row->periode ?? ''))->toDateString();
            $branchKey = $this->branchLabel((string) ($row->cabang ?? ''));
            $carry[$branchKey.'|'.$periodKey] = true;

            return $carry;
        }, []);
        $activity = $snapshotRows->reduce(function (array $carry, object $row): array {
            $pn = $this->normalisePn((string) ($row->rm ?? ''));
            if ($pn === '') {
                return $carry;
            }

            $key = implode('|', [
                (string) ($row->periode ?? ''),
                $this->branchLabel((string) ($row->cabang ?? '')),
                $pn,
            ]);
            $carry[$key] = (float) ($carry[$key] ?? 0.0)
                + (float) ($row->realization_amount ?? 0.0);

            return $carry;
        }, []);

        $eligible = $roster
            ->map(function (array $person) use ($closedPeriods, $activity, $coverage): array {
                $eligiblePeriods = $closedPeriods->filter(
                    fn (string $closedPeriod): bool => $this->isMantriEligibleAtClosing($person, $closedPeriod)
                )->values();
                $coveredMonths = 0;
                foreach ($eligiblePeriods as $closedPeriod) {
                    if (! isset($coverage[$person['branch'].'|'.$closedPeriod])) {
                        break;
                    }
                    $coveredMonths++;
                }

                $inactiveMonths = 0;
                foreach ($eligiblePeriods as $closedPeriod) {
                    if ($inactiveMonths >= $coveredMonths) {
                        break;
                    }
                    $activityKey = implode('|', [$closedPeriod, $person['branch'], $person['pn']]);
                    if (abs((float) ($activity[$activityKey] ?? 0.0)) > 0.001) {
                        break;
                    }
                    $inactiveMonths++;
                }

                return array_merge($person, [
                    'covered_months' => $coveredMonths,
                    'inactive_months' => $inactiveMonths,
                    'eligible_closed_periods' => $eligiblePeriods->all(),
                ]);
            })
            ->sortBy(fn (array $person): string => implode('|', [
                $person['branch'],
                strtoupper((string) ($person['unit'] ?? '')),
                strtoupper((string) ($person['name'] ?? '')),
                $person['pn'],
            ]))
            ->values();

        $metricBuilder = static function (Collection $people) use ($definitions, $closedPeriods): array {
            $metrics = [];
            foreach ($definitions as $key => $definition) {
                $minimum = (int) $definition['minimum'];
                $monitoredPeople = $people
                    ->filter(static fn (array $person): bool => (int) ($person['covered_months'] ?? 0) >= $minimum)
                    ->values();
                $enabled = $closedPeriods->count() >= $minimum && $monitoredPeople->isNotEmpty();
                $matches = $enabled
                    ? $monitoredPeople->filter(static fn (array $person): bool => (int) ($person['inactive_months'] ?? 0) >= $minimum)->values()
                    : collect();
                $metrics[$key] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'count' => $matches->count(),
                    'percentage' => $monitoredPeople->isNotEmpty() ? ($matches->count() / $monitoredPeople->count()) * 100 : 0.0,
                    'monitored' => $monitoredPeople->count(),
                    'enabled' => $enabled,
                    'mantri' => $matches->all(),
                ];
            }

            return $metrics;
        };
        $branchLabels = $branchScope !== null
            ? [$this->branchLabel((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''))]
            : array_map(fn (string $branch): string => 'KC '.$branch, self::AREA_BRANCHES);
        $oldestPeriod = (string) $closedPeriods->last();
        $latestPeriod = (string) $closedPeriods->first();

        return [
            'available' => true,
            'period_label' => Carbon::parse($oldestPeriod)->translatedFormat('M y')
                .' - '.Carbon::parse($latestPeriod)->translatedFormat('M y'),
            'total_mantri' => $eligible->count(),
            'closed_periods' => $closedPeriods
                ->map(fn (string $closedPeriod): array => [
                    'period' => $closedPeriod,
                    'label' => Carbon::parse($closedPeriod)->translatedFormat('M y'),
                ])
                ->all(),
            'totals' => $metricBuilder($eligible),
            'branches' => collect($branchLabels)->map(function (string $branch) use ($eligible, $metricBuilder): array {
                $people = $eligible->where('branch', $branch)->values();

                return [
                    'branch' => $branch,
                    'total_mantri' => $people->count(),
                    'metrics' => $metricBuilder($people),
                ];
            })->all(),
            'source' => 'Performance RM Snapshot - Daily Loan Dinamis dengan guard TMT BRIHC',
        ];
    }

    /** @param array<string, string> $person */
    private function isMantriEligibleAtClosing(array $person, string $closedPeriod): bool
    {
        $historyStart = trim((string) ($person['history_start'] ?? ''));
        if ($historyStart === '') {
            return true;
        }

        try {
            return Carbon::parse($closedPeriod)->endOfMonth()->greaterThanOrEqualTo(Carbon::parse($historyStart)->startOfDay());
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return Collection<int, string> Closing terbaru ke terlama.
     */
    private function closedMicroPeriods(string $period, ?array $branchScope, int $limit): Collection
    {
        $selected = Carbon::parse($period)->startOfDay();
        $cursor = $selected->copy()->startOfMonth();
        if (! $selected->isLastOfMonth()) {
            $cursor->subMonthNoOverflow();
        }

        $periods = collect();
        for ($month = 0; $month < $limit; $month++) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $query = $this->periodLookupQuery()
                ->whereBetween('periode', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            $this->applyDailyMicroFilter($query);
            $this->applyDailyBranchFilter($query, '', $branchScope);
            $candidate = $query->max('periode');

            if ($candidate === null || ! Carbon::parse((string) $candidate)->isSameDay($monthEnd)) {
                break;
            }

            $periods->push(Carbon::parse((string) $candidate)->toDateString());
            $cursor->subMonthNoOverflow();
        }

        return $periods->values();
    }

    /** @param array<string, mixed>|null $branchScope */
    private function branchCodeMap(string $period, ?array $branchScope): array
    {
        if (! $this->hasColumn(self::SOURCE_TABLE, 'kode_cabang1')) {
            return [];
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereNotNull('kode_cabang1')
            ->select('cabang1', 'kode_cabang1')
            ->distinct();
        $this->applyDailyBranchFilter($query, '', $branchScope);

        return $query->get()
            ->mapWithKeys(fn ($row): array => [
                $this->branchLabel((string) ($row->cabang1 ?? '')) => trim((string) ($row->kode_cabang1 ?? '')),
            ])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    private function workingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $holidays = array_flip(self::NATIONAL_HOLIDAYS[(int) $end->year] ?? []);
        $total = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekday() && ! isset($holidays[$day->toDateString()])) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @param  array<string, mixed>  $mantriPerformance
     * @return array<string, mixed>
     */
    private function buildRealizationNeed(string $period, ?array $branchScope, array $mantriPerformance): array
    {
        $periodDate = Carbon::parse($period);
        $headcount = (int) data_get($mantriPerformance, 'total.headcount.pt_non_briguna', 0)
            + (int) data_get($mantriPerformance, 'total.headcount.contract', 0)
            + (int) data_get($mantriPerformance, 'total.headcount.briguna', 0);
        $totalWorkingDays = $this->workingDays(
            $periodDate->copy()->startOfMonth()->toDateString(),
            $periodDate->copy()->endOfMonth()->toDateString()
        );
        $elapsedWorkingDays = $this->workingDays(
            $periodDate->copy()->startOfMonth()->toDateString(),
            $period
        );
        $remainingWorkingDays = $this->workingDays(
            $periodDate->copy()->addDay()->toDateString(),
            $periodDate->copy()->endOfMonth()->toDateString()
        );

        try {
            $kancaSelection = $branchScope === null
                ? array_map(fn (string $branch): string => 'KC '.ucfirst(strtolower($branch)), self::AREA_BRANCHES)
                : (string) ($branchScope['label'] ?? $branchScope['upper_label'] ?? '');
            $dashboard = app(DashboardHarianSnapshotService::class)->buildDashboardPayload(
                $period,
                null,
                $kancaSelection
            );
            $dashboardRows = collect((array) data_get($dashboard, 'rows', []));
            $microRow = $dashboardRows->firstWhere('key', 'micro_os');
            $currentOs = (float) data_get($microRow, 'values.current', 0.0);
            $rka = (float) data_get($microRow, 'values.rka', 0.0);

            $runOffReport = app(RunOffReportService::class)->build($branchScope);
            $runOffLatestPeriod = (string) data_get($runOffReport, 'latest_period', '');
            $runOffMatchesMonth = $runOffLatestPeriod !== ''
                && Carbon::parse($runOffLatestPeriod)->format('Y-m') === $periodDate->format('Y-m');
            $runOffBranch = $branchScope === null
                ? 'Area 6'
                : (string) ($branchScope['label'] ?? $branchScope['upper_label'] ?? '');
            $runOffRow = collect((array) data_get($runOffReport, 'rows', []))->first(function (array $row) use ($runOffBranch): bool {
                return strtoupper(trim((string) ($row['category'] ?? ''))) === 'MICRO TOTAL'
                    && strtolower(trim((string) ($row['branch'] ?? ''))) === strtolower(trim($runOffBranch));
            });
            $runOffAvailable = data_get($runOffReport, 'error') === null
                && $runOffMatchesMonth
                && is_array($runOffRow);
            $runOffAmount = $runOffAvailable
                ? (float) data_get($runOffRow, 'remaining_amount_cents', 0) / 100
                : 0.0;
            $productNeeds = collect($this->microNeedProductDefinitions())
                ->map(function (array $definition) use (
                    $dashboardRows,
                    $runOffReport,
                    $runOffBranch,
                    $runOffAvailable,
                    $headcount,
                    $remainingWorkingDays
                ): array {
                    $dashboardRow = $dashboardRows->firstWhere('key', $definition['dashboard_key']);
                    $productRunOffRow = collect((array) data_get($runOffReport, 'rows', []))
                        ->first(function (array $row) use ($definition, $runOffBranch): bool {
                            return in_array(strtoupper(trim((string) ($row['category'] ?? ''))), $definition['run_off_categories'], true)
                                && strtolower(trim((string) ($row['branch'] ?? ''))) === strtolower(trim($runOffBranch));
                        });
                    $currentOs = (float) data_get($dashboardRow, 'values.current', 0.0);
                    $rka = (float) data_get($dashboardRow, 'values.rka', 0.0);
                    $runOff = $runOffAvailable && is_array($productRunOffRow)
                        ? (float) data_get($productRunOffRow, 'remaining_amount_cents', 0) / 100
                        : 0.0;

                    return [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        ...$this->calculateRealizationNeed($currentOs, $rka, $runOff, $headcount, $remainingWorkingDays),
                        'available' => $rka > 0.0 && $headcount > 0 && $runOffAvailable,
                    ];
                })
                ->values()
                ->all();

            return [
                ...$this->calculateRealizationNeed(
                    $currentOs,
                    $rka,
                    $runOffAmount,
                    $headcount,
                    $remainingWorkingDays
                ),
                'available' => $rka > 0.0 && $headcount > 0 && $runOffAvailable,
                'scope_label' => (string) ($branchScope['label'] ?? 'Area 6'),
                'period' => $period,
                'period_label' => $periodDate->translatedFormat('d M Y'),
                'rka_period_label' => (string) data_get($dashboard, 'selected_rka_label', $periodDate->translatedFormat('F Y')),
                'run_off_period' => $runOffLatestPeriod !== '' ? $runOffLatestPeriod : null,
                'run_off_period_label' => $runOffLatestPeriod !== ''
                    ? Carbon::parse($runOffLatestPeriod)->translatedFormat('d M Y')
                    : '-',
                'total_working_days' => $totalWorkingDays,
                'elapsed_working_days' => $elapsedWorkingDays,
                'remaining_working_days' => $remainingWorkingDays,
                'products' => $productNeeds,
                'error' => ! $runOffAvailable
                    ? ((string) data_get($runOffReport, 'error', '') ?: 'Sisa Run Off untuk bulan posisi belum tersedia.')
                    : '',
            ];
        } catch (Throwable $exception) {
            Log::warning('Kebutuhan realisasi landing Mikro gagal dihitung.', [
                'period' => $period,
                'scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                'error' => $exception->getMessage(),
            ]);

            return [
                ...$this->calculateRealizationNeed(0.0, 0.0, 0.0, $headcount, $remainingWorkingDays),
                'available' => false,
                'scope_label' => (string) ($branchScope['label'] ?? 'Area 6'),
                'period' => $period,
                'period_label' => $periodDate->translatedFormat('d M Y'),
                'rka_period_label' => $periodDate->translatedFormat('F Y'),
                'run_off_period' => null,
                'run_off_period_label' => '-',
                'total_working_days' => $totalWorkingDays,
                'elapsed_working_days' => $elapsedWorkingDays,
                'remaining_working_days' => $remainingWorkingDays,
                'products' => collect($this->microNeedProductDefinitions())
                    ->map(fn (array $definition): array => [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        ...$this->calculateRealizationNeed(0.0, 0.0, 0.0, $headcount, $remainingWorkingDays),
                        'available' => false,
                    ])
                    ->values()
                    ->all(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /** @return array<int, array{key:string, label:string, dashboard_key:string, run_off_categories:array<int, string>}> */
    private function microNeedProductDefinitions(): array
    {
        return [
            ['key' => 'kur-kecil', 'label' => 'KUR Kecil', 'dashboard_key' => 'kur_kecil_os', 'run_off_categories' => ['KUR KECIL']],
            ['key' => 'kupedes', 'label' => 'Kupedes', 'dashboard_key' => 'kupedes_os', 'run_off_categories' => ['KUPEDES']],
            ['key' => 'kur-mikro', 'label' => 'KUR Mikro', 'dashboard_key' => 'kur_mikro_os', 'run_off_categories' => ['KUR MIKRO']],
            ['key' => 'briguna-mikro', 'label' => 'Briguna Mikro', 'dashboard_key' => 'briguna_mikro_os', 'run_off_categories' => ['BRIGUNA', 'BRIGUNA MIKRO']],
            ['key' => 'kur-kpp', 'label' => 'KUR KPP', 'dashboard_key' => 'kur_kpp_os', 'run_off_categories' => ['KPP', 'KUR KPP']],
        ];
    }

    /** @return array<string, int|float> */
    private function calculateRealizationNeed(
        float $currentOs,
        float $rka,
        float $runOffAmount,
        int $headcount,
        int $remainingWorkingDays
    ): array {
        $currentOs = is_finite($currentOs) ? max(0.0, $currentOs) : 0.0;
        $rka = is_finite($rka) ? max(0.0, $rka) : 0.0;
        $runOffAmount = is_finite($runOffAmount) ? max(0.0, $runOffAmount) : 0.0;
        $signedRkaGap = $rka > 0.0 ? $rka - $currentOs : 0.0;
        $totalNeed = $signedRkaGap + $runOffAmount;
        $needPerMantri = $headcount > 0 ? $totalNeed / $headcount : 0.0;

        return [
            'current_os' => $currentOs,
            'rka' => $rka,
            'signed_rka_gap' => $signedRkaGap,
            'rka_gap' => $signedRkaGap,
            'run_off' => $runOffAmount,
            'total_need' => $totalNeed,
            'total_mantri' => max(0, $headcount),
            'need_per_mantri' => $needPerMantri,
            'need_per_mantri_per_hke' => $remainingWorkingDays > 0
                ? $needPerMantri / $remainingWorkingDays
                : $needPerMantri,
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function buildBurdenPayload(string $period, ?string $previousPeriod, ?string $ytdPeriod, ?array $branchScope): array
    {
        $currentUnits = $this->portfolioAggregate($period, $branchScope, 'unit');
        $previousUnits = $previousPeriod ? $this->portfolioAggregate($previousPeriod, $branchScope, 'unit') : [];

        return [
            'comparison' => 'mtd',
            'comparison_period' => $previousPeriod,
            'units' => $this->rankBurden($currentUnits, $previousUnits, [], 5),
            'branches' => null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<string, array<string, mixed>>  $previous
     * @param  array<string, array<string, mixed>>  $ytd
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function rankBurden(array $current, array $previous, array $ytd, int $limit): array
    {
        $keys = array_unique(array_merge(array_keys($current), array_keys($previous)));
        $rows = collect($keys)->map(function (string $key) use ($current, $previous, $ytd): array {
            $identity = $current[$key] ?? $previous[$key] ?? $ytd[$key];
            $currentRow = $current[$key] ?? ['os' => 0.0, 'sml' => 0.0, 'npl' => 0.0];
            $previousRow = $previous[$key] ?? ['os' => 0.0, 'sml' => 0.0, 'npl' => 0.0];
            $ytdRow = $ytd[$key] ?? ['os' => 0.0, 'sml' => 0.0, 'npl' => 0.0];

            return [
                'key' => $key,
                'label' => (string) ($identity['label'] ?? '-'),
                'branch' => (string) ($identity['branch'] ?? ''),
                'os' => (float) $currentRow['os'],
                'os_delta' => (float) $currentRow['os'] - (float) $previousRow['os'],
                'sml' => (float) $currentRow['sml'],
                'sml_delta' => (float) $currentRow['sml'] - (float) $previousRow['sml'],
                'npl' => (float) $currentRow['npl'],
                'npl_delta' => (float) $currentRow['npl'] - (float) $previousRow['npl'],
            ];
        });

        return [
            'os' => $rows->sortBy('os_delta')->take($limit)->values()->all(),
            'sml' => $rows->sortByDesc('sml_delta')->take($limit)->values()->all(),
            'npl' => $rows->sortByDesc('npl_delta')->take($limit)->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, array<string, mixed>>
     */
    private function portfolioAggregate(string $period, ?array $branchScope, string $mode): array
    {
        if (! $this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [];
        }

        $summaryRows = $mode === 'branch';
        $branchColumn = $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : 'branch_label';
        $unitColumn = $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : 'uker_label';

        return $this->harianSnapshotQuery($period, $branchScope, $summaryRows)
            ->select([$branchColumn, $unitColumn, 'micro_os', 'micro_sml', 'micro_npl'])
            ->get()
            ->map(function ($row) use ($branchColumn, $unitColumn, $summaryRows): array {
                $branch = $this->branchLabel((string) ($row->{$branchColumn} ?? ''));
                $label = $summaryRows
                    ? $branch
                    : (trim((string) ($row->{$unitColumn} ?? '')) ?: 'Tidak Terpetakan');

                return [
                    'key' => strtoupper($branch.'|'.$label),
                    'label' => $label,
                    'branch' => $branch,
                    'os' => (float) ($row->micro_os ?? 0.0),
                    'sml' => (float) ($row->micro_sml ?? 0.0),
                    'npl' => (float) ($row->micro_npl ?? 0.0),
                ];
            })
            ->filter(fn (array $row): bool => $summaryRows || $this->isOperationalUnitLabel((string) $row['label']))
            ->groupBy('key')
            ->map(function (Collection $rows): array {
                $identity = $rows->first();

                return [
                    'label' => (string) ($identity['label'] ?? 'Tidak Terpetakan'),
                    'branch' => (string) ($identity['branch'] ?? 'Tidak Terpetakan'),
                    'os' => (float) $rows->sum('os'),
                    'sml' => (float) $rows->sum('sml'),
                    'npl' => (float) $rows->sum('npl'),
                ];
            })
            ->all();
    }

    private function isOperationalUnitLabel(string $label): bool
    {
        $normalized = strtoupper(trim($label));
        $normalized = preg_replace('/^\d+\s*(?:--|-)+\s*/', '', $normalized) ?? $normalized;

        return $normalized !== ''
            && preg_match('/^(?:KC|KCP|KANCA|KANTOR\s+CABANG(?:\s+PEMBANTU)?)\b/', $normalized) !== 1;
    }

    /** @param array<string, mixed>|null $branchScope */
    private function resolvePeriod(?string $requestedPeriod, ?array $branchScope): ?string
    {
        $query = $this->periodLookupQuery();
        $this->applyDailyMicroFilter($query);
        $this->applyDailyBranchFilter($query, '', $branchScope);
        if ($requestedPeriod !== null && trim($requestedPeriod) !== '') {
            $query->where('periode', '<=', Carbon::parse($requestedPeriod)->toDateString());
        }

        return $query->orderByDesc('periode')->value('periode');
    }

    /** @param array<string, mixed>|null $branchScope */
    private function resolvePeriodBefore(string $exclusiveDate, ?array $branchScope): ?string
    {
        $query = $this->periodLookupQuery()->where('periode', '<', $exclusiveDate);
        $this->applyDailyMicroFilter($query);
        $this->applyDailyBranchFilter($query, '', $branchScope);

        return $query->orderByDesc('periode')->value('periode');
    }

    /** @param array<string, mixed>|null $branchScope */
    private function harianSnapshotQuery(string $period, ?array $branchScope, bool $summaryRows): Builder
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $period);
        $branchColumn = $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : 'branch_label';
        $branches = $branchScope !== null
            ? [strtoupper((string) ($branchScope['upper_label'] ?? $branchScope['label'] ?? ''))]
            : array_map(fn (string $branch): string => 'KC '.$branch, self::AREA_BRANCHES);
        $query->whereIn(DB::raw("UPPER(TRIM({$branchColumn}))"), array_values(array_filter($branches)));

        if (
            $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key')
            && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')
        ) {
            return $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        return $query;
    }

    private function periodLookupQuery(): Builder
    {
        $tableSql = $this->indexHintResolver->qualify(
            self::SOURCE_TABLE,
            null,
            self::PERIOD_LOOKUP_INDEXES
        );

        return DB::table(DB::raw($tableSql));
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExistsMemo[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), $this->columnListing($table), true);
    }

    /** @return array<int, string> */
    private function columnListing(string $table): array
    {
        if (! array_key_exists($table, $this->columnListingMemo)) {
            $this->columnListingMemo[$table] = array_map(
                static fn (string $column): string => strtolower($column),
                Schema::getColumnListing($table)
            );
        }

        return $this->columnListingMemo[$table];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function applyBranchFilter(Builder $query, string $column, ?array $branchScope): void
    {
        $branches = $branchScope !== null
            ? [(string) ($branchScope['plain_label'] ?? $branchScope['label'] ?? '')]
            : self::AREA_BRANCHES;
        $query->where(function (Builder $scope) use ($column, $branches): void {
            foreach (array_filter($branches) as $index => $branch) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $scope->{$method}("UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?", ['%'.strtoupper(trim($branch)).'%']);
            }
        });
    }

    private function decisionRoleKey(string $role): string
    {
        $role = strtoupper(trim($role));

        return match (true) {
            str_contains($role, 'SBOH') => 'sboh',
            str_contains($role, 'RSBH'), str_contains($role, 'RMBH'),
            str_contains($role, 'BOH'), str_contains($role, 'PINCA') => 'boh',
            str_contains($role, 'MBM') => 'mbm',
            default => 'ka_unit',
        };
    }

    private function fallbackRole(float $plafon): string
    {
        return match (true) {
            $plafon < 100_000_000 => 'KA UNIT',
            $plafon <= 250_000_000 => 'MBM',
            default => 'BOH',
        };
    }

    private function isMusiman(string $pattern, int $frequency): bool
    {
        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim($pattern))) ?? '';
        if (preg_match('/^BULANAN(?:\s|$)/', $normalized) === 1) {
            return false;
        }
        if ($normalized === '' || $normalized === 'TIDAK TERPETAKAN') {
            return $frequency > 1;
        }

        return true;
    }

    private function musimanPaymentDetailKey(string $pattern, int $frequency): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim($pattern))) ?? '';

        return match (true) {
            str_contains($normalized, 'PERIODIK') => 'periodik',
            str_contains($normalized, 'MUSIMAN')
                && preg_match('/(?:^|\s)1\s*X(?:\s|$)/', $normalized) === 1 => 'satu_kali',
            ($normalized === '' || $normalized === 'TIDAK TERPETAKAN') && $frequency > 1 => 'periodik',
            default => 'lainnya',
        };
    }

    /** @return array{key:string, label:string, months:?int} */
    private function normaliseLoanTerm(mixed $value): array
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw !== '' && preg_match('/\d+/', $raw, $matches) === 1) {
            $months = max(1, (int) $matches[0]);

            return [
                'key' => 'm'.$months,
                'label' => $months.' Bulan',
                'months' => $months,
            ];
        }

        return ['key' => 'unknown', 'label' => 'Jangka Waktu Belum Terpetakan', 'months' => null];
    }

    private function branchLabel(string $value): string
    {
        $normalized = strtoupper(trim($value));
        foreach (self::AREA_BRANCHES as $branch) {
            if (str_contains($normalized, $branch)) {
                return 'KC '.$branch;
            }
        }

        return $normalized !== '' ? $normalized : 'TIDAK TERPETAKAN';
    }

    private function normaliseToken(string $value): string
    {
        return strtolower(trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($value)) ?? '', '-'));
    }

    /** @param array<string, mixed>|null $branchScope */
    private function emptyPayload(?array $branchScope): array
    {
        return [
            'meta' => [
                'available' => false,
                'period' => null,
                'period_label' => '-',
                'previous_period' => null,
                'previous_period_label' => '-',
                'ytd_period' => null,
                'ytd_period_label' => '-',
                'scope' => $branchScope === null ? UserBranchScope::AREA_SCOPE : 'branch',
                'scope_label' => $branchScope['label'] ?? 'Area 6',
                'is_area' => $branchScope === null,
                'source' => 'Daily Loan Dinamis',
                'generated_at' => null,
                'error' => 'Data Daily Loan Dinamis belum tersedia.',
            ],
            'products_position' => ['available' => false, 'total' => 0.0, 'items' => []],
            'realization' => [
                'available' => false,
                'total' => ['deb' => 0, 'amount' => 0.0],
                'mantri' => ['eligible' => 0, 'realized' => 0, 'not_realized' => 0],
                'mantri_roster' => [
                    'total_active' => 0,
                    'pt' => 0,
                    'contract' => 0,
                    'briguna' => 0,
                    'kupedes_eligible' => 0,
                    'kupedes_realized' => 0,
                    'kupedes_not_realized' => 0,
                    'daily_placement_matched' => 0,
                    'branches' => [],
                    'source' => 'BRIHC aktif dipadukan dengan penempatan dan realisasi Daily Loan Dinamis',
                ],
                'kupedes_not_realized' => [],
                'types' => [],
                'products' => [],
                'patterns' => [],
                'decisions' => [],
            ],
            'net_disbursement' => [
                'available' => false,
                'total' => ['deb' => 0, 'amount' => 0.0],
                'types' => [],
                'products' => [],
                'patterns' => [],
                'decisions' => [],
            ],
            'pdwk_limits' => [
                'available' => false,
                'default_role' => 'mbm',
                'roles' => [],
                'source' => 'PDWK MBM dan Kaunit.xlsx',
            ],
            'decision_ranking' => [
                'available' => false,
                'default_metric' => 'plafond',
                'metrics' => [
                    'plafond' => ['key' => 'plafond', 'label' => 'Plafon', 'available' => false, 'top' => [], 'bottom' => []],
                    'net' => ['key' => 'net', 'label' => 'Nett Disbursement', 'available' => false, 'top' => [], 'bottom' => []],
                ],
            ],
            'realization_need' => [
                'available' => false,
                'current_os' => 0.0,
                'rka' => 0.0,
                'signed_rka_gap' => 0.0,
                'rka_gap' => 0.0,
                'run_off' => 0.0,
                'total_need' => 0.0,
                'total_mantri' => 0,
                'need_per_mantri' => 0.0,
                'need_per_mantri_per_hke' => 0.0,
                'total_working_days' => 0,
                'elapsed_working_days' => 0,
                'remaining_working_days' => 0,
                'products' => collect($this->microNeedProductDefinitions())
                    ->map(fn (array $definition): array => [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        ...$this->calculateRealizationNeed(0.0, 0.0, 0.0, 0, 0),
                        'available' => false,
                    ])
                    ->values()
                    ->all(),
                'error' => 'Sumber RKA dan Run Off belum tersedia.',
            ],
            'ph' => $this->emptyPhSummary(),
            'mantri_performance' => [
                'available' => false,
                'working_days' => 0,
                'daily_period' => null,
                'daily_period_label' => '-',
                'accumulation_start' => null,
                'accumulation_label' => '-',
                'rows' => [],
                'total' => [],
            ],
            'burden' => [
                'comparison' => 'mtd',
                'comparison_period' => null,
                'units' => ['os' => [], 'sml' => [], 'npl' => []],
                'branches' => null,
            ],
            'unproductive_mantri' => [
                'available' => false,
                'period_label' => '-',
                'total_mantri' => 0,
                'closed_periods' => [],
                'totals' => collect([
                    'month_1' => '1 bulan',
                    'month_3' => '3 bulan berturut-turut',
                    'month_6' => '6 bulan berturut-turut',
                ])->mapWithKeys(static fn (string $label, string $key): array => [$key => [
                    'key' => $key,
                    'label' => $label,
                    'count' => 0,
                    'percentage' => 0.0,
                    'monitored' => 0,
                    'enabled' => false,
                    'mantri' => [],
                ]])->all(),
                'branches' => [],
                'source' => 'Performance RM Snapshot - Daily Loan Dinamis',
            ],
            'billing' => [
                'available' => false,
                'period' => null,
                'current_day' => 0,
                'days_in_month' => 0,
                'm0' => [],
                'm1' => [],
                'cards' => [],
            ],
        ];
    }

    /**
     * Jadwal & realisasi billing mikro harian berdasarkan Next Payment Date (NPD)
     * beserta tolak ukur komparasi bulan sebelumnya (M-1).
     *
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function billingSchedule(?array $branchScope, ?string $period = null, ?string $previousPeriod = null): array
    {
        if (! $this->hasTable(self::SOURCE_TABLE) || ! $this->hasColumn(self::SOURCE_TABLE, 'next_pmt_date')) {
            return [
                'available' => false,
                'period' => null,
                'current_day' => 0,
                'days_in_month' => 0,
                'm0' => [],
                'm1' => [],
                'cards' => [],
            ];
        }

        $period = $period ?? $this->resolvePeriod(null, $branchScope);
        if ($period === null) {
            return [
                'available' => false,
                'period' => null,
                'current_day' => 0,
                'days_in_month' => 0,
                'm0' => [],
                'm1' => [],
                'cards' => [],
            ];
        }

        $currentCarbon = Carbon::parse($period);
        $m0Start = $currentCarbon->copy()->startOfMonth()->toDateString();
        $m0End = $currentCarbon->copy()->endOfMonth()->toDateString();
        $m0DaysCount = $currentCarbon->daysInMonth;
        $m0CurrentDay = (int) $currentCarbon->format('d');
        $m0MonthLabel = $currentCarbon->translatedFormat('F Y');

        $m0Baseline = $previousPeriod ?? $this->resolvePeriodBefore($m0Start, $branchScope);
        if ($m0Baseline === null) {
            $hasRows = DB::table(self::SOURCE_TABLE)
                ->where('periode', $period)
                ->whereBetween('next_pmt_date', [$m0Start, $m0End])
                ->exists();
            if ($hasRows) {
                $m0Baseline = $period;
            } else {
                return [
                    'available' => false,
                    'period' => $period,
                    'current_day' => $m0CurrentDay,
                    'days_in_month' => $m0DaysCount,
                    'm0' => [],
                    'm1' => [],
                    'cards' => [],
                ];
            }
        }

        $driver = DB::connection()->getDriverName();
        $dayFn = static fn (string $col): string => $driver === 'sqlite'
            ? "CAST(strftime('%d', {$col}) AS INTEGER)"
            : "DAY({$col})";

        // 1. Query M0 Scheduled Billing from baseline
        $m0BillingQuery = DB::table(self::SOURCE_TABLE)
            ->where('periode', $m0Baseline)
            ->whereBetween('next_pmt_date', [$m0Start, $m0End]);
        $this->applyDailyMicroFilter($m0BillingQuery);
        $this->applyDailyBranchFilter($m0BillingQuery, '', $branchScope);

        $m0BillingRows = $m0BillingQuery
            ->selectRaw("{$dayFn('next_pmt_date')} as day, COUNT(*) as debitur, SUM(baki_debet1) as os")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        // 2. Query M0 Paid debtors as of $period
        $m0PaidQuery = DB::table(self::SOURCE_TABLE.' as a')
            ->leftJoin(self::SOURCE_TABLE.' as b', function ($join) use ($period): void {
                $join->on('a.nomor_rekening1', '=', 'b.nomor_rekening1')
                    ->where('b.periode', '=', $period);
            })
            ->where('a.periode', $m0Baseline)
            ->whereBetween('a.next_pmt_date', [$m0Start, $m0End])
            ->where(function (Builder $q): void {
                $q->whereNull('b.nomor_rekening1')
                    ->orWhereColumn('b.next_pmt_date', '>', 'a.next_pmt_date');
            });
        $this->applyDailyMicroFilter($m0PaidQuery, 'a');
        $this->applyDailyBranchFilter($m0PaidQuery, 'a', $branchScope);

        $m0PaidRows = $m0PaidQuery
            ->selectRaw("{$dayFn('a.next_pmt_date')} as day, COUNT(*) as debitur, SUM(a.baki_debet1) as os")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        // 3. Resolve M-1 baseline & completed period
        // Anchor at the first day before subtracting a month so dates such as
        // 31 May cannot overflow back into May when resolving April.
        $m1Month = $currentCarbon->copy()->startOfMonth()->subMonth();
        $m1Start = $m1Month->copy()->startOfMonth()->toDateString();
        $m1End = $m1Month->copy()->endOfMonth()->toDateString();
        $m1DaysCount = Carbon::parse($m1Start)->daysInMonth;
        $m1MonthLabel = Carbon::parse($m1Start)->translatedFormat('F Y');
        $m1SettledPeriod = $m0Baseline;
        $m1Baseline = $this->resolvePeriodBefore($m1Start, $branchScope);

        $m1BillingRows = collect();
        $m1PaidRows = collect();

        if ($m1Baseline !== null && $m1SettledPeriod !== null) {
            $m1BillQ = DB::table(self::SOURCE_TABLE)
                ->where('periode', $m1Baseline)
                ->whereBetween('next_pmt_date', [$m1Start, $m1End]);
            $this->applyDailyMicroFilter($m1BillQ);
            $this->applyDailyBranchFilter($m1BillQ, '', $branchScope);

            $m1BillingRows = $m1BillQ
                ->selectRaw("{$dayFn('next_pmt_date')} as day, COUNT(*) as debitur, SUM(baki_debet1) as os")
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $m1PaidQ = DB::table(self::SOURCE_TABLE.' as a')
                ->leftJoin(self::SOURCE_TABLE.' as b', function ($join) use ($m1SettledPeriod): void {
                    $join->on('a.nomor_rekening1', '=', 'b.nomor_rekening1')
                        ->where('b.periode', '=', $m1SettledPeriod);
                })
                ->where('a.periode', $m1Baseline)
                ->whereBetween('a.next_pmt_date', [$m1Start, $m1End])
                ->where(function (Builder $q): void {
                    $q->whereNull('b.nomor_rekening1')
                        ->orWhereColumn('b.next_pmt_date', '>', 'a.next_pmt_date');
                });
            $this->applyDailyMicroFilter($m1PaidQ, 'a');
            $this->applyDailyBranchFilter($m1PaidQ, 'a', $branchScope);

            $m1PaidRows = $m1PaidQ
                ->selectRaw("{$dayFn('a.next_pmt_date')} as day, COUNT(*) as debitur, SUM(a.baki_debet1) as os")
                ->groupBy('day')
                ->get()
                ->keyBy('day');
        }

        // 4. Build Days Array (1..$m0DaysCount)
        $dailyCards = [];
        $totalM0BillingDeb = 0;
        $totalM0BillingOs = 0.0;
        $totalM0PaidDeb = 0;
        $totalM0PaidOs = 0.0;
        $totalM0DueSoFarBillingDeb = 0;
        $totalM0DueSoFarBillingOs = 0.0;

        // Closing M-1 harus memakai seluruh hari di bulan M-1. Jangan menjumlahkannya
        // di dalam kalender M0 karena jumlah hari antarbulan dapat berbeda
        // (contoh: September 30 hari dibanding Agustus 31 hari).
        $sumDebitur = static fn ($rows): int => (int) $rows->sum(
            static fn ($row): int => (int) ($row->debitur ?? 0)
        );
        $sumOs = static fn ($rows): float => (float) $rows->sum(
            static fn ($row): float => (float) ($row->os ?? 0.0)
        );
        $m1SameDayCutoff = min($m0CurrentDay, $m1DaysCount);
        $m1BillingRowsDueSoFar = $m1BillingRows->filter(
            static fn ($row, $day): bool => (int) $day <= $m1SameDayCutoff
        );
        $m1PaidRowsDueSoFar = $m1PaidRows->filter(
            static fn ($row, $day): bool => (int) $day <= $m1SameDayCutoff
        );

        $totalM1BillingDeb = $sumDebitur($m1BillingRows);
        $totalM1BillingOs = $sumOs($m1BillingRows);
        $totalM1PaidDeb = $sumDebitur($m1PaidRows);
        $totalM1PaidOs = $sumOs($m1PaidRows);
        $totalM1DueSoFarBillingDeb = $sumDebitur($m1BillingRowsDueSoFar);
        $totalM1DueSoFarBillingOs = $sumOs($m1BillingRowsDueSoFar);
        $totalM1DueSoFarPaidDeb = $sumDebitur($m1PaidRowsDueSoFar);
        $totalM1DueSoFarPaidOs = $sumOs($m1PaidRowsDueSoFar);

        for ($d = 1; $d <= $m0DaysCount; $d++) {
            $dateStr = sprintf('%s-%02d', substr($m0Start, 0, 7), $d);
            $carbonDay = Carbon::parse($dateStr);
            $dayOfWeek = (int) $carbonDay->format('N');
            $isWeekend = ($dayOfWeek >= 6);

            $m0Bill = $m0BillingRows->get($d);
            $m0Paid = $m0PaidRows->get($d);

            $bDeb = (int) ($m0Bill->debitur ?? 0);
            $bOs = (float) ($m0Bill->os ?? 0.0);
            $pDeb = (int) ($m0Paid->debitur ?? 0);
            $pOs = (float) ($m0Paid->os ?? 0.0);

            $totalM0BillingDeb += $bDeb;
            $totalM0BillingOs += $bOs;

            $isPast = ($d < $m0CurrentDay);
            $isToday = ($d === $m0CurrentDay);
            $isDue = ($d <= $m0CurrentDay);

            if ($isDue) {
                $totalM0PaidDeb += $pDeb;
                $totalM0PaidOs += $pOs;
                $totalM0DueSoFarBillingDeb += $bDeb;
                $totalM0DueSoFarBillingOs += $bOs;
            }

            // Bandingkan dengan tanggal kalender yang sama di M-1. Jika tanggal
            // M0 tidak tersedia di M-1 (mis. 31 vs bulan 30 hari), gunakan
            // tanggal terakhir M-1 sebagai cutoff yang paling sebanding.
            $comparisonDay = min($d, $m1DaysCount);
            $comparisonDate = Carbon::parse($m1Start)->day($comparisonDay);
            $m1Bill = $m1BillingRows->get($comparisonDay);
            $m1Paid = $m1PaidRows->get($comparisonDay);

            $m1bDeb = (int) ($m1Bill->debitur ?? 0);
            $m1bOs = (float) ($m1Bill->os ?? 0.0);
            $m1pDeb = (int) ($m1Paid->debitur ?? 0);
            $m1pOs = (float) ($m1Paid->os ?? 0.0);

            $pctDeb = $bDeb > 0 ? round(($pDeb / $bDeb) * 100, 1) : 0.0;
            $pctOs = $bOs > 0 ? round(($pOs / $bOs) * 100, 1) : 0.0;

            $m1PctDeb = $m1bDeb > 0 ? round(($m1pDeb / $m1bDeb) * 100, 1) : 0.0;
            $m1PctOs = $m1bOs > 0 ? round(($m1pOs / $m1bOs) * 100, 1) : 0.0;

            $status = $isToday ? 'today' : ($isPast ? 'past' : 'upcoming');

            $dailyCards[] = [
                'day' => $d,
                'date' => $dateStr,
                'day_name' => $carbonDay->translatedFormat('D'),
                'is_weekend' => $isWeekend,
                'status' => $status,
                'is_past' => $isPast,
                'is_today' => $isToday,
                'is_due' => $isDue,
                'comparison_day' => $comparisonDay,
                'comparison_date' => $comparisonDate->toDateString(),
                'comparison_date_label' => $comparisonDate->translatedFormat('d M'),
                'comparison_date_adjusted' => $comparisonDay !== $d,
                // M0
                'billing_debitur' => $bDeb,
                'billing_os' => $bOs,
                'paid_debitur' => $pDeb,
                'paid_os' => $pOs,
                'pct_debitur' => $pctDeb,
                'pct_os' => $pctOs,
                // M-1
                'm1_billing_debitur' => $m1bDeb,
                'm1_billing_os' => $m1bOs,
                'm1_paid_debitur' => $m1pDeb,
                'm1_paid_os' => $m1pOs,
                'm1_pct_debitur' => $m1PctDeb,
                'm1_pct_os' => $m1PctOs,
                // Delta vs M-1 for this day
                'delta_pct_os' => round($pctOs - $m1PctOs, 1),
                'delta_pct_deb' => round($pctDeb - $m1PctDeb, 1),
            ];
        }

        return [
            'available' => true,
            'period' => $period,
            'current_day' => $m0CurrentDay,
            'days_in_month' => $m0DaysCount,
            'm0' => [
                'month_label' => $m0MonthLabel,
                'baseline_period' => $m0Baseline,
                'latest_period' => $period,
                'total_billing_debitur' => $totalM0BillingDeb,
                'total_billing_os' => $totalM0BillingOs,
                'due_so_far_billing_debitur' => $totalM0DueSoFarBillingDeb,
                'due_so_far_billing_os' => $totalM0DueSoFarBillingOs,
                'paid_debitur' => $totalM0PaidDeb,
                'paid_os' => $totalM0PaidOs,
                'collection_rate_deb' => $totalM0DueSoFarBillingDeb > 0 ? round(($totalM0PaidDeb / $totalM0DueSoFarBillingDeb) * 100, 1) : 0.0,
                'collection_rate_os' => $totalM0DueSoFarBillingOs > 0 ? round(($totalM0PaidOs / $totalM0DueSoFarBillingOs) * 100, 1) : 0.0,
                'overall_progress_os' => $totalM0BillingOs > 0 ? round(($totalM0PaidOs / $totalM0BillingOs) * 100, 1) : 0.0,
            ],
            'm1' => [
                'month_label' => $m1MonthLabel,
                'baseline_period' => $m1Baseline,
                'settled_period' => $m1SettledPeriod,
                'total_billing_debitur' => $totalM1BillingDeb,
                'total_billing_os' => $totalM1BillingOs,
                'paid_debitur' => $totalM1PaidDeb,
                'paid_os' => $totalM1PaidOs,
                'due_so_far_paid_debitur' => $totalM1DueSoFarPaidDeb,
                'due_so_far_paid_os' => $totalM1DueSoFarPaidOs,
                'collection_rate_deb' => $totalM1BillingDeb > 0 ? round(($totalM1PaidDeb / $totalM1BillingDeb) * 100, 1) : 0.0,
                'collection_rate_os' => $totalM1BillingOs > 0 ? round(($totalM1PaidOs / $totalM1BillingOs) * 100, 1) : 0.0,
                'same_day_collection_rate_os' => $totalM1DueSoFarBillingOs > 0 ? round(($totalM1DueSoFarPaidOs / $totalM1DueSoFarBillingOs) * 100, 1) : 0.0,
                'same_day_collection_rate_deb' => $totalM1DueSoFarBillingDeb > 0 ? round(($totalM1DueSoFarPaidDeb / $totalM1DueSoFarBillingDeb) * 100, 1) : 0.0,
                'same_day_cutoff' => $m1SameDayCutoff,
                'same_day_cutoff_label' => Carbon::parse($m1Start)->day($m1SameDayCutoff)->translatedFormat('d M Y'),
            ],
            'cards' => $dailyCards,
        ];
    }
}
