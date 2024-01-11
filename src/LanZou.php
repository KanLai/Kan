<?php
/*
 * 蓝奏云直连解析
 * @author: QQ:380943047
 * @site:https://www.kanlai.com.cn
 * @email:admin@kanlai.com.cn
 * @ex LanZou::get($url,$password);
 * @return object item {code:1 || 0 ,real="real link",msg="error"}
 */

namespace KanLai;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;
use QL\QueryList;
use stdClass;
use Throwable;

class LanZou
{
    private static ?LanZou $lanZou = null;
    private string $url;
    protected Client $client;
    private string $body;
    private string $pw;


    /**
     * start get real link parse
     * @param string $url
     * @param string $pw
     * @return object item {code:1 || 0 ,real="real link",msg="error"}
     */
    public static function get(string $url, string $pw = ""): object
    {
        if (is_null(self::$lanZou)) {
            self::$lanZou = new static ($url, $pw);
        }
        self::$lanZou->setUrl($url);
        return self::$lanZou->exec();
    }

    /**
     * init http client
     * @return void
     */
    private function initHttp(): void
    {
        $params = parse_url($this->url);
        $baseUrl = "https://{$params['host']}/";
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36',
                'referer' => $baseUrl
            ]
        ]);


    }

    /**
     * password is not empty run
     * @throws Exception
     */
    private function isPassWord(): object
    {
        $url = $this->getUrl();
        $pattern = "/var\s*skdklds\s*=\s*'(.*?)'/";
        $bool = preg_match($pattern, $this->body, $params);
        if ($bool) {
            $data = new stdClass();
            $data->url = $url;
            $data->data = [
                'action' => 'downprocess',
                'sign' => $params[1],
                'p' => $this->pw
            ];
            return $data;
        }
        throw new Exception('password parse is fail');
    }

    /**
     * core code
     * @return object
     */
    protected function exec(): object
    {
        $this->initHttp();
        $realLink = '';
        $code = 0;
        try {
            $response = $this->client->get($this->url);
            if ($response->getStatusCode() == 200) {
                $body = $response->getBody()->getContents();
                $this->body = $body;
                $dom = QueryList::getInstance()->html($body);
                if (!empty($this->pw)) {
                    $obj = $this->isPassWord();
                } else {
                    $iframeSrc = $dom->find('iframe.ifr2')->src;
                    $this->body = $this->parseFn($iframeSrc);
                    $obj = $this->getParams();
                }
                $code = 1;
                $realLink = $this->parseLink($obj);
                $errMsg = "success";
            } else {
                $errMsg = 'fail';
            }
        } catch (Throwable $exception) {
            $errMsg = $exception->getMessage();
        }
        $response = new stdClass();
        $response->code = $code;
        $response->file = $realLink['file'];
        $response->real = $realLink['url'];
        $response->msg = $errMsg;
        return $response;
    }

    /**
     * @param stdClass $o
     * @return array
     * @throws Exception
     */
    #[ArrayShape(['url' => '', 'file' => ''])]
    private function parseLink(stdClass $o): array
    {
        try {
            $response = $this->client->post($o->url, [
                'form_params' => $o->data,
                'headers' => [
                    'referer' => $this->url
                ],
            ]);
            $data = json_decode($response->getBody()->getContents());
            if (is_null($data)) {
                throw new Exception("json parse is no success");
            }
            return ['url' => $data->dom . '/file/' . $data->url, 'file' => $data->inf];
        } catch (GuzzleException $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * @return stdClass
     * @throws Exception
     */
    private function getParams(): stdClass
    {
        try {

            $pattern = "/data\s*:\s*\{(.*?)},/s";
            $bool = preg_match($pattern, $this->body, $arr);
            $str = trim($arr[1]);
            $paramsStr = explode(',', $str);
            $params = new stdClass();
            foreach ($paramsStr as $param) {
                list($key, $value) = explode(':', $param);
                $key = trim($key, '\'');
                $value = trim($value, '\'');
                $params->$key = $value;
            }
            $data = new stdClass();
            if ($bool) {
                $url = $this->getUrl();
                $data->url = $url;
                $data->data = $params;
                return $data;
            }
            throw new Exception('params parse is not success');
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function getUrl(): string
    {
        $pattern = "/url\s*:\s*'(.*?)'/";
        $bool = preg_match($pattern, $this->body, $arr);
        if ($bool) {
            return $arr[1];
        }
        throw  new Exception('url is empty');
    }
    /**
     * parse fn link
     * @throws Exception
     */
    protected function parseFn(string $fn): string
    {
        try {
            $response = $this->client->get($fn);
            return $response->getBody()->getContents();
        } catch (Throwable $exception) {
            throw  new Exception($exception->getMessage());
        }
    }

    public function __construct(string $url, string $pw = '')
    {
        $this->url = $url;
        $this->pw = $pw;
    }

    /**
     * @param string $url
     */
    private function setUrl(string $url): void
    {
        $this->url = $url;
    }


}