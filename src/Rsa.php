<?php
namespace Kan;

use OpenSSLAsymmetricKey;

class Rsa
{
    private static ?Rsa $instance = null;
    private string $keyFile = 'private.pem';
    private string $path;
    private bool|OpenSSLAsymmetricKey $privateKey;
    private string|bool $publicKey;

    const en_size = 200;
    const de_size = 256;

    public function __construct(string $path = './Rsa/')
    {
        $this->path = $path;
        $this->createRsa();
        $this->privateKey = $this->getPrivate();
        $this->publicKey = $this->getPublicKey();
    }

    /**
     *
     * @param string $path 存放文件的路径
     * @return static
     */
    public static function getInstance(string $path = ''): static
    {
        if (self::$instance === null) {
            self::$instance = new static($path);
        }
        return self::$instance;
    }

    /**
     * createRsaPemFile
     */
    private function createRsa()
    {
        $res = openssl_pkey_new(
            [
                "private_key_bits" => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA
            ]
        );
        openssl_pkey_export($res, $privKey);
        if (!is_dir($this->path)) {
            mkdir($this->path, 755, true);
        }
        if (!file_exists($this->path . $this->keyFile)) {
            file_put_contents($this->path . $this->keyFile, $privKey);
        }
    }

    /**
     * 私钥加密 返回 base64
     * @param string $plainData
     * @param int $padding
     * @return bool|string
     */
    public function privateKeyEncryptStr(string $plainData, int $padding = 1): bool|string
    {
        if ($this->privateKey !== false) {
            $encrypted = '';
            $plainData = str_split($plainData, Rsa::en_size);
            foreach ($plainData as $chunk) {
                $encryptionOk = openssl_private_encrypt($chunk, $partialEncrypted, $this->privateKey, $padding);
                if ($encryptionOk === false) {
                    return false;
                }
                $encrypted .= $partialEncrypted;
            }
            return base64_encode($encrypted);
        }
        return false;
    }

    /**
     * 公钥解密 base64数据
     * @param string $data
     * @param int $padding
     * @return bool|string
     */
    public function publicKeyDecryptStr(string $data, int $padding = 1): bool|string
    {
        $decrypted = '';
        $data = str_split(base64_decode($data), Rsa::de_size);
        if ($this->publicKey !== false) {
            $partial = '';
            foreach ($data as $chunk) {
                $decryptedOk = openssl_public_decrypt($chunk, $partial, $this->publicKey, $padding);
                if ($decryptedOk === false) {
                    return false;
                }
                $decrypted .= $partial;
            }
            return $decrypted;
        }
        return false;
    }

    /**
     * 私钥解密 base64 数据
     * @param string $data
     * @param int $padding
     * @return bool|string
     */
    public function privateKeyDecryptStr(string $data, int $padding = 1): bool|string
    {
        if ($this->privateKey !== false) {
            $data = str_split(base64_decode($data), Rsa::de_size);
            $decrypted = '';
            foreach ($data as $item) {
                $string = '';
                $decryptedOk = openssl_private_decrypt($item, $string, $this->privateKey, $padding);
                if ($decryptedOk === false) {
                    return false;
                }
                $decrypted .= $string;
            }
            return $decrypted;
        }
        return false;

    }

    /**
     * 公钥加密 base64 数据
     * @param string $data
     * @param int $padding
     * @return bool|string
     */
    public function publicKeyEncryptStr(string $data, int $padding = 1): bool|string
    {
        if ($this->publicKey !== false) {
            $encrypted = '';
            $plainData = str_split($data, Rsa::en_size);
            foreach ($plainData as $plainDatum) {
                $string = '';
                $encryptedOk = openssl_public_encrypt($plainDatum, $string, $this->publicKey, $padding);
                if ($encryptedOk === false) {
                    return false;
                }
                $encrypted .= $string;
            }
            return base64_encode($encrypted);
        }
        return false;
    }

    /**
     * 获取私钥
     * @return false|OpenSSLAsymmetricKey
     */
    protected function getPrivate(): bool|OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_private(file_get_contents($this->path . $this->keyFile));
    }

    /**
     * 获取公钥
     * @return string|bool
     */
    private function getPublicKey(): string|bool
    {

        if ($this->privateKey !== false) {
            $OpenSSLPublicKey = openssl_pkey_get_details($this->privateKey);
            if ($OpenSSLPublicKey !== false) {
                return $OpenSSLPublicKey['key'];
            }
        }
        return false;
    }
}