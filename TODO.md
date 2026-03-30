# TODO: Fix ImportFileBrimoController Logic

## Steps

- [x] Gather information and create plan
- [x] Create migration for `user_brimo_rpt_v2` table
- [x] Create migration for `user_brimo_fin` table
- [x] Run migrations (tables already existed in DB — migrations updated with hasTable guard)
- [x] Fix `ImportFileBrimoController.php` (upload session, preview route, processImport table logic)
- [x] Fix `ImportFileController.php` (add import_type to session, pass processRoute to view)
- [x] Update `routes/web.php` (add Brimo routes)
- [x] Update `resources/views/import/index.blade.php` (route Brimo to brimo.upload)
- [x] Update `resources/views/import/select-file.blade.php` (dynamic preview route)
- [x] Update `resources/views/import/preview.blade.php` (dynamic process route)

## COMPLETED ✅
