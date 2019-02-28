<?php
/*
 * Plugin Name: WooCommerce Alipay
 * Plugin URI: http://www.winton.wang
 * Description: WooCommerce中进行支付宝支付
 * Author: Winton Wang
 * Version: 1.0
 * Author URI:  http://www.winton.wang
 * QQ: 1309603684
 */

if (!defined('ABSPATH'))
    exit (); // Exit if accessed directly


require_once("alipay-sdk-PHP-3.0.0/AopSdk.php");


define('WC_Alipay_URL', plugins_url('', __FILE__));     // 当前插件目录的URI

// 加载插件时，初始化自定义类
add_action('plugins_loaded', 'init_my_gateway_class');
function init_my_gateway_class()
{
    require_once('class_wc_gateway_alipay.php');
    global $wc_getway_alipay;
    $wc_getway_alipay = new WC_Gateway_Alipay();
}

// 告诉WC这个类的存在
add_filter('woocommerce_payment_gateways', 'add_my_gateway_class');
function add_my_gateway_class($methods)
{
    $methods[] = 'WC_Gateway_Alipay';
    return $methods;
}

//添加hook钩子，设置回调函数
add_action('woocommerce_api_wc_gateway_alipay', 'notify');
function notify()
{
//    file_put_contents('tmp/debug.log', 'date：' . date("Y-m-d h:i:s") . "\n" . var_export($_POST, true) . "\n", FILE_APPEND);

    $data = stripslashes_deep($_POST);
    unset($data['wc-api']);

    $wc_getway_alipay = new WC_Gateway_Alipay();
    if ($wc_getway_alipay->check_response($data)) {
        if (!$wc_getway_alipay->check_data($data)) {
            die("fail");
        }
        $order = new WC_Order($data['out_trade_no']);
        //删除支付时生成的页面
        $file = 'tmp/' . md5($order->get_id()) . ".php";
        if (is_file($file)) {
            unlink($file);
        }
        if ($order->needs_payment()) {
            $order->payment_complete();
        }
        die("success");
    } else {
        die("fail");
    }
}
