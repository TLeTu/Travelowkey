window.addEventListener('pageshow', checkLoggedIn);

async function checkLoggedIn() {
  const userLoggedIn = getCookie('userLoggedIn');

  // Keep user on page when client auth flag is present.
  if (userLoggedIn === 'true') {
    return;
  }

  try {
    const response = await fetch('../../server/data-controller/check-user-info.php?action=check-user-info');
    const raw = await response.text();
    const data = raw ? JSON.parse(raw) : null;

    // Server-authenticated user via JWT cookie.
    if (response.ok && data && !data.error) {
      return;
    }

    window.location.href = '../login/';
  } catch (error) {
    window.location.href = '../login/';
    console.error('check-logged-in error:', error);
  }
}

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