<?php
/**
 * 使用curl方法用来获取接口返回值
 * 返回的信息中可能存在bom头信息，所以使用trim($str, chr(239).chr(187).chr(191))解决.
 *
 * @author lambert
 */
class CurlUtil
{
    public static $log;
    public static $_api_curl_upload_file = '__file';

    //get cookie for openapi
    public static $file = 'crmOpenApiCookie.txt';

    static public $capath='/etc/pki/tls/certs/cacert.pem';

    static public $caname='/etc/pki/tls/certs/cacert.pem';

    /**
     * 通过get方法获取$url对应的返回值.
     *
     * @param string $url
     * @param string $jsonstr
     * @param bool   $isjson  传递的参数是否为json格式
     *
     * @return ArrayIterator
     */
    public static function getInfoByGet($url, $jsonstr = null, $isjson = false)
    {
        $httpHeader = array();
         
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        if ($isjson === true) {
            // 设置请求格式为json
            $httpHeader[] = 'Content-Type: application/json; charset=utf-8';
            $httpHeader[] = 'Content-Length: '.strlen($jsonstr);
        }
    //openapi set cookie
        if ($jsonstr) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonstr);
        }
        // 头部信息不获取
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // 返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        // 执行并获取返回结果
        self::setAuthHttpHeader($httpHeader, $url);
        if ($httpHeader) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        }
        if (self::checkIsHttps($url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $content = curl_exec($ch);
        //判断http头状态信息是否为200
        $httpInfo = curl_getinfo($ch);

        if ('200' != $httpInfo['http_code']) {
            if ($httpInfo['http_code'] == '0') {
                throw new Exception('http请求超时', 0);
            } else {
                throw new Exception('http服务器错误', $httpInfo['http_code']);
            }
            //$content = '';
        }
        // 关闭CURL
        curl_close($ch);
        self::log($url, $jsonstr, $content, 'get');
    /*     $isEyar = self::checkIsEyar($url);
        if ($isEyar) {
        	$cookie_crm_openapi = self::getValidCookie();
            self::updateCookie($cookie_crm_openapi);
        } */

        return self::dealBom($content);
    }

    /**
     * 通过post方法获取$url对应的返回值，$postArr为提交的数据.
     *
     * @param string       $url
     * @param array|string $postArr
     * @param bool         $isjson  传递的参数是否为json格式
     *
     * @return ArrayIterator
     */
    public static function getInfoByPost($url, $postArr, $isjson = false, $params = array(), $isUpload = false)
    {
        $httpHeader = array();
     
        self::setAuthHttpHeader($httpHeader, $url);
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
       
        if ($isjson === true) {
            // 设置请求格式为json
            $httpHeader[] = 'Content-Type: application/json; charset=utf-8';
            $httpHeader[] = 'Content-Length: '.strlen($postArr);
        } else {
            // 设置提交方式
            curl_setopt($ch, CURLOPT_POST, count($postArr));
        }
        if ($httpHeader) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        }
       //openapi set cookie
        if ($isUpload) { //如果是上传文件，则对post的数组中的@作特殊处理
            $postArr = self::curl_set_post_file($postArr);
        }
        $datas = self::__datas_set($ch, $postArr); //对post的数组中的@作处理
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datas); // 传递信息{"customerMobi":"13700000000","customerName":"张磊磊"}
        // 头部信息不获取
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //如果需要向平台post信息则设置超时时间
        if (isset($params['timeout']) && $params['timeout'] > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $params['timeout']);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        // 返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        // 执行并获取返回结果
       
        if (self::checkIsHttps($url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $content = curl_exec($ch);
    
        //判断http头状态信息是否为200
        $httpInfo = curl_getinfo($ch);
        firephp(array($httpInfo,$content), __file__.__line__);
        if ('200' != $httpInfo['http_code']) {
            if ($httpInfo['http_code'] == '0') {
                throw new Exception('http请求超时', 0);
            } else {
                throw new Exception('http服务器错误', $httpInfo['http_code']);
            }
            //$content = '';
        }
        // 关闭CURL
        curl_close($ch);
        self::log($url, $postArr, $content, 'post');
       
        $result=self::dealBom($content);
        return $result;
    }

    private static function verifyHttps($url, &$ch)
    {
        $SSL = substr($url, 0, 8) == "https://" ? true : false;
        if ($SSL) {
            if (defined("URL_IS_CHECK_CA_CER") && URL_IS_CHECK_CA_CER) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_CAPATH, self::$capath);
                curl_setopt($ch, CURLOPT_CAINFO, self::$caname);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }
        }
          return;
    }
    /**
     * 设置鉴权Cookie     *
     * @param array       $httpHeader
     */
    public static function setAuthHttpHeader(&$httpHeader, $url)
    {
        //get cookie for openapi
        $cookie_crm_openapi = '';
        $isEyar = false;
        if (defined("OPEN_API_AUTHORITY") && OPEN_API_AUTHORITY == 1) {
            //开通鉴权
            $isEyar = self::checkIsEyar($url);
            if ($isEyar == true) {
                $cookie_crm_openapi = self::getValidCookie();
            }
        }
        //openapi set cookie
        if (!empty($cookie_crm_openapi)) {
            $httpHeader[] = 'Cookie:tsid='.$cookie_crm_openapi;
            self::updateCookie($cookie_crm_openapi);
        }
    }

    /**
     * 处理请求的参数数组.
     *
     * @method __datas_set
     *
     * @author   lingrongwei
     *
     * @param unknown_type $curl
     * @param array        $datas : 参数数组
     *
     * @return array
     *
     * @since v1.4.0
     */
    public static function __datas_set($curl, $datas = null)
    {
        if (empty($datas)) {
            return;
        }
        if (is_array($datas)) {
            $_multipart_form_data_head_fmt = 'Content-Type: multipart/form-data; boundary=----------------------------%s';
            $_multipart_form_data_body_string = "------------------------------%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n";
            $_multipart_form_data_body_file = "------------------------------%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: application/octet-stream\r\n\r\n%s\r\n";
            $_multipart_form_data_body_end = "------------------------------%s--\r\n\r\n";
            $custom_body = false;
            $uniqid = uniqid();
            $custom_body_str = '';
            foreach ($datas as $name => $data) {
                if (is_array($data) && array_key_exists(self::$_api_curl_upload_file, $data)) {
                    $file = substr($data[self::$_api_curl_upload_file], 1); //去掉字符串开头的@符号
                    if (file_exists($file)) {
                        $custom_body = true;
                        $custom_body_str    .= sprintf($_multipart_form_data_body_file,$uniqid, $name, $file, file_get_contents($file));
                    }
                } else {
                    $custom_body = true;
                    $custom_body_str.= sprintf($_multipart_form_data_body_string, $uniqid, $name, $data);
                }
            }
            if ($custom_body) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(sprintf($_multipart_form_data_head_fmt, $uniqid)));
                $datas = $custom_body_str.sprintf($_multipart_form_data_body_end, $uniqid);
            }
        }

        return $datas;
    }

    /**
     * 重构参数数组，当以@开头表示上传文件时，将该字段的值转为数组.
     *
     * @method curl_set_post_file
     *
     * @author   lingrongwei
     *
     * @param array $postArr
     *
     * @return array
     *
     * @since v1.4.0
     */
    public static function curl_set_post_file($postArr)
    {
        foreach ($postArr as $key => $val) {
            if (strpos($val, '@') === 0) {
                $postArr[$key] = array(self::$_api_curl_upload_file => $val);
            }
        }

        return $postArr;
    }

    /**
     * 将$content中可能出现的bom信息清楚，否则使用json_decode解析时会出现空白情况.
     *
     * @param string $content
     *
     * @return string
     */
    protected static function dealBom($content)
    {
        return json_decode(trim($content, chr(239).chr(187).chr(191)), true); // 0xEF 0xBB 0xBF
    }

    /**
     * @method log
     *
     * @author   yuminkang
     *
     * @param unknown_type $url
     * @param unknown_type $postArr
     * @param unknown_type $content
     *
     * @return
     *
     * @since v1.6.0
     */
    public static function log($url, $postArr, $content, $method)
    {
        if (defined('EETOPIN_LOG_DEBUG') && EETOPIN_LOG_DEBUG) {
            $json_str = @json_encode($postArr);
            //$haveError = json_last_error();
            $haveError = false;
            if ($haveError) {
                $json_str = '含有json无法encode的数据（例如：流格式），所以不予log记录';
            }
            self::$log = 'url:'.$url.';params:'.$json_str.';return:'.$content;
        }
        if (defined('API_DEBUG') && API_DEBUG) {
            if (isset($_GET['api_debug'])) {
                $boreder = 'red';
                if ($method == 'get') {
                    $boreder = 'green';
                }
                echo '<div style="border:1px solid '.$boreder.';background:#f0f0f0;border-top:5px solid '.$boreder.';margin-bottom:5px;"><div>api '.$method.'</div><div style="padding:10px 0;width:100%">URL:'.$url.'</div>';
                echo '<div>';
                print_r($postArr);
                echo '</div>';
                echo '	<div style="border-top:1px solid #aa5500;padding:10px 0;width:100%">RETURN:'.$content.'</div></div>';
            }
        }
    }

    //get cookie for openapi

    public static function post($action, $post_data, $headerflag)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $action);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //检验并兼容Https
        if (self::checkIsHttps($action)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_HEADER, $headerflag);
        // post数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // post的变量
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

  /**
   * 从crm获取cookie存储 openapi
   * 获取头部信息
   * 通过get方法获取$url对应的返回值.
   *
   * @param string $url
   * @param string $jsonstr
   * @param bool $isjson 传递的参数是否为json格式
   *
   * @return ArrayIterator
   */
    public static function get($url, $headerflag, $cookie, $jsonstr = null, $isjson = false)
    {
        $httpHeader = array();
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        if ($isjson === true) {
            // 设置请求格式为json
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json; charset=utf-8',
              'Content-Length: '.strlen($jsonstr),
            ));
        }
     
        if ($jsonstr) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonstr);
        }
        if (!empty($cookie)) {
            $httpHeader[] = 'Cookie:tsid='.$cookie;
        }
        if ($httpHeader) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        // 头部信息获取
        curl_setopt($ch, CURLOPT_HEADER, $headerflag);
        // 返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        // 执行并获取返回结果
        if (self::checkIsHttps($url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $content = curl_exec($ch);
        //判断http头状态信息是否为200
        $httpInfo = curl_getinfo($ch);
        if ('200' != $httpInfo['http_code']) {
            if ($httpInfo['http_code'] == '0') {
                throw new Exception('http请求超时', 0);
            } else {
                throw new Exception('http服务器错误', $httpInfo['http_code']);
            }
        //$content = '';
        }
        // 关闭CURL
        curl_close($ch);
        return $content;
    }
    private static function checkIsHttps($url)
    {
        $SSL = substr($url, 0, 8) == "https://" ? true : false;
        return $SSL;
    }
    //使openApi Cookie有效
    public static function validCookieForLogin()
    {
        try {
            //登录获取Location tgt
            $crmPostArr = '';
            $crmPostArr = 'username='.CRM_OPENAPI_USER.'&password='.CRM_OPENAPI_PASSWD;
   
            $rsContent = self::post(CRM_OPENAPI_GET_TGT, $crmPostArr, 1);
            preg_match_all("|Location: ([^\s]+)|", $rsContent, $match_array);
          
            $Loaction = $match_array[1][0];
            $Loaction_array = preg_split("|\/|", $Loaction);
          
            $tgt = $Loaction_array[count($Loaction_array) - 1];
           
          //获取tickets
            $crmPostArr = 'service='.API_SANYE_HOSPITALS;
          
            $ticket = self::post(CRM_OPENAPI_GET_TGT.'/'.$tgt, $crmPostArr, 0);
         
            $rsContent = self::get(API_SANYE_HOSPITALS."?ticket={$ticket}", 1, ''); // Http::getInfoByGet_crm(API_SANYE_HOSPITALS."?ticket={$ticket}");
         
            preg_match_all('|tsid=([A-Za-z0-9]+)|', $rsContent, $match_array);
        
            $cookie = $match_array[1][1];
          
            self::updateCookie($cookie);
    
            return $cookie;
        } catch (Exception $e) {
            throw new Exception('openapi登录验证失败：'.$e->getMessage(), 0);
        }
    }

    public static function getValidCookie()
    {
        //return self::validCookieForLogin();
        $file = self::$file;
        if (!file_exists($file)) {
            $fp = fopen($file, 'w');
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, rand(5000, 9000).'|'.(time() - CRM_OPENAPI_CACHE_TIME - 60));//置为过期
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
        $cookie_crm_openapi = file_get_contents($file,  true);
        $cookie_crm_openapi_array = explode('|', $cookie_crm_openapi);
        $cookie_crm_openapi = $cookie_crm_openapi_array[0];
        $cookie_crm_openapi_time = $cookie_crm_openapi_array[1];

        //超过10分钟重新登录
        if ((time() - $cookie_crm_openapi_time) > CRM_OPENAPI_CACHE_TIME || $cookie_crm_openapi == '') {
            if (strlen($cookie_crm_openapi) < 10) {
                //第1次创建cookie文件，必须登录获取cookie
                return self::validCookieForLogin();
            } else {
                //已经有cookie文件了
                try {
                    $rsContent = self::get(API_SANYE_HOSPITALS, 0, $cookie_crm_openapi);
                    return $cookie_crm_openapi;
                } catch (Exception $e) {
                    return self::validCookieForLogin();
                }
            }
        } else {
            return $cookie_crm_openapi;
        }
    }

  //更新时间
    public static function updateCookie($cookie)
    {
        $cookie = $cookie.'|'.time();
        //写Cookie
        $file = self::$file;
        $fp = fopen($file, 'w');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $cookie);
            flock($fp, LOCK_UN);
        }
            fclose($fp);
    }

    //检查是否是eyar
    public static function checkIsEyar($url)
    {
        $domainstr = parse_url($url);
        $domain = strtolower($domainstr['host']); //取域名部分
        return strpos($domain, 'eyar.com') == false ? false : true;
    }
}