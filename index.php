<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TataPlay</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body, html {
      height: 100%;
      width: 100%;
      font-family: Arial, sans-serif;
      background-color: #141414;
      color: #fff;
    }

    #splash {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: black;
      z-index: 1000;
    }

    #splash video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .container {
      display: none;
      background-color: #1c1c1c;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.8);
      width: 320px;
      margin: 80px auto 0;
      text-align: center;
    }

    input {
      width: 100%;
      padding: 0.75rem;
      margin: 0.5rem 0;
      border: none;
      border-radius: 6px;
      background-color: #333;
      color: #fff;
      font-size: 1rem;
    }

    input:disabled {
      background-color: #555;
    }

    button {
      width: 100%;
      padding: 0.75rem;
      margin-top: 1rem;
      background-color: #e50914;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
    }

    button:hover {
      background-color: #f40612;
    }

    button:disabled {
      background-color: #555;
      cursor: not-allowed;
      opacity: 0.7;
    }

    .hidden {
      display: none;
    }

    .spinner {
      margin-top: 10px;
      display: none;
    }

    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      background-color: #222;
      color: #fff;
      padding: 12px 20px;
      border-radius: 6px;
      font-size: 0.9rem;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
      z-index: 999;
    }

    .toast.show {
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>
<body>

  <div id="splash">
    <video autoplay muted playsinline>
      <source src="https://watch.tataplay.com/images/splash.mp4" type="video/mp4">
    </video>
  </div>

  <div class="container" id="app">
    <h2 id="pageTitle">Login with OTP</h2>

    <div id="loginUI">
      <input type="text" id="mobile" placeholder="Enter Mobile Number" maxlength="10">
      <button id="sendOtpBtn">Send OTP</button>

      <div id="otpSection" class="hidden">
        <input type="text" id="otp" placeholder="Enter OTP" maxlength="4">
        <button id="verifyOtpBtn">Verify OTP</button>
      </div>
    </div>

    <div class="spinner" id="spinner">? Loading...</div>

    <div id="postLoginActions" class="hidden">
      <div style="margin-top: 1rem;">
        <a href="playlist.php" download>
          <button style="background-color:#444;">Download Playlist</button>
        </a>
        <input type="text"
               value=""
               id="playlistUrl"
               readonly
               onclick="this.select()"
               style="margin-top: 0.5rem; padding: 0.6rem; background:#333; color:#fff; border: 1px solid #555; border-radius: 6px; width: 100%; text-align: center; font-size: 0.9rem;">
      </div>
      <button id="logoutBtn" style="background-color:#666;">Logout</button>
    </div>
  </div>

  <div id="toast" class="toast"></div>

  <script>
    const mobileInput = document.getElementById('mobile');
    const otpInput = document.getElementById('otp');
    const otpSection = document.getElementById('otpSection');
    const postLoginActions = document.getElementById('postLoginActions');
    const spinner = document.getElementById('spinner');
    const sendBtn = document.getElementById('sendOtpBtn');
    const loginUI = document.getElementById('loginUI');
    const pageTitle = document.getElementById('pageTitle');
    const toast = document.getElementById('toast');
    const playlistInput = document.getElementById('playlistUrl');
    const app = document.getElementById('app');

    const showSpinner = () => spinner.style.display = 'block';
    const hideSpinner = () => spinner.style.display = 'none';

    function showToast(message) {
      toast.innerText = message;
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }

    function getPlaylistUrl() {
      return window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/') + 'playlist.php';
    }

    window.addEventListener('DOMContentLoaded', () => {
      const splash = document.getElementById('splash');
      const video = splash.querySelector('video');
      video.addEventListener('ended', async () => {
        splash.remove();
        app.style.display = 'block';

        const res = await fetch('app/check_login.php');
        const { exists } = await res.json();
        if (exists) {
          loginUI.classList.add('hidden');
          postLoginActions.classList.remove('hidden');
          pageTitle.innerText = "You are already logged in.";
          playlistInput.value = getPlaylistUrl();
        }
      });
    });

    sendBtn.addEventListener('click', async () => {
      const mobile = mobileInput.value.trim();
      if (!/^\d{10}$/.test(mobile)) return alert("Enter valid 10-digit mobile");
      showSpinner();
      const res = await fetch('app/send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `mobile=${mobile}`
      });
      const text = await res.text();
      hideSpinner();
      showToast(text);
      if (text.includes("OTP generated successfully")) {
        otpSection.classList.remove('hidden');
        mobileInput.disabled = true;
        otpInput.focus();
        let countdown = 60;
        sendBtn.disabled = true;
        const originalText = sendBtn.innerText;
        sendBtn.innerText = `Resend in ${countdown}s`;
        window.otpTimerInterval = setInterval(() => {
          countdown--;
          if (countdown > 0) {
            sendBtn.innerText = `Resend in ${countdown}s`;
          } else {
            clearInterval(window.otpTimerInterval);
            window.otpTimerInterval = null;
            sendBtn.disabled = false;
            sendBtn.innerText = originalText;
          }
        }, 1000);
      }
    });

    document.getElementById('verifyOtpBtn').addEventListener('click', async () => {
      const otp = otpInput.value.trim();
      const mobile = mobileInput.value.trim();
      if (!otp) return alert("Enter OTP");
      showSpinner();
      const res = await fetch('app/verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `mobile=${mobile}&otp=${otp}`
      });
      const text = await res.text();
      hideSpinner();
      showToast(text);
      if (text.includes("Logged in successfully")) {
        pageTitle.innerText = "You are already logged in.";
        loginUI.classList.add('hidden');
        postLoginActions.classList.remove('hidden');
        playlistInput.value = getPlaylistUrl();
        if (window.otpTimerInterval) {
          clearInterval(window.otpTimerInterval);
          window.otpTimerInterval = null;
        }
      }
    });

    otpInput.addEventListener('input', () => {
      if (otpInput.value.trim().length === 4) {
        document.getElementById('verifyOtpBtn').click();
      }
    });

    mobileInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !sendBtn.disabled) {
        sendBtn.click();
      }
    });

    otpInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('verifyOtpBtn').click();
      }
    });

    document.getElementById('logoutBtn').addEventListener('click', async () => {
      showSpinner();
      const res = await fetch('app/logout.php', { method: 'POST' });
      const text = await res.text();
      hideSpinner();
      showToast(text);
      setTimeout(() => location.reload(), 1000);
    });
  </script>
</body>
</html>
