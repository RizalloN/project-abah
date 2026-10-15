<?php

namespace Tests\Unit;

use App\Http\Controllers\EkosistemController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EkosistemMonitoringUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-10-15 09:00:00');
        $fixture = new EkosistemControllerTest('test_view_renders_with_menu_route');
        (new \ReflectionMethod($fixture, 'fakeSheets'))->invoke($fixture);
        $this->actingAs(new User(['pn' => 'test-area-monitor', 'name' => 'Area']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_summary_shows_the_real_map_and_separate_categories_without_loading_the_directory(): void
    {
        $view = $this->monitorView();
        $html = $view->render();
        $this->assertSame('overview', $view->getData()['activeView']);
        foreach (['Peta cakupan wilayah', 'Potensi Simpanan', 'Potensi Kredit', 'Potensi Payroll', 'Arus dana / tahun', 'Rapor kantor cabang'] as $label) {
            $this->assertTrue(str_contains($html, $label), $label);
        }
        $this->assertFalse(str_contains($html, 'data-em-detail="P001"'));
        $this->assertFalse(str_contains($html, 'id="em-detail-dialog"'));
        $this->assertTrue(str_contains($html, 'class="em-region"'));
    }

    public function test_each_monitoring_tab_renders_and_has_no_edit_or_import_actions(): void
    {
        foreach (['overview', 'monitor', 'ecosystems', 'prospects'] as $tab) {
            $html = $this->monitorView(['view' => $tab])->render();
            $start = strpos($html, '<div class="em" id="eco-workspace">');
            $surface = substr($html, $start, strpos($html, '</section>\n    </div>', $start) ?: 80000);
            $this->assertFalse(str_contains($surface, 'Tambah prospek'));
            $this->assertFalse(str_contains($surface, 'Simpan perubahan'));
            $this->assertFalse(str_contains($surface, 'method="POST"'));
            $this->assertFalse(str_contains($html, 'google.script.run'));
            $this->assertTrue(str_contains($html, 'aria-current="page"'));
        }
    }

    public function test_summary_and_detail_share_the_same_filters_and_scoped_source_rows(): void
    {
        $query = ['kc' => 'madiun', 'jalur' => 'RMFT', 'status' => 'Berjalan', 'ring' => 'L1', 'q' => 'P001'];
        $overview = $this->monitorView($query)->getData();
        $detail = $this->monitorView($query + ['view' => 'prospects'])->getData();
        $this->assertSame(1, $overview['monitorSummary']['total']);
        $this->assertSame(['P001'], array_column($detail['prospects'], 'ID_Prospek'));
        $this->assertSame($overview['monitorSummary'], $detail['monitorSummary']);
        $this->assertSame(15000.0, $detail['monitorSummary']['categories']['Simpanan']['max']);
        $this->assertSame(1, array_sum(array_column(array_column($overview['branches'], 'stats'), 'total')));
        $this->assertSame(['P001'], array_column($detail['paginator']->items(), 'ID_Prospek'));
    }

    public function test_locked_branch_cannot_leak_other_kc_data_through_map_reports_filters_or_dialog_payload(): void
    {
        $this->actingAs(new User(['pn' => '0049', 'name' => 'KC Magetan']));
        foreach (['overview', 'monitor', 'ecosystems', 'prospects'] as $tab) {
            $view = $this->monitorView(['kc' => 'madiun', 'view' => $tab]);
            $data = $view->getData();
            $this->assertSame('magetan', $data['selectedKc']);
            $this->assertSame(['magetan'], array_keys($data['branches']));
            $this->assertSame(['magetan'], array_keys($data['geography']['regions']));
            $this->assertSame(['P003'], array_column($data['prospects'], 'ID_Prospek'));
            $html = $view->render();
            $this->assertFalse(str_contains($html, 'RSUD Caruban &amp; RSUD Dolopo'));
            $this->assertFalse(str_contains($html, 'data-em-detail="P001"'));
        }
    }

    public function test_pagination_changes_the_list_without_changing_summary_totals_and_preserves_filters(): void
    {
        $this->seedMoreRows(47);
        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $data = $this->monitorView(['view' => 'prospects', 'page' => $page, 'jalur' => 'RMFT'])->getData();
            $this->assertSame(46, $data['monitorSummary']['total']);
            $this->assertLessThanOrEqual(20, count($data['paginator']->items()));
            $ids = array_merge($ids, array_column($data['paginator']->items(), 'ID_Prospek'));
            if ($page === 1) {
                $this->assertStringContainsString('jalur=RMFT', $data['paginator']->nextPageUrl());
            }
        }
        $this->assertCount(46, array_unique($ids));
        $last = $this->monitorView(['view' => 'prospects', 'page' => 999, 'jalur' => 'RMFT'])->getData();
        $this->assertSame(3, $last['paginator']->currentPage());
        $this->assertCount(6, $last['paginator']->items());
    }

    public function test_pic_pagination_keeps_all_groups_available_and_drilldowns_match_group_counts(): void
    {
        $this->seedMoreRows(47);
        $data = $this->monitorView(['view' => 'monitor'])->getData();
        $this->assertGreaterThan(40, count($data['picGroups']));
        $this->assertCount(20, $data['picPaginator']->items());
        $this->assertStringContainsString('pic_page=2', $data['picPaginator']->nextPageUrl());
        foreach ($data['picGroups'] as $group) {
            $params = ['view' => 'prospects', 'kc' => strtolower($group['kc']), 'jalur' => $group['jalur'], 'pic' => $group['pic'] ?: '__unassigned'];
            if ($group['pic'] === '') {
                $params['peran'] = $group['peran'] ?: '__empty';
            }
            $detail = $this->monitorView($params)->getData();
            $this->assertSame($group['stats']['total'], $detail['monitorSummary']['total']);
        }
    }

    public function test_empty_result_invalid_filter_and_source_failure_have_recoverable_views(): void
    {
        $empty = $this->monitorView(['view' => 'prospects', 'q' => 'tidak ada kecocokan']);
        $this->assertSame(0, $empty->getData()['monitorSummary']['total']);
        $this->assertTrue(str_contains($empty->render(), 'Tidak ada prospek sesuai filter'));
        $invalid = $this->monitorView(['kc' => ['x'], 'q' => ['x'], 'condition' => ['x'], 'status' => 'invalid', 'view' => 'invalid'])->getData();
        $this->assertSame('all', $invalid['selectedKc']);
        $this->assertSame('overview', $invalid['activeView']);
        $this->assertSame(3, $invalid['monitorSummary']['total']);
        Cache::put($this->key(), ['error' => 'Sumber belum tersedia', 'prospek' => [], 'ekosistem' => [], 'log' => []], 60);
        $error = $this->monitorView()->render();
        $this->assertTrue(str_contains($error, 'role="alert"'));
        $this->assertTrue(str_contains($error, 'Gunakan tombol Perbarui'));
    }

    public function test_detail_dialog_renders_source_strings_literally_and_preserves_zero_values(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if (! $node) {
            $this->markTestSkipped('Node.js is required for the detail dialog runtime test.');
        }
        $source = file_get_contents(resource_path('views/report/ekosistem.blade.php'));
        preg_match('/<script>([\s\S]*?)<\/script>/', $source, $match);
        $payload = '<img src=x onerror=alert(1)>';
        $record = ['P1' => ['ID_Prospek' => 'P1', 'Prospek' => $payload, 'KC' => 'Madiun', 'Kondisi' => ['label' => 'Belum ada PIC'], 'Est_Nasabah' => 0, 'Realisasi_Nominal_Juta' => 0, 'Catatan' => '</script><script>alert(1)</script>']];
        $script = str_replace('@json($detailRecords)', json_encode($record, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), $match[1]);
        $setup = <<<'JS'
let opened=false,closed=false;
class Element {
 constructor(){this.children=[];this.handlers={};this.dataset={};this.textContent='';}
 addEventListener(name,callback){this.handlers[name]=callback;}
 append(...children){this.children.push(...children);}
 replaceChildren(){this.children=[];}
 focus(){}
 set innerHTML(value){throw new Error('Source content must not enter innerHTML');}
 showModal(){opened=true;}
 close(){closed=true;}
}
const button=new Element();button.dataset.emDetail='P1';
const elements={};for(const id of ['em-detail-dialog','em-detail-title','em-detail-id','em-detail-body','em-detail-close'])elements[id]=new Element();
global.document={querySelector:()=>null,getElementById:id=>elements[id],createElement:()=>new Element(),querySelectorAll:()=>[button]};
function allText(el){return el.textContent+' '+el.children.map(allText).join(' ');}
JS;
        $process = new Process([$node]);
        $process->setInput($setup."\n".$script."\n"."button.handlers.click.call(button);const text=allText(elements['em-detail-body']);elements['em-detail-close'].handlers.click();console.log(JSON.stringify({opened,closed,title:elements['em-detail-title'].textContent,text}));");
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($result['opened']);
        $this->assertTrue($result['closed']);
        $this->assertSame($payload, $result['title']);
        $this->assertStringContainsString('</script><script>alert(1)</script>', $result['text']);
        $this->assertStringContainsString('Rp0 juta', $result['text']);
    }

    private function monitorView(array $query = []): View
    {
        return (new EkosistemController)->index(Request::create('/ekosistem', 'GET', $query));
    }

    private function seedMoreRows(int $total): void
    {
        $this->monitorView();
        $source = Cache::get($this->key());
        for ($i = 4; $i <= $total; $i++) {
            $row = $source['prospek'][0];
            $row['ID_Prospek'] = 'P'.str_pad($i, 3, '0', STR_PAD_LEFT);
            $row['Nama_PIC'] = 'PIC '.$i;
            $source['prospek'][] = $row;
        }
        Cache::put($this->key(), $source, 600);
    }

    private function key(): string
    {
        return 'ekosistem:source:v1:1bssXQGe2PVUQYuNQVfiXuo5TDLuidO6TeU1cYR9iHZI';
    }
}
