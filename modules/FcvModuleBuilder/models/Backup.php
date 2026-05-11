<?php
/*+***********************************************************************************
 * FcvModuleBuilder_Backup_Model
 * Snapshot trạng thái trước mỗi thao tác create/delete để hỗ trợ undo
 ************************************************************************************/

class FcvModuleBuilder_Backup_Model {

    const BACKUP_DIR = 'storage/fcvmodulebuilder/backups';

    /**
     * Tạo snapshot trước khi tạo/xóa module
     * @param string $moduleName Tên module
     * @param string $action     'create' | 'delete_before'
     * @return string backup_ref (dùng để undo)
     */
    public static function snapshot(string $moduleName, string $action): string {
        $ref = date('Ymd_His') . '_' . $moduleName . '_' . $action;
        $dir = self::BACKUP_DIR . '/' . $ref;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $db = PearDatabase::getInstance();

        // Lưu vtiger_tab row nếu module đã tồn tại
        $r      = $db->pquery('SELECT * FROM vtiger_tab WHERE name=?', [$moduleName]);
        $tabRow = ($db->num_rows($r) > 0) ? $db->query_result_rowdata($r, 0) : null;

        // Liệt kê files hiện tại nếu module đã có
        $files    = [];
        $srcDirs  = [
            "modules/$moduleName",
            "layouts/v7/modules/$moduleName",
        ];
        foreach ($srcDirs as $d) {
            if (is_dir($d)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $files[] = str_replace('\\', '/', $f->getPathname());
                    }
                }
            }
        }

        $manifest = [
            'ref'     => $ref,
            'module'  => $moduleName,
            'action'  => $action,
            'time'    => date('Y-m-d H:i:s'),
            'tab_row' => $tabRow,
            'files'   => $files,
            'dirs'    => $srcDirs,
            'tables'  => [
                'vtiger_' . strtolower($moduleName),
                'vtiger_' . strtolower($moduleName) . 'cf',
                'vtiger_' . strtolower($moduleName) . 'grouprel',
            ],
        ];

        file_put_contents("$dir/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Backup từng file source (chỉ khi xóa — để có thể restore)
        if ($action === 'delete_before' && !empty($files)) {
            $filesDir = "$dir/files";
            if (!is_dir($filesDir)) {
                mkdir($filesDir, 0755, true);
            }
            foreach ($files as $f) {
                if (file_exists($f)) {
                    // Flatten path để tránh conflict tên file
                    $safeName = str_replace(['/', '\\', ':'], '__', $f);
                    copy($f, "$filesDir/$safeName");
                }
            }
        }

        return $ref;
    }

    /**
     * Liệt kê tất cả backups, mới nhất trước
     */
    public static function listAll(): array {
        $backups = [];
        if (!is_dir(self::BACKUP_DIR)) {
            return $backups;
        }
        foreach (glob(self::BACKUP_DIR . '/*/manifest.json') as $f) {
            $data = json_decode(file_get_contents($f), true);
            if ($data) {
                $backups[] = $data;
            }
        }
        usort($backups, fn($a, $b) => strcmp($b['time'], $a['time']));
        return $backups;
    }

    /**
     * Xóa 1 backup theo ref
     */
    public static function remove(string $ref): void {
        $dir = self::BACKUP_DIR . '/' . $ref;
        if (is_dir($dir)) {
            self::rmdirRecursive($dir);
        }
    }

    /**
     * Khôi phục files từ backup (dùng khi undo delete)
     * @return array danh sách files đã restore
     */
    public static function restoreFiles(string $ref): array {
        $filesDir = self::BACKUP_DIR . '/' . $ref . '/files';
        $restored = [];
        if (!is_dir($filesDir)) {
            return $restored;
        }
        foreach (glob("$filesDir/*") as $f) {
            // Chuyển tên flattened về đường dẫn gốc
            $origPath = str_replace('__', '/', basename($f));
            // Xử lý Windows drive letter (C:/ → C:/)
            $origPath = preg_replace('/^([A-Za-z])__/', '$1:/', $origPath);
            $origDir  = dirname($origPath);
            if (!is_dir($origDir)) {
                mkdir($origDir, 0755, true);
            }
            if (copy($f, $origPath)) {
                $restored[] = $origPath;
            }
        }
        return $restored;
    }

    /**
     * Đọc manifest của 1 backup
     */
    public static function getManifest(string $ref): ?array {
        $path = self::BACKUP_DIR . '/' . $ref . '/manifest.json';
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true);
    }

    private static function rmdirRecursive(string $dir): void {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = "$dir/$f";
            is_dir($path) ? self::rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
