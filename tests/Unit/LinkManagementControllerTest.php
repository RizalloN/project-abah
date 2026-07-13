<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\LinkManagementController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LinkManagementControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('external_report_links');
        Schema::create('external_report_links', function (Blueprint $table): void {
            $table->string('uniqueid_link', 120)->primary();
            $table->string('group_key', 80);
            $table->string('link_key', 100);
            $table->string('label', 160);
            $table->string('sheet_name', 160)->nullable();
            $table->string('spreadsheet_id', 160)->nullable();
            $table->text('link_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['group_key', 'link_key'], 'uq_external_report_links_scope');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('external_report_links');

        parent::tearDown();
    }

    public function test_link_management_includes_rm_mikro_kpi_link(): void
    {
        $view = (new LinkManagementController())->index();
        $links = $view->getData()['kpiLinks'];
        $sppgLink = $view->getData()['sppgLink'];
        $marketShareLinks = $view->getData()['marketShareLinks'];

        $this->assertSame(['mbm', 'ka-unit', 'rm-mikro', 'mantri'], array_keys($links));
        $this->assertSame('KPI RM Mikro', $links['rm-mikro']['label']);
        $this->assertSame('rank', $links['rm-mikro']['sheet_name']);
        $this->assertSame('1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM', $links['rm-mikro']['spreadsheet_id']);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM/edit?usp=sharing',
            $links['rm-mikro']['link_url']
        );
        $this->assertSame('SPPG', $sppgLink['label']);
        $this->assertSame('Area 6', $sppgLink['sheet_name']);
        $this->assertSame(['mapping'], array_keys($marketShareLinks));
        $this->assertSame('Mapping Market Share', $marketShareLinks['mapping']['label']);
        $this->assertSame('DASHBOARD', $marketShareLinks['mapping']['sheet_name']);
        $this->assertSame('18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY', $marketShareLinks['mapping']['spreadsheet_id']);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing',
            $marketShareLinks['mapping']['link_url']
        );
    }

    public function test_default_kpi_links_do_not_overwrite_existing_custom_link(): void
    {
        DB::table('external_report_links')->insert([
            'uniqueid_link' => 'almafacts_kpi_mbm',
            'group_key' => 'almafacts_kpi',
            'link_key' => 'mbm',
            'label' => 'KPI MBM',
            'sheet_name' => 'Custom MBM',
            'spreadsheet_id' => 'custom-spreadsheet-id',
            'link_url' => 'https://docs.google.com/spreadsheets/d/custom-spreadsheet-id/edit?usp=sharing',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new LinkManagementController())->index();

        $row = DB::table('external_report_links')
            ->where('group_key', 'almafacts_kpi')
            ->where('link_key', 'mbm')
            ->first();

        $this->assertSame('Custom MBM', $row->sheet_name);
        $this->assertSame('custom-spreadsheet-id', $row->spreadsheet_id);
        $this->assertSame('https://docs.google.com/spreadsheets/d/custom-spreadsheet-id/edit?usp=sharing', $row->link_url);
        $this->assertDatabaseHas('external_report_links', [
            'group_key' => 'almafacts_kpi',
            'link_key' => 'rm-mikro',
            'sheet_name' => 'rank',
        ]);
    }

    public function test_market_share_mapping_replaces_legacy_sharepoint_link_with_google_sheet(): void
    {
        DB::table('external_report_links')->insert([
            'uniqueid_link' => 'market_share_mapping',
            'group_key' => 'market_share',
            'link_key' => 'mapping',
            'label' => 'Mapping Market Share',
            'sheet_name' => 'DASHBOARD',
            'spreadsheet_id' => 'old-sharepoint',
            'link_url' => 'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=GRPhfF',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new LinkManagementController())->index();

        $row = DB::table('external_report_links')
            ->where('group_key', 'market_share')
            ->where('link_key', 'mapping')
            ->first();

        $this->assertSame('DASHBOARD', $row->sheet_name);
        $this->assertSame('18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY', $row->spreadsheet_id);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing',
            $row->link_url
        );
    }
}
