let totalPrice;

let busPaymentInfo;

if (sessionStorage.getItem("busPaymentInfo")) {
    busPaymentInfo = JSON.parse(sessionStorage.getItem("busPaymentInfo"))

    console.log(busPaymentInfo);
}

window.onload = function () {
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            let busInfo = JSON.parse(this.responseText);
            document.getElementById("txt-from").innerHTML = busInfo.From;
            document.getElementById("txt-to").innerHTML = busInfo.To;
            document.getElementById("txt-departureDate").innerHTML = changeDateFormat(busInfo.Date);
            document.getElementById("txt-departureTime").innerHTML = busInfo.DepartureTime;
            document.getElementById("txt-brand").innerHTML = busInfo.Name;
            document.getElementById("txt-pickPoint").innerHTML = busInfo.PickPoint;
            document.getElementById("txt-desPoint").innerHTML = busInfo.DesPoint;
            document.getElementById("txt-busType").innerHTML = busInfo.SeatCount;
            document.getElementById("txt-price").innerHTML = changeMoneyFormat(busInfo.Price) + ' VND';
            document.getElementById("txt-ticket").innerHTML = 'x ' + busPaymentInfo.ticketNumber;
            document.getElementById("txt-totalPrice").innerHTML =
                changeMoneyFormat(busInfo.Price * busPaymentInfo.ticketNumber) + ' VND';
            document.getElementById("txt-travelTime").innerHTML = busInfo.TravelTime;
            document.getElementById("txt-arrivalTime").innerHTML = busInfo.ArrivalTime;
            totalPrice = busInfo.Price * busPaymentInfo.ticketNumber;
        };
    };
    xhttp.open("GET", "../../server/data-controller/payment-bus/get-data.php?action=load&ID="+busPaymentInfo.ID, true);
    xhttp.send();
}
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

const btnPayment = document.querySelector(".btn-payment");

btnPayment.addEventListener("click", async function () {
    try {
        const initResponse = await fetch("../../server/data-controller/payment-bus/post-data.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `action=payment&busID=${encodeURIComponent(busPaymentInfo.ID)}&ticketNumber=${encodeURIComponent(busPaymentInfo.ticketNumber)}&totalPrice=${encodeURIComponent(totalPrice)}`
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
                service_type: "bus"
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