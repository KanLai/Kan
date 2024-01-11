<?php

namespace KanLai;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;


class YaoPan
{
    private static ?YaoPan $yaoPan = null;
    private string $url = '';
    private Client $client;
    private string $password = '';
    private string $shareKey = '';


    private string $firstUrl = '/b/api/share/get?limit=100&next=1&orderBy=share_id&orderDirection=desc&shareKey={key}&SharePwd={pw}&ParentFileId=0&Page=1';
    private string $secondUrl = '/b/api/share/download/info';

    /**
     * 获取真实链接
     * @param string $url 完整的链接地址包含https
     * @param string $pw 提取码
     * @return object item {code:1 || 0 ,real="real link",msg="error"}
     */
    public static function get(string $url, string $pw): object
    {
        if (is_null(self::$yaoPan)) {
            self::$yaoPan = new static ();
        }
        self::$yaoPan->parseUrl($url, $pw);
        return self::$yaoPan->exec();

    }

    public function exec(): object
    {

        $real = str_replace('{key}', $this->shareKey, $this->firstUrl);
        $real = str_replace('{pw}', $this->password, $real);
        try {
            $response = $this->client->get($real, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Redmi K30 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Mobile Safari/537.36',
                ]
            ]);
            $data = $response->getBody()->getContents();
            if (empty($data)) {
                return (object)[
                    'code' => 0,
                    'msg' => 'response is empty'
                ];
            }
            $data = json_decode($data);
            if (isset($data->code) && $data->code == 0) {
                return $this->parseSecond($data->data->InfoList[0]);
            }
            return (object)[
                'code' => 0,
                'msg' => 'json data is empty'
            ];
        } catch (GuzzleException $e) {
            return (object)[
                'code' => 0,
                'msg' => $e->getMessage()
            ];
        }

    }

    private function parseUrl(string $url, $pw): void
    {
        $this->password = $pw;
        $params1 = parse_url($url);
        $this->url = $params1['scheme'] . '://' . $params1['host'];
        $params = explode('/', $params1['path']);
        list($this->shareKey) = explode('.', $params[2]);
        $this->initHttp($url);
    }


    private function initHttp($url): void
    {
        $this->client = new Client([
            'base_uri' => $this->url,
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36',
                'referer' => $url
            ]
        ]);
    }

    private function parseSecond(object $data): object
    {
        try {
            list($token, $key) = $this->getSign($this->secondUrl);
            $url = $this->secondUrl . '?' . $token . '=' . $key;
            $response = $this->client->post($url, [
                'json' =>
                    [
                        'ShareKey' => $this->shareKey,
                        'FileID' => $data->FileId,
                        'S3keyFlag' => $data->S3KeyFlag,
                        'Size' => $data->Size,
                        'Etag' => $data->Etag,
                    ],
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'Referer' => $this->url . '/s/' . $this->shareKey . 'html',
                    'Origin' => $this->url,
                    'Accept-Language' => 'zh-CN,zh;q=0.9',
                    'App-Version' => '3',
                    'platform' => 'web',
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Redmi K30 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Mobile Safari/537.36',
                ]
            ]);
            $responseData = json_decode($response->getBody()->getContents());
            if (isset($responseData->code) && $responseData->code == 0) {
                $downloadUrl = $responseData->data->DownloadURL;
                $paresData = parse_url($downloadUrl);
                parse_str($paresData['query'], $params);
                $realUrl = base64_decode($params['params']);
                $response = $this->client->get($realUrl);
                $responseData = json_decode($response->getBody()->getContents());
                if (isset($responseData->code) && $responseData->code == 0) {
                    return (object)[
                        'code' => 1,
                        'msg' => $responseData->message,
                        'real' => $responseData->data->redirect_url
                    ];
                }

            }
            return (object)[
                'code' => 0,
                'msg' => 'get link is fail',
            ];
        } catch (GuzzleException $e) {
            return (object)[
                'code' => 0,
                'msg' => $e->getMessage()
            ];
        }
    }

    function processDate(string $datetime): array
    {

        try {
            $time = new DateTime(date('Y-m-d H:i:s', $datetime), new DateTimeZone('Asia/Shanghai'));
            return [
                'y' => $time->format('Y'),
                'm' => $time->format('m'),
                'd' => $time->format('d'),
                'h' => $time->format('H'),
                'f' => $time->format('i'),
            ];
        } catch (Exception) {
            return [
                'y' => date('Y'),
                'm' => date('m'),
                'd' => date('d'),
                'h' => date('H'),
                'f' => date('i'),
            ];
        }

    }


    function generateHash($inputString, $base = 10): string
    {
        $crc32Table = $this->generateCrc32Table();
        $hash = -1;

        for ($i = 0; $i < strlen($inputString); $i++) {
            $hash = $this->upright($hash, 8) ^ $crc32Table[0xFF & ($hash ^ ord($inputString[$i]))];
        }

        $hash = $this->upright((-1 ^ $hash), 0);
        return base_convert($hash, 10, $base);
    }

    function generateCrc32Table(): array
    {
        $crc32Table = [];
        for ($i = 0; $i < 256; $i++) {
            $crc = $i;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 1) ? 0xEDB88320 ^ $this->upright($crc, 1) : $this->upright($crc, 1);
            }
            $crc32Table[$i] = $crc;
        }
        return $crc32Table;
    }

    function upright($num, $n): int
    {
        $num = intval($num) & 0xFFFFFFFF;
        return $num >> $n;
    }


    function getSign($input): array
    {
        $web = 'web';
        $num = 3;
        $timestamp = time();
        $key = explode(',', 'a,d,e,f,g,h,l,m,y,i,j,n,o,p,k,q,r,s,t,u,b,c,v,w,s,z');
        $randomNumber = rand(0, 1000000);
        $dateParts = $this->processDate($timestamp);
        $dateString = implode('', array_values($dateParts));
        $mappedString = '';
        foreach (str_split($dateString) as $char) {
            $mappedString .= $key[(int)$char];
        }
        $firstPart = $this->generateHash($mappedString);
        $secondPart = $this->generateHash($timestamp . '|' . $randomNumber . '|' . $input . '|' . $web . '|' . $num . '|' . $firstPart);

        return [$firstPart, $timestamp . '-' . $randomNumber . '-' . $secondPart];
    }


}