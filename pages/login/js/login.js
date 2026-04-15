document.querySelector(".btn-login").addEventListener("click", async (e) => {
  e.preventDefault();

  let emailOrPhone = document.querySelector('input[type="text"]').value;
  let password = document.querySelector('input[type="password"]').value;

  try {
    let response = await fetch("login.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ emailOrPhone, password }),
    });

    const raw = await response.text();
    let result;

    try {
      result = raw ? JSON.parse(raw) : null;
    } catch (parseError) {
      throw new Error(`Invalid server response (${response.status})`);
    }

    if (!result || typeof result.success === "undefined") {
      throw new Error(`Unexpected server response (${response.status})`);
    }

    if (result.success) {
      // setCookie("userId", result.userId, 1);
      setCookie("userLoggedIn", true, 1);
      CheckUserInfo();
    } else {
      alert(result.error || "Invalid email/phone or password");
    }
  } catch (error) {
    console.error("Error:", error);
    alert("Login failed due to a server error. Please try again.");
  }
});

async function CheckUserInfo() {
  try {
    // We only send the action parameter. The browser automatically attaches the JWT cookie!
    let response = await fetch("../../server/data-controller/check-user-info.php?action=check-user-info");
    let data = await response.json();

    // If the server sends back an error (like token expired), stop here.
    if (data.error) {
      console.error("Authorization failed:", data.error);
      return;
    }

    let userInfo = data[0];

    // Assume profile is complete to start
    setCookie("userAuth", true, 1);
    
    // Check every field in the database row for a null value
    for (let key in userInfo) {
      if (userInfo[key] == null) {
        // Redirect to info page because a field is missing
        setCookie("userAuth", false, 1);
        break;
      }
    }

    // Redirect the user based on the cookie we just set
    if (getCookie("userAuth") == "true") {
      window.location.href = "../main/";
    } else {
      window.location.href = "../account/";
    }
  } catch (error) {
    console.error("Error fetching user info:", error);
  }
}

function setCookie(cname, cvalue, exdays) {
  const d = new Date();
  d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
  let expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(";");
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == " ") {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}

const loginIcon = document.querySelector(".login-input-icon");
const passwordInput = document.getElementById("txt-password");

loginIcon.addEventListener("click", () => { 
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    loginIcon.name = "eye-off-outline";
  } else {
    passwordInput.type = "password";
    loginIcon.name = "eye-outline";
  }
});