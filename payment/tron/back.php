<?php
$rootPath = $_SERVER['DOCUMENT_ROOT'];
$Pathfile = dirname(dirname($_SERVER['PHP_SELF'], 2));
$Pathfiles = $rootPath.$Pathfile;
$Pathfile = $Pathfiles.'/config.php';
$jdf = $Pathfiles.'/jdf.php';
$botapi = $Pathfiles.'/botapi.php';
require_once $Pathfile;
require_once $jdf;
require_once $botapi;

$data = json_decode(file_get_contents('php://input'), true);

$PaySetting = getPaymentSetting($connect, 'marchent_tronseller');
$Payment_report = getPaymentReport($connect, $data['PaymentID']);

if ($data['IsPaid']) {
    processPaidPayment($connect, $Payment_report, $PaySetting);
} else {
    processUnpaidPayment($connect, $data, $PaySetting);
}

function getPaymentSetting($connect, $namePay) {
    return mysqli_fetch_assoc(mysqli_query($connect, "SELECT (ValuePay) FROM PaySetting WHERE NamePay = '$namePay'"))['ValuePay'];
}

function getPaymentReport($connect, $PaymentID) {
    return mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM Payment_report WHERE id_order = '$PaymentID' LIMIT 1"));
}

function processPaidPayment($connect, $Payment_report, $PaySetting) {
    if($Payment_report['payment_Status'] != "paid"){
        $Balance_id = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM user WHERE id = '{$Payment_report['id_user']}' LIMIT 1"));
        updateUserBalance($connect, $Balance_id, $Payment_report['price']);
        updatePaymentStatus($connect, $Payment_report, 'paid');
        sendPaymentConfirmationMessage($connect, $Payment_report, $Payment_report['price'], $Balance_id);
    }
}

function updateUserBalance($connect, $Balance_id, $price) {
    $stmt = $connect->prepare("UPDATE user SET Balance = ? WHERE id = ?");
    $Balance_confrim = intval($Balance_id['Balance']) + $price;
    $stmt->bind_param("ss", $Balance_confrim, $Balance_id['id']);
    $stmt->execute();
}

function updatePaymentStatus($connect, $Payment_report, $status) {
    $stmt = $connect->prepare("UPDATE Payment_report SET payment_Status = ? WHERE id_order = ?");
    $stmt->bind_param("ss", $status, $Payment_report['id_order']);
    $stmt->execute();
}

function sendPaymentConfirmationMessage($connect, $Payment_report, $price, $Balance_id) {
    $setting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM setting"));
    $text_report = "💵 پرداخت جدید
        
آیدی عددی کاربر : $Balance_id
مبلغ تراکنش $price
روش پرداخت :  درگاه ترون";

    sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ $price تومان به کیف پول شما واریز گردید با تشکر از پرداخت شما.
    
    🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');

    if (strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
}

function processUnpaidPayment($connect, $data, $setting) {
    if (strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $data, null, 'HTML');
    }
}
?>
<html>
<head>
    <title>فاکتور پرداخت</title>
    <style>
    @font-face {
    font-family: 'vazir';
    src: url('/Vazir.eot');
    src: local('☺'), url('../fonts/Vazir.woff') format('woff'), url('../fonts/Vazir.ttf') format('truetype');
}

        body {
            font-family:vazir;
            background-color: #f2f2f2;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .confirmation-box {
            background-color: #ffffff;
            border-radius: 8px;
            width:25%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

        p {
            color: #666666;
            margin-bottom: 10px;
        }
        .btn{
            display:block;
            margin : 10px 0;
            padding:10px 20px;
            background-color:#49b200;
            color:#fff;
            text-decoration :none;
            border-radius:10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <h1><?php echo $data['IsPaid'] ?></h1>
        <p>شماره تراکنش:<span><?php echo $PaymentID ?></span></p>
        <p>مبلغ پرداختی:  <span><?php echo  $Payment_report['price']; ?></span>تومان</p>
        <p>تاریخ: <span>  <?php echo jdate('Y/m/d')  ?>  </span></p>
        <p><?php echo $data ?></p>
        <a class = "btn" href = "https://t.me/<?php echo $usernamebot ?>">بازگشت به ربات</a>
    </div>
</body>
</html>