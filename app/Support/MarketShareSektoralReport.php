<?php

namespace App\Support;

final class MarketShareSektoralReport
{
    private const DEFAULT_SCOPE = 'area6';

    private const SCOPES = [
        'area6' => 'Area 6 (Semua Cabang)',
        'madiun' => 'KC Madiun',
        'magetan' => 'KC Magetan',
        'ngawi' => 'KC Ngawi',
        'ponorogo' => 'KC Ponorogo',
    ];

    /**
     * Snapshot terstruktur dari workbook "Sektoral per segmen Area 6 Maret.xlsx".
     * Kolom: scope|sektor|OS Mar-25|OS Des-25|OS Mar-26|SML Mar-26|NPL Mar-26|
     *         Industri OS Mar-26|Industri SML Mar-26|Industri NPL Mar-26.
     */
    private const ROWS = <<<'ROWS'
madiun|Administrasi Pemerintahan, Pertahanan Dan Jaminan Sosial Wajib|0|0|0|0|0|0.267795306|0|0.002570774
madiun|Aktivitas Badan Internasional dan Badan Ekstra Internasional Lainnya|0|0|0|0|0|0.0001677|0|0
madiun|Bukan Lapangan Usaha|234.5655499858|263.077501648|268.927980755|11.779028397|9.241664585|5048.047635261|139.920070712|88.143029551
madiun|Industri Pengolahan|282.4374695016|246.8885945351|238.4558306452|18.7471618254|15.2288974123|3536.318508288|26.270303852|16.778534685
madiun|Informasi Dan Komunikasi|2.926126|2.187946445|2.1280007084|0.066690072|0|1871.704885709|0.187917888|0.795085694
madiun|Jasa Kesehatan Dan Kegiatan Lainnya|6.2330125964|7.173074925|8.7229159864|0.512418399|0.2946374933|68.517594848|0.510555585|0.421737418
madiun|Jasa Keuangan Dan Asuransi|4.2399590498|4.0327264828|7.2737343628|0|0.01941984|26.778169372|0.542808769|0.435229544
madiun|Jasa Lainnya|281.8423169993|259.0088451058|251.1850577969|19.1227278698|18.0767400466|453.273374143|26.918942386|22.687557378
madiun|Jasa Pendidikan|8.7697053277|8.699499315|8.029273636|0.211600967|0.115586014|23.580674081|0.692011459|0.14656527
madiun|Jasa Perusahaan|6.4885304168|9.9505923424|7.4929703546|0.388627867|0.277642543|24.529479076|3.426641522|1.551366431
madiun|Konstruksi|7.3202475889|8.4357157139|27.8815161829|0.332835538|0.352937117|1169.327911481|7.42503311|22.161774028
madiun|Pengadaan Air, Pengelolaan Sampah, Limbah Dan Daur Ulang|0.601921807|0.683276507|0.526287303|0.023872513|0|4.251736844|0.182090573|0.212414846
madiun|Pengadaan Listrik Dan Gas|2.357461148|2.385970312|2.701104212|0.009296267|0.022186384|501.656609964|0.016289972|0.022164787
madiun|Penyediaan Akomodasi Dan Makan Minum|61.2439443116|52.665513601|58.8066255685|3.3787013193|2.461679278|270.425223473|9.990059151|7.245404084
madiun|Perdagangan Besar Dan Eceran, Reparasi Mobil Dan Motor|1748.0514403358|1626.3342081481|1647.368168966|122.2053925382|96.986502751|3587.59727828|443.356108328|156.721243176
madiun|Pertambangan Dan Penggalian|1.98557242|1.263725186|1.115822135|0.255896058|0.001385391|10.288245936|2.153833563|0.001383936
madiun|Pertanian, Kehutanan, dan Perikanan|1156.673108602|1210.7064049127|1210.0757868827|64.4716395493|46.080997482|1702.527428824|68.726516935|59.683490693
madiun|Real Estate|3.44641428|4.734611948|7.067240747|0|0|25.113136852|0.016832254|1.21014966
madiun|Transportasi Dan Pergudangan|24.212420166|16.094000356|16.5648758714|0.9742871214|0.874847014|108.080102722|3.375134913|1.491254653
magetan|Administrasi Pemerintahan, Pertahanan Dan Jaminan Sosial Wajib|0|0|0|0|0|0.062178003|0.057142996|0
magetan|Aktivitas Badan Internasional dan Badan Ekstra Internasional Lainnya|0|0|0|0|0|0|0|0
magetan|Bukan Lapangan Usaha|2.590601016|2.292121817|2.23819114|0|0|2062.079108948|48.163599235|26.733937477
magetan|Industri Pengolahan|189.1525045613|174.479934585|173.3333814629|20.3448621204|11.913378719|286.024759237|28.73075773|15.011206404
magetan|Informasi Dan Komunikasi|8.4281348943|7.9950757815|7.4266695465|0.037031404|2.865072242|10.154917418|0.090989893|2.864968359
magetan|Jasa Kesehatan Dan Kegiatan Lainnya|2.512001021|2.9699354212|2.8150162072|0.2020373032|0.015505024|5.697230634|0.219553203|0.015503564
magetan|Jasa Keuangan Dan Asuransi|0.170823465|0.095472956|0.16578626|0|0|24.686419835|2.056293461|0.00216892
magetan|Jasa Lainnya|190.203645017|174.6387382877|167.8181637788|13.7247444206|4.4543128746|229.431096356|16.311323661|5.299596971
magetan|Jasa Pendidikan|1.065366787|1.057853541|0.982968667|0|0|6.276246685|0|0.008095913
magetan|Jasa Perusahaan|3.5869503168|3.2784826718|3.2632732476|0.255190748|0.068545483|4.659669318|0.282884255|0.103072934
magetan|Konstruksi|2.833859978|3.210764124|3.159434014|0.501113834|0.125449225|21.12654768|0.737367523|0.125382318
magetan|Pengadaan Air, Pengelolaan Sampah, Limbah Dan Daur Ulang|0.472788619|0.404569712|1.057821954|0|0.030622751|3.04782232|0.086605673|0.030583066
magetan|Pengadaan Listrik Dan Gas|0.222501779|0.206980265|0.200272621|0.068030106|0|2.960102092|0.078270754|0
magetan|Penyediaan Akomodasi Dan Makan Minum|67.2036965911|59.0911971312|54.2050106496|5.0880231116|2.697896216|114.680745938|7.646659523|2.965470083
magetan|Perdagangan Besar Dan Eceran, Reparasi Mobil Dan Motor|1381.2315796988|1271.3024550733|1233.203647618|91.8209882776|45.2189300937|2062.70073606|151.15753432|88.458495267
magetan|Pertambangan Dan Penggalian|0.972117309|0.677277183|0.604455555|0|0.250080975|3.435727503|0|0.247279785
magetan|Pertanian, Kehutanan, dan Perikanan|984.8314923317|1002.5405627749|1026.4223336495|49.0699604209|30.0066222981|1520.202593857|66.719910087|39.19010692
magetan|Real Estate|0.438868756|10.580964656|23.42625012|0|0|33.37088615|0.155225516|1.308956195
magetan|Transportasi Dan Pergudangan|16.5188702684|16.739712021|16.4515060422|1.426248974|0.526227181|33.202405356|3.15858662|0.833622989
ngawi|Administrasi Pemerintahan, Pertahanan Dan Jaminan Sosial Wajib|0|0|0|0|0|0.250277777|0|0
ngawi|Aktivitas Badan Internasional dan Badan Ekstra Internasional Lainnya|0|0|0|0|0|0|0|0
ngawi|Bukan Lapangan Usaha|3.3657658581|2.873980146|2.817571325|0|0|2541.082826412|78.542059384|60.58758276
ngawi|Industri Pengolahan|117.8434593865|108.0307278274|109.6139737571|5.0229246265|6.387387464|959.687017187|16.371150716|423.689277118
ngawi|Informasi Dan Komunikasi|6.49515498|3.742896775|3.695813914|0.06920948|0|3.323523757|0.500199376|0
ngawi|Jasa Kesehatan Dan Kegiatan Lainnya|6.224124762|5.0320901396|4.7812919426|0.0755005738|0.7180978958|32.812966817|0.263641183|0.161781404
ngawi|Jasa Keuangan Dan Asuransi|0.27060235|0|0|0|0|8.948108895|0.425630309|0.10598923
ngawi|Jasa Lainnya|160.9922508714|158.442022321|154.7824915127|8.8840831909|4.1421470716|255.027931948|10.042336221|6.537108712
ngawi|Jasa Pendidikan|1.838133499|1.917658465|2.077854421|0.012044141|0.458629806|8.124198338|0.318740104|0.007797517
ngawi|Jasa Perusahaan|5.421087428|5.3499761264|5.0886042134|0.152802847|0.344658031|4.867013343|0.060631542|0
ngawi|Konstruksi|1.455357351|1.8124565956|1.8779017326|0.071335728|0.006644139|56.240545812|0.108004655|1.326041563
ngawi|Pengadaan Air, Pengelolaan Sampah, Limbah Dan Daur Ulang|1.214713972|2.125544572|2.26871229|0.009505077|0|1.72432338|0.031574396|0.012954855
ngawi|Pengadaan Listrik Dan Gas|0.579546148|0.491133481|0.526437881|0|0.099453996|5.357580595|0|0
ngawi|Penyediaan Akomodasi Dan Makan Minum|54.212185586|55.0604964051|56.2631273935|6.1695525575|1.456468313|127.413229059|5.810365661|3.068146426
ngawi|Perdagangan Besar Dan Eceran, Reparasi Mobil Dan Motor|1355.4386242619|1286.5533350363|1258.2145290135|76.4545546229|38.5132103553|2409.191572587|140.498583213|129.258069828
ngawi|Pertambangan Dan Penggalian|0.359323885|0.294434749|0.508876421|0.043765104|0.006227|8.450170907|0|1.10418511
ngawi|Pertanian, Kehutanan, dan Perikanan|1033.1353144529|1112.4624121061|1119.8158296051|37.4813776568|29.5657550742|1772.68852618|88.469174376|99.578292565
ngawi|Real Estate|0.684872839|12.659329277|31.393445132|0.020417352|0.006699562|34.516608583|0.03580762|0
ngawi|Transportasi Dan Pergudangan|21.908257027|20.8958119564|20.9059381031|1.2412514361|0.926560765|71.849987927|5.436638986|2.008198646
ponorogo|Administrasi Pemerintahan, Pertahanan Dan Jaminan Sosial Wajib|0|0.583334|0.583334|0|0.583334|0.583634|0|0.583334
ponorogo|Aktivitas Badan Internasional dan Badan Ekstra Internasional Lainnya|0|0|0|0|0|0|0|0
ponorogo|Bukan Lapangan Usaha|22.8024127957|20.34974569|18.0892679224|0.8931904684|0.558290159|2248.335244997|50.398063606|45.806149637
ponorogo|Industri Pengolahan|358.2773476977|306.9494398305|298.6047368112|23.8438485322|17.0396478969|649.319447686|28.223279759|23.818222082
ponorogo|Informasi Dan Komunikasi|3.869567772|7.206267925|6.806566419|0.027657417|0.36302662|27.368497553|0.037129569|18.177645704
ponorogo|Jasa Kesehatan Dan Kegiatan Lainnya|11.9910726038|10.289297926|9.9934959673|0.99207848|0.537995825|208.571620026|2.505407554|0.625002639
ponorogo|Jasa Keuangan Dan Asuransi|3.34415438|5.7122600966|5.7663629456|0.016325015|0.095254712|9.262910653|0.204059562|0.959753621
ponorogo|Jasa Lainnya|342.2571652422|307.8768175287|296.0771403841|22.0147403157|15.4951490397|373.265311283|25.97267757|17.292132669
ponorogo|Jasa Pendidikan|3.183456109|8.299232102|10.966976548|0.471011943|0.01924487|34.746725712|0.469104866|3.864906991
ponorogo|Jasa Perusahaan|11.019519546|8.88488387|8.0666563934|0.151455268|1.297814826|5.702186776|0.01388389|0
ponorogo|Konstruksi|18.983510276|20.63947722|20.415129902|0.959351518|0.335568721|59.761194241|7.022900519|11.209000121
ponorogo|Pengadaan Air, Pengelolaan Sampah, Limbah Dan Daur Ulang|0.901393028|1.097803673|0.995849879|0|0|8.917203344|2.415850179|3.7655
ponorogo|Pengadaan Listrik Dan Gas|0.503842609|1.638993|1.405964007|0.047420931|0.033609933|2249.42058016|0.047407219|0.033601697
ponorogo|Penyediaan Akomodasi Dan Makan Minum|68.838457689|64.0576111052|59.4034467392|5.135586709|3.6954146118|106.36898291|7.150147691|5.435154982
ponorogo|Perdagangan Besar Dan Eceran, Reparasi Mobil Dan Motor|1834.2570471775|1658.3821169668|1584.1490408389|129.6900226337|119.3138672073|2768.157211833|214.84036993|164.706928572
ponorogo|Pertambangan Dan Penggalian|5.2509605788|3.059335764|2.739503048|0.041607006|0.486398501|3.044126013|0.041599746|0.481355648
ponorogo|Pertanian, Kehutanan, dan Perikanan|1540.7002852123|1680.2010989055|1725.2432544488|117.7021265094|68.6826358275|1899.587931429|127.271259718|73.93410193
ponorogo|Real Estate|1.475821979|8.275350492|33.62412658|0|0.135936497|36.538924595|0.045684276|0.153112721
ponorogo|Transportasi Dan Pergudangan|37.108107611|31.6035465616|28.744013761|4.541118016|3.504906904|47.847360473|6.368896289|4.941790796
ROWS;

