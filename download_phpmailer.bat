@echo off
setlocal

REM Create vendor directory if it doesn't exist
if not exist "vendor" mkdir vendor

REM Download PHPMailer
curl -L -o phpmailer.zip https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.1.zip

REM Extract PHPMailer
powershell -Command "Expand-Archive -Path phpmailer.zip -DestinationPath vendor -Force"

REM Rename the extracted folder
if exist "vendor\PHPMailer-6.8.1" (
    move /Y "vendor\PHPMailer-6.8.1" "vendor\phpmailer"
)

REM Clean up
del phpmailer.zip

echo PHPMailer has been downloaded and extracted to vendor/phpmailer/
pause
