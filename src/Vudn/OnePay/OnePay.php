<?php

namespace Vudn\OnePay;


class Onepay
{
    protected $vpc_Merchant;
    protected $vpc_AccessCode;
    protected $vpc_SecureHash;
    protected $vpc_url;
    protected $config;
    protected $params;

    CONST DOMESTIC_PAYMENT = 'domestic';
    CONST INTERNATIONAL_PAYMENT = 'international';

    public function __construct($data = array())
    {
        $this->config = include_once(dirname(__FILE__) . '/../Configuration/Configuration.php');
//        var_dump($this->config);die;
        if (!empty($data)) {
            if (array_key_exists('type', $data) && $data['type'] == self::INTERNATIONAL_PAYMENT) {  # international || domestic
                $this->vpc_Merchant = $this->config[self::INTERNATIONAL_PAYMENT]['vpc_Merchant'];
                $this->vpc_AccessCode = $this->config[self::INTERNATIONAL_PAYMENT]['vpc_AccessCode'];
                $this->vpc_SecureHash = $this->config[self::INTERNATIONAL_PAYMENT]['vpc_SecureHash'];
                $this->vpc_url = $this->config[self::INTERNATIONAL_PAYMENT]['vpc_url'];
                $this->params = array(
                    'Title' => $data['Title'],
                    'AgainLink' => $data['AgainLink'],
                    'vpc_Merchant' => $this->vpc_Merchant,
                    'vpc_AccessCode' => $this->vpc_AccessCode,
                    'vpc_Amount' => $data['vpc_Amount'] * 100,
                    'vpc_Command' => 'pay',
                    'vpc_Customer_Email' => $data['email'],
                    'vpc_Customer_Id' => $data['email'],
                    'vpc_Customer_Phone' => $data['phone'],
                    'vpc_Locale' => 'vn',
                    'vpc_MerchTxnRef' => $data['vpc_MerchTxnRef'],
                    'vpc_OrderInfo' => $data['vpc_OrderInfo'],
                    'vpc_ReturnURL' => $data['vpc_ReturnURL'],
//                    'vpc_SHIP_City' => ucwords($this->unicode2ascii($data['s_state'])),
//                    'vpc_SHIP_Country' => ucwords($this->unicode2ascii($data['s_country'])),
//                    'vpc_SHIP_Provice' => ucwords($this->unicode2ascii($data['s_province'])),
//                    'vpc_SHIP_Street01' => ucwords($this->unicode2ascii($data['s_address'])),
                    'vpc_TicketNo' => $data['vpc_TicketNo'],
                    'vpc_Version' => '2',
                );
            } else {
                $this->vpc_Merchant = $this->config[self::DOMESTIC_PAYMENT]['vpc_Merchant'];
                $this->vpc_AccessCode = $this->config[self::DOMESTIC_PAYMENT]['vpc_AccessCode'];
                $this->vpc_SecureHash = $this->config[self::DOMESTIC_PAYMENT]['vpc_SecureHash'];
                $this->vpc_url = $this->config[self::DOMESTIC_PAYMENT]['vpc_url'];

                $this->params = array(
                    'Title' => $data['Title'],
                    'vpc_Merchant' => $this->vpc_Merchant,
                    'vpc_AccessCode' => $this->vpc_AccessCode,
                    'vpc_Amount' => $data['vpc_Amount'] * 100,
                    'vpc_Currency' => 'VND',
                    'vpc_Command' => 'pay',
                    'vpc_Locale' => 'vn',
                    'vpc_MerchTxnRef' => $data['vpc_MerchTxnRef'],
                    'vpc_OrderInfo' => $data['vpc_OrderInfo'],
                    'vpc_ReturnURL' => $data['vpc_ReturnURL'],
                    'vpc_TicketNo' => $data['vpc_TicketNo'],
                    'vpc_Version' => '2',
                );
            }
        }
    }

