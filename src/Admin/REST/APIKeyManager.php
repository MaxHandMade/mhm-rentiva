<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\REST\Helpers\AuthHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ API KEY MANAGER - API Key Management
 * 
 * Manages API key creation, listing, revocation and deletion
 * Uses existing AuthHelper::verifyApiKey() function for compatibility
 */
final class APIKeyManager
{
    const OPTION_NAME = 'mhm_rentiva_api_keys';
    
    /**
     * Create API Key
     * 
     * @param string $name Key name/description
     * @param array $permissions Permissions (read, write, admin)
     * @param int|null $expires_at Expiry timestamp (optional)
     * @return array|false Key information on success, false on error
     */
    public static function create_api_key(string $name, array $permissions = ['read'], ?int $expires_at = null): array|false
    {
        if (empty($name)) {
            return false;
        }
        
        // Generate key
        $key_prefix = 'mhm_rentiva_' . (defined('WP_DEBUG') && WP_DEBUG ? 'test' : 'live') . '_';
        $random_part = bin2hex(random_bytes(24)); // 48 characters
        $api_key = $key_prefix . $random_part;
        
        // Hash key for security
        $key_hash = self::hash_key($api_key);
        
        // Generate key ID
        $key_id = 'key_' . wp_generate_password(16, false);
        
        // Key data
        $key_data = [
            'id' => $key_id,
            'name' => sanitize_text_field($name),
            'key_hash' => $key_hash,
            'permissions' => array_map('sanitize_text_field', $permissions),
            'created_at' => time(),
            'expires_at' => $expires_at,
            'last_used_at' => null,
            'status' => 'active'
        ];
        
        // Get existing keys
        $all_keys = self::get_all_keys();
        $all_keys[$key_id] = $key_data;
        
        // Save (only store hashes)
        $keys_to_save = [];
        foreach ($all_keys as $id => $data) {
            $keys_to_save[$id] = [
                'name' => $data['name'],
                'key_hash' => $data['key_hash'],
                'permissions' => $data['permissions'],
                'created_at' => $data['created_at'],
                'expires_at' => $data['expires_at'],
                'last_used_at' => $data['last_used_at'],
                'status' => $data['status']
            ];
        }
        
        update_option(self::OPTION_NAME, $keys_to_save);
        
        // Return key information (key is only shown at creation time)
        return [
            'id' => $key_id,
            'name' => $key_data['name'],
            'key' => $api_key, // ✅ Only shown at creation time
            'key_preview' => self::get_key_preview($api_key),
            'permissions' => $key_data['permissions'],
            'created_at' => $key_data['created_at'],
            'expires_at' => $expires_at,
            'status' => 'active'
        ];
    }
    
    /**
     * List all API keys
     * 
     * @return array API key list (keys are hashed, only preview shown)
     */
    public static function list_api_keys(): array
    {
        $all_keys = self::get_all_keys();
        $keys_list = [];
        
        foreach ($all_keys as $id => $key_data) {
            // Create key preview (actual key not shown)
            $keys_list[] = [
                'id' => $id,
                'name' => $key_data['name'],
                'key_preview' => self::get_key_preview_from_hash($key_data['key_hash']),
                'permissions' => $key_data['permissions'] ?? ['read'],
                'created_at' => $key_data['created_at'] ?? 0,
                'expires_at' => $key_data['expires_at'] ?? null,
                'last_used_at' => $key_data['last_used_at'] ?? null,
                'status' => self::get_key_status($key_data),
                'is_expired' => self::is_key_expired($key_data)
            ];
        }
        
        // Sort by created_at (newest first)
        usort($keys_list, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });
        
