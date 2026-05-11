-- ============================================================
-- FcvModuleBuilder Uninstall SQL
-- Xóa FcvModuleBuilder khỏi Settings + drop tracking table
-- ============================================================

DELETE FROM `vtiger_settings_field`
WHERE `linkto` LIKE '%FcvModuleBuilder%';

DROP TABLE IF EXISTS `vtiger_fcv_custom_modules`;