    public function buildUrl()
    {
        $vpcURL = $this->vpc_url . '?';

        //$stringHashData = $this->vpc_SecureHash; *****************************Khởi tạo chuỗi dữ liệu mã hóa trống*****************************
        $stringHashData = "";

        // sắp xếp dữ liệu theo thứ tự a-z trước khi nối lại
        // arrange array data a-z before make a hash
        ksort($this->params);

        // set a parameter to show the first pair in the URL
        // đặt tham số đếm = 0
        $appendAmp = 0;

        foreach ($this->params as $key => $value) {
            // create the md5 input and URL leaving out any fields that have no value
            // tạo chuỗi đầu dữ liệu những tham số có dữ liệu
            if (strlen($value) > 0) {
                // this ensures the first paramter of the URL is preceded by the '?' char
                if ($appendAmp == 0) {
                    $vpcURL .= urlencode($key) . '=' . urlencode($value);
                    $appendAmp = 1;
                } else {
                    $vpcURL .= '&' . urlencode($key) . "=" . urlencode($value);
                }
                //$stringHashData .= $value; *****************************sử dụng cả tên và giá trị tham số để mã hóa*****************************
                if ((strlen($value) > 0) && ((substr($key, 0, 4) == "vpc_") || (substr($key, 0, 5) == "user_"))) {
                    $stringHashData .= $key . "=" . $value . "&";
                }
            }
        }

        //*****************************xóa ký tự & ở thừa ở cuối chuỗi dữ liệu mã hóa*****************************
        $stringHashData = rtrim($stringHashData, "&");

        // Create the secure hash and append it to the Virtual Payment Client Data if
        // the merchant secret has been provided.
        // thêm giá trị chuỗi mã hóa dữ liệu được tạo ra ở trên vào cuối url
        if (strlen($this->vpc_SecureHash) > 0) {
            //$vpcURL .= "&vpc_SecureHash=" . strtoupper(md5($stringHashData));
            // *****************************Thay hàm mã hóa dữ liệu*****************************
            $vpcURL .= "&vpc_SecureHash=" . strtoupper(hash_hmac('SHA256', $stringHashData, pack('H*', $this->vpc_SecureHash)));
        }

        return $vpcURL;
    }

    public function getResponse($response = array())
    {
        $vpc_Txn_Secure_Hash = $response["vpc_SecureHash"];
        unset($response["vpc_SecureHash"]);

        $txnResponseCode = $this->null2unknown($response["vpc_TxnResponseCode"]);
        if (strlen($this->vpc_SecureHash) > 0 && $txnResponseCode != "7" && $txnResponseCode != "No Value Returned") {
            ksort($response);

            $stringHashData = "";

            // sort all the incoming vpc response fields and leave out any with no value
            foreach ($response as $key => $value) {
                //     *****************************chỉ lấy các tham số bắt đầu bằng "vpc_" hoặc "user_" và khác trống và không phải chuỗi hash code trả về*****************************
                if ($key != "vpc_SecureHash" && (strlen($value) > 0) && ((substr($key, 0, 4) == "vpc_") || (substr($key, 0, 5) == "user_"))) {
                    $stringHashData .= $key . "=" . $value . "&";
                }
            }
            //  *****************************Xóa dấu & thừa cuối chuỗi dữ liệu*****************************
            $stringHashData = rtrim($stringHashData, "&");


            //    *****************************Thay hàm tạo chuỗi mã hóa*****************************
            if (strtoupper($vpc_Txn_Secure_Hash) == strtoupper(hash_hmac('SHA256', $stringHashData, pack('H*', $this->vpc_SecureHash)))) {
                // Secure Hash validation succeeded, add a data field to be displayed
                // later.
                $hashValidated = "CORRECT";
            } else {
                // Secure Hash validation failed, add a data field to be displayed
                // later.
                $hashValidated = "INVALID_HASH";
            }
        } else {
            // Secure Hash was not validated, add a data field to be displayed later.
            $hashValidated = "INVALID_HASH";
        }


        $response = [
            'result' => 0,
            'message' => $this->getResponseDescription($txnResponseCode)
        ];
        if ($hashValidated == "CORRECT" && $txnResponseCode == "0") {
            $response['result'] = 1;
        } elseif ($txnResponseCode != '0') {
            $response['message'] = $this->getResponseDescription($txnResponseCode);
        } else {
            $response['message'] = $this->getResponseDescription(100);
        }

        return $response;
    }

    private function getResponseDescription($responseCode)
    {

        switch ($responseCode) {
            case "0" :
                $result = "Giao dịch thành công - Approved";
                break;
            case "1" :
                $result = "Ngân hàng từ chối giao dịch - Bank Declined";
                break;
            case "3" :
                $result = "Mã đơn vị không tồn tại - Merchant not exist";
                break;
            case "4" :
                $result = "Không đúng access code - Invalid access code";
                break;
            case "5" :
                $result = "Số tiền không hợp lệ - Invalid amount";
                break;
            case "6" :
                $result = "Mã tiền tệ không tồn tại - Invalid currency code";
                break;
            case "7" :
                $result = "Lỗi không xác định - Unspecified Failure ";
                break;
            case "8" :
                $result = "Số thẻ không đúng - Invalid card Number";
                break;
            case "9" :
                $result = "Tên chủ thẻ không đúng - Invalid card name";
                break;
            case "10" :
                $result = "Thẻ hết hạn/Thẻ bị khóa - Expired Card";
                break;
            case "11" :
                $result = "Thẻ chưa đăng ký sử dụng dịch vụ - Card Not Registed Service(internet banking)";
                break;
            case "12" :
                $result = "Ngày phát hành/Hết hạn không đúng - Invalid card date";
                break;
            case "13" :
                $result = "Vượt quá hạn mức thanh toán - Exist Amount";
                break;
            case "21" :
                $result = "Số tiền không đủ để thanh toán - Insufficient fund";
                break;
            case "99" :
                $result = "Người sủ dụng hủy giao dịch - User cancel";
                break;
            default :
                $result = "Giao dịch thất bại - Failured";
        }
        return $result;
    }

    private function null2unknown($data)
    {
        return ($data == "") ? "No Value Returned" : $data;
    }

    private function unicode2ascii($string)
    {
        $string_unicode = 'é,è,ẻ,ẽ,ẹ,ê,ế,ề,ể,ễ,ệ,ý,ỳ,ỷ,ỹ,ỵ,ú,ù,ủ,ũ,ụ,ư,ứ,ừ,ử,ữ,ự,í,ì,ỉ,ĩ,ị,ó,ò,ỏ,õ,ọ,ô,ố,ồ,ổ,ỗ,ộ,ơ,ớ,ờ,ở,ỡ,ợ,á,à,ả,ã,ạ,â,ấ,ầ,ẩ,ẫ,ậ,ă,ắ,ằ,ẳ,ẵ,ặ,đ,ð,ď,É,È,Ẻ,Ẽ,Ẹ,Ê,Ế,Ề,Ể,Ễ,Ệ,Ý,Ỳ,Ỷ,Ỹ,Ỵ,Ú,Ù,Ủ,Ũ,Ụ,Ư,Ứ,Ừ,Ử,Ữ,Ự,Í,Ì,Ỉ,Ĩ,Ị,Ó,Ò,Ỏ,Õ,Ọ,Ô,Ố,Ồ,Ổ,Ỗ,Ộ,Ơ,Ớ,Ờ,Ở,Ỡ,Ợ,Á,À,Ả,Ã,Ạ,Â,Ấ,Ầ,Ẩ,Ẫ,Ậ,Ă,Ắ,Ằ,Ẳ,Ẵ,Ặ,Ð,Ď,Đ';
        $string_abc = 'e,e,e,e,e,e,e,e,e,e,e,y,y,y,y,y,u,u,u,u,u,u,u,u,u,u,u,i,i,i,i,i,o,o,o,o,o,o,o,o,o,o,o,o,o,o,o,o,o,a,a,a,a,a,a,a,a,a,a,a,a,a,a,a,a,a,d,d,d,E,E,E,E,E,E,E,E,E,E,E,Y,Y,Y,Y,Y,U,U,U,U,U,U,U,U,U,U,U,I,I,I,I,I,O,O,O,O,O,O,O,O,O,O,O,O,O,O,O,O,O,A,A,A,A,A,A,A,A,A,A,A,A,A,A,A,A,A,D,D,D';

        $string_unicode_array = explode(",", $string_unicode);
        $string_abc_array = explode(",", $string_abc);

        $string = str_replace($string_unicode_array, $string_abc_array, $string);

        return $string;
    }
}