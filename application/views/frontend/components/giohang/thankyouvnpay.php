<?php 
defined('BASEPATH') OR exit('No direct script access allowed'); 
?>
<section id="checkout-cart">
    <div class="container">
        <div class="col-md-12">
            <div class="wrapper overflow-hidden">
                <div class="checkout-content">
                    <div class="tks-header">
                        <h3 class="fa fa-check-circle"> Thông tin đơn hàng đã được gửi đến 
                            <?php 
                            if($this->session->userdata('info-customer')){
                                $data=$this->session->userdata('info-customer');
                                echo $data['email'];
                                $this->session->unset_userdata('info-customer');
                            }else{
                                if($this->session->userdata('sessionKhachHang')){
                                    $data=$this->session->userdata('sessionKhachHang');
                                    echo $data['email'];
                                }
                            }
                            ?>
                            . Qúy khách hãy đăng nhập gmail để kiểm tra thông tin đơn hàng.
                        </h3>
                    </div>
                    <div class="tks-content" style="min-height: 1px;
                    overflow: hidden;">
                    <div class="col-xs-12 col-sm-12 col-md-7 col-login-checkout" style="margin-bottom: 20px">
                        <table class="table tks-tabele-info-cus">
                            <thead>
                                <tr>
                                    <th colspan="2">Thông tin thanh toán nhận hàng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Khách hàng :</td>
                                    <td><?php echo $get['fullname'] ?></td>
                                </tr>
                                <tr>
                                    <td>Số điện thoại :</td>
                                    <td><?php echo $get['phone'] ?></td>
                                </tr>
                                <tr>
                                    <td>Địa chỉ thanh toán :</td>
                                    <td><?php echo $get['address'] ?>, <?php echo $this->Mdistrict->district_name($get['district'] )?>, <?php echo $this->Mprovince->province_name($get['province'] )?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                        
                    </div>
                </div>

                
                <div class="btn-tks clearfix">
                    
                    <button type="button" onclick="window.location.href='<?php echo base_url() ?>trang-chu'" class="btn-next-checkout pull-left">Tiếp tục mua hàng</button>
                    <button type="button" onclick="window.print()" class="btn-update-order pull-left">In</button>
                </div>
                
        </div>
    </div>
</div>
</section>