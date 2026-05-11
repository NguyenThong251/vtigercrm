-- ============================================================
-- FcvModuleBuilder Install SQL
-- Tạo bảng tracking + đăng ký vào Settings menu
-- ============================================================

-- Tạo bảng tracking các module đã tạo qua FcvModuleBuilder
CREATE TABLE IF NOT EXISTS `vtiger_fcv_custom_modules` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `module_name`  VARCHAR(100) NOT NULL UNIQUE,
    `module_label` VARCHAR(100) NOT NULL,
    `description`  TEXT DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `backup_ref`   VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lấy fieldid tiếp theo
SET @next_id = (SELECT COALESCE(MAX(fieldid), 39) + 1 FROM vtiger_settings_field);

-- Đăng ký FcvModuleBuilder vào Settings menu (blockid=5 = LBL_MODULE_MANAGER)
INSERT IGNORE INTO `vtiger_settings_field`
    (`fieldid`, `blockid`, `name`, `iconpath`, `description`, `linkto`, `sequence`, `active`, `pinned`)
SELECT
    @next_id, 5,
    'LBL_FCV_MODULE_BUILDER',
    '',
    'LBL_FCV_MODULE_BUILDER_DESC',
    'index.php?module=FcvModuleBuilder&parent=Settings&view=Index',
    5, 0, 0
WHERE NOT EXISTS (
    SELECT 1 FROM vtiger_settings_field WHERE linkto LIKE '%FcvModuleBuilder%'
);

-- Cập nhật sequence table
UPDATE `vtiger_settings_field_seq`
SET id = (SELECT MAX(fieldid) FROM vtiger_settings_field);
