<?php
/**
 * ============================================================
 * SecureVault 简单缓存类
 * ============================================================
 * 功能：
 *   1. 文件缓存
 *   2. 内存缓存（可选）
 *   3. 自动过期
 *   4. 缓存标签
 * ============================================================
 */

class Cache {
    private static $cacheDir = null;
    private static $enabled = true;
    
    /**
     * 初始化缓存目录
     */
    public static function init() {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/../cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }
    
    /**
     * 获取缓存
     */
    public static function get($key, $default = null) {
        if (!self::$enabled) return $default;
        
        $cacheFile = self::getCacheFile($key);
        if (!file_exists($cacheFile)) return $default;
        
        $data = unserialize(file_get_contents($cacheFile));
        if ($data === false) return $default;
        
        // 检查是否过期
        if ($data['expire'] > 0 && time() > $data['expire']) {
            unlink($cacheFile);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * 设置缓存
     */
    public static function set($key, $value, $ttl = 3600) {
        if (!self::$enabled) return false;
        
        self::init();
        
        $data = [
            'value' => $value,
            'expire' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time()
        ];
        
        $cacheFile = self::getCacheFile($key);
        return file_put_contents($cacheFile, serialize($data)) !== false;
    }
    
    /**
     * 删除缓存
     */
    public static function delete($key) {
        $cacheFile = self::getCacheFile($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }
    
    /**
     * 检查缓存是否存在
     */
    public static function has($key) {
        $cacheFile = self::getCacheFile($key);
        if (!file_exists($cacheFile)) return false;
        
        $data = unserialize(file_get_contents($cacheFile));
        if ($data === false) return false;
        
        if ($data['expire'] > 0 && time() > $data['expire']) {
            unlink($cacheFile);
            return false;
        }
        
        return true;
    }
    
    /**
     * 清空所有缓存
     */
    public static function flush() {
        self::init();
        $files = glob(self::$cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    /**
     * 清理过期缓存
     */
    public static function cleanExpired() {
        self::init();
        $files = glob(self::$cacheDir . '/*');
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = unserialize(file_get_contents($file));
                if ($data && $data['expire'] > 0 && time() > $data['expire']) {
                    unlink($file);
                    $count++;
                }
            }
        }
        return $count;
    }
    
    /**
     * 获取缓存文件路径
     */
    private static function getCacheFile($key) {
        self::init();
        $hash = md5($key);
        return self::$cacheDir . '/' . $hash . '.cache';
    }
    
    /**
     * 记住模式：如果缓存存在则返回，否则执行回调并缓存
     */
    public static function remember($key, $ttl, $callback) {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
    
    /**
     * 启用/禁用缓存
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
}
