<?php
/**
 * newebpay Payment Gateway
 * Plugin URI: http://www.newebpay.com/
 * Description: 藍新金流收款/物流 模組
 * Version: 1.0.4
 * Author URI: http://www.newebpay.com/
 * Author: 藍新金流 newebpay
 * Plugin Name:   藍新金流
 * @class       newebpay
 * @extends     WC_Payment_Gateway
 * @version
 * @author  Pya2go Libby
 * @author  Pya2go Chael
 * @author  Spgateway Geoff
 * @author  Spgateway_Pay2go Q //20170217 1.0.1
 * @author  Spgateway_Pay2go jack //20170622 1.0.2
 * @author  Spgateway_Pay2go Stally //20180420 1.0.3 20181018 1.0.4 20181222 newebpay 1.0.0 20190417 1.0.1 20190711 1.0.2 20200326 1.0.3 20210305 1.0.4
 */
add_action('plugins_loaded', 'newebpay_gateway_init', 0);

function newebpay_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_newebpay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {
            // Check ExpireDate is validate or not
            if(isset($_POST['woocommerce_newebpay_ExpireDate']) && (!preg_match('/^\d*$/', $_POST['woocommerce_newebpay_ExpireDate']) || $_POST['woocommerce_newebpay_ExpireDate'] < 1 || $_POST['woocommerce_newebpay_ExpireDate'] > 180)){
              $_POST['woocommerce_newebpay_ExpireDate'] = 7;
            }

            $this->id = 'newebpay';
            $this->icon = apply_filters('woocommerce_newebpay_icon', plugins_url('icon/newebpay.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('藍新金流', 'woocommerce');

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title = $this->settings['title'];
            $this->version = '1.4';
            $this->LangType = $this->settings['LangType'];
            $this->description = $this->settings['description'];
            $this->MerchantID = trim($this->settings['MerchantID']);
            $this->HashKey = trim($this->settings['HashKey']);
            $this->HashIV = trim($this->settings['HashIV']);
            $this->ExpireDate = $this->settings['ExpireDate'];
            $this->TestMode = $this->settings['TestMode'];
            $this->eiChk = $this->settings['eiChk'];
            $this->InvMerchantID = trim($this->settings['InvMerchantID']);
            $this->InvHashKey = trim($this->settings['InvHashKey']);
            $this->InvHashIV = trim($this->settings['InvHashIV']);            
            $this->TaxType = $this->settings['TaxType'];
            $this->eiStatus = $this->settings['eiStatus'];
            $this->CreateStatusTime = $this->settings['CreateStatusTime'];
            $this->notify_url = add_query_arg('wc-api', 'WC_newebpay', home_url('/')) . '&callback=return';

            // Test Mode
            if ($this->TestMode == 'yes') {
                $this->gateway = "https://ccore.newebpay.com/MPG/mpg_gateway"; //測試網址
            } else {
                $this->gateway = "https://core.newebpay.com/MPG/mpg_gateway"; //正式網址
            }

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'receive_response')); //api_"class名稱(小寫)"
            add_action('woocommerce_after_order_notes', array($this, 'electronic_invoice_fields'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'electronic_invoice_fields_update_order_meta'));
            // 接收spgateway送出之交易的回傳
            add_action('woocommerce_thankyou_spgateway', array($this, 'thankyou_page'));
            add_action('woocommerce_receipt_spgateway', array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_spgateway', array($this, 'receive_response'));
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         * 後台欄位設置
         */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('啟用/關閉', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('啟動 藍新金流 收款模組', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('標題', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('客戶在結帳時所看到的標題', 'woocommerce'),
                    'default' => __('藍新金流Newebpay第三方金流平台', 'woocommerce')
                ),
                'LangType' => array(
                    'title' => __('支付頁語系', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        'zh-tw' => '中文',                        
                        'en' => 'En',
                    )
                ),
                'description' => array(
                    'title' => __('客戶訊息', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('', 'woocommerce'),
                    'default' => __('透過 藍新金流 付款。<br>會連結到 藍新金流 頁面。', 'woocommerce')
                ),
                'MerchantID' => array(
                    'title' => __('藍新金流商店 Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您的藍新金流商店代號', 'woocommerce')
                ),
                'HashKey' => array(
                    'title' => __('藍新金流商店 Hash Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您的藍新金流的HashKey', 'woocommerce')
                ),
                'HashIV' => array(
                    'title' => __('藍新金流商店 Hash IV', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請填入您的藍新金流的HashIV", 'woocommerce')
                ),
                'ExpireDate' => array(
                    'title' => __('繳費有效期限(天)', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請設定繳費有效期限(1~180天), 預設為7天", 'woocommerce'),
                    'default' => 7
                ),
                'eiChk' => array(
                    'title' => __('ezPay電子發票', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('開立電子發票', 'woocommerce'),
                    'default' => 'no'
                ),
                'InvMerchantID' => array(
                    'title' => __('ezPay電子發票 Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您的電子發票商店代號', 'woocommerce')
                ),
                'InvHashKey' => array(
                    'title' => __('ezPay電子發票 Hash Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您的電子發票的HashKey', 'woocommerce')
                ),
                'InvHashIV' => array(
                    'title' => __('ezPay電子發票 Hash IV', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請填入您的電子發票的HashIV", 'woocommerce')
                ),                
                'TaxType' => array(
                    'title' => __('稅別', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        '1' => '應稅',
                        '2.1' => '零稅率-非經海關出口',
                        '2.2' => '零稅率-經海關出口',
                        '3' => '免稅'
                    )
                ),
                'eiStatus' => array(
                    'title' => __('開立發票方式', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        '1' => '立即開立發票',
                        '3' => '預約開立發票'
                    )
                ),
                'CreateStatusTime' => array(
                    'title' => __('延遲開立發票(天)', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('此參數在"開立發票方式"選擇"預約開立發票"才有用', 'woocommerce'),
                    'default' => 7
                ),
                'TestMode' => array(
                    'title' => __('測試模組', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('啟動測試模組', 'woocommerce'),
                    'default' => 'yes'
                )
            );
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @access public
         * @return void
         */
        public function admin_options() {

            ?>
            <h3><?php _e('藍新金流 收款模組', 'woocommerce'); ?></h3>
            <p><?php _e('此模組可以讓您使用藍新金流的收款功能', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
                <script>
                  var invalidate = function(){
                        jQuery(this).css('border-color', 'red');
                        jQuery('#'+this.id+'_error_msg').show();
                        jQuery('input[type="submit"]').prop('disabled', 'disabled');
                      },
                      validate = function(){
                        jQuery(this).css('border-color', '');
                        jQuery('#'+this.id+'_error_msg').hide();
                        jQuery('input[type="submit"]').prop('disabled', '');
                      }

                            validate = function () {
                                jQuery(this).css('border-color', '');
                                jQuery('#' + this.id + '_error_msg').hide();
                                jQuery('input[type="submit"]').prop('disabled', '');

                            }

                    jQuery('#woocommerce_newebpay_eiStatus')
                            .bind('change', function (e) {
                                switch (parseInt(this.value, 10)) {
                                    case 1:
                                        jQuery('#woocommerce_newebpay_CreateStatusTime').prop('disabled', 'disabled').css('background', 'gray').val('');
                                        break;
                                    case 3:
                                        jQuery('#woocommerce_newebpay_CreateStatusTime').prop('disabled', '').css('background', '');
                                        break;
                                }
                            })
                            .trigger('change');

                    jQuery('#woocommerce_newebpay_ExpireDate, #woocommerce_newebpay_CreateStatusTime')
                            .bind('keypress', function (e) {
                                if (e.charCode < 48 || e.charCode > 57) {
                                    return false;
                                }
                            })
                            .bind('blur', function (e) {
                                if (!this.value) {
                                    validate.call(this);
                                }
                            });

                    jQuery('#woocommerce_newebpay_CreateStatusTime')
                            .bind('input', function (e) {
                                if (!this.value) {
                                    validate.call(this);
                                    return false;
                                }

                                if (this.value < 1) {
                                    invalidate.call(this);
                                } else {
                                    validate.call(this);
                                }
                            })
                            .after('<span style="display: none;color: red;" id="woocommerce_newebpay_CreateStatusTime_error_msg">請輸入1以上的數字</span>')

                    jQuery('#woocommerce_newebpay_ExpireDate')
                            .bind('input', function (e) {
                                if (!this.value) {
                                    validate.call(this);
                                    return false;
                                }

                                if (this.value < 1 || this.value > 180) {
                                    invalidate.call(this);

                                } else {
                                    validate.call(this);
                                }
                            })
                            .bind('blur', function (e) {
                                if (!this.value) {
                                    this.value = 7;
                                    validate.call(this);
                                }
                            })
                    .after('<span style="display: none;color: red;" id="woocommerce_newebpay_ExpireDate_error_msg">請輸入範圍內1~180的數字</span>')
                </script>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Get newebpay Args for passing to newebpay
         *
         * @access public
         * @param mixed $order
         * @return array
         *
         * MPG參數格式
         */
        function get_newebpay_args($order) {

            global $woocommerce;

            return apply_filters('woocommerce_newebpay_args',
                $this->transformNewebPayDataByVersion($order,$this->version)
            );
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function thankyou_page() {
            $this->writeLog('return紀錄傳入值');
            $req_data = array();
            //回傳CheckCode的為MPG1.1版
            if (! empty($_REQUEST['CheckCode'])) {
                if (! $this->chkCheckCodeIsVaildByReturnData($_REQUEST)) {
                    echo "請重新填單";
                    exit();
                }
                $req_data = $_REQUEST;
            }

            if (! empty($_REQUEST['TradeSha'])) {
                if (!$this->chkShaIsVaildByReturnData($_REQUEST)) {
                    echo "請重新填單";
                    exit();
                }
                $req_data = $this->create_aes_decrypt($_REQUEST['TradeInfo'], $this->HashKey,
                $this->HashIV);
            }

            //銀聯卡return時無帶參數 用查詢交易API 3次失敗先顯示請稍後重整
            if(empty($_REQUEST['CheckCode']) && empty($_REQUEST['TradeSha']) && isset($_GET['key'])) {
                $order_id = wc_get_order_id_by_order_key($_GET['key']);
                if(!empty($order_id)) {
                    $order = wc_get_order($order_id);   //原$_REQUEST['order-received']
                    //查詢交易API
                    $amount = round($order->get_total());
                    $api_url = ($this->TestMode == 'yes') ? 'https://ccore.newebpay.com/API/QueryTradeInfo' : 'https://core.newebpay.com/API/QueryTradeInfo';
                    $CheckValue = "IV=" . $this->HashIV . "&Amt=" . $amount . "&MerchantID=" . $this->MerchantID . "&MerchantOrderNo=" . $order_id . "&Key=" . $this->HashKey;
                    $CheckValue = strtoupper(hash("sha256", $CheckValue));
                    $post_data = array(
                        'MerchantID' => $this->MerchantID,
                        'Version' => '1.1',
                        'RespondType' => 'JSON',
                        'CheckValue' => $CheckValue,
                        'TimeStamp' => time(),
                        'MerchantOrderNo' => $order_id,
                        'Amt' => $amount
                    );
                    for($i = 0; $i < 3; $i++) {
                        $result = $this->curl_work($api_url, http_build_query($post_data));
                        $respondDecode = json_decode($result["web_info"], true);
                        if($respondDecode['Status'] == 'SUCCESS') {
                            $req_data = $respondDecode['Result'];
                            $req_data['Status'] = $respondDecode['Status'];
                            $req_data['Message'] = $respondDecode['Message'];
                            if($respondDecode['Result']['TradeStatus'] != '0'){
                                break;
                            }
                        }
                    }
                    if($respondDecode['Status'] !== 'SUCCESS' || $req_data['TradeStatus'] == '0') {
                        echo "若使用銀聯卡做為支付工具，交易結果會較晚回覆，請稍候重新整理確認付款結果，謝謝";
                        exit();
                    } elseif($req_data['TradeStatus'] > 1) {
                        echo "交易失敗，請重新填單<br>錯誤訊息：" . urldecode($req_data['RespondMsg']);
                        exit();
                    } else {
                        //超商取貨資料
                        $pre = (empty(get_post_meta($order_id, '_newebpayConsignee', true ))) ? 'spgateway' : 'newebpay'; //取得參數名稱前置詞
                        $cvs_data = array(
                            'StoreName' => get_post_meta($order_id, "_{$pre}StoreName", true ),
                            'StoreAddr' => get_post_meta($order_id, "_{$pre}StoreAddr", true ),
                            'CVSCOMName' => get_post_meta($order_id, "_{$pre}Consignee", true ),
                            'CVSCOMPhone' => get_post_meta($order_id, "_{$pre}ConsigneePhone", true )
                        );
                        if(!empty($cvs_data['StoreName'])) {
                            $req_data = array_merge($req_data, $cvs_data);
                        }
                    }
                } else {
                    unset($order_id);
                }
            }

            //初始化$req_data 避免因index不存在導致NOTICE 若無傳入值的index將設為null
            $init_indexes = 'Status,Message,TradeNo,MerchantOrderNo,PaymentType,P2GPaymentType,BankCode,CodeNo,Barcode_1,Barcode_2,Barcode_3,ExpireDate,CVSCOMName,StoreName,StoreAddr,CVSCOMPhone';
            $req_data = $this->init_array_data($req_data, $init_indexes);

            if(!empty($req_data['MerchantOrderNo']) && isset($_GET['key']) && preg_match('/^wc_order_/', $_GET['key'])){
                $order_id = wc_get_order_id_by_order_key($_GET['key']);
                $order = wc_get_order($order_id);   //原$_REQUEST['order-received']
            }
            
            if (empty($order)) {
                echo "交易失敗，請重新填單";
                exit();
            }

            if (empty($req_data['PaymentType']) || empty($req_data['Status'])) {
                echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                exit();
            }

            echo "付款方式：" . $this->get_payment_type_str($req_data['PaymentType'], !empty($req_data['P2GPaymentType'])) . "<br>";
            switch ($req_data['PaymentType']) {
                case 'CREDIT':
                case 'WEBATM':
                case 'P2GEACC':
                case 'ACCLINK':
                    if($req_data['Status'] == "SUCCESS") {
                        echo "交易成功<br>";
                    } else {
                        echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                    }
                break;
                case 'VACC':
                     if (!empty($req_data['BankCode']) && !empty($req_data['CodeNo'])) {
                        echo "取號成功<br>";
                        echo "銀行代碼：" . $req_data['BankCode'] . "<br>";
                        echo "繳費代碼：" . $req_data['CodeNo'] . "<br>";
                    } else {
                        echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                    }
                break;
                case 'CVS':
                    if (!empty($req_data['CodeNo'])) {
                        echo "取號成功<br>";
                        echo "繳費代碼：" . $req_data['CodeNo'] . "<br>";
                    } else {
                        echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                    }
                break;
                case 'BARCODE':
                    if (!empty($req_data['Barcode_1']) || !empty($req_data['Barcode_2']) || !empty($req_data['Barcode_3'])) {
                        echo "取號成功<br>";
                        echo "請前往信箱列印繳費單<br>";
                    } else {
                        echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                    }
                break;
                case 'CVSCOM':
                    if (empty($req_data['CVSCOMName']) || empty($req_data['StoreName']) || empty($req_data['StoreAddr'])) {
                        echo "交易失敗，請重新填單<br>錯誤代碼：" . $req_data['Status'] . "<br>錯誤訊息：" . urldecode($req_data['Message']);
                    }
                break;
                default:
                    //未來新增之付款方式
                    if($req_data['Status'] == "SUCCESS") {
                        echo "付款方式：{$req_data['PaymentType']}<br>";
                        if(!empty($req_data['ExpireDate'])) {
                            echo "非即時付款，詳細付款資訊請從信箱確認<br>";    //非即時付款 部分非即時付款可能沒ExpireDate
                        } else {
                            echo "交易成功<br>";    //即時付款
                        }
                        echo "藍新金流交易序號：{$req_data['TradeNo']}<br>";
                        break;
                    }
                break;
            }
            if(!empty($req_data['CVSCOMName']) || !empty($req_data['StoreName'])|| !empty($req_data['StoreAddr'])) {
                $order_id = (isset($order_id)) ? $order_id: $req_data['MerchantOrderNo'];
                $storeName = urldecode($req_data['StoreName']); //店家名稱
                $storeAddr = urldecode($req_data['StoreAddr']); //店家地址
                $name = urldecode($req_data['CVSCOMName']); //取貨人姓名
                $phone = $req_data['CVSCOMPhone'];
                echo "<br>取貨人：$name<br>電話：$phone<br>店家：$storeName<br>地址：$storeAddr<br>";
                echo "請等待超商通知取貨<br>";
                //若未接到notify改由return更新超商資訊
                if(empty(get_post_meta($order_id, "_newebpayStoreName", true ))) {
                    update_post_meta($order_id, '_newebpayStoreName', $storeName);
                    update_post_meta($order_id, '_newebpayStoreAddr', $storeAddr);
                    update_post_meta($order_id, '_newebpayConsignee', $name);
                    update_post_meta($order_id, '_newebpayConsigneePhone', $phone);
                }
            }
        }

        private function get_payment_type_str($payment_type = '', $isEZP = false)
        {
            $PaymentType_Ary = Array(
                "CREDIT"    => "信用卡",
                "WEBATM"    => "WebATM",
                "VACC"      => "ATM轉帳",
                "CVS"       => "超商代碼繳費",
                "BARCODE"   => "超商條碼繳費",
                "CVSCOM"    => "超商取貨付款",
                "P2GEACC"   => "電子帳戶",
                "ACCLINK"   => "約定連結存款帳戶"
            );
            $re_str = (isset($PaymentType_Ary[$payment_type])) ? $PaymentType_Ary[$payment_type] : $payment_type;
            $re_str = (!$isEZP) ? $re_str : $re_str . '(ezPay)'; //智付雙寶
            return $re_str;
        }

        /**
         *依照規則版本轉換藍新金流需求資料
         *
         * @access private
         * @param order $order, string $version
         * @return array
         */
        private function transformNewebPayDataByVersion($order,$version)
        {
            switch ($version) {
                case '1.1':
                    return $this->mpgOnePointOneHandler($order);
                break;
                
                default:
                    return $this->mpgOnePointFourHandler($order);
                break;
            }
        }

        /**
         *MPG1.1版資料處理
         *
         * @access private
         * @param order $order
         * @version 1.1
         * @return array
         */
        private function mpgOnePointOneHandler($order)
        {
            $merchantid = $this->MerchantID; //商店代號
            $respondtype = "String"; //回傳格式
            $timestamp = time(); //時間戳記
            $version = $this->version; //串接版本
            $order_id = $order->id;
            $amt = (int) $order->get_total(); //訂單總金額
            $logintype = "0"; //0:不需登入藍新金流會員，1:須登入藍新金流會員
            //商品資訊
            $item_name = $order->get_items();
            $item_cnt = 1;
            $itemdesc = "";
            foreach ($item_name as $item_value) {
                if ($item_cnt != count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'] . "，";
                } elseif ($item_cnt == count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'];
                }

                //支付寶、財富通參數
                $newebpay_args_1["Count"] = $item_cnt;
                $newebpay_args_1["Pid$item_cnt"] = $item_value['product_id'];
                $newebpay_args_1["Title$item_cnt"] = $item_value['name'];
                $newebpay_args_1["Desc$item_cnt"] = $item_value['name'];
                $newebpay_args_1["Price$item_cnt"] = $item_value['line_subtotal'] / $item_value['qty'];
                $newebpay_args_1["Qty$item_cnt"] = $item_value['qty'];

                $item_cnt++;
            }

            //CheckValue 串接
            $check_arr = array('MerchantID' => $merchantid, 'TimeStamp' => $timestamp, 'MerchantOrderNo' => $order_id, 'Version' => $version, 'Amt' => $amt);
            //按陣列的key做升幕排序
            ksort($check_arr);
            //排序後排列組合成網址列格式
            $check_merstr = http_build_query($check_arr, '', '&');
            $checkvalue_str = "HashKey=" . $this->HashKey . "&" . $check_merstr . "&HashIV=" . $this->HashIV;
            $CheckValue = strtoupper(hash("sha256", $checkvalue_str));

            $buyer_name = $order->billing_last_name . $order->billing_first_name;
            $total_fee = $order->order_total;
            $tel = $order->billing_phone;
            $newebpay_args_2 = array(
                "MerchantID" => $merchantid,
                "RespondType" => $respondtype,
                "CheckValue" => $CheckValue,
                "TimeStamp" => $timestamp,
                "Version" => $version,
                "MerchantOrderNo" => $order_id,
                "Amt" => $amt,
                "ItemDesc" => $itemdesc,
                "ExpireDate" => date('Ymd', time()+intval($this->ExpireDate)*24*60*60),
                "Email" => $order->billing_email,
                "LoginType" => $logintype,
                "NotifyURL" => $this->notify_url, //幕後
                "ReturnURL" => $this->get_return_url($order), //幕前(線上)
                "ClientBackURL" => $this->get_return_url($order), //取消交易
                "CustomerURL" => $this->get_return_url($order), //幕前(線下)
                "Receiver" => $buyer_name, //支付寶、財富通參數
                "Tel1" => $tel, //支付寶、財富通參數
                "Tel2" => $tel, //支付寶、財富通參數
                "LangType" => $this->LangType
            );
            $newebpay_args = array_merge($newebpay_args_1, $newebpay_args_2);
            return $newebpay_args;
        }

        /**
         *MPG1.4版資料處理
         *
         * @access private
         * @param order $order
         * @version 1.4
         * @return array
         */
        private function mpgOnePointFourHandler($order)
        {
            $post_data = [
                'MerchantID' => $this->MerchantID,//商店代號
                'RespondType' => 'JSON',//回傳格式
                'TimeStamp' => time(),//時間戳記
                'Version' => '1.4',
                'MerchantOrderNo' => $order->get_id(),
                'Amt' => round($order->get_total()),
                'ItemDesc' => $this->genetateItemDescByOrderItem($order),
                "ExpireDate" => date('Ymd', time()+intval($this->ExpireDate)*24*60*60),
                "Email" => $order->get_billing_email(),
                'LoginType' => '0',
                "NotifyURL" => $this->notify_url, //幕後
                "ReturnURL" => $this->get_return_url($order), //幕前(線上)
                "ClientBackURL" => wc_get_cart_url(), //返回商店 wc_get_checkout_url
                "CustomerURL" => $this->get_return_url($order), //幕前(線下)
                "LangType" => $this->LangType,
            ];
            if(!$order->has_shipping_method('newebpay_cvscom')) {   //藍新金流超商取貨
                $post_data['DeliveryMethod'] = 1;
            }

            $aes = $this->create_mpg_aes_encrypt($post_data, $this->HashKey, $this->HashIV);
            $sha256 = $this->aes_sha256_str($aes, $this->HashKey, $this->HashIV);

            return [
                'MerchantID' => $this->MerchantID,
                'TradeInfo' => $aes,
                'TradeSha' => $sha256,
                'Version' => '1.4',
                'CartVersion'=>'NewebPay_woocommerce_1_0_4'
            ];
        }

        /**
         *MPG aes加密
         *
         * @access private
         * @param array $parameter ,string $key, string $iv
         * @version 1.4
         * @return string
         */
        private function create_mpg_aes_encrypt($parameter, $key = "", $iv = "")
        {
            $return_str = '';
            if (!empty($parameter)) {
                ksort($parameter);
                $return_str = http_build_query($parameter);
            }
            return trim(bin2hex(openssl_encrypt($this->addpadding($return_str), 'aes-256-cbc', $key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv)));
        }

        private function addpadding($string, $blocksize = 32) {
            $len = strlen($string);
            $pad = $blocksize - ($len % $blocksize);
            $string .= str_repeat(chr($pad), $pad);
            return $string;
        }

         /**
         *MPG sha256加密
         *
         * @access private
         * @param string $str ,string $key, string $iv
         * @version 1.4
         * @return string
         */
        private function aes_sha256_str($str, $key = "", $iv = "")
        {
            return strtoupper(hash("sha256", 'HashKey='.$key.'&'.$str.'&HashIV='.$iv));
        }

        /**
         *MPG aes解密
         *
         * @access private
         * @param array $parameter ,string $key, string $iv
         * @version 1.4
         * @return string
         */
        private function create_aes_decrypt($parameter = "", $key = "", $iv = "")
        {
            $dec_str = $this->strippadding(openssl_decrypt(hex2bin($parameter),'AES-256-CBC', $key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv));
            if(json_decode($dec_str)) {
                $return_data = $this->decrypt_json_data($dec_str);
            } else {
                $return_data = $this->decrypt_str_data($dec_str);
            }

            return $return_data;
        }

        private function strippadding($string) 
        {
            $slast = ord(substr($string, -1));
            $slastc = chr($slast);
            if (preg_match("/$slastc{" . $slast . "}/", $string)) {
                $string = substr($string, 0, strlen($string) - $slast);
                return $string;
            } else {
                return false;
            }
        }

        private function decrypt_json_data($dec_str) 
        {
            $dec_data = json_decode($dec_str, true);
            $dec_data['Result']['Status'] = $dec_data['Status'];
            $dec_data['Result']['Message'] = $dec_data['Message'];
            return $dec_data['Result']; //整理成跟String回傳相同格式
        }

        private function decrypt_str_data($dec_str) 
        {
            $dec_data = explode('&', $dec_str);
            foreach ($dec_data as $_ind => $value) {
                $trans_data = explode('=', $value);
                $return_data[$trans_data[0]] = $trans_data[1];
            }
            return $return_data;
        }

        /**
         *依照訂單產生物品名稱
         *
         * @access private
         * @param order $order
         * @version 1.4
         * @return string
         */
        private function genetateItemDescByOrderItem($order)
        {
            if (! isset($order)) return '';
            $item_name = $order->get_items();
            $item_cnt = 1;
            $itemdesc = "";
            foreach ($item_name as $item_value) {
                if ($item_cnt != count($item_name)) {
                    $itemdesc .= $item_value->get_name() . " × " . $item_value->get_quantity() . "，";
                } elseif ($item_cnt == count($item_name)) {
                    $itemdesc .= $item_value->get_name() . " × " . $item_value->get_quantity();
                }

                $item_cnt++;
            }
            return $itemdesc;
        }

        /**
         *依照回傳參數產生CheckCode
         *
         * @access private
         * @param array $return_data
         * @version 1.4
         * @return string
         */
        private function generateCheckCodeByReturnData($return_data)
        {
            //CheckCode 串接
            $code_arr = [
                'MerchantID' => $return_data['MerchantID'],
                'TradeNo' => $return_data['TradeNo'],
                'MerchantOrderNo' => $return_data['MerchantOrderNo'],
                'Amt' => $return_data['Amt']
            ];

            //按陣列的key做升幕排序
            ksort($code_arr);
            //排序後排列組合成網址列格式
            $code_merstr = http_build_query($code_arr, '', '&');
            $checkcode_str = "HashIV=" . $this->HashIV . "&" . $code_merstr . "&HashKey=" . $this->HashKey;
            return strtoupper(hash("sha256", $checkcode_str));
        }

        function curl_work($url = "", $parameter = "") {
            $curl_options = array(
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => "Google Bot",
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_SSL_VERIFYHOST => FALSE,
                CURLOPT_POST => "1",
                CURLOPT_POSTFIELDS => $parameter
            );
            $ch = curl_init();
            curl_setopt_array($ch, $curl_options);
            $result = curl_exec($ch);
            $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_errno($ch);
            curl_close($ch);

            $return_info = array(
                "url" => $url,
                "sent_parameter" => $parameter,
                "http_status" => $retcode,
                "curl_error_no" => $curl_error,
                "web_info" => $result
            );
            return $return_info;
        }

        function electronic_invoice($order, $tradeNum) 
        {
            if ($this->TestMode == 'yes') {
                $url = "https://cinv.ezpay.com.tw/API/invoice_issue"; //測試網址
            } else {
                $url = "https://inv.ezpay.com.tw/API/invoice_issue"; //正式網址
            }
            $MerchantID = $this->InvMerchantID; //商店代號
            $key = $this->InvHashKey;  //商店專屬串接金鑰HashKey值
            $iv = $this->InvHashIV;  //商店專屬串接iv
            
            $order_id = $order->get_id();
            $status = $this->eiStatus;
            $createStatusTime = (int) $this->CreateStatusTime;
            $createStatusTime = date('Y-m-d', time() + ($createStatusTime * 86400)); //加上預約開立時間
            $discount_with_no_tax = $order->get_total_discount();
            $discount_with_tax = $order->get_total_discount(false);
            // $_tax = new WC_Tax();
            //商品資訊
            $item_name = $order->get_items();
            $item_cnt = 1;
            // $itemPriceSum = 0;

            $buyerNeedUBN = get_post_meta($order_id, '_billing_needUBN', true);
            if ($buyerNeedUBN) {
                $buyerUBN = get_post_meta($order_id, '_billing_UBN', true);
                $category = "B2B";
                $invoiceFlag = -1;
            } else {
                $buyerUBN = "";
                $category = "B2C";
                $invoiceFlag = get_post_meta($order_id, '_billing_invoiceFlag', true);
            }
            $itemName = '';
            $itemCount = '';
            $itemUnit = '';
            $itemPrice = '';
            $itemAmt = '';
            foreach ($item_name as $keyx => $item_value) {
                //$pid 若取的到variation_id為可變商品 取到0為非可變商品
                $pid = (empty($item_name[$keyx]->get_variation_id())) ? $item_name[$keyx]->get_product_id() : $item_name[$keyx]->get_variation_id();
                $tax_class = $item_name[$keyx]->get_tax_class();
                // $item_count = $item_name[$keyx]['qty'];
                $product = wc_get_product($pid);
                // $product = new WC_Product($pid);
                @$rates_data = array_shift(WC_Tax::get_rates($tax_class));  //array_shift($_tax->get_rates($product->get_tax_class()));
                $taxRate = (float) $rates_data['rate'];//取得稅率

                if (! $this->chkProductInvCategoryisValid($product,$category)) {
                    $orderNote = "發票開立失敗<br>錯誤訊息：" . '無法取得商品資訊';
                    $order->add_order_note(__($orderNote, 'woothemes'));
                    exit();
                }

                if ($item_cnt != count($item_name)) {
                    $itemName .= $item_value->get_name() . "|";
                    $itemCount .= $item_value->get_quantity() . "|";
                    $itemUnit .= "個|";
                    $itemPrice .= $this->getProductPriceByCategory($product,$category) . "|";
                    $itemAmt .= $this->getProductPriceByCategory($product,$category)*$item_value['qty'] . "|";
                } elseif ($item_cnt == count($item_name)) {
                    $itemName .= $item_value->get_name();
                    $itemCount .= $item_value->get_quantity();
                    $itemUnit .= "個";
                    $itemPrice .= $this->getProductPriceByCategory($product, $category);
                    $itemAmt .= $this->getProductPriceByCategory($product, $category)*$item_value['qty'];
                }
                // $itemPriceSum += $itemAmtRound;

                $item_cnt++;
            }

            if (! $this->chkOrderInvCategoryisValid($order,$category)) {
                $orderNote = "發票開立失敗<br>錯誤訊息：" . '無法取得訂單資訊';
                $order->add_order_note(__($orderNote, 'woothemes'));
                exit();
            }

            if ($order->get_total_shipping() > 0) {
                $itemName .= '|' . $order->get_shipping_method();
                $itemCount .= '|1';
                $itemUnit .= '|個';
                $itemPrice .= '|' . $this->getShippingPriceByCategory($order, $category);
                $itemAmt .= '|' . $this->getShippingPriceByCategory($order, $category);
            }

            if ($discount_with_tax > 0) {
                $itemName .= '|' . "折扣";
                $itemCount .= '|1';
                $itemUnit .= '|次';
                $itemPrice .= '|-' . $discount_with_tax;
                $itemAmt .= '|-' . $discount_with_tax;
            }

            $amt = round($order->get_total()) - round($order->get_total_tax());
            $taxAmt = round($order->get_total_tax());
            $totalAmt = round($order->get_total());

            $customsClearance = NULL;
            $taxType = $this->TaxType;

            switch ($taxType) {
                case 2.1:
                    $taxType = 2;
                    $customsClearance = 1;
                    break;
                case 2.2:
                    $taxType = 2;
                    $customsClearance = 2;
                    break;
            }

            $buyerName = get_post_meta($order_id, '_billing_Buyer', true);  //B2B可輸入買受人名稱 若無輸入就使用帳單的姓名(B2C直接用這個)
            $buyerName = (empty($buyerName)) ? $order->get_billing_last_name() . " " . $order->get_billing_first_name() : $buyerName;
            $buyerEmail = $order->get_billing_email();
            $buyerAddress = $order->get_billing_postcode() . $order->get_billing_state() . $order->get_billing_city() . $order->get_billing_address_1() . " " . $order->get_billing_address_2();
            $buyerComment = $order->get_customer_note();
            
            $invoiceFlagNum = get_post_meta($order_id, '_billing_invoiceFlagNum', true);

            switch ($invoiceFlag) {
                case -1:
                    $printFlag = "Y";
                    $carruerType = "";
                    $carruerNum = "";
                    $loveCode = "";
                    break;
                case 0:
                    $printFlag = "N";
                    $carruerType = 0;
                    $carruerNum = $invoiceFlagNum;
                    $loveCode = "";
                    break;
                case 1:
                    $printFlag = "N";
                    $carruerType = 1;
                    $carruerNum = $invoiceFlagNum;
                    $loveCode = "";
                    break;
                case 2:
                    $printFlag = "N";
                    $carruerType = 2;
                    $carruerNum = $buyerEmail;
                    $loveCode = "";
                    break;
                case 3:
                    $printFlag = "N";
                    $carruerType = "";
                    $carruerNum = "";
                    $loveCode = $invoiceFlagNum;
                    break;
                default:
                    $printFlag = "N";
                    $carruerType = 2;
                    $carruerNum = $buyerEmail;
                    $loveCode = "";
            }
            $post_data_array = array(//post_data欄位資料
                "RespondType" => "JSON",
                "Version" => "1.4",
                "TimeStamp" => time(),
                "TransNum" => $tradeNum,
                "MerchantOrderNo" => $order_id,
                "Status" => $status,
                "CreateStatusTime" => $createStatusTime,
                "Category" => $category,
                "BuyerName" => $buyerName,
                "BuyerUBN" => $buyerUBN,
                "BuyerAddress" => $buyerAddress,
                "BuyerEmail" => $buyerEmail,
                "CarrierType" => $carruerType,
                "CarrierNum" => $carruerNum,
                "LoveCode" => $loveCode,
                "PrintFlag" => $printFlag,
                "TaxType" => $taxType,
                "CustomsClearance" => $customsClearance,
                "TaxRate" => $taxRate,
                "Amt" => $amt,
                "TaxAmt" => $taxAmt,
                "TotalAmt" => $totalAmt,
                "ItemName" => $itemName,
                "ItemCount" => $itemCount,
                "ItemUnit" => $itemUnit,
                "ItemPrice" => $itemPrice,
                "ItemAmt" => $itemAmt,
                "Comment" => $buyerComment
            );

            $post_data_str = http_build_query($post_data_array);
            // $post_data = trim(bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $this->addpadding($post_data_str), MCRYPT_MODE_CBC, $iv))); //加密
            $post_data = trim(bin2hex(openssl_encrypt($this->addpadding($post_data_str), 'aes-256-cbc', $key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv))); //加密
            $transaction_data_array = array(//送出欄位
                "MerchantID_" => $MerchantID,
                "PostData_" => $post_data,
                "CartVersion" => 'NewebPay_woocommerce_1_0_4'
            );
            $transaction_data_str = http_build_query($transaction_data_array);
            $result = $this->curl_work($url, $transaction_data_str); //背景送出
            $this->writeLog('開立發票送出值:' . $post_data_str . '|回應:' . $result["web_info"], false);
            //Add order notes on admin
            $respondDecode = json_decode($result["web_info"]);
            if (in_array($respondDecode->Status, array('SUCCESS', 'CUSTOM'))) {
                $resultDecode = json_decode($respondDecode->Result);
                $invoiceTransNo = $resultDecode->InvoiceTransNo;
                $invoiceNumber = $resultDecode->InvoiceNumber;
                $orderNote = $respondDecode->Message . "<br>ezPay開立序號: " . $invoiceTransNo . "<br>" . "發票號碼: " . $invoiceNumber;
            } else {
                $orderNote = "發票開立失敗<br>錯誤訊息：" . $respondDecode->Message;
            }
            $order->add_order_note(__($orderNote, 'woothemes'));
        }

        /**
         * 依照發票類型取得單一產品價格
         *
         * @access public
         * @param product $product , string $category
         * @return float|boolean
         */
        public function getProductPriceByCategory($product, $category)
        {
            switch ($category) {
                case 'B2B':
                    return round(wc_get_price_including_tax($product), 2);
                break;
                
                case 'B2C':
                    return round($product->get_price(), 2);//含稅價
                break;
                default:
                    return false;
                break;
            }
        }

        /**
         * 依照發票類型取得運費價格
         *
         * @access public
         * @param order $order , string $category
         * @return float|boolean
         */
        public function getShippingPriceByCategory($order,$category)
        {
            switch ($category) {
                case 'B2B':
                    return round($order->get_total_shipping());
                break;
                
                case 'B2C':
                    return round($order->get_total_shipping()+$order->get_shipping_tax());//含稅價
                break;
                default:
                    return false;
                break;
            }
        }


        private function chkOrderInvCategoryisValid($order,$category)
        {
            if (! isset($order)) return false;
            if (! isset($category)) return false;
            return true;
        }

        private function chkProductInvCategoryisValid($product,$category)
        {
            if (! isset($product)) return false;
            if (! isset($category)) return false;
            return true;
        }

        private function chkShaIsVaildByReturnData($return_data)
        {
            if (empty($return_data['TradeSha'])) return false;
            if (empty($return_data['TradeInfo'])) return false;
            $local_sha = $this->aes_sha256_str(
                $return_data['TradeInfo'],
                $this->HashKey,
                $this->HashIV
            );
            if ($return_data['TradeSha'] != $local_sha) return false;
            return true;
        }

        private function chkCheckCodeIsVaildByReturnData($return_data)
        {
            if (empty($return_data['CheckCode'])) return false;
            //CheckCode 串接1.1ver
            $code_arr = array('MerchantID' => $this->MerchantID, 'TradeNo' => $return_data['TradeNo'], 'MerchantOrderNo' => $return_data['MerchantOrderNo'], 'Amt' => $return_data['Amt']);
            //按陣列的key做升幕排序
            ksort($code_arr);
            //排序後排列組合成網址列格式
            $code_merstr = http_build_query($code_arr, '', '&');
            $checkcode_str = "HashIV=" . $this->HashIV . "&" . $code_merstr . "&HashKey=" . $this->HashKey; //跟1.4版HashIV KEY順序不同
            $CheckCode = strtoupper(hash("sha256", $checkcode_str));
            if ($return_data['CheckCode'] != $CheckCode) return false;
            return true;
        }
        /**
         * 接收回傳參數驗證
         *
         * @access public
         * @return void
         */
        function receive_response() { 
            $req_data = array();
            //回傳CheckCode的為MPG1.1版
            if (! empty($_REQUEST['CheckCode'])) {
                if (! $this->chkCheckCodeIsVaildByReturnData($_REQUEST)) {
                    echo 'CheckCode vaild fail';
                    $this->writeLog("CheckCode vaild fail");
                    exit; //一定要有離開，才會被正常執行
                }
                $req_data = $_REQUEST;
            }

            //檢查SHA值是否正確 MPG1.4版
            if (! empty($_REQUEST['TradeSha'])) {
                if (!$this->chkShaIsVaildByReturnData($_REQUEST)) {
                    echo 'SHA vaild fail';
                    $this->writeLog("SHA vaild fail");
                    exit; //一定要有離開，才會被正常執行
                }
                $req_data = $this->create_aes_decrypt($_REQUEST['TradeInfo'], $this->HashKey,
                $this->HashIV);
                if(!is_array($req_data)) {
                    echo '解密失敗';
                    $this->writeLog("解密失敗");
                    exit; //一定要有離開，才會被正常執行
                }
            }

            //初始化$req_data 避免因index不存在導致NOTICE 若無傳入值的index將設為null
            $init_indexes = 'Status,Message,TradeNo,MerchantOrderNo,PaymentType,P2GPaymentType,PayTime';
            $req_data = $this->init_array_data($req_data, $init_indexes);

            $re_MerchantOrderNo = trim($req_data['MerchantOrderNo']);
            // $re_MerchantID = $req_data['MerchantID'];
            $re_Status = isset($_REQUEST['Status']) ? $_REQUEST['Status'] : null;
            $re_TradeNo = $req_data['TradeNo'];
            $re_Amt = $req_data['Amt'];

            $order = wc_get_order($re_MerchantOrderNo);
            if(!$order) {
                echo "取得訂單失敗，訂單編號:$re_MerchantOrderNo";
                exit();
            }
            $Amt = round($order->get_total());

            if($order->is_paid()) {
                echo '訂單已付款';
                exit(); //已付款便不重複執行
            }

            //檢查回傳狀態是否為成功
            if (! in_array($re_Status, array('SUCCESS', 'CUSTOM'))) { 
                $msg = "訂單處理失敗: ";
                $order->cancel_order();
                $msg .= urldecode($req_data['Message']);
                $order->add_order_note(__($msg, 'woothemes'));
                echo $msg;
                $this->writeLog($msg);
                exit();
            }

            //檢查是否付款
            if (empty($req_data['PayTime'])) {
                $msg = "訂單並未付款";
                echo $msg;
                $this->writeLog($msg);
                exit; //一定要有離開，才會被正常執行
            };
            
            //檢查金額是否一樣
            if ($Amt != $re_Amt) {
                $msg = "金額不一致";
                $order->cancel_order();
                echo $msg;
                $this->writeLog($msg);
                exit();
            }

            //訂單備註
            $note_text = '<<<code>藍新金流</code>>>';
            $note_text .= '</br>商店訂單編號：' . $re_MerchantOrderNo;
            $note_text .= '</br>藍新金流支付方式：' . $this->get_payment_type_str($req_data['PaymentType'], !empty($req_data['P2GPaymentType']));
            $note_text .= '</br>藍新金流交易序號：' . $req_data['TradeNo'];
            $order->add_order_note($note_text);

            //超商取貨
            if(!empty($req_data['CVSCOMName']) || !empty($req_data['StoreName']) || !empty($req_data['StoreAddr'])){
                $storeName = urldecode($req_data['StoreName']); //店家名稱
                $storeAddr = urldecode($req_data['StoreAddr']); //店家地址
                $name = urldecode($req_data['CVSCOMName']); //取貨人姓名
                $phone = $req_data['CVSCOMPhone'];
                update_post_meta($re_MerchantOrderNo, '_newebpayStoreName', $storeName);
                update_post_meta($re_MerchantOrderNo, '_newebpayStoreAddr', $storeAddr);
                update_post_meta($re_MerchantOrderNo, '_newebpayConsignee', $name);
                update_post_meta($re_MerchantOrderNo, '_newebpayConsigneePhone', $phone);
            }

            //全部確認過後，修改訂單狀態(處理中，並寄通知信)
            $order->payment_complete();
            $msg = "訂單修改成功";
            $this->writeLog($msg);
            $eiChk = $this->eiChk;
            if ($eiChk == 'yes') {
                $this->electronic_invoice($order, $re_TradeNo);
            }

            if (isset($_GET['callback'])) {
                echo $msg;
                exit; //一定要有離開，才會被正常執行
            }
        }

        /**
         * Generate the newebpay button link (POST method)
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_newebpay_form($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $newebpay_args = $this->get_newebpay_args($order);
            $newebpay_gateway = $this->gateway;
            $newebpay_args_array = array();
            foreach ($newebpay_args as $key => $value) {
                $newebpay_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            return '<form id="newebpay" name="newebpay" action="' . $newebpay_gateway . '" method="post" target="_top">' . implode('', $newebpay_args_array) . '
                <input type="submit" class="button-alt" id="submit_newebpay_payment_form" value="' . __('前往 藍新金流 支付頁面', 'newebpay') . '" />
                </form>'. "<script>setTimeout(\"document.forms['newebpay'].submit();\",\"3000\")</script>";
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page($order) {
            echo '<p>' . __('3秒後會自動跳轉到藍新金流支付頁面，或者按下方按鈕直接前往<br>', 'newebpay') . '</p>';
            echo $this->generate_newebpay_form($order);
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);

            // Empty awaiting payment session
            unset($_SESSION['order_awaiting_payment']);
            //$this->receipt_page($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Payment form on checkout page
         *
         * @access public
         * @return void
         */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        function check_newebpay_response() {
            echo "ok";
        }

        /**
         * Add electronic invoice text in checkout page
         *
         * @access public
         */
        function electronic_invoice_fields($checkout) {
            $eiChk = $this->eiChk;
            if ($eiChk == 'yes') {
                echo "<div id='electronic_invoice_fields'><h3>發票資訊</h3>";
                woocommerce_form_field("billing_needUBN", array(
                    'type' => 'select',
                    'label' => __('發票是否需要打統一編號'),
                    'options' => array(
                        '0' => '否',
                        '1' => '是')
                        ), $checkout->get_value('billing_needUBN'));

                echo "<div id='buDiv'>";
                woocommerce_form_field("billing_UBN", array(
                    'type' => 'text',
                    'label' => __('<div id="UBNdiv" style="display:inline;">統一編號</div><div id="UBNdivAlert" style="display:none;color:#FF0000;">&nbsp&nbsp格式錯誤!!!</div></p>'),
                    'placeholder' => __('請輸入統一編號'),
                    'required' => false,
                    'default' => ''
                        ), $checkout->get_value('billing_UBN'));
                woocommerce_form_field("billing_Buyer", array(
                    'type' => 'text',
                    'label' => __('<div id="Buyerdiv" style="display:inline;">買受人名稱</div>'),
                    'placeholder' => __('請輸入買受人名稱'),
                    'required' => false,
                    'default' => ''
                        ), $checkout->get_value('billing_Buyer'));
                echo "電子發票將寄送至您的電子郵件地址，請自行列印。</div>";

                echo "<div id='bifDiv'>";
                woocommerce_form_field("billing_invoiceFlag", array(
                    'type' => 'select',
                    'label' => __('電子發票索取方式'),
                    'options' => array(
                        '2' => '會員載具',
                        '0' => '手機條碼',
                        '1' => '自然人憑證條碼',
                        '3' => '捐贈發票',
                        '-1' => '索取紙本發票')
                        ), $checkout->get_value('billing_invoiceFlag'));
                echo "</div>";

                echo "<div id='bifnDiv' style='display:none;'>";
                woocommerce_form_field("billing_invoiceFlagNum", array(
                    'type' => 'text',
                    'label' => __('<div id="ifNumDiv">載具編號</div>'),
                    'placeholder' => __('電子發票通知將寄送至您的電子郵件地址'),
                    'required' => false,
                    'default' => ''
                        ), $checkout->get_value('billing_invoiceFlagNum'));
                echo "</div>";
                echo "<div id='bifnDivAlert' style='display:none;color:#FF0000;'>請輸入載具編號</div>";
                echo "</div>";
            }

            echo '<script type="text/javascript" src="http://code.jquery.com/jquery-1.1.1.js"></script>
                    <script type="text/javascript">
                        function idchk(idvalue) {
                            var tmp = new String("12121241");
                            var sum = 0;
                            re = /^\d{8}$/;
                            if (!re.test(idvalue)) {
                                return false;
                            }

                            for (i = 0; i < 8; i++) {
                                s1 = parseInt(idvalue.substr(i, 1));
                                s2 = parseInt(tmp.substr(i, 1));
                                sum += cal(s1 * s2);
                            }

                            if (!valid(sum)) {
                                if (idvalue.substr(6, 1) == "7")
                                    return(valid(sum + 1));
                            }

                            return(valid(sum));
                        }

                        function valid(n) {
                            return (n % 10 == 0) ? true : false;
                        }

                        function cal(n) {
                            var sum = 0;
                            while (n != 0) {
                                sum += (n % 10);
                                n = (n - n % 10) / 10;
                            }
                            return sum;
                        }

                        function UBNrog() {
                            var rog = "r";
                            var UBN = 0;
                            var tof = false;
                            var needUBN = jQuery("#billing_needUBN").val();
                            var UBNval = jQuery("#billing_UBN").val();
                            if (needUBN == 1) {
                                jQuery("#bifnDvi").css("display", "inline");
                                jQuery("#bifnDivAlert").css("display", "none");
                                tof = idchk(UBNval);
                                if (tof == true) {
                                    rog = "g";
                                } else {
                                    rog = "r";
                                }
                            } else {
                                jQuery("#ifDivAlert").css("display", "none");
                                jQuery("#billing_UBN").val("");
                                jQuery("#billing_Buyer").val("");
                                rog = "g";
                            }

                            if (rog == "r") {
                                jQuery("#UBNdivAlert").css("display", "inline");
                                if (jQuery("#billing_UBN").val().length == 0) {
                                    jQuery("#UBNdivAlert").html("&nbsp&nbsp請輸入統一編號!!!");
                                }else{
                                    jQuery("#UBNdivAlert").html("&nbsp&nbsp格式錯誤!!!");
                                }
                                jQuery("#place_order").attr("disabled", true);
                                jQuery("#place_order").css("background-color", "red");
                            } else {
                                jQuery("#UBNdivAlert").css("display", "none");
                                jQuery("#place_order").attr("disabled", false);
                                jQuery("#place_order").css("background-color", "#1fb25a");
                            }
                        }

                        function invoiceFlagChk() {
                            var ifVal = jQuery("#billing_invoiceFlag").val();
                            buOrBif();
                            jQuery("#billing_invoiceFlagNum").val("");
                            jQuery("#billing_invoiceFlagNum").attr("disabled", false);
                            if(ifVal == -1){
                                jQuery("#bifnDiv").css("display", "none");
                            }else if(ifVal == 0){
                                jQuery("#ifNumDiv").html("載具編號");
                                jQuery("#billing_invoiceFlagNum").attr("placeholder", "請輸入手機條碼");
                            }else if(ifVal == 1){
                                jQuery("#ifNumDiv").html("載具編號");
                                jQuery("#billing_invoiceFlagNum").attr("placeholder", "請輸入自然人憑證條碼");
                            }else if(ifVal == 3){
                                jQuery("#ifNumDiv").html(' . "'" . '捐贈碼&nbsp&nbsp<a href="https://www.einvoice.nat.gov.tw/APCONSUMER/BTC603W/" target="_blank">查詢捐贈碼</a>' . "'" . ');
                                jQuery("#billing_invoiceFlagNum").attr("placeholder", "請輸入受捐單位捐贈碼");
                            }else{
                                jQuery("#ifNumDiv").html("載具編號");
                                jQuery("#billing_invoiceFlagNum").attr("placeholder", "電子發票通知將寄送至您的電子郵件地址");
                                jQuery("#billing_invoiceFlagNum").attr("disabled", true);
                            }
                            invoiceFlagNumChk();
                        }

                        function invoiceFlagNumChk() {
                            var ifnVal = jQuery("#billing_invoiceFlagNum").val();
                            var ifVal = jQuery("#billing_invoiceFlag").val();
                            var needUBN = jQuery("#billing_needUBN").val();
                            if (needUBN == 0){
                                if(ifnVal || ifVal == 2 || ifVal == -1){
                                    jQuery("#bifnDivAlert").css("display", "none");
                                    jQuery("#place_order").attr("disabled", false);
                                    jQuery("#place_order").css("background-color", "#1fb25a");
                                }else{
                                    jQuery("#bifnDivAlert").css("display", "");
                                    jQuery("#place_order").attr("disabled", true);
                                    jQuery("#place_order").css("background-color", "red");
                                    if(ifVal == 3){
                                        jQuery("#bifnDivAlert").html("請輸入捐贈碼");
                                    }else{
                                        jQuery("#bifnDivAlert").html("請輸入載具編號");
                                    }
                                }
                            }
                        }

                        jQuery(document).ready(function () {
                            buOrBif();
                            jQuery("#billing_UBN").attr("maxlength", "8");
                            jQuery("#billing_invoiceFlagNum").attr("disabled", true);
                            jQuery("#billing_UBN").keyup(function () {
                                UBNrog();
                                if (jQuery("#billing_UBN").val().length < 8) {
                                    jQuery("#UBNdivAlert").css("display", "none");
                                }
                                invoiceFlagChk();
                            });

                            jQuery("#billing_UBN").change(function () {
                                UBNrog();
                                invoiceFlagChk();
                            });

                            jQuery("#billing_UBN").bind("paste", function () {
                                setTimeout(function () {
                                    UBNrog();
                                }, 100);
                                invoiceFlagChk();
                            });

                            jQuery("#billing_invoiceFlag").change(function () {
                                invoiceFlagChk();
                            });

                            jQuery("#billing_invoiceFlagNum").keyup(function () {
                                invoiceFlagNumChk();
                            });

                            jQuery("#billing_needUBN").change(function () {
                                setTimeout(function () {
                                    UBNrog();
                                    buOrBif();
                                }, 100);
                            });

                            jQuery("#billing_invoiceFlagNum").css("width", "100%");
                        });

                        function buOrBif(){
                            if(jQuery("#billing_needUBN").val() == 1){
                                jQuery("#buDiv").css("display", "");
                                jQuery("#bifDiv").css("display", "none");
                                jQuery("#bifnDiv").css("display", "none");
                            }else{
                                jQuery("#buDiv").css("display", "none");
                                jQuery("#bifDiv").css("display", "");
                                jQuery("#bifnDiv").css("display", "");
                            }
                        }
                    </script>
            ';

            return $checkout;
        }

        function electronic_invoice_fields_update_order_meta($order_id) {
            $order = wc_get_order($order_id);
            if (!in_array($_POST['payment_method'], ['newebpay', 'spgateway']) && $this->eiChk == 'yes') {
                $orderNote = "此訂單尚未開立電子發票，如確認收款完成須開立發票，請至ezPay電子發票平台進行手動單筆開立。<br>發票資料如下<br>發票是否需要打統一編號： ";
                if ($_POST['billing_needUBN']) {
                    $orderNote .= "是<br>";
                    $orderNote .= "統一編號： " . $_POST['billing_UBN'];
                } else {
                    $invoiceFlag = $_POST['billing_invoiceFlag'];
                    $invoiceFlagNum = $_POST['billing_invoiceFlagNum'];
                    $orderNote .= "否<br>電子發票索取方式： ";
                    switch ($invoiceFlag) {
                        case -1:
                            $orderNote .= "索取紙本發票";
                            break;
                        case 0:
                            $orderNote .= "手機條碼 <br>載具編號： " . $invoiceFlagNum;
                            break;
                        case 1:
                            $orderNote .= "自然人憑證條碼 <br>載具編號： " . $invoiceFlagNum;
                            break;
                        case 2:
                            $invoiceFlagNum = $_POST['billing_email'];
                            $orderNote .= "會員載具 <br>載具編號： " . $invoiceFlagNum;
                            break;
                        case 3:
                            $orderNote .= "捐贈發票 <br>捐贈碼： " . $invoiceFlagNum;
                            break;
                        default:
                            $orderNote .= "會員載具 <br>載具編號： " . $invoiceFlagNum;
                    }
                }
                $order->add_order_note(__($orderNote, 'woothemes'));
            }

            //Hidden Custom Fields: keys starting with an "_".
            update_post_meta($order_id, '_billing_needUBN', sanitize_text_field($_POST['billing_needUBN']));
            update_post_meta($order_id, '_billing_UBN', sanitize_text_field($_POST['billing_UBN']));
            update_post_meta($order_id, '_billing_invoiceFlag', sanitize_text_field($_POST['billing_invoiceFlag']));
            update_post_meta($order_id, '_billing_invoiceFlagNum', sanitize_text_field($_POST['billing_invoiceFlagNum']));
            update_post_meta($order_id, '_billing_Buyer', sanitize_text_field($_POST['billing_Buyer']));
        }

        private function writeLog($msg = '', $with_input = true)
        {
            $file_path = __DIR__ .'/order_log/'; // 檔案路徑
            if(! is_dir($file_path)) {
                return;
            }

            $file_name = 'newebpay_' . date('Ymd') . '.txt';  // 取時間做檔名 (YYYYMMDD)
            $file = $file_path . $file_name;
            $fp = fopen($file, 'a');
            $input = ($with_input) ? '|REQUEST:' . json_encode($_REQUEST) : '';
            $log_str = date('Y-m-d H:i:s') . '|' . $msg . $input . "\n";
            fwrite($fp, $log_str);
            fclose($fp);
            $this->clean_old_log($file_path);
        }

        private function clean_old_log($dir = '') {
            $del_date = date('Ymd', strtotime('-30 day'));
            $scan_dir = glob($dir . 'newebpay_*.txt');
            foreach ($scan_dir as $value) {
                $date = explode('_', basename($value, '.txt'));
                if (strtotime($del_date) > strtotime($date[1])) {
                    unlink($value);
                }
            }
        }

        //初始化陣列 避免部分交易回傳值差異導致PHP NOTICE
        private function init_array_data($arr = array(), $indexes = '') {
            $index_array = explode(',', $indexes);
            foreach($index_array as $val){
                $init_array[$val] = null; 
            }
            if(!empty($arr)){
                return array_merge($init_array, $arr);
            }
            return $init_array;
        }
    }

    /**
     * Add the gateway to WooCommerce
     *
     * @access public
     * @param array $methods
     * @package     WooCommerce/Classes/Payment
     * @return array
     */
    function add_newebpay_gateway($methods) {
        $methods[] = 'WC_newebpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_newebpay_gateway');

    // 物流
    function newebpay_shipping_method_init() {
        if(!class_exists('WC_NewebPay_Shipping')) {
    	    require_once 'class-newebpay_shipping.php';
        }
    }
    add_action('woocommerce_shipping_init', 'newebpay_shipping_method_init');

    // 新增物流選項
    function add_newebpay_shipping_method($methods) {
        if(!class_exists('WC_NewebPay_Shipping')) {
    	    require_once 'class-newebpay_shipping.php';
        }
        $methods['WC_CVSCOM_newebpay'] = new WC_NewebPay_Shipping('newebpay_cvscom', '藍新金流超商取貨');
        return $methods;
    }
    add_filter( 'woocommerce_shipping_methods', 'add_newebpay_shipping_method');

    // 選擇藍新金流超商取貨後 payment只輸出藍新金流
    function newebpay_alter_payment_gateways($list) {
        if(isset($_GET['pay_for_order']) && isset($_GET['key'])) {
            $order_id = wc_get_order_id_by_order_key($_GET['key']);
            $order = wc_get_order($order_id);
            if($order->has_shipping_method('newebpay_cvscom')) {
                $list = array('WC_newebpay');
            }
        } elseif(!is_admin() && function_exists('wc_get_chosen_shipping_method_ids')) { //後台無wc_get_chosen_shipping_method_ids function
            $chosen_shipping = wc_get_chosen_shipping_method_ids();
            //判斷購物車內商品是否全為虛擬商品 全為虛擬商品時會無法選擇物流方式 導致session的chosen_shipping會維持上次所選
            $virtual_count = 0;
            $cart_items = WC()->cart->get_cart();
            foreach ( $cart_items as $key => $cart_item ) {
                $virtual_count += ($cart_item['data']->is_virtual()) ? 1 : 0;
            }
            if(@ in_array('newebpay_cvscom', $chosen_shipping) && $virtual_count < count($cart_items)) {
                $list = array('WC_newebpay');
            }
        }

        return $list;
    }
    add_filter('woocommerce_payment_gateways', 'newebpay_alter_payment_gateways', 100);

    // 選擇藍新金流超商取貨後 只能使用藍新金流付款
    function newebpay_validate_payment() {
        $shipping = (isset($_POST['shipping_method'][0])) ? $_POST['shipping_method'][0] : null;
        $payment = (isset($_POST['payment_method'])) ? $_POST['payment_method'] : null;
        global $woocommerce;
        if ($shipping === 'newebpay_cvscom') {
            if ($payment !== 'newebpay') {
                wc_add_notice("藍新金流超商取貨 僅能使用 藍新金流 付款", 'error');
            }
            $cart_total = round($woocommerce->cart->total);
            if($cart_total < 30 || $cart_total > 20000) {
                wc_add_notice("藍新金流超商取貨 商品小計不得小於30元或大於2萬元", 'error');
            }
        }
    }
    add_action('woocommerce_after_checkout_validation', 'newebpay_validate_payment');

    // 訂單頁->付款 阻擋藍新金流超商取貨時 選擇藍新金流以外付款方式
    function newebpay_pay_for_order_validate($order) {
        $payment = (isset($_POST['payment_method'])) ? $_POST['payment_method'] : null;
        if($order->has_shipping_method('newebpay_cvscom') && $payment !== 'newebpay') {
            wc_add_notice("藍新金流超商取貨 僅能使用 藍新金流 付款", 'error');
        }
    }
    add_action('woocommerce_before_pay_action', 'newebpay_pay_for_order_validate');

    //前台view-order 後台編輯訂單
    function newebpay_order_shipping_fields($order)
    {
        $id = (is_object($order)) ? $order->get_id(): $order;   //後台傳入值會是object 前台傳入為order id

        $pre = (empty(get_post_meta($id, '_newebpayConsignee', true ))) ? 'spgateway' : 'newebpay'; //取得參數名稱前置詞
        $data = array(
            'storeName' => get_post_meta($id, "_{$pre}StoreName", true ),
            'storeAddr' => get_post_meta($id, "_{$pre}StoreAddr", true ),
            'consignee' => get_post_meta($id, "_{$pre}Consignee", true ),
            'consigneePhone' => get_post_meta($id, "_{$pre}ConsigneePhone", true )
        );
        $fieldsName = array(
            'storeName' => '門市名稱',
            'storeAddr' => '門市地址',
            'consignee' => '收件人',
            'consigneePhone' => '收件人電話'
        );
        $count = 0;
        $for_echo = array();
        foreach($data as $key => $val) {
            if(!empty($val)) {
                $count++;
                $for_echo[] = __('<p><strong>' . $fieldsName[$key] . ' : </strong>' . $val. '</p>');
            }
        }
        if($count > 0) {
            if(!is_object($order)) {  //前台
                echo '<h2 class="woocommerce-column__title">超商取貨</h2>';
            } elseif(is_object($order)) { //後台
                echo __('<h3>藍新金流超商取貨</h3>');
            }
            echo implode('', $for_echo) . '<br/>';
        }
    }
    if (is_admin()) {
        add_action('woocommerce_admin_order_data_after_shipping_address', 'newebpay_order_shipping_fields' );
    } else {
        add_action('woocommerce_view_order', 'newebpay_order_shipping_fields' );
    }
}
?>