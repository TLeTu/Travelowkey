window.addEventListener('pageshow', () => {
    // const userId = getCookie("userId");
    // if (!userId) {
    //     window.location.href = '../login/';
    // } 
    // Use the userLoggedIn cookie to determine if the user is logged in
    const userLoggedIn = getCookie("userLoggedIn");
    if (userLoggedIn !== "true") {
        window.location.href = '../login/';
    }
});

function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for(let i = 0; i <ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
}