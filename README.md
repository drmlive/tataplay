# 📺 Self-Host TataPlay on Android or PC (KSWEB / XAMPP)

This guide helps you run the [tataplay](https://github.com/drmlive/tataplay) project on your **Android** device using **KSWEB**, or on your **PC** using **XAMPP** (a cross-platform PHP development environment).

---

## 🎯 HOW TO USE

### 1️⃣ Download PHP Web Server

- **For Mobiles:** [KSWEB PRO v3.987](https://tsneh.vercel.app/ksweb_3.987.apk)
- **For PC (Windows):** [XAMPP](https://www.apachefriends.org/download.html)

### 2️⃣ Download and Extract Script

- **Download:** [Script Zip](https://github.com/drmlive/tataplay/archive/refs/heads/main.zip)

1. Extract all files into the `htdocs` under `tataplay` folder in file manager (path may vary for XAMPP).
    ```bash
    📂FileManager/
    └── 📂htdocs/
      └── 📂tataplay/
          ├── 📄index.php
          ├── 📄playlist.php
          ├── 📄get-mpd.php
          ├── 📂app/
          │   ├── 📄send_otp.php
          │   ├── 📄verify_otp.php
          │   ├── 📄functions.php
          │   ├── 📄check_login.php
          │   └── 📄logout.php
          └── ......
    ```
2. Open KSWEB app (or XAMPP for PC) and start the **APACHE** server.

3. The setup is complete, and the script is ready to use.

## How to Login:

- Open the Login page<br>(port `80` for XAMPP): [http://localhost:8000/tataplay](http://localhost:8000/tataplay)
- Login with ANY indian mobile number and enter the received OTP.

## Supported Players

<div style="font-size: 1.5em; color: #0000ff;">
    This playlist is currently only supported by OTT Navigator, TiviMate (latest version), Sparkle, and Ultimate IPTV Loader.
</div>

## Join Our Telegram

Stay updated and join our community on Telegram by clicking the button below.

[![Join Telegram](https://img.shields.io/badge/Join-Telegram-blue?logo=telegram)](https://t.me/+rQTz5VL8CRpjNTZl)
