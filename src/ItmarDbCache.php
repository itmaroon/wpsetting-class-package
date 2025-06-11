<?php

namespace Itmar\WpsettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarDbCache
{

    protected static string $group = 'itmar_cache';

    public static function get_var_cached(string $sql, string $cache_key, int $expire = 3600)
    {
        $result = wp_cache_get($cache_key, self::$group);
        if ($result === false) {
            global $wpdb;
            $result = $wpdb->get_var($sql);
            if ($result !== null) {
                wp_cache_set($cache_key, $result, self::$group, $expire);
            }
        }
        return $result;
    }

    public static function get_row_cached(string $sql, string $cache_key, int $expire = 3600, string $output = ARRAY_A)
    {
        $result = wp_cache_get($cache_key, self::$group);
        if ($result === false) {
            global $wpdb;
            $result = $wpdb->get_row($sql, $output);
            if ($result !== null) {
                wp_cache_set($cache_key, $result, self::$group, $expire);
            }
        }
        return $result;
    }

    public static function update_and_clear_cache(string $table, array $data, array $where, ?array $data_format = null, ?array $where_format = null, array $cache_keys = [])
    {
        global $wpdb;
        $result = $wpdb->update($table, $data, $where, $data_format, $where_format);
        if ($result !== false) {
            foreach ($cache_keys as $key) {
                wp_cache_delete($key, self::$group);
            }
        }
        return $result;
    }

    public static function delete_and_clear_cache(string $table, array $where, ?array $where_format = null, array $cache_keys = [])
    {
        global $wpdb;
        $result = $wpdb->delete($table, $where, $where_format);
        if ($result !== false) {
            foreach ($cache_keys as $key) {
                wp_cache_delete($key, self::$group);
            }
        }
        return $result;
    }

    public static function set_cache_group(string $group)
    {
        self::$group = $group;
    }
}
