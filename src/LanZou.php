<?php
/*
 * 蓝奏云直连解析
 * @author: QQ:380943047
 * @site:https://www.kanlai.com.cn
 * @email:admin@kanlai.com.cn
 * @ex LanZou::get($url,$password);
 * @return object item {code:1 || 0 ,real="real link",msg="error"}
 */

namespace Kan;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use PHPHtmlParser\Dom;
use stdClass;
use Throwable;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;

class LanZou
{
    private static ?LanZou $lanZou = null;
    private string $url;
    protected Client $client;
    private string $body;
    private string $pw;
    private static bool $isService = false;

    /**
     * start get real link parse
     * @param string $url
     * @param bool $isService
     * @param string $pw
     * @return object
     */
    public static function get(string $url, bool $isService = false, string $pw = ""): object
    {
        self::$isService = $isService;
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
        if (self::$isService) {
            $handler =new SwooleHandler();
            $stack = HandlerStack::create($handler);
            $this->client = new Client([
                'handler' => $stack,
                'base_uri' => $baseUrl,
                'timeout' => 60,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36',
                    'referer' => $baseUrl
                ]
            ]);
        }else{
            $this->client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 60,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36',
                    'referer' => $baseUrl
                ]
            ]);
        }

    }

    /**
     * password is not empty run
     * @throws Exception
     */
    private function isPassWord(): object
    {
        $url = $this->getUrl();
        $bool = preg_match('/data : \'(.*?)&p=\'\+pwd/', $this->body, $params);
        if ($bool) {
            $result = end($params);
            $result .= '&p=' . $this->pw;
            parse_str($result, $arr);
            $data = new stdClass();
            $data->url = $url;
            $data->data = $arr;
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
                $dom = new Dom();
                $dom->loadStr($body);
                if (!empty($this->pw)) {
                    $obj = $this->isPassWord();
                } else {
                    $iframe = $dom->find('iframe.ifr2')[0];
                    $fn = $iframe->getAttribute('src');
                    $this->body = $this->parseFn($fn);
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
        $response->real = $realLink;
        $response->msg = $errMsg;
        return $response;
    }

    /**
     * @throws Exception
     */
    private function parseLink(stdClass $o): string
    {
        try {
            $response = $this->client->post($o->url, [
                'form_params' => $o->data
            ]);
            $data = json_decode($response->getBody()->getContents());
            if (is_null($data)) {
                throw new Exception("json parse is no success");
            }
            return $data->dom . '/file/' . $data->url;
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
            $bool = preg_match('/\'action\':\'(.*?)\',\'signs\':(.*?),\'sign\':(.*?),\'ves\':(.*?),\'websign\':\'(.*?)\',\'websignkey\':\'(.*?)\'/', $this->body, $arr);
            $data = new stdClass();
            if ($bool) {
                $url = $this->getUrl();
                $ajaxData = ($this->getAjaxDataValue($arr[2]));
                $pDownload = $this->getPDownload($arr[3]);
                $data->url = $url;
                $data->data = [
                    'action' => $arr[1],
                    'signs' => $ajaxData,
                    'sign' => $pDownload,
                    'ves' => $arr[4],
                    'websign' => $arr[5],
                    'websignkey' => $arr[6],
                ];
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
        $bool = preg_match('/url : \'(.*?)\',/', $this->body, $arr);
        if ($bool) {
            return $arr[1];
        }
        throw  new Exception('url is empty');
    }

    /**
     * @throws Exception
     */
    private function getPDownload(string $name): string
    {
        $bool = preg_match('/' . $name . ' = \'(.*?)\';/', $this->body, $arr);
        if ($bool) {
            return $arr[1];
        }
        throw  new Exception('pDownload is empty');
    }

    /**
     * @throws Exception
     */
    private function getAjaxDataValue(string $name): string
    {
        $bool = preg_match('/' . $name . ' = \'(.*?)\';/', $this->body, $arr);
        if ($bool) {
            return $arr[1];
        }
        throw  new Exception('ajaxData is empty');
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