<?php
/**
 * Created by PhpStorm.
 * User: winton
 * Date: 2018/4/18
 * Time: 21:51
 */

class WC_Gateway_Alipay extends WC_Payment_Gateway
{

    /**
     * WC_Gateway_Alipay constructor.
     */
    public function __construct()
    {
        $this->id = "alipay-for-wc";
        $this->icon = WC_Alipay_URL . "/assets/imgs/alipay.png";
        $this->has_fields = false;
        $this->method_title = "支付宝";
        $this->method_description = "用支付宝进行结算";

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option("enabled");
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        $this->appid = $this->get_option("appid");
        $this->appsecret = $this->get_option("appsecret");
        $this->signtype = $this->get_option("signtype");
        $this->alipaysecret = $this->get_option("alipaysecret");
        $this->pid = $this->get_option("pid");
        $this->sandbox = $this->get_option("sandbox");

        global $aop;
        $aop = new AopClient();

        if ($this->sandbox == 'yes') {
            //默认使用支付宝沙箱环境网关
            $aop->gatewayUrl = "https://openapi.alipaydev.com/gateway.do";
        } else {
            $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        }

        $aop->appId = $this->appid;
        $aop->rsaPrivateKey = $this->appsecret; #应用私钥
        $aop->apiVersion = '1.0';
        $aop->signType = $this->signtype;
        $aop->alipayrsaPublicKey = $this->alipaysecret;
        $aop->postCharset = "utf-8";
        $aop->format = "json";
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * 配置列表项
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => '开启支付宝支付',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => '用户在结算时会看到的标题',
                'default' => '支付宝支付',
                'desc_tip' => true,
                'css' => 'width:400px'
            ),

            'description' => array(
                'title' => '支付网关描述',
                'type' => 'textarea',
                'default' => '用户在结算时会看到的描述',
                'css' => 'width:400px'
            ),
            'appid' => array(
                'title' => __('APP ID', 'woocommerce'),
                'type' => 'text',
                'default' => '',
                'description' => '应用的APP ID',
                'css' => 'width:400px'
            ),

            'appsecret' => array(
                'title' => 'APP私钥',
                'type' => 'text',
                'default' => '',
                'description' => '应用秘钥',
                'css' => 'width:400px'
            ),
            'signtype' => array(
                'title' => '加签方式',
                'type' => 'text',
                'default' => 'RSA2',
                'description' => '加签方式，最新的支付宝加密方式应该是RSA2，按需填写',
                'css' => 'width:400px'
            ),
            'alipaysecret' => array(
                'title' => '支付宝公钥',
                'type' => 'text',
                'default' => '',
                'description' => '支付宝公钥',
                'css' => 'width:400px'
            ),
            'pid' => array(
                'title' => '卖家支付宝PID',
                'type' => 'text',
                'default' => '',
                'description' => '卖家支付宝用户号，以2088开头',
                'css' => 'width:400px'
            ),
            'sandbox' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => '开启支付宝沙箱环境',
                'default' => 'yes'
            )
        );
    }

    /**
     * 支付方法
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;
        global $aop;

        if (!is_dir('tmp/')) {
            mkdir('tmp/', 0777);
        }
        $file_name = 'tmp/' . md5($order_id) . ".php";
        $file = fopen($file_name, "w");

        $order = new WC_Order($order_id);
        $total_amount = $order->get_total();
        $order_title = $this->get_order_title($order);
        // 调用支付宝支付接口
        $request = new AlipayTradePagePayRequest();
        $return_url = $this->get_return_url($order);
        $notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'wc_gateway_alipay', home_url('/')));
        $request->setReturnUrl($return_url); // 同步返回地址，http//https开头
        $request->setNotifyUrl($notify_url); // 支付宝服务器主动通知商户服务器里指定的页面http/https路径
        $request->setBizContent('{"product_code":"FAST_INSTANT_TRADE_PAY","out_trade_no": "' . $order_id . '", "subject": "' . $order_title . '", "total_amount": "' . $total_amount . '"}');   // 业务请求参数的集合，最大长度不限

        try {
            $response = $aop->pageExecute($request); //请求支付，返回的是一个表单

            fwrite($file, $response);
            fclose($file);

            // 返重定向到支付表单
            return array(
                'result' => 'success',
                'redirect' => home_url() . '/' . $file_name
            );
        } catch (Exception $e) {
            wc_add_notice("errcode:{$e->getCode()},errmsg:{$e->getMessage()}", 'error');
            return array(
                'result' => 'fail',
                'redirect' => $this->get_return_url($order)
            );
        }
    }

    private function get_order_title($order)
    {
        $order_id = $order->get_id();
        $title = "#{$order_id}";
        $order_items = $order->get_items();  // 获取这个订单中包含的商品
        if ($order_items) {
            foreach ($order_items as $item_id => $item) {
                $title .= "|{$item['name']}";
            }
        }
        $limit = 250;
        $title = mb_strimwidth($title, 0, $limit, "utf-8");
        return $title;

    }

    /**
     * 检查是不是支付宝发送的请求
     * @param $arr
     * @return mixed
     */
    public function check_response($arr)
    {
        $aop = new AopClient();

        $aop->alipayrsaPublicKey = $this->alipaysecret;
        $result = $aop->rsaCheckV1($arr, $this->alipaysecret, $this->signtype);

        return $result;
    }

    /**
     * 检查参数对不对
     * @param $arr
     * @return bool
     */
    public function check_data($arr)
    {
        // 商户订单号
        $out_trade_no = $arr['out_trade_no'];
        $order = new WC_Order($out_trade_no);
        // 不是商户系统中的订单号
        if (!$order) {
            return false;
        }
        // 订单金额
        $total_amount = $arr['total_amount'];
        // 订单金额有问题
        if ($total_amount != $order->get_total()) {
            return false;
        }
        // 卖家支付宝用户号，以2088开头
        $seller_id = $arr['seller_id'];
        if ($seller_id != $this->pid) {
            return false;
        }
        // APPID
        $app_id = $arr['app_id'];
        if ($app_id != $this->appid) {
            return false;
        }

        return true;
    }
}
