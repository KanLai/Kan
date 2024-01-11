<?php

namespace KanLai;
class YaoReal
{
    private static ?YaoReal $yaoReal = null;
    private string $authKey = 'kanlai';
    private string $uid = '1825990344';
    private string $url = 'http://pan.kanlai.com.cn/';

    public static function get(string $folder, string $name): object
    {
        if (is_null(self::$yaoReal)) {
            self::$yaoReal = new static ();
        }
        return self::$yaoReal->exec($folder, $name);
    }

    private function exec(string $folder, string $name): object
    {
        $end = time() + 600;
        $rond = rand(100000, 999999);
        $auth_key = md5("/$this->uid/$folder/$name-$end-$rond-$this->uid-" . $this->authKey);
        $url = $this->url . "$this->uid/$folder/$name?auth_key=$end-$rond-$this->uid-$auth_key";
        return (object)[
            'code' => 1,
            'real' => $url
        ];
    }
}