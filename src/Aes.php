<?php
namespace KanLai;

class Aes
{

    public static ?Aes $instance = null;
    protected string $method;
    protected string $secret_key;
    protected string $iv;
    protected int $options = 0;

    /**
     * 构造函数
     *
     * @param string $key 密钥
     * @param string $method 加密方式
     * @param string $iv iv向量
     * @param int $options 还不是很清楚
     *
     */
    public function __construct(string $key = 'kanlai', string $method = 'AES-256-ECB', string $iv = '', int $options = 0)
    {
        $this->secret_key = $key;
        $this->method = $method;
        $this->iv = $iv;
        $this->options = $options;
    }

    /**
     * @param string $key
     * @param string $method
     * @param string $iv
     * @param int $options
     * @return Aes
     */
    public static function getInstance(string $key = 'kanlai', string $method = 'AES-256-ECB', string $iv = '', int $options = 0): Aes
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($key, $method, $iv, $options);
        }
        return self::$instance;
    }

    /**
     * 加密方法，对数据进行加密，返回加密后的数据
     * @param string $data
     * @return string|bool
     */
    public function encrypt(string $data): string|bool
    {
        return base64_encode(openssl_encrypt($data, $this->method, $this->secret_key, $this->options, $this->iv));
    }

    /**
     * 解密方法，对数据进行解密，返回解密后的数据
     * @param string $data
     * @return string|bool
     */
    public function decrypt(string $data): string|bool
    {
        return openssl_decrypt(base64_decode($data), $this->method, $this->secret_key, $this->options, $this->iv);
    }
}