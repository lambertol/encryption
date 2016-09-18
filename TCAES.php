<?PHP
/**
 * AES加密、解密类.
 *
 * @author lambert
 *
 * 用法：
 * <pre>
 * // 实例化类
 * // 参数$_bit：格式，支持256、192、128，默认为128字节的
 * // 参数$_type：加密/解密方式，支持cfb、cbc、nofb、ofb、stream、ecb，默认为ecb
 * // 参数$_key：密钥，默认为abcdefghijuklmno
 * $tcaes = new TCAES();
 * $string = 'laohu';
 * // 加密
 * $encodeString = $tcaes->encode($string);
 * // 解密
 * $decodeString = $tcaes->decode($encodeString);
 * </pre>
 */
class TCAES
{
    private $_bit = MCRYPT_RIJNDAEL_256;
    private $_type = MCRYPT_MODE_CBC;
    //private $_key = 'abcdefghijuklmno0123456789012345';
    private $_key = 'abcdefghijuklmno'; // 密钥
    private $_use_base64 = true;
    private $_iv_size = null;
    private $_iv = null;

    /**
     * @param string $_key        密钥
     * @param int    $_bit        默认使用128字节
     * @param string $_type       加密解密方式
     * @param bool   $_use_base64 默认使用base64二次加密
     */
    public function __construct($_key = '', $_bit = 128, $_type = 'ecb', $_use_base64 = true)
    {
        // 加密字节
        if (192 === $_bit) {
            $this->_bit = MCRYPT_RIJNDAEL_192;
        } elseif (128 === $_bit) {
            $this->_bit = MCRYPT_RIJNDAEL_128;
        } else {
            $this->_bit = MCRYPT_RIJNDAEL_256;
        }
        // 加密方法
        if ('cfb' === $_type) {
            $this->_type = MCRYPT_MODE_CFB;
        } elseif ('cbc' === $_type) {
            $this->_type = MCRYPT_MODE_CBC;
        } elseif ('nofb' === $_type) {
            $this->_type = MCRYPT_MODE_NOFB;
        } elseif ('ofb' === $_type) {
            $this->_type = MCRYPT_MODE_OFB;
        } elseif ('stream' === $_type) {
            $this->_type = MCRYPT_MODE_STREAM;
        } else {
            $this->_type = MCRYPT_MODE_ECB;
        }
        // 密钥
        if (!empty($_key)) {
            $this->_key = $_key;
        }
        // 是否使用base64
        $this->_use_base64 = $_use_base64;

        $this->_iv_size = mcrypt_get_iv_size($this->_bit, $this->_type);
        $this->_iv = mcrypt_create_iv($this->_iv_size, MCRYPT_RAND);
    }

    /**
     * 加密.
     *
     * @param string $string 待加密字符串
     *
     * @return string
     */
    public function encode($string)
    {
        if (MCRYPT_MODE_ECB === $this->_type) {
            $encodeString = @mcrypt_encrypt($this->_bit, $this->_key, $string, $this->_type);
        } else {
            $encodeString = @mcrypt_encrypt($this->_bit, $this->_key, $string, $this->_type, $this->_iv);
        }
        if ($this->_use_base64) {
            $encodeString = base64_encode($encodeString);
        }

        return $encodeString;
    }

    /**
     * 解密.
     *
     * @param string $string 待解密字符串
     *
     * @return string
     */
    public function decode($string)
    {
        if ($this->_use_base64) {
            $string = base64_decode($string);
        }

        if (MCRYPT_MODE_ECB === $this->_type) {
            $decodeString = @mcrypt_decrypt($this->_bit, $this->_key, $string, $this->_type);
        } else {
            $decodeString = @mcrypt_decrypt($this->_bit, $this->_key, $string, $this->_type, $this->_iv);
        }
        $decodeString = rtrim($decodeString, "\0\4\x0");

        return $decodeString;
    }

    /**
     * 将$string转换成十六进制.
     *
     * @param string $string
     *
     * @return stream
     */
    private function toHexString($string)
    {
        $buf = '';
        for ($i = 0; $i < strlen($string); ++$i) {
            $val = dechex(ord($string{$i}));
            if (strlen($val) < 2) {
                $val = '0'.$val;
            }
            $buf .= $val;
        }

        return $buf;
    }

    /**
     * 将十六进制流$string转换成字符串.
     *
     * @param stream $string
     *
     * @return string
     */
    private function fromHexString($string)
    {
        $buf = '';
        for ($i = 0; $i < strlen($string); $i += 2) {
            $val = chr(hexdec(substr($string, $i, 2)));
            $buf .= $val;
        }

        return $buf;
    }
}
