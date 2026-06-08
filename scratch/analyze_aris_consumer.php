<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$period = '2026-05-31';
$previous = '2026-04-30';
$rm = '00079608 - ARIS SULISTYAWAN';

$realisasiColumn = \Illuminate\Support\Facades\Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1')
    ? 'tgl_realisasi1'
    : 'tgl_realisasi';

$sql = str_replace('{REALISASI_COLUMN}', $realisasiColumn, <<<'SQL'
select
  sum(case when prev.account_key is not null then cur.current_os - coalesce(prev.previous_os, 0) else 0 end) as existing_delta,
  sum(case when prev.account_key is not null and cur.current_os - coalesce(prev.previous_os, 0) > 0 then cur.current_os - coalesce(prev.previous_os, 0) else 0 end) as existing_positive,
  sum(case when prev.account_key is not null and cur.current_os - coalesce(prev.previous_os, 0) < 0 then cur.current_os - coalesce(prev.previous_os, 0) else 0 end) as existing_negative,
  count(case when prev.account_key is not null and cur.current_os - coalesce(prev.previous_os, 0) > 0 then 1 end) as existing_positive_deb,
  count(case when prev.account_key is not null and cur.current_os - coalesce(prev.previous_os, 0) < 0 then 1 end) as existing_negative_deb,
  sum(case when prev.account_key is null and cur.is_current_month_realization = 1 then cur.current_os else 0 end) as new_current_month_os,
  count(case when prev.account_key is null and cur.is_current_month_realization = 1 then 1 end) as new_current_month_deb,
  sum(case when prev.account_key is null and cur.is_current_month_realization = 0 then cur.current_os else 0 end) as new_not_current_month_os,
  count(case when prev.account_key is null and cur.is_current_month_realization = 0 then 1 end) as new_not_current_month_deb,
  sum(case when prev.account_key is null then cur.current_os else 0 end) as all_new_os,
  count(case when prev.account_key is null then 1 end) as all_new_deb
from (
  select
    upper(trim(nomor_rekening1)) as account_key,
    sum(coalesce(baki_debet1, 0)) as current_os,
    max(case when {REALISASI_COLUMN} between ? and ? then 1 else 0 end) as is_current_month_realization
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja = 'BRIGUNAKONSUMER'
    and rm_normalized = ?
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by upper(trim(nomor_rekening1))
) cur
left join (
  select
    upper(trim(nomor_rekening1)) as account_key,
    sum(coalesce(baki_debet1, 0)) as previous_os
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja in ('BRIGUNAKONSUMER', 'KPR')
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by upper(trim(nomor_rekening1))
) prev on prev.account_key = cur.account_key
SQL
);

$components = DB::select($sql, ['2026-05-01', $period, $period, $rm, $previous])[0] ?? null;

$topSql = str_replace('{REALISASI_COLUMN}', $realisasiColumn, <<<'SQL'
select
  cur.account_key,
  cur.nama_debitur,
  cur.tgl_realisasi,
  coalesce(prev.previous_os, 0) as previous_os,
  cur.current_os,
  case
    when prev.account_key is null then case when cur.is_current_month_realization = 1 then cur.current_os else 0 end
    else cur.current_os - coalesce(prev.previous_os, 0)
  end as delta_os
from (
  select
    upper(trim(nomor_rekening1)) as account_key,
    max(nama_debitur1) as nama_debitur,
    max({REALISASI_COLUMN}) as tgl_realisasi,
    sum(coalesce(baki_debet1, 0)) as current_os,
    max(case when {REALISASI_COLUMN} between ? and ? then 1 else 0 end) as is_current_month_realization
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja = 'BRIGUNAKONSUMER'
    and rm_normalized = ?
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by upper(trim(nomor_rekening1))
) cur
left join (
  select
    upper(trim(nomor_rekening1)) as account_key,
    sum(coalesce(baki_debet1, 0)) as previous_os
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja in ('BRIGUNAKONSUMER', 'KPR')
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by upper(trim(nomor_rekening1))
) prev on prev.account_key = cur.account_key
having abs(delta_os) > 0.0001
order by delta_os desc
limit 20
SQL
);
$top = DB::select($topSql, ['2026-05-01', $period, $period, $rm, $previous]);