    public static function payload(?string $selectedScope = null): array
    {
        $scope = array_key_exists((string) $selectedScope, self::SCOPES)
            ? (string) $selectedScope
            : self::DEFAULT_SCOPE;
        $sourceRows = self::parseRows();
        $rawRows = $scope === self::DEFAULT_SCOPE
            ? self::aggregateAreaRows($sourceRows)
            : array_values(array_filter($sourceRows, static fn (array $row): bool => $row['scope'] === $scope));
        $total = self::sumRows($rawRows);
        $rows = array_map(static fn (array $row): array => self::decorateRow($row, $total), $rawRows);

        usort($rows, static fn (array $left, array $right): int => $right['bri_os'] <=> $left['bri_os']);

        return [
            'title' => 'Marketshare Sektoral',
            'subtitle' => 'Perbandingan posisi BRI dan potensi industri per sektor lapangan usaha.',
            'period' => 'Maret 2026',
            'unit' => 'Rp dalam Miliar',
            'source' => 'Sektoral per segmen Area 6 Maret.xlsx',
            'scopes' => self::SCOPES,
            'selected_scope' => $scope,
            'selected_scope_label' => self::SCOPES[$scope],
            'rows' => $rows,
            'total' => self::decorateRow([
                'scope' => $scope,
                'sector' => 'Grand Total',
                ...$total,
            ], $total),
            'charts' => [
                'top_sectors' => array_slice($rows, 0, 8),
                'comparison' => $rows,
            ],
        ];
    }