        return $keys_list;
    }
    
    /**
     * Revoke API Key (disable)
     * 
     * @param string $key_id Key ID
     * @return bool Success status
     */
    public static function revoke_api_key(string $key_id): bool
    {
        $all_keys = self::get_all_keys();
        
        if (!isset($all_keys[$key_id])) {
            return false;
        }
        
        $all_keys[$key_id]['status'] = 'revoked';
        
        return self::save_all_keys($all_keys);
    }
    
    /**
     * Delete API Key
     * 
     * @param string $key_id Key ID
     * @return bool Success status
     */
    public static function delete_api_key(string $key_id): bool
    {
        $all_keys = self::get_all_keys();
        
        if (!isset($all_keys[$key_id])) {
            return false;
        }
        
        unset($all_keys[$key_id]);
        
        return self::save_all_keys($all_keys);
    }
    
    /**
     * Verify API Key (compatible with existing AuthHelper::verifyApiKey())
     * 
     * @param string $api_key API key
     * @return array|false Key information or false
     */
    public static function verify_api_key(string $api_key): array|false
    {
        if (empty($api_key)) {
            return false;
        }
        
        $key_hash = self::hash_key($api_key);
        $all_keys = self::get_all_keys();
        
        foreach ($all_keys as $id => $key_data) {
            // Hash comparison
            if (hash_equals($key_data['key_hash'], $key_hash)) {
                // Status check
                if ($key_data['status'] !== 'active') {
                    return false;
                }
                
                // Expiry check
                if (self::is_key_expired($key_data)) {
                    return false;
                }
                
                // Update last used
                $key_data['last_used_at'] = time();
                $all_keys[$id] = $key_data;
                self::save_all_keys($all_keys);
                
                return [
                    'id' => $id,
                    'name' => $key_data['name'],
                    'permissions' => $key_data['permissions'] ?? ['read']
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Hash key
     * 
     * @param string $key API key
     * @return string Hash
     */
    private static function hash_key(string $key): string
    {
        return hash_hmac('sha256', $key, wp_salt());
    }
    
    /**
     * Create key preview (first 8 + last 4 characters)
     * 
     * @param string $key API key
     * @return string Preview
     */
    private static function get_key_preview(string $key): string
    {
        if (strlen($key) <= 12) {
            return '***';
        }
        return substr($key, 0, 8) . '...' . substr($key, -4);
    }
    
    /**
     * Create key preview from hash (approximate)
     * 
     * @param string $key_hash Key hash
     * @return string Preview
     */
    private static function get_key_preview_from_hash(string $key_hash): string
    {
        // Create preview from first and last characters of hash
        return substr($key_hash, 0, 8) . '...' . substr($key_hash, -4);
    }
    
    /**
     * Check key status
     * 
     * @param array $key_data Key data
     * @return string Status
     */
    private static function get_key_status(array $key_data): string
    {
        if (isset($key_data['status']) && $key_data['status'] === 'revoked') {
            return 'revoked';
        }
        
        if (self::is_key_expired($key_data)) {
            return 'expired';
        }
        
        return 'active';
    }
    
    /**
     * Check if key is expired
     * 
     * @param array $key_data Key data
     * @return bool Is expired?
     */
    private static function is_key_expired(array $key_data): bool
    {
        if (!isset($key_data['expires_at']) || $key_data['expires_at'] === null) {
            return false; // No expiry, permanent
        }
        
        return time() > $key_data['expires_at'];
    }
    
    /**
     * Get all keys
     * 
     * @return array Key list
     */
    private static function get_all_keys(): array
    {
        $keys = get_option(self::OPTION_NAME, []);
        
        // Old format conversion (backward compatibility)
        if (!empty($keys) && !isset($keys[array_key_first($keys)]['id'])) {
            // Old format: ['general' => 'key', 'type2' => 'key2']
            // Convert to new format
            $new_keys = [];
            foreach ($keys as $type => $key) {
                if (is_string($key)) {
                    // Old format - single key
                    $key_id = 'key_' . md5($type . $key);
                    $new_keys[$key_id] = [
                        'id' => $key_id,
                        'name' => ucfirst($type) . ' API Key',
                        'key_hash' => self::hash_key($key),
                        'permissions' => ['read', 'write'],
                        'created_at' => time(),
                        'expires_at' => null,
                        'last_used_at' => null,
                        'status' => 'active'
                    ];
                }
            }
            
            if (!empty($new_keys)) {
                update_option(self::OPTION_NAME, $new_keys);
                return $new_keys;
            }
        }
        
        return is_array($keys) ? $keys : [];
    }
    
    /**
     * Save all keys
     * 
     * @param array $keys Key list
     * @return bool Success status
     */
    private static function save_all_keys(array $keys): bool
    {
        return update_option(self::OPTION_NAME, $keys) !== false;
    }
}

