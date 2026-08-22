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

        $this->assertSame(['mbm', 'ka-unit', 'rm-mikro', 'rm-sme', 'mantri', 'consumer'], array_keys($links));
        $this->assertSame('KPI RM Mikro', $links['rm-mikro']['label']);
        $this->assertSame('KPI RM SME', $links['rm-sme']['label']);
        $this->assertSame('KPI RM SME', $links['rm-sme']['sheet_name']);
        $this->assertSame('1Qlc5Bb9n_h-k0nmdQRxdYhoHIij3tdHu', $links['rm-sme']['spreadsheet_id']);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1Qlc5Bb9n_h-k0nmdQRxdYhoHIij3tdHu/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true',
            $links['rm-sme']['link_url']
        );
        $this->assertSame('KPI Konsumer', $links['consumer']['label']);
        $this->assertSame('KPI', $links['consumer']['sheet_name']);
        $this->assertSame('1a-fr7OnoTIa_aJZ_b-yt_KkLJiJu92McQxv93ab58qQ', $links['consumer']['spreadsheet_id']);
        $this->assertSame('14As5M-bVMRa9OSFEo1mcaH1M1Derm7ca', $links['mantri']['spreadsheet_id']);
        $this->assertSame('rank', $links['mantri']['sheet_name']);
        $this->assertSame('KPI RM Mikro', $links['rm-mikro']['sheet_name']);
        $this->assertSame('1NNMRC8w2Z35n6WfKHL9Q9i575jwkMRzF', $links['rm-mikro']['spreadsheet_id']);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1NNMRC8w2Z35n6WfKHL9Q9i575jwkMRzF/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true',
            $links['rm-mikro']['link_url']
        );
        $this->assertSame('SPPG', $sppgLink['label']);
        $this->assertSame('Area 6', $sppgLink['sheet_name']);
        $this->assertSame(['mapping'], array_keys($marketShareLinks));
        $this->assertSame('Mapping Market Share', $marketShareLinks['mapping']['label']);
        $this->assertSame('REKAP', $marketShareLinks['mapping']['sheet_name']);
        $this->assertSame('1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt', $marketShareLinks['mapping']['spreadsheet_id']);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true',
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
            'sheet_name' => 'KPI RM Mikro',
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

        $this->assertSame('REKAP', $row->sheet_name);
        $this->assertSame('1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt', $row->spreadsheet_id);
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true',
            $row->link_url
        );
    }

    public function test_market_share_mapping_replaces_the_previous_google_workbook(): void
    {
        DB::table('external_report_links')->insert([
            'uniqueid_link' => 'market_share_mapping',
            'group_key' => 'market_share',
            'link_key' => 'mapping',
            'label' => 'Mapping Market Share',
            'sheet_name' => 'DASHBOARD',
            'spreadsheet_id' => '1au_fie_dm1BwvxpAtN_1EEFdAOvUuYM0',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1au_fie_dm1BwvxpAtN_1EEFdAOvUuYM0/edit?usp=sharing',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new LinkManagementController())->index();

        $this->assertDatabaseHas('external_report_links', [
            'group_key' => 'market_share',
            'link_key' => 'mapping',
            'sheet_name' => 'REKAP',
            'spreadsheet_id' => '1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1hbFZpQL4IbN8aDkCsXzei7YtOw8q_zBt/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true',
        ]);
    }

    public function test_market_share_mapping_keeps_a_custom_google_workbook_from_link_management(): void
    {
        DB::table('external_report_links')->insert([
            'uniqueid_link' => 'market_share_mapping',
            'group_key' => 'market_share',
            'link_key' => 'mapping',
            'label' => 'Mapping Market Share',
            'sheet_name' => 'REKAP',
            'spreadsheet_id' => 'custom-mapping-workbook',
            'link_url' => 'https://docs.google.com/spreadsheets/d/custom-mapping-workbook/edit?usp=sharing',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new LinkManagementController())->index();

        $this->assertDatabaseHas('external_report_links', [
            'group_key' => 'market_share',
            'link_key' => 'mapping',
            'sheet_name' => 'REKAP',
            'spreadsheet_id' => 'custom-mapping-workbook',
            'link_url' => 'https://docs.google.com/spreadsheets/d/custom-mapping-workbook/edit?usp=sharing',
        ]);
    }
}