    /** @return array<int, array<string, float|string>> */
    private static function parseRows(): array
    {
        $rows = [];
        foreach (preg_split('/\R/', trim(self::ROWS)) ?: [] as $line) {
            $parts = explode('|', $line);
            if (count($parts) !== 10) {
                continue;
            }

            $rows[] = [
                'scope' => $parts[0],
                'sector' => $parts[1],
                'bri_os_mar25' => (float) $parts[2],
                'bri_os_dec25' => (float) $parts[3],
                'bri_os' => (float) $parts[4],
                'bri_sml' => (float) $parts[5],
                'bri_npl' => (float) $parts[6],
                'industry_os' => (float) $parts[7],
                'industry_sml' => (float) $parts[8],
                'industry_npl' => (float) $parts[9],
            ];
        }

        return $rows;
    }

    /** @param  array<int, array<string, float|string>>  $rows */
    private static function aggregateAreaRows(array $rows): array
    {
        $aggregated = [];
        foreach ($rows as $row) {
            $sector = (string) $row['sector'];
            $aggregated[$sector] ??= [
                'scope' => self::DEFAULT_SCOPE,
                'sector' => $sector,
                ...self::emptyTotals(),
            ];

            foreach (array_keys(self::emptyTotals()) as $metric) {
                $aggregated[$sector][$metric] += (float) $row[$metric];
            }
        }

        return array_values($aggregated);
    }

