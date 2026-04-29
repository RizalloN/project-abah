<?php
$conn = mysqli_connect('127.0.0.1', 'root', '', 'project_abah');
$result = mysqli_query($conn, "
  SELECT COLUMN_NAME 
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_NAME = 'daily_loan_dinamis' 
  AND (COLUMN_NAME LIKE '%kinerja%' 
       OR COLUMN_NAME LIKE '%normalized%' 
       OR COLUMN_NAME LIKE '%clean%')
");
while ($row = mysqli_fetch_assoc($result)) {
  echo $row['COLUMN_NAME'] . PHP_EOL;
}
mysqli_close($conn);
