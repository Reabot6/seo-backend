<?php
namespace app\model;

use think\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    // Get a setting value by key
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }

    // Set a setting value by key
    public static function set($key, $value)
    {
        $setting = self::where('key', $key)->find();

        if ($setting) {
            $setting->value = $value;
            $setting->save();
        } else {
            self::create(['key' => $key, 'value' => $value]);
        }
    }

    // Set multiple settings at once
    public static function setMany(array $data)
    {
        foreach ($data as $key => $value) {
            self::set($key, $value);
        }
    }
}