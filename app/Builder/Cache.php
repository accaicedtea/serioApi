<?php

class CacheHandler {
    private $enabled = __CACHE_ENABLED__;
    private $ttl = __CACHE_TTL__; // seconds
    private $dir = __CACHE_DIR__;

    public function __construct() {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    private function keyToPath(string $key): string {
        $safe = preg_replace('/[^A-Za-z0-9:_-]/', '_', $key);
        return rtrim($this->dir, '/').'/'.$safe.'.json';
    }

    public function get(string $key) {
        if (!$this->enabled) return null;
        $file = $this->keyToPath($key);
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) return null;
        if (($data['expires_at'] ?? 0) < time()) { @unlink($file); return null; }
        return $data['value'] ?? null;
    }

    public function set(string $key, $value, int $ttl = null): void {
        if (!$this->enabled) return;
        $ttl = $ttl ?? $this->ttl;
        $file = $this->keyToPath($key);
        $payload = [ 'expires_at' => time() + max(1, $ttl), 'value' => $value ];
        @file_put_contents($file, json_encode($payload));
    }

    public function delete(string $key): void {
        $file = $this->keyToPath($key);
        if (is_file($file)) @unlink($file);
    }
}
