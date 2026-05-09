@echo off
cd /D "D:\XAMPP\htdocs\project-ABAH"
"php" "D:\XAMPP\htdocs\project-ABAH\artisan" db:backup-progressive "backup_69fee441aeb9d" >> "D:\XAMPP\htdocs\project-ABAH\storage\logs/database-backup-backup_69fee441aeb9d.log" 2>&1
del "%~f0" >NUL 2>&1
