<?php
session_start();
if (empty($_SESSION['tp_updater_nonce'])) { $_SESSION['tp_updater_nonce'] = bin2hex(random_bytes(32)); }
$tpUpdaterNonce = $_SESSION['tp_updater_nonce'];
?>
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

  <div class="container" id="app">
    <h2 id="pageTitle">Login with OTP</h2>

    <div id="loginUI">
      <input type="text" id="mobile" placeholder="Enter Mobile Number" maxlength="10">
      <label style="display:flex;align-items:flex-start;gap:9px;text-align:left;font-size:0.85rem;line-height:1.4;color:#ddd;margin:0.75rem 0;">
        <input type="checkbox" id="autoUpdateConsent" style="width:auto;margin:2px 0 0;flex:0 0 auto;">
        <span>Allow automatic updates from the official GitHub repository. Updates are checked when an MPD is fetched (at most once per UTC hour). Local modifications and personal data are preserved. You can change this later.</span>
      </label>
      <button id="sendOtpBtn">Send OTP</button>

      <div id="otpSection" class="hidden">
        <input type="text" id="otp" placeholder="Enter OTP" maxlength="4">
        <button id="verifyOtpBtn">Verify OTP</button>
      </div>
    </div>

    <div class="spinner" id="spinner">
      Loading...
    </div>

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

  <div class="container" id="updaterPanel" style="display:block;margin-top:18px;text-align:left;">
    <h3 style="text-align:center;margin-bottom:8px;">GitHub updater</h3>
    <p style="font-size:0.82rem;color:#bbb;line-height:1.45;">You can preview and manually update this installation without logging in. Automatic hourly MPD-triggered updates still require login and explicit consent.</p>
      <div style="margin-top:1rem;text-align:left;font-size:0.85rem;color:#ddd;">
        <div id="autoUpdateControls" class="hidden">
          <label style="display:flex;align-items:flex-start;gap:9px;line-height:1.4;">
            <input type="checkbox" id="manageAutoUpdate" style="width:auto;margin:2px 0 0;flex:0 0 auto;">
            <span>Allow automatic updates from the official GitHub repository (checked during MPD fetching, at most once per UTC hour).</span>
          </label>
        </div>
        <div id="lastUpdateInfo" style="margin-top:10px;padding:10px;background:#292929;border-radius:6px;font-size:0.8rem;line-height:1.55;">
          <strong>Last successful update</strong><br>
          <span id="lastUpdateAt">Not recorded yet</span><br>
          <span id="lastUpdateSha" style="color:#aaa;"></span>
          <div id="lastCheckedAt" style="color:#aaa;margin-top:4px;"></div>
        </div>
        <div id="updateStatus" style="margin-top:8px;color:#ddd;font-size:0.85rem;"></div>
        <button id="previewUpdatesBtn" type="button" style="margin-top:0.6rem;background-color:#315b8a;">Preview changes</button>
        <div id="updatePreview" style="display:none;margin-top:10px;padding:10px;background:#292929;border-radius:6px;text-align:left;font-size:0.8rem;line-height:1.5;"></div>
        <button id="applyUpdatesBtn" type="button" style="margin-top:0.6rem;background-color:#287a45;" disabled>Apply safe updates</button>
        <button id="forceUpdatesBtn" type="button" style="margin-top:0.6rem;background-color:#a05a19;">Force update (overwrite local changes)</button>
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
    const updaterNonce = <?php echo json_encode($tpUpdaterNonce); ?>;

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
    
    window.addEventListener('DOMContentLoaded', async () => {
      app.style.display = 'block';
      try {
        const res = await fetch('app/check_login.php');
        const loginInfo = await res.json();
        document.getElementById('lastUpdateAt').innerText = loginInfo.last_update_at || 'Not recorded yet';
        document.getElementById('lastUpdateSha').innerText = loginInfo.last_update_sha ? ('Commit: ' + loginInfo.last_update_sha.substring(0, 12)) : '';
        document.getElementById('lastCheckedAt').innerText = loginInfo.last_checked_at ? ('Last GitHub check (UTC): ' + loginInfo.last_checked_at) : 'No GitHub check recorded yet';
        document.getElementById('updateStatus').innerText = loginInfo.update_status || 'Manual updates are available without login.';
        if (loginInfo.exists) {
          loginUI.classList.add('hidden');
          postLoginActions.classList.remove('hidden');
          document.getElementById('autoUpdateControls').classList.remove('hidden');
          pageTitle.innerText = 'You are already logged in.';
          playlistInput.value = getPlaylistUrl();
          document.getElementById('manageAutoUpdate').checked = !!loginInfo.auto_update;
        }
      } catch (e) {
        console.error(e);
      }
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
        body: new URLSearchParams({ mobile, otp, auto_update: document.getElementById('autoUpdateConsent').checked ? '1' : '0' })
      });
      const text = await res.text();
      hideSpinner();
      showToast(text);
      if (text.includes("Logged in successfully")) {
        pageTitle.innerText = "You are already logged in.";
        loginUI.classList.add('hidden');
        postLoginActions.classList.remove('hidden');
        playlistInput.value = getPlaylistUrl();
        document.getElementById('autoUpdateControls').classList.remove('hidden');
        document.getElementById('manageAutoUpdate').checked = document.getElementById('autoUpdateConsent').checked;
        document.getElementById('updateStatus').innerText = document.getElementById('manageAutoUpdate').checked
          ? 'Automatic updates enabled. GitHub is checked during MPD fetching, at most once per UTC hour.'
          : 'Automatic updates are disabled. Manual preview and updates remain available.';
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

    document.getElementById('manageAutoUpdate').addEventListener('change', async (event) => {
      const toggle = event.target;
      toggle.disabled = true;
      try {
        const res = await fetch('app/set_auto_update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ enabled: toggle.checked ? '1' : '0' })
        });
        const result = await res.json();
        showToast(result.message || 'Could not save preference.');
        if (!result.success) toggle.checked = !toggle.checked;
        else if (!toggle.checked) document.getElementById('updateStatus').innerText = 'Automatic updates are disabled.';
        else document.getElementById('updateStatus').innerText = 'Automatic updates enabled. They will be checked during MPD fetching, at most once per UTC hour.';
      } catch (e) {
        toggle.checked = !toggle.checked;
        showToast('Could not save update preference.');
      }
      toggle.disabled = false;
    });

    async function updaterRequest(action) {
      const status = document.getElementById('updateStatus');
      status.innerText = action === 'preview' ? 'Fetching GitHub changes...' : 'Applying update...';
      const response = await fetch('app/run_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action, nonce: updaterNonce })
      });
      const result = await response.json();
      status.innerText = result.message || 'Updater finished.';
      if (action === 'preview' && result.success) {
        const panel = document.getElementById('updatePreview');
        panel.style.display = 'block';
        panel.replaceChildren();
        const summary = document.createElement('div');
        summary.innerHTML = '<strong>GitHub commit:</strong> ';
        summary.appendChild(document.createTextNode((result.latest_sha || '').substring(0, 12)));
        panel.appendChild(summary);
        const files = result.files || [];
        const counts = result.counts || {};
        const countLine = document.createElement('div');
        countLine.style.color = '#bbb';
        countLine.textContent = 'Changed/locally different files: ' + files.length +
          ' | upstream changes: ' + (counts.upstream_changed || 0) +
          ' | local modifications: ' + (counts.local_modified || 0) +
          ' | new files: ' + (counts.new_file || 0);
        panel.appendChild(countLine);
        if (!files.length) {
          const none = document.createElement('p'); none.textContent = 'No file changes found.'; panel.appendChild(none);
        } else {
          const list = document.createElement('ul'); list.style.margin = '8px 0 0 18px';
          files.forEach(file => {
            const li = document.createElement('li');
            li.textContent = file.path + ' — ' + file.status.replace(/_/g, ' ');
            list.appendChild(li);
          });
          panel.appendChild(list);
        }
        document.getElementById('applyUpdatesBtn').disabled = !files.some(f => f.status === 'upstream_changed' || f.status === 'new_file');
      }
      if (action === 'apply' || action === 'force') {
        document.getElementById('lastUpdateAt').innerText = result.last_update_at || 'No successful update recorded';
        document.getElementById('lastUpdateSha').innerText = result.last_update_sha ? ('Commit: ' + result.last_update_sha.substring(0, 12)) : '';
        document.getElementById('lastCheckedAt').innerText = result.last_checked_at ? ('Last GitHub check (UTC): ' + result.last_checked_at) : '';
        if (result.success) document.getElementById('applyUpdatesBtn').disabled = true;
      }
      showToast(result.message || 'Updater finished.');
      return result;
    }

    document.getElementById('previewUpdatesBtn').addEventListener('click', async () => {
      const button = document.getElementById('previewUpdatesBtn');
      button.disabled = true; button.innerText = 'Loading preview...';
      try { await updaterRequest('preview'); }
      catch (e) { document.getElementById('updateStatus').innerText = 'Preview failed. Check PHP error logs.'; }
      finally { button.disabled = false; button.innerText = 'Preview changes'; }
    });

    document.getElementById('applyUpdatesBtn').addEventListener('click', async () => {
      if (!confirm('Apply safe updates? Locally modified files will be skipped. Existing files will be backed up.')) return;
      const button = document.getElementById('applyUpdatesBtn'); button.disabled = true; button.innerText = 'Updating...';
      try { await updaterRequest('apply'); }
      catch (e) { document.getElementById('updateStatus').innerText = 'Update failed. Check PHP error logs.'; }
      finally { button.disabled = false; button.innerText = 'Apply safe updates'; }
    });

    document.getElementById('forceUpdatesBtn').addEventListener('click', async () => {
      const ok = confirm('FORCE UPDATE: This overwrites locally modified files in get-mpd.php, index.php, playlist.php and managed app files. Backups are created first. Continue?');
      if (!ok) return;
      const button = document.getElementById('forceUpdatesBtn'); button.disabled = true; button.innerText = 'Force updating...';
      try { await updaterRequest('force'); }
      catch (e) { document.getElementById('updateStatus').innerText = 'Force update failed. Check PHP error logs.'; }
      finally { button.disabled = false; button.innerText = 'Force update (overwrite local changes)'; }
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
