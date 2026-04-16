let HotelSearchInfo;

if (sessionStorage.getItem("HotelSearchInfo")) {
    HotelSearchInfo = JSON.parse(sessionStorage.getItem("HotelSearchInfo"))
}

let hotelPaymentInfo;

if (sessionStorage.getItem("hotelPaymentInfo")) {
    hotelPaymentInfo = JSON.parse(sessionStorage.getItem("hotelPaymentInfo"))
    console.log(hotelPaymentInfo);
}

window.addEventListener('load', () => {
    document.getElementById("txt-startDate").innerText = changeDateFormat(HotelSearchInfo.checkinDate);
    document.getElementById("txt-endDate").innerText = changeDateFormat(HotelSearchInfo.checkoutDate);
    document.getElementById("txt-guestNum").innerText = hotelPaymentInfo.guestNum;
    document.getElementById("txt-name").innerText = hotelPaymentInfo.hotelName;
    document.getElementById("txt-address").innerText = hotelPaymentInfo.hotelAddress;
    document.getElementById("txt-price").innerText = changeMoneyFormat(hotelPaymentInfo.price) + ' VND';
    document.getElementById("txt-totalPrice").innerText = changeMoneyFormat(calculateTotalPrice()) + ' VND';

    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            let roomInfo = JSON.parse(this.responseText);
            document.getElementById("txt-roomName").innerHTML = roomInfo.Name;
        }
    };

    xhttp.open("GET", "../../server/data-controller/payment-hotel/get-data.php?action=load&roomID=" + hotelPaymentInfo.ID, true);
    xhttp.send();
});

const btnPayment = document.querySelector(".btn-payment");


btnPayment.addEventListener("click", async function () {
    try {
        const initResponse = await fetch("../../server/data-controller/payment-hotel/post-data.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `action=payment&roomID=${encodeURIComponent(hotelPaymentInfo.ID)}&checkIn=${encodeURIComponent(HotelSearchInfo.checkinDate)}&checkOut=${encodeURIComponent(HotelSearchInfo.checkoutDate)}&totalPrice=${encodeURIComponent(calculateTotalPrice())}`
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
                service_type: "hotel"
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
});

function calculateTotalPrice() {
    let startDate = new Date(HotelSearchInfo.checkinDate);
    let endDate = new Date(HotelSearchInfo.checkoutDate);
    let totalDate = (endDate - startDate) / (1000 * 60 * 60 * 24);
    let totalprice;
    if (totalDate == 0) {
        totalprice = hotelPaymentInfo.price;
    }
    else {
        totalprice = totalDate * hotelPaymentInfo.price;
    }

    return totalprice;
}

function changeMoneyFormat(money) {
    return money.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
function changeDateFormat(date) {
    let dateArray = date.split('-');
    let day = dateArray[2];
    let month = dateArray[1];
    let year = dateArray[0];
    let monthArray = ['tháng 1', 'tháng 2', 'tháng 3', 'tháng 4',
        'tháng 5', 'tháng 6', 'tháng 7', 'tháng 8',
        'tháng 9', 'tháng 10', 'tháng 11', 'tháng 12'];
    return `${day}, ${monthArray[month - 1]}, ${year}`;
}