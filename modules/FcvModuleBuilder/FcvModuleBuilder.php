<?php
/*+***********************************************************************************
 * FcvModuleBuilder - Custom Module Builder for Vtiger CRM
 * Cho phép tạo/xóa custom modules trực tiếp vào source theo chuẩn vtlib
 ************************************************************************************/

class FcvModuleBuilder extends CRMEntity {
    public $table_name  = 'vtiger_fcv_custom_modules';
    public $table_index = 'id';
    public $column_fields = [];
    public $module_name = 'FcvModuleBuilder';
}
