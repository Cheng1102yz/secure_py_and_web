<?php
/**
 * ============================================================
 * SecureVault 用户系统 - 数据库连接类
 * ============================================================
 * 支持 SQLite 和 MySQL 两种数据库
 * 使用 PDO 进行数据库操作，防止SQL注入
 * 单例模式，全局共享一个数据库连接
 * ============================================================
 */

// 安全保护：禁止直接访问此文件
if (basename($_SERVER['PHP_SELF'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('禁止直接访问此文件');
}

require_once __DIR__ . '/config.php';

class Database {
    // 单例实例
    private static $instance = null;
    
    // PDO 连接对象
    private $pdo = null;
    
    // 数据库类型
    private $dbType = '';

    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {
        $this->dbType = DB_TYPE;
        $this->connect();
        $this->initTables();
    }

    /**
     * 获取单例实例
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 连接数据库
     */
    private function connect() {
        try {
            if ($this->dbType === 'sqlite') {
                // SQLite 连接
                $dsn = 'sqlite:' . SQLITE_PATH;
                $this->pdo = new PDO($dsn);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // 启用外键约束
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                // MySQL 连接
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    MYSQL_HOST,
                    MYSQL_PORT,
                    MYSQL_DBNAME,
                    MYSQL_CHARSET
                );
                $this->pdo = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $this->logError('数据库连接失败: ' . $e->getMessage());
            die('数据库连接失败，请检查配置。');
        }
    }

    /**
     * 初始化数据库表（仅SQLite自动创建，MySQL需要手动导入schema.sql）
     */
    private function initTables() {
        if ($this->dbType !== 'sqlite') {
            return;
        }
        
        try {
            $schemaFile = __DIR__ . '/../database/schema_sqlite.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $this->pdo->exec($sql);
            }
        } catch (PDOException $e) {
            $this->logError('初始化数据表失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取PDO连接对象
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * 获取数据库类型
     * @return string
     */
    public function getDbType() {
        return $this->dbType;
    }

    /**
     * 执行查询并返回所有结果
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return array
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            // 手动绑定参数，根据类型设置PDO参数类型
            // 解决SQLite中LIMIT/OFFSET需要整数类型的问题
            foreach ($params as $index => $value) {
                $paramIndex = $index + 1; // PDO参数索引从1开始
                if (is_int($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_BOOL);
                } elseif ($value === null) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError('查询失败: ' . $e->getMessage() . ' SQL: ' . $sql);
            return [];
        }
    }

    /**
     * 执行查询并返回单行结果
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return array|null
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            // 手动绑定参数，根据类型设置PDO参数类型
            foreach ($params as $index => $value) {
                $paramIndex = $index + 1;
                if (is_int($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_BOOL);
                } elseif ($value === null) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            $this->logError('查询失败: ' . $e->getMessage() . ' SQL: ' . $sql);
            return null;
        }
    }

    /**
     * 执行插入/更新/删除操作
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return int 受影响的行数
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            // 手动绑定参数，根据类型设置PDO参数类型
            foreach ($params as $index => $value) {
                $paramIndex = $index + 1;
                if (is_int($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_BOOL);
                } elseif ($value === null) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError('执行失败: ' . $e->getMessage() . ' SQL: ' . $sql);
            return 0;
        }
    }

    /**
     * 插入数据并返回最后插入的ID
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return int|string
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            // 手动绑定参数，根据类型设置PDO参数类型
            foreach ($params as $index => $value) {
                $paramIndex = $index + 1;
                if (is_int($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_BOOL);
                } elseif ($value === null) {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($paramIndex, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logError('插入失败: ' . $e->getMessage() . ' SQL: ' . $sql);
            return 0;
        }
    }

    /**
     * 获取最后插入的ID
     * @return int|string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * 回滚事务
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * 记录错误日志
     * @param string $message 错误信息
     */
    private function logError($message) {
        $logDir = dirname(ERROR_LOG_PATH);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $time = date('Y-m-d H:i:s');
        $logMsg = "[{$time}] {$message}" . PHP_EOL;
        file_put_contents(ERROR_LOG_PATH, $logMsg, FILE_APPEND);
    }

    /**
     * 防止克隆
     */
    private function __clone() {}

    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception('不能反序列化单例对象');
    }
}