    /** @param  array<int, array<string, float|string>>  $rows */
    private static function sumRows(array $rows): array
    {
        $total = self::emptyTotals();
        foreach ($rows as $row) {
            foreach (array_keys($total) as $metric) {
                $total[$metric] += (float) $row[$metric];
            }
        }

        return $total;
    }

    /** @return array<string, float> */
    private static function emptyTotals(): array
    {
        return [
            'bri_os_mar25' => 0.0,
            'bri_os_dec25' => 0.0,
            'bri_os' => 0.0,
            'bri_sml' => 0.0,
            'bri_npl' => 0.0,
            'industry_os' => 0.0,
            'industry_sml' => 0.0,
            'industry_npl' => 0.0,
        ];
    }

    /**
     * @param  array<string, float|string>  $row
     * @param  array<string, float>  $total
     * @return array<string, float|string|null>
     */
    private static function decorateRow(array $row, array $total): array
    {
        $briOs = (float) $row['bri_os'];
        $industryOs = (float) $row['industry_os'];
        $potentialOs = max(0.0, $industryOs - $briOs);

        return [
            ...$row,
            'outside_os' => $potentialOs,
            'potential_os' => $potentialOs,
            'bri_prop' => self::ratio($briOs, $total['bri_os']),
            'bri_sml_ratio' => self::ratio((float) $row['bri_sml'], $briOs),
            'bri_npl_ratio' => self::ratio((float) $row['bri_npl'], $briOs),
            'market_share_os' => self::ratio($briOs, $industryOs),
            'market_share_sml' => self::ratio((float) $row['bri_sml'], (float) $row['industry_sml']),
            'market_share_npl' => self::ratio((float) $row['bri_npl'], (float) $row['industry_npl']),
            'yoy_os' => self::growth($briOs, (float) $row['bri_os_mar25']),
            'ytd_os' => self::growth($briOs, (float) $row['bri_os_dec25']),
        ];
    }

    private static function ratio(float $numerator, float $denominator): ?float
    {
        return abs($denominator) < 0.0000001 ? null : $numerator / $denominator;
    }

    private static function growth(float $current, float $previous): ?float
    {
        return abs($previous) < 0.0000001 ? null : ($current - $previous) / $previous;
    }
}
