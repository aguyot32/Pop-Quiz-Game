<?php

class ImageManager {
    
    public static function imageExists($filename) {
        if (empty($filename)) {
            return false;
        }
        
        $imagePath = HINTS_PATH . '/' . $filename;
        return file_exists($imagePath);
    }
    
    public static function getImageUrl($filename) {
        if (self::imageExists($filename)) {
            return HINTS_URL . '/' . $filename;
        }
        
        return HINTS_URL . '/' . DEFAULT_HINT_IMAGE;
    }
    
    public static function getImageOrPlaceholder($filename, $text = 'Image non disponible') {
        if (!empty($filename) && (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0)) {
            return $filename;
        }
        
        if (self::imageExists($filename)) {
            return self::getImageUrl($filename);
        }
        
        $query = urlencode($text);
        return "https://source.unsplash.com/400x300/?{$query}";
    }
    
    public static function uploadImage($file, $newFilename = null) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erreur lors de l\'upload'];
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Type de fichier non autorisé'];
        }
        
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)'];
        }
        
        if (!file_exists(HINTS_PATH)) {
            mkdir(HINTS_PATH, 0755, true);
        }
        
        if ($newFilename === null) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid('hint_', true) . '.' . $extension;
        }
        
        $newFilename = self::sanitizeFilename($newFilename);
        
        $destination = HINTS_PATH . '/' . $newFilename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            self::optimizeImage($destination);
            
            return [
                'success' => true, 
                'filename' => $newFilename,
                'url' => HINTS_URL . '/' . $newFilename
            ];
        }
        
        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
    }
    
    private static function sanitizeFilename($filename) {
        $filename = str_replace(' ', '_', $filename);
        
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
        
        $filename = strtolower($filename);
        
        return $filename;
    }
    
    private static function optimizeImage($imagePath) {
        $maxWidth = 800;
        $maxHeight = 600;
        
        list($width, $height, $type) = getimagesize($imagePath);
        
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return true;
        }
        
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($imagePath);
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                return false;
        }
        
        imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $imagePath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $imagePath, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $imagePath);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($newImage);
        
        return true;
    }
    
    public static function deleteImage($filename) {
        if (empty($filename)) {
            return false;
        }
        
        $imagePath = HINTS_PATH . '/' . $filename;
        
        if (file_exists($imagePath)) {
            return unlink($imagePath);
        }
        
        return false;
    }
    
    public static function listImages() {
        if (!file_exists(HINTS_PATH)) {
            return [];
        }
        
        $images = [];
        $files = scandir(HINTS_PATH);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file(HINTS_PATH . '/' . $file)) {
                $images[] = [
                    'filename' => $file,
                    'url' => HINTS_URL . '/' . $file,
                    'size' => filesize(HINTS_PATH . '/' . $file),
                    'modified' => filemtime(HINTS_PATH . '/' . $file)
                ];
            }
        }
        
        return $images;
    }
}