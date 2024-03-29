<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Giohang extends CI_Controller {
	// Hàm khởi tạo
    function __construct() {
        parent::__construct();
        $this->load->model('frontend/Morder');
        $this->load->model('frontend/Mproduct');
        $this->load->model('frontend/Morderdetail');
        $this->load->model('frontend/Mcustomer');
        $this->load->model('frontend/Mcategory');
        $this->load->model('frontend/Mconfig');
        $this->load->model('frontend/Mdistrict');
        $this->load->model('frontend/Mprovince');
        $this->data['com']='giohang';
    }
    
    public function index(){
        $this->data['title']='Smart store - Giỏ hàng của bạn';
        $this->data['view']='index';
        $this->load->view('frontend/layout',$this->data);
    }
    function check_mail(){
        $email = $this->input->post('email');
        if($this->Mcustomer->customer_detail_email($email))
        {
            $this->form_validation->set_message(__FUNCTION__, 'Email đã đã là thành viên, Vui lòng đăng nhập hoặc nhập Email khác !');
            return FALSE;
        }
        return TRUE;
    }

    // create link thanh toán VNPay
    public function paymentVnpay($data_payment)
    {
        $vnp_TmnCode = 'ZVNO739N'; //Mã website tại VNPAY
        $vnp_HashSecret = 'GZEXLKULIIZDNKAKKFEZCMGOQVIZYBTK'; //Chuỗi bí mật
        $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
        $vnp_Returnurl = 'http://localhost/webdoan/onlinecheckout/thankyouvnpay';

        $vnp_TxnRef = $data_payment['order_id']; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
        $vnp_OrderInfo ='Thanh toán test.';
        $vnp_OrderType = 'other';
        $vnp_Amount = $data_payment['amount']*100;
        $vnp_Locale = 'vn';
        $vnp_BankCode = '';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        $returnData = array('code' => '00'
            , 'message' => 'success'
            , 'data' => $vnp_Url);
        return $vnp_Url;
    }

    public function info_order(){
        $this->load->library('session');
        $this->load->helper('string');
        $this->load->library('email');
        $this->load->library('form_validation');
        $d=getdate();
        $today=$d['year']."/".$d['mon']."/".$d['mday']." ".$d['hours'].":".$d['minutes'].":".$d['seconds'];
        if(!$this->session->userdata('sessionKhachHang'))
        {
            $this->form_validation->set_rules('email', 'Địa chỉ email', 'required|is_unique[db_customer.email]');
        }
        $this->form_validation->set_rules('phone', 'Số điện thoại', 'required');
        $this->form_validation->set_rules('name', 'Họ và tên', 'required|min_length[3]');
        $this->form_validation->set_rules('address', 'Địa chỉ', 'required');
        $this->form_validation->set_rules('city', 'Tỉnh thành', 'required');
        $this->form_validation->set_rules('DistrictId', 'Quận huyện', 'required');
        $priceShip=$this->Mconfig->config_price_ship();
        if($this->form_validation->run() == TRUE){
            //Tinh tien don hang
            $money=0;
            if($this->session->userdata('cart')){
                $data=$this->session->userdata('cart');
                foreach ($data as $key => $value) {
                    $row = $this->Mproduct->product_detail_id($key);
                    $total=0;
                    if($row['price_sale'] > 0){
                        $total=$row['price_sale']*$value;
                    }else{
                        $total=$row['price'] * $value;
                    }
                    $money+=$total;
                }
            }
            $idCustomer=null;
            if($this->session->userdata('sessionKhachHang')){
                $emailtemp = $this->session->userdata('email');
                $info=$this->session->userdata('sessionKhachHang');
                $idCustomer=$info['id'];
            }else{
                $emailtemp = $_POST['email'];
            }
            if(!$this->session->userdata('sessionKhachHang')){
                $datacustomer= array(
                    'fullname'=>$_POST['name'],
                    'phone'=> $_POST['phone'],
                    'email'=> $emailtemp,
                    'created' =>$today,
                    'status'=>1,
                    'trash'=>1
                );
                $this->Mcustomer->customer_insert($datacustomer);
                $row=$this->Mcustomer->customer_detail_email($_POST['email']);
                $this->session->set_userdata('info-customer',$row);
                $info=$this->session->userdata('info-customer');
                if($info['id']){
                    $idCustomer=$info['id'];
                    $this->session->set_userdata('id-info-customer',$idCustomer);
                }
            }
            //kt ma giam gia
            if($this->session->userdata('coupon_price'))
            {
                $coupon =$this->session->userdata('coupon_price');
                $coupon_percent =$this->session->userdata('coupon_percent');
                $is_percent_coupon =$this->session->userdata('is_percent_coupon');
                $idcoupon =$this->session->userdata('id_coupon_price');
                $amount_number_used = $this->Mconfig->get_amount_number_used($idcoupon);
                $mycoupon=array(
                    'number_used' => $amount_number_used+1,
                );
                $this->Mconfig->coupon_update($mycoupon, $idcoupon);
            }
            else{
                $coupon = 0;
            }

            if($is_percent_coupon){
                $discount = $money * $coupon_percent / 100;
            }else{
                $discount = $coupon;
            }

            $provinceId = $_POST['city'];
            $districtId = $_POST['DistrictId'];
            $mydata=array(
                'orderCode' => random_string('alnum', 8),
                'customerid' => $idCustomer,
                'orderdate' => $today,
                'fullname' => $_POST['name'],
                'phone' => $_POST['phone'],
                'address' => $_POST['address'],
                'money' => $money + $priceShip - $discount,
                'price_ship' => $priceShip,
                'coupon' => $discount,
                'province' => $provinceId,
                'district' => $districtId,
                'trash' => 1,
                'status' => 0
            );

            //Insert to db_order
            $this->Morder->order_insert($mydata);


            // lưu tt đơn hàng và xóa session coupon
            $this->session->unset_userdata('id_coupon_price');
            $this->session->unset_userdata('coupon_price');

            //Insert to db_orderdetail
            $order_detail = $this->Morder->order_detail_customerid($idCustomer);
            $orderid = $order_detail['id'];
            $data=[];
            if($this->session->userdata('cart')){
                $val = $this->session->userdata('cart');
                foreach ($val as $key => $value){
                    $row = $this->Mproduct->product_detail_id($key);
                    if($row['price_sale'] > 0){
                        $price = $row['price_sale'];
                    }else{
                        $price = $row['price'];
                    }
                    $data = array(
                        'orderid' => $orderid,
                        'productid' => $key,
                        'price' => $price,
                        'count' => $value,
                        'trash' => 1,
                        'status' => 1
                    );
                    $this->Morderdetail->orderdetail_insert($data);
                }
            }
            $array_items = array('cart');
            $this->session->unset_userdata($array_items);

            if ($_POST['payment_method'] == 'vnpay') {
                $url = $this->paymentVnpay([
                    'order_id' => $mydata['orderCode'],
                    'amount' => $mydata['money'],
                ]);

                return redirect($url);
            }
            
            redirect('/thankyou','refresh');

        }else{
            $this->data['title']='Smart store - Thông tin đơn hàng';
            $this->data['view']='info-order';
            $this->load->view('frontend/layout',$this->data);
        }
    }

//     public function info_ordermomo(){
//         $this->load->library('session');
//         $this->load->helper('string');
//         $this->load->library('email');
//         $this->load->library('form_validation');
//         $d=getdate();
//         $today=$d['year']."/".$d['mon']."/".$d['mday']." ".$d['hours'].":".$d['minutes'].":".$d['seconds'];
//         if(!$this->session->userdata('sessionKhachHang'))
//         {
//             $this->form_validation->set_rules('email', 'Địa chỉ email', 'required|is_unique[db_customer.email]');
//         }
//         $this->form_validation->set_rules('phone', 'Số điện thoại', 'required');
//         $this->form_validation->set_rules('name', 'Họ và tên', 'required|min_length[3]');
//         $this->form_validation->set_rules('address', 'Địa chỉ', 'required');
//         $this->form_validation->set_rules('city', 'Tỉnh thành', 'required');
//         $this->form_validation->set_rules('DistrictId', 'Quận huyện', 'required');
//         $priceShip=$this->Mconfig->config_price_ship();
//         if($this->form_validation->run() == TRUE){
//             //Tinh tien don hang
//             $money=0;
//             if($this->session->userdata('cart')){
//                 $data=$this->session->userdata('cart');
//                 foreach ($data as $key => $value) {
//                     $row = $this->Mproduct->product_detail_id($key);
//                     $total=0;
//                     if($row['price_sale'] > 0){
//                         $total=$row['price_sale']*$value;
//                     }else{
//                         $total=$row['price'] * $value;
//                     }
//                     $money+=$total;
//                 }
//             }
//             $idCustomer=null;
//             if($this->session->userdata('sessionKhachHang')){
//                 $emailtemp = $this->session->userdata('email');
//                 $info=$this->session->userdata('sessionKhachHang');
//                 $idCustomer=$info['id'];
//             }else{
//                 $emailtemp = $_POST['email'];
//             }
//             if(!$this->session->userdata('sessionKhachHang')){
//                 $datacustomer= array(
//                     'fullname'=>$_POST['name'],
//                     'phone'=> $_POST['phone'],
//                     'email'=> $emailtemp,
//                     'created' =>$today,
//                     'status'=>1,
//                     'trash'=>1
//                 );
//                 $this->Mcustomer->customer_insert($datacustomer);
//                 $row=$this->Mcustomer->customer_detail_email($_POST['email']);
//                 $this->session->set_userdata('info-customer',$row);
//                 $info=$this->session->userdata('info-customer');
//                 if($info['id']){
//                     $idCustomer=$info['id'];
//                     $this->session->set_userdata('id-info-customer',$idCustomer);
//                 }
//             }
//             //kt ma giam gia
//             if($this->session->userdata('coupon_price'))
//             {
//                 $coupon =$this->session->userdata('coupon_price');
//                 $coupon_percent =$this->session->userdata('coupon_percent');
//                 $is_percent_coupon =$this->session->userdata('is_percent_coupon');
//                 $idcoupon =$this->session->userdata('id_coupon_price');
//                 $amount_number_used = $this->Mconfig->get_amount_number_used($idcoupon);
//                 $mycoupon=array(
//                     'number_used' => $amount_number_used+1,
//                 );
//                 $this->Mconfig->coupon_update($mycoupon, $idcoupon);
//             }
//             else{
//                 $coupon = 0;
//             }

//             if($is_percent_coupon){
//                 $discount = $money * $coupon_percent / 100;
//             }else{
//                 $discount = $coupon;
//             }

//             $provinceId = $_POST['city'];
//             $districtId = $_POST['DistrictId'];
//             $mydata=array(
//                 'orderCode' => random_string('alnum', 8),
//                 'customerid' => $idCustomer,
//                 'orderdate' => $today,
//                 'fullname' => $_POST['name'],
//                 'phone' => $_POST['phone'],
//                 'address' => $_POST['address'],
//                 'money' => $money + $priceShip - $discount,
//                 'price_ship' => $priceShip,
//                 'coupon' => $discount,
//                 'province' => $provinceId,
//                 'district' => $districtId,
//                 'trash' => 1,
//                 'status' => 0
//             );

//             //Insert to db_order
//             $this->Morder->order_insert($mydata);


//             // lưu tt đơn hàng và xóa session coupon
//             $this->session->unset_userdata('id_coupon_price');
//             $this->session->unset_userdata('coupon_price');

//             //Insert to db_orderdetail
//             $order_detail = $this->Morder->order_detail_customerid($idCustomer);
//             $orderid = $order_detail['id'];
//             $data=[];
//             if($this->session->userdata('cart')){
//                 $val = $this->session->userdata('cart');
//                 foreach ($val as $key => $value){
//                     $row = $this->Mproduct->product_detail_id($key);
//                     if($row['price_sale'] > 0){
//                         $price = $row['price_sale'];
//                     }else{
//                         $price = $row['price'];
//                     }
//                     $data = array(
//                         'orderid' => $orderid,
//                         'productid' => $key,
//                         'price' => $price,
//                         'count' => $value,
//                         'trash' => 1,
//                         'status' => 1
//                     );
//                     $this->Morderdetail->orderdetail_insert($data);
//                 }
//             }
//             $array_items = array('cart');
//             $this->session->unset_userdata($array_items);
//             redirect('/thankyou','refresh');

//         }else{
//             $this->data['title']='Smart store - Thông tin đơn hàng';
//             $this->data['view']='info-ordermomo';
//             $this->load->view('frontend/layout',$this->data);
//         }
//     }


//     public function execPostRequest($url, $data)
//     {   
//         $ch = curl_init($url);
//         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
//         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//             'Content-Type: application/json',
//             'Content-Length: ' . strlen($data))
//             );
//         curl_setopt($ch, CURLOPT_TIMEOUT, 5);
//         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
//          //execute post
//          $result = curl_exec($ch);
//          //close connection
//          curl_close($ch);
//          return $result;
//     }   
//     public function online_checkout(){
//        if(isset($_POST['payUrl'])){
//         $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
//         $partnerCode = 'MOMOBKUN20180529';
//         $accessKey = 'klm05TvNBzhg7h7j';
//         $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
//         $orderInfo = "Thanh toán qua MoMo";
//         $amount = "10000";
//         $orderId = time() ."";
//         $redirectUrl = "http://localhost/hoangvushop/thankyou.html";
//         $ipnUrl = "http://localhost/hoangvushop/thankyou.html";
//         $extraData = "";



//     $partnerCode = $partnerCode;
//     $accessKey = $accessKey;
//     $serectkey = $secretKey;
//     $orderId = $orderId; // Mã đơn hàng
//     $orderInfo = $orderInfo;
//     $amount = $amount;
//     $ipnUrl = $ipnUrl;
//     $redirectUrl = $redirectUrl;
//     $extraData = $extraData;

//     $requestId = time() . "";
//     $requestType = "payWithATM";
//     //$extraData = ($_POST["extraData"] ? $_POST["extraData"] : "");
//     //before sign HMAC SHA256 signature
//     $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
//     $signature = hash_hmac("sha256", $rawHash, $serectkey);
//     $data = array('partnerCode' => $partnerCode,
//         'partnerName' => "Test",
//         "storeId" => "MomoTestStore",
//         'requestId' => $requestId,
//         'amount' => $amount,
//         'orderId' => $orderId,
//         'orderInfo' => $orderInfo,
//         'redirectUrl' => $redirectUrl,
//         'ipnUrl' => $ipnUrl,
//         'lang' => 'vi',
//         'extraData' => $extraData,
//         'requestType' => $requestType,
//         'signature' => $signature);
//     $result = $this->execPostRequest($endpoint, json_encode($data));
//     $jsonResult = json_decode($result, true);  // decode json

//     //Just a example, please check more in there

//     header('Location: ' . $jsonResult['payUrl']);
// }
//     }
    public function thankyou(){
        if($this->session->userdata('info-customer')||$this->session->userdata('sessionKhachHang')){
            if($this->session->userdata('sessionKhachHang')){
                $val = $this->session->userdata('sessionKhachHang');
            }else{
                $val = $this->session->userdata('info-customer');
            }
            $list = $this->Morder->order_detail_customerid($val['id']);
            $data = array(
                'order' => $list,
                'customer' => $val,
                'orderDetail' => $this->Morderdetail->orderdetail_order_join_product($list['id']),
                'province' => $this->Mprovince->province_name($list['province']),
                'district' => $this->Mdistrict->district_name($list['district']),
                'priceShip' => $this->Mconfig->config_price_ship(),
                'coupon' => $list['coupon'],

            );
            $this->data['customer']=$val;
            $this->data['get']=$list;
            $this->load->library('email');
            $this->load->library('parser');
            $this->email->clear();
            $config['protocol']    = 'smtp';
            $config['smtp_host']    = 'ssl://smtp.gmail.com';
            $config['smtp_port']    = '465';
            $config['smtp_timeout'] = '7';
            $config['smtp_user']    = 'hoangvu11promax256@gmail.com';
            $config['smtp_pass']    = 'spxtmrdfxloyxvhk';
            // mk trên la mat khau dung dung cua gmail, có thể dùng gmail hoac mat khau. Tao mat khau ung dung de bao mat tai khoan
            $config['charset']    = 'utf-8';
            $config['newline']    = "\r\n";
            $config['wordwrap'] = TRUE;
            $config['mailtype'] = 'html';
            $config['validation'] = TRUE;   
            $this->email->initialize($config);
            $this->email->from('hoangvu11promax256@gmail.com', 'Smart Store');
            $list = array($val['email']);
            $this->email->to($list);
            $this->email->subject('Hệ thống Smart Store');
            $body = $this->load->view('frontend/modules/email',$data,TRUE);
            $this->email->message($body); 
            $this->email->send();

            $datax = array('email' => '');
            $idx= $this->session->userdata('id-info-customer');
            $this->Mcustomer->customer_update($datax,$idx);
            $this->session->unset_userdata('id-info-customer','money_check_coupon');
        }   
        $this->data['title']='Smart Store.vn - Kết quả đơn hàng';
        $this->data['view']='thankyou';
        $this->load->view('frontend/layout',$this->data);
    }




    public function district(){
        $this->load->library('session');
        $id=$_POST['provinceid'];
        $list = $this->Mdistrict->district_provinceid($id);
        $html="<option value =''>--- Chọn quận huyện ---</option>";
        foreach ($list as $row) 
        {
            $html.='<option value = '.$row["id"].'>'.$row["name"].'</option>';
        }
        echo json_encode($html);
    }
    public function coupon(){
        $d=getdate();
        $today=$d['year']."-".$d['mon']."-".$d['mday'];
        $html='';
        if($this->session->userdata('coupon_price')){
         $html.='<p>Mỗi đơn hàng chỉ áp dụng 1 Mã giảm giá !!</p>';
     }else{
        if(empty($_POST['code']))
        {
            $html.='<p>Vui lòng nhập Mã giảm giá nếu có !!</p>';
        }
        else
        {
            // KIỂM TRA SỐ TIỀN TRONG GIỎ HÀNG
            $money=0;
            if($this->session->userdata('cart')){
                $data=$this->session->userdata('cart');
                foreach ($data as $key => $value) {
                    $row = $this->Mproduct->product_detail_id($key);
                    $total=0;
                    if($row['price_sale'] > 0){
                        $total=$row['price_sale']*$value;
                    }else{
                        $total=$row['price'] * $value;
                    }
                    $money+=$total;
                }
            }
            //
            // KIỂM TRA MÃ GIẢM GIÁ CÓ TỒN TẠI KO
            $coupon = $_POST['code'];
            $getcoupon = $this->Mconfig->get_config_coupon_discount($coupon);
            if(empty($getcoupon)) {
               $html.='<p>Mã giảm giá không tồn tại!</p>';
           }
           foreach ($getcoupon as $value) {
            if($value['code'] == $coupon)
            {
                if (strtotime($value['expiration_date']) <= strtotime($today)){
                    $html.='<p>Mã giảm giá '.$value['code'].' đã hết hạn sử dụng từ ngày '.$value['expiration_date'].' !</p>';
                }else if($value['limit_number'] -$value['number_used'] == 0){
                    $html.='<p>Mã giảm giá '.$value['code'].' đã hết số lần nhập !</p>';
                }else if($value['payment_limit'] >= $money ){
                    $html.='<p> Mã giảm giá này chỉ áp dụng cho đơn hàng từ '.number_format($value['payment_limit']).' đ trở lên !</p>';
                }else{
                    $html.='<script>document.location.reload(true);</script> <p>Mã giảm giá '.$value['code'].' đã được kích hoạt !</p>';
                    $this->session->set_userdata('coupon_price',$value['discount']);
                    $this->session->set_userdata('coupon_percent',$value['discount_percent']);
                    $this->session->set_userdata('is_percent_coupon',$value['use_discount_percent']);
                    $this->session->set_userdata('id_coupon_price',$value['id']);
                }
            }
        }
    }

}
echo json_encode($html);
}
public function removecoupon(){
    $html='<script>document.location.reload(true);</script>';
    $this->session->unset_userdata('coupon_price');
    $this->session->unset_userdata('id_coupon_price');
    echo json_encode($html);
}
}
// email trang thankyou bị sai
