<?php
namespace Zero\Lib;

class Session {
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function clear() {
        session_unset();
    }

    public static function success($key, $value = null) {
        if ($value) {
            self::set('success', $value);
        } else {
            return self::get('success');
        }
    }

    public static function error($key, $value = null) {
        if ($value) {
            self::set('error', $value);
        } else {
            return self::get('error');
        }
    }

    public static function flash($key, $value = null) {
        if ($value) {
            self::set('flash', $value);
        } else {
            $val = self::get($key);
            self::remove($key);
            return $val;
        }
    }

    public static function old($key, $value = null) {
        if ($value) {
            self::set('old', $value);
        } else {
            return self::get('old');
        }
    }

    public static function destroy() {
        session_destroy();
    }
}