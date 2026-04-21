<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');

echo "=== Import Jobs untuk LW325_PH (April 19-21, 2026) ===\n";
$query = $db->prepare("
  SELECT id, table_name, status, message, created_at, completed_at, total_rows, source 
  FROM import_jobs 
  WHERE table_name = 'lw325_ph' 
  AND DATE(created_at) >= '2026-04-19'
  ORDER BY id DESC
  LIMIT 10
");
$query->execute();
$results = $query->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
  echo "❌ Tidak ada import job untuk LW325_PH\n";
} else {
  foreach ($results as $row) {
    echo "\n--- Job ID: {$row['id']} ---";
    echo "\nTable: {$row['table_name']}";
    echo "\nStatus: {$row['status']}";
    echo "\nRows: {$row['total_rows']}";
    echo "\nSource: {$row['source']}";
    echo "\nMessage: {$row['message']}";
    echo "\nCreated: {$row['created_at']}";
    echo "\nCompleted: {$row['completed_at']}\n";
  }
}

echo "\n\n=== Snapshot Status untuk April 19 ===\n";
$query2 = $db->prepare("
  SELECT COUNT(*) as total_rows, MIN(updated_at) as first_update, MAX(updated_at) as last_update
  FROM dashboard_harian_snapshots 
  WHERE snapshot_period = '2026-04-19'
");
$query2->execute();
$snap = $query2->fetch(PDO::FETCH_ASSOC);
echo "Rows in snapshot: {$snap['total_rows']}\n";
echo "First update: {$snap['first_update']}\n";
echo "Last update: {$snap['last_update']}\n";

echo "\n\n=== Check Failed Jobs ===\n";
$failedQuery = $db->prepare("
  SELECT COUNT(*) as failed_count FROM failed_jobs 
  WHERE DATE(created_at) >= '2026-04-19'
");
$failedQuery->execute();
$failed = $failedQuery->fetch(PDO::FETCH_ASSOC);
echo "Failed jobs in last 2 days: {$failed['failed_count']}\n";

echo "\n\n=== Check Queue Status ===\n";
$queueQuery = $db->prepare("
  SELECT COUNT(*) as total_jobs, COUNT(CASE WHEN reserved_at IS NULL THEN 1 END) as pending
  FROM jobs
  WHERE DATE(created_at) >= '2026-04-19'
");
$queueQuery->execute();
$queue = $queueQuery->fetch(PDO::FETCH_ASSOC);
echo "Total jobs: {$queue['total_jobs']}\n";
echo "Pending jobs: {$queue['pending']}\n";

echo "\n\n=== Check LW325_PH data untuk April 19 ===\n";
$lw325Query = $db->prepare("
  SELECT COUNT(*) as total_rows FROM lw325_ph WHERE periode = '2026-04-19'
");
$lw325Query->execute();
$lw = $lw325Query->fetch(PDO::FETCH_ASSOC);
echo "Rows in lw325_ph: {$lw['total_rows']}\n";