$cifPlafonSql = str_replace('{REALISASI_COLUMN}', $realisasiColumn, <<<'SQL'
select
  sum(current_groups.current_plafon) as current_plafon,
  sum(coalesce(previous_closed.previous_os, 0)) as previous_closed_os,
  sum(current_groups.current_plafon - coalesce(previous_closed.previous_os, 0)) as net_formula,
  sum(current_groups.debitur) as debitur
from (
  select
    current_base.clean_cif,
    count(distinct current_base.account_key) as debitur,
    sum(current_base.current_plafon) as current_plafon
  from (
    select
      upper(trim(nomor_rekening1)) as account_key,
      cifno_clean as clean_cif,
      coalesce(plafon, 0) as current_plafon
    from daily_loan_dinamis
    where periode = ?
      and segmen_kinerja = 'CONSUMER'
      and produk_kinerja = 'BRIGUNAKONSUMER'
      and rm_normalized = ?
      and pn_pengelola1 is not null
      and pn_pengelola1 <> ''
      and nomor_rekening1 is not null
      and nomor_rekening1 <> ''
      and cifno_clean is not null
      and cifno_clean <> ''
      and {REALISASI_COLUMN} between ? and ?
  ) current_base
  group by current_base.clean_cif
) current_groups
left join (
  select previous_base.clean_cif, previous_base.previous_os
  from (
    select
      cifno_clean as clean_cif,
      upper(trim(nomor_rekening1)) as account_key,
      coalesce(baki_debet1, 0) as previous_os,
      row_number() over (partition by cifno_clean order by uniqueid_namareport) as row_num
    from daily_loan_dinamis
    where periode = ?
      and cifno_clean is not null
      and cifno_clean <> ''
      and nomor_rekening1 is not null
      and nomor_rekening1 <> ''
      and cifno_clean in (
        select distinct cifno_clean
        from daily_loan_dinamis
        where periode = ?
          and segmen_kinerja = 'CONSUMER'
          and produk_kinerja = 'BRIGUNAKONSUMER'
          and rm_normalized = ?
          and pn_pengelola1 is not null
          and pn_pengelola1 <> ''
          and nomor_rekening1 is not null
          and nomor_rekening1 <> ''
          and cifno_clean is not null
          and cifno_clean <> ''
          and {REALISASI_COLUMN} between ? and ?
      )
  ) previous_base
  left join (
    select distinct upper(trim(nomor_rekening1)) as account_key
    from daily_loan_dinamis
    where periode = ?
      and nomor_rekening1 is not null
      and nomor_rekening1 <> ''
  ) current_accounts on current_accounts.account_key = previous_base.account_key
  where previous_base.row_num = 1
    and current_accounts.account_key is null
) previous_closed on previous_closed.clean_cif = current_groups.clean_cif
SQL
);
$cifPlafon = DB::select($cifPlafonSql, [
    $period,
    $rm,
    '2026-05-01',
    $period,
    $previous,
    $period,
    $rm,
    '2026-05-01',
    $period,
    $period,
])[0] ?? null;

$cifVariantsSql = str_replace('{REALISASI_COLUMN}', $realisasiColumn, <<<'SQL'
select
  sum(current_groups.current_plafon) as current_plafon,
  sum(coalesce(prev_first.previous_os, 0)) as prev_first_os,
  sum(current_groups.current_plafon - coalesce(prev_first.previous_os, 0)) as net_minus_prev_first,
  sum(coalesce(prev_max.previous_os, 0)) as prev_max_os,
  sum(current_groups.current_plafon - coalesce(prev_max.previous_os, 0)) as net_minus_prev_max,
  sum(coalesce(prev_sum.previous_os, 0)) as prev_sum_os,
  sum(current_groups.current_plafon - coalesce(prev_sum.previous_os, 0)) as net_minus_prev_sum
from (
  select cifno_clean as clean_cif, sum(coalesce(plafon, 0)) as current_plafon
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja = 'BRIGUNAKONSUMER'
    and rm_normalized = ?
    and pn_pengelola1 is not null
    and pn_pengelola1 <> ''
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
    and cifno_clean is not null
    and cifno_clean <> ''
    and {REALISASI_COLUMN} between ? and ?
  group by cifno_clean
) current_groups
left join (
  select clean_cif, previous_os
  from (
    select
      cifno_clean as clean_cif,
      coalesce(baki_debet1, 0) as previous_os,
      row_number() over (partition by cifno_clean order by uniqueid_namareport) as row_num
    from daily_loan_dinamis
    where periode = ?
      and cifno_clean is not null
      and cifno_clean <> ''
      and nomor_rekening1 is not null
      and nomor_rekening1 <> ''
  ) ranked_prev
  where row_num = 1
) prev_first on prev_first.clean_cif = current_groups.clean_cif
left join (
  select cifno_clean as clean_cif, max(coalesce(baki_debet1, 0)) as previous_os
  from daily_loan_dinamis
  where periode = ?
    and cifno_clean is not null
    and cifno_clean <> ''
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by cifno_clean
) prev_max on prev_max.clean_cif = current_groups.clean_cif
left join (
  select cifno_clean as clean_cif, sum(coalesce(baki_debet1, 0)) as previous_os
  from daily_loan_dinamis
  where periode = ?
    and cifno_clean is not null
    and cifno_clean <> ''
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
  group by cifno_clean
) prev_sum on prev_sum.clean_cif = current_groups.clean_cif
SQL
);
$cifVariants = DB::select($cifVariantsSql, [
    $period,
    $rm,
    '2026-05-01',
    $period,
    $previous,
    $previous,
    $previous,
])[0] ?? null;

$excludedPrevious = DB::select(str_replace('{REALISASI_COLUMN}', $realisasiColumn, <<<'SQL'
select
  current_groups.clean_cif,
  current_groups.current_plafon,
  previous_base.account_key as previous_account,
  previous_base.previous_os,
  previous_base.nama_debitur,
  previous_base.row_num
from (
  select cifno_clean as clean_cif, sum(coalesce(plafon, 0)) as current_plafon
  from daily_loan_dinamis
  where periode = ?
    and segmen_kinerja = 'CONSUMER'
    and produk_kinerja = 'BRIGUNAKONSUMER'
    and rm_normalized = ?
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
    and cifno_clean is not null
    and cifno_clean <> ''
    and {REALISASI_COLUMN} between ? and ?
  group by cifno_clean
) current_groups
join (
  select
    cifno_clean as clean_cif,
    upper(trim(nomor_rekening1)) as account_key,
    nama_debitur1 as nama_debitur,
    coalesce(baki_debet1, 0) as previous_os,
    row_number() over (partition by cifno_clean order by uniqueid_namareport) as row_num
  from daily_loan_dinamis
  where periode = ?
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
    and cifno_clean is not null
    and cifno_clean <> ''
) previous_base on previous_base.clean_cif = current_groups.clean_cif
join (
  select distinct upper(trim(nomor_rekening1)) as account_key
  from daily_loan_dinamis
  where periode = ?
    and nomor_rekening1 is not null
    and nomor_rekening1 <> ''
) current_accounts on current_accounts.account_key = previous_base.account_key
where previous_base.row_num = 1
order by previous_base.previous_os desc
SQL
), [$period, $rm, '2026-05-01', $period, $previous, $period]);

$snapshot = DB::table('performance_rm_snapshots')
    ->where('periode', $period)
    ->where('segmen', 'CONSUMER')
    ->where('produk', 'BRIGUNA-KONSUMER')
    ->where('rm', $rm)
    ->first();

echo json_encode([
    'realisasi_column' => $realisasiColumn,
    'snapshot' => $snapshot,
    'cif_plafon_formula' => $cifPlafon,
    'cif_variants' => $cifVariants,
    'excluded_previous_sum' => array_sum(array_map(fn ($row) => (float) $row->previous_os, $excludedPrevious)),
    'excluded_previous' => $excludedPrevious,
    'components' => $components,
    'top_positive' => $top,
], JSON_PRETTY_PRINT);
echo PHP_EOL;
