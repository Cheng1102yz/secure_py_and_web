<?php
/**
 * 数据迁移脚本：把现有的base64图片转换为文件存储
 * 运行方式：访问 backend/migrate_images_to_files.php?password=cyznb666
 * 
 * 功能：
 * 1. 遍历所有帖子的images字段
 * 2. 把base64编码的图片解码并保存为文件
 * 3. 更新images字段为文件URL数组
 * 4. 支持断点续传（已转换的图片会跳过）
 */

require_once __DIR__ . '/helpers.php';

// 密码验证
$password = getParam('password', '');
if ($password !== 'cyznb666') {
    die('密码错误');
}

$db = Database::getInstance();

echo "<h2>数据迁移：base64图片转文件存储</h2>";
echo "<hr>";

// 上传目录
$uploadDir = __DIR__ . '/../uploads/images';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 查询所有有图片的帖子
echo "<h3>1. 查询所有有图片的帖子...</h3>";
$posts = $db->fetchAll("SELECT id, user_id, images FROM posts WHERE images IS NOT NULL AND images != '' AND images != '[]'");
echo "<p>共找到 " . count($posts) . " 条有图片的帖子</p>";

if (empty($posts)) {
    echo "<p style='color:green;'>没有需要迁移的图片</p>";
    exit;
}

// 统计
$totalImages = 0;
$successImages = 0;
$failedImages = 0;
$skippedImages = 0;
$updatedPosts = 0;

echo "<h3>2. 开始迁移图片...</h3>";
echo "<div style='background:#f5f7fa; padding:15px; border-radius:8px; max-height:400px; overflow-y:auto; font-family:monospace; font-size:12px;'>";

foreach ($posts as $post) {
    $postId = $post['id'];
    $userId = $post['user_id'];
    $images = json_decode($post['images'], true);
    
    if (!is_array($images) || empty($images)) {
        continue;
    }
    
    $newImages = [];
    $postChanged = false;
    
    foreach ($images as $index => $image) {
        $totalImages++;
        
        // 如果已经是URL（不是base64），跳过
        if (is_string($image) && (strpos($image, 'http') === 0 || strpos($image, '/uploads/') === 0)) {
            $newImages[] = $image;
            $skippedImages++;
            continue;
        }
        
        // 如果是base64编码的图片，解码并保存
        if (is_string($image) && preg_match('/^data:image\/(\w+);base64,/', $image, $matches)) {
            $imageType = $matches[1];
            $base64Data = substr($image, strpos($image, ',') + 1);
            $imageData = base64_decode($base64Data);
            
            if ($imageData === false) {
                echo "<p style='color:red;'>帖子#{$postId} 图片{$index} 解码失败</p>";
                $failedImages++;
                continue;
            }
            
            // 生成唯一文件名
            $timestamp = date('Ymd_His');
            $random = substr(md5(uniqid() . $userId . $postId . $index), 0, 8);
            $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
            $fileName = "migrated_{$userId}_{$postId}_{$timestamp}_{$random}.{$extension}";
            $filePath = $uploadDir . '/' . $fileName;
            
            // 保存文件
            if (file_put_contents($filePath, $imageData) !== false) {
                $imageUrl = '/uploads/images/' . $fileName;
                $newImages[] = $imageUrl;
                $successImages++;
                $postChanged = true;
                echo "<p style='color:green;'>帖子#{$postId} 图片{$index} 迁移成功 -> {$fileName}</p>";
            } else {
                echo "<p style='color:red;'>帖子#{$postId} 图片{$index} 保存失败</p>";
                $failedImages++;
                // 保留原base64数据
                $newImages[] = $image;
            }
        } else {
            // 其他格式，保留原样
            $newImages[] = $image;
            $skippedImages++;
        }
    }
    
    // 如果帖子有变化，更新数据库
    if ($postChanged) {
        $newImagesJson = json_encode($newImages);
        $db->execute("UPDATE posts SET images = ? WHERE id = ?", [$newImagesJson, $postId]);
        $updatedPosts++;
    }
}

echo "</div>";

// 统计结果
echo "<h3>3. 迁移结果统计：</h3>";
echo "<ul>";
echo "<li>总图片数：{$totalImages}</li>";
echo "<li>成功迁移：<span style='color:green;'>{$successImages}</span></li>";
echo "<li>迁移失败：<span style='color:red;'>{$failedImages}</span></li>";
echo "<li>跳过（已是URL）：{$skippedImages}</li>";
echo "<li>更新帖子数：{$updatedPosts}</li>";
echo "</ul>";

echo "<hr>";
if ($failedImages === 0) {
    echo "<h2 style='color:green;'>✅ 数据迁移完成！所有图片已成功转换为文件存储</h2>";
} else {
    echo "<h2 style='color:orange;'>⚠️ 数据迁移完成，但有 {$failedImages} 张图片迁移失败，请检查</h2>";
}
