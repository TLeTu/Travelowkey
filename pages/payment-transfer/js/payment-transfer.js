let transferSearchInfo;

if (sessionStorage.getItem("transferSearchInfo")) {
    transferSearchInfo = JSON.parse(sessionStorage.getItem("transferSearchInfo"))

    // console.log(transferSearchInfo);
}

let transferPaymentInfo;

if (sessionStorage.getItem("transferPaymentInfo")) {
    transferPaymentInfo = JSON.parse(sessionStorage.getItem("transferPaymentInfo"))

    console.log(transferPaymentInfo);
}

window.addEventListener("load", () => {
    document.getElementById("txt-brand").innerHTML = transferPaymentInfo.name;
    document.getElementById("txt-location").innerHTML = transferSearchInfo.location;
    document.getElementById("txt-startDate").innerHTML = changeDateFormat(transferSearchInfo.startDate);
    document.getElementById("txt-startTime").innerHTML = transferSearchInfo.startTime;
    document.getElementById("txt-endDate").innerHTML = changeDateFormat(transferSearchInfo.endDate);
    document.getElementById("txt-endTime").innerHTML = transferSearchInfo.endTime;
    if (transferSearchInfo.haveDriver) {
        document.getElementById("txt-taxiType").innerHTML = "Có tài xế";
    }
    else {
        document.getElementById("txt-taxiType").innerHTML = "Tự lái";
    }
    document.getElementById("txt-price").innerHTML = changeMoneyFormat(transferPaymentInfo.price) + ' VND /ngày';
    document.getElementById("txt-totalPrice").innerHTML =
        changeMoneyFormat(
            calculateTotalDate(
                transferSearchInfo.startDate, transferSearchInfo.endDate, transferSearchInfo.startTime, transferSearchInfo.endTime)
            * transferPaymentInfo.price) + ' VND';

})

function changeDateFormat(date) {
    let dateArray = date.split('-');
    let day = dateArray[2];
    let month = dateArray[1];
    let year = dateArray[0];
    let monthArray = ['tháng 1', 'tháng 2', 'tháng 3', 'tháng 4',
        'tháng 5', 'tháng 6', 'tháng 7', 'tháng 8',
        'tháng 9', 'tháng 10', 'tháng 11', 'tháng 12'];
    return `${day} ${monthArray[month - 1]}, ${year}`;
}

function changeMoneyFormat(money) {
    return money.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function calculateTotalDate(startDate, endDate, startTime, endTime) {
    let totalDate = 0;
    let startDateArray = startDate.split('-');
    let endDateArray = endDate.split('-');
    let startTimeArray = startTime.split(':');
    let endTimeArray = endTime.split(':');
    let startDateObj = new Date(startDateArray[0], startDateArray[1], startDateArray[2], startTimeArray[0], startTimeArray[1]);
    let endDateObj = new Date(endDateArray[0], endDateArray[1], endDateArray[2], endTimeArray[0], endTimeArray[1]);
    let timeDiff = endDateObj.getTime() - startDateObj.getTime();
    totalDate = Math.ceil(timeDiff / (1000 * 3600 * 24));
    return totalDate;
}

const btnPayment = document.querySelector(".btn-payment");

let totalPrice = calculateTotalDate(
    transferSearchInfo.startDate, transferSearchInfo.endDate, transferSearchInfo.startTime, transferSearchInfo.endTime)
    * transferPaymentInfo.price;

btnPayment.addEventListener("click", async function () {
    try {
        const payload = "action=payment"
            + "&taxiID=" + encodeURIComponent(transferPaymentInfo.transferID)
            + "&startDate=" + encodeURIComponent(transferSearchInfo.startDate)
            + "&startTime=" + encodeURIComponent(transferSearchInfo.startTime)
            + "&endDate=" + encodeURIComponent(transferSearchInfo.endDate)
            + "&endTime=" + encodeURIComponent(transferSearchInfo.endTime)
            + "&totalPrice=" + encodeURIComponent(totalPrice);

        const initResponse = await fetch("../../server/data-controller/payment-transfer/post-data.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: payload
        });

        const initData = await initResponse.json();
        if (!initData.success) {
            alert("Khởi tạo thanh toán thất bại!");
            return;
        }

        const zaloResponse = await fetch("../../server/data-controller/zalopay/create-zalopay.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                amount: initData.amount,
                invoice_id: initData.invoice_id,
                service_type: "transfer"
            })
        });

        const zaloData = await zaloResponse.json();
        if (zaloData.success) {
            window.location.href = zaloData.payment_url;
            return;
        }

        alert("Không thể kết nối ZaloPay!");
    } catch (error) {
        console.error("Payment error:", error);
        alert("Đã có lỗi xảy ra khi thanh toán!");
    }
})