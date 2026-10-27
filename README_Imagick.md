# Installing Imagick on Windows

> **Imagick is a hard requirement of this application, not an optional extra.**
> `composer.json` declares `ext-imagick`, so `composer install` fails its platform
> check on a machine without it rather than deferring to a boot-time fatal in an
> unrelated Artisan command (UAMS-Web/uams-statamic-archive#1830). Two independent things need it: the
> configured manipulation driver (`IMAGE_MANIPULATION_DRIVER=imagick`, constructed
> eagerly at boot) and `App\Support\Imaging\SvgRasterizer`, which backs the
> brand-logo SVG→PNG fallback — GD cannot rasterize SVG, so switching the driver
> would move the failure rather than remove it. CI provisions the extension via
> `setup-php`; on a developer machine, install it by hand with the guide below.

Installing or activating the Imagick PHP extension for Laravel Herd on Windows involves a few steps, as Herd uses precompiled PHP versions and doesn’t natively support adding extensions out of the box like some other environments (e.g., Laragon or XAMPP). However, you can manually add the Imagick extension by downloading the necessary files and configuring Herd’s PHP setup. Below is a step-by-step guide based on common practices for installing Imagick on Windows with PHP, tailored to Laravel Herd.

## Quick reference (this project: PHP 8.4 NTS x64)

- **Herd PHP directory**: `%USERPROFILE%\.config\herd\bin\php84` (e.g. `C:\Users\McCoyLH\.config\herd\bin\php84`).
- **php.ini**: `%USERPROFILE%\.config\herd\bin\php84\php.ini`.
- **DLL to download**: Imagick for **PHP 8.4**, **NTS** (Non Thread Safe), **x64** — e.g. from [PECL imagick 3.8.0 Windows](https://pecl.php.net/package/imagick/3.8.0/windows) (`php_imagick-3.8.0-8.4-nts-vs17-x64.zip` or equivalent).
- **Steps**: Install ImageMagick and add it to PATH → download the NTS x64 Imagick ZIP → put `php_imagick.dll` in `php84\ext` and any other DLLs in `php84` → add `extension=php_imagick.dll` to `php84\php.ini` → restart Herd → run `herd php -i | findstr imagick` to verify.

## Prerequisites

1. **Laravel Herd Installed**: Ensure Herd is installed and running on your Windows machine.
2. **PHP Version**: Identify the PHP version Herd is using for your project (e.g., PHP 8.1, 8.2, etc.). You can check this in the Herd UI or by running `php -v` from the Herd CLI.
3. **ImageMagick Installed**: The Imagick PHP extension requires the ImageMagick library to be installed on your system.

## Step-by-Step Instructions

### 1. Install ImageMagick

- Download the ImageMagick binary for Windows from the official website: [ImageMagick Downloads](https://imagemagick.org/script/download.php#windows).
- Choose a version compatible with your system (e.g., `ImageMagick-<version>-Q16-x64-dll.exe` for 64-bit Windows).
- Run the installer and follow the prompts. Ensure you check the option to "Install legacy utilities" if available, and note the installation path (e.g., `C:\Program Files\ImageMagick-<version>`).
- After installation, add the ImageMagick folder to your system’s PATH environment variable:
  - Open the Start menu, search for "Environment Variables," and select "Edit the system environment variables."
  - In the "System Properties" window, click "Environment Variables."
  - Under "System variables," find and edit the `Path` variable, adding the ImageMagick installation path (e.g., `C:\Program Files\ImageMagick-7.1.1-Q16-HDRI`).
  - Click OK to save changes.

### 2. Determine PHP Details

- Herd stores its PHP binaries in `%USERPROFILE%\.config\herd\bin\php<XX>` (e.g. `php84` for PHP 8.4).
- Run the following from any directory to get PHP details for the active Herd PHP:
  ```
  herd php -i
  ```
- Look for:
  - **PHP Version** (e.g., 8.1.x).
  - **Thread Safety** (enabled for TS, disabled for NTS).
  - **Architecture** (x64 for 64-bit, x86 for 32-bit).

### 3. Download the Imagick DLL

- Visit the PECL website: [Imagick DLL Downloads](https://pecl.php.net/package/imagick) (or the [3.8.0 Windows](https://pecl.php.net/package/imagick/3.8.0/windows) page for PHP 8.4).
- Download the Imagick extension matching your PHP version, thread safety, and architecture (e.g. `php_imagick-3.8.0-8.4-nts-vs17-x64.zip` for PHP 8.4, NTS, 64-bit). Herd’s PHP is typically **NTS**; check with `herd php -i | findstr "Thread Safety"`.
- Extract the ZIP file.

### 4. Copy Files to Herd’s PHP Directory

- From the extracted ZIP:
  - Copy `php_imagick.dll` to the `ext` folder of Herd’s PHP installation (e.g., `C:\Users\<YourUsername>\.config\herd\bin\php81\ext`).
  - Copy any additional DLLs (e.g., those starting with `CORE_RL_`, `IM_MOD_RL_`, etc.) to the root PHP directory (e.g., `C:\Users\<YourUsername>\.config\herd\bin\php81`).
- Unblock the copied files (e.g. after downloading, Windows may block DLLs). In PowerShell, from any directory:
  ```powershell
  Get-ChildItem $env:USERPROFILE\.config\herd\bin\php84 -Recurse | Unblock-File
  ```
  Use your PHP version folder in place of `php84` if different.

### 5. Update php.ini

- Locate the `php.ini` file for your PHP version in Herd. On Windows it is typically in the PHP bin folder:
  - `C:\Users\<YourUsername>\.config\herd\bin\php<XX>\php.ini` (e.g. `php84\php.ini` for PHP 8.4). Confirm with `herd php --ini` (Loaded Configuration File).
- Open `php.ini` in a text editor.
- Find the section where extensions are defined (look for lines like `extension=...`) and add:
  ```
  extension=php_imagick.dll
  ```
- Save the file.

### 6. Restart Herd

- In the Herd UI, stop and restart PHP:
  - Click the Herd icon in the system tray.
  - Select "Stop All," then "Start All."
- Alternatively, restart Herd via the terminal:
  ```
  herd stop
  herd start
  ```

### 7. Verify Installation

- From your project directory (or any directory where Herd uses the correct PHP version), run:
  ```
  herd php -i | findstr "imagick"
  ```
- You should see output like:
  ```
  imagick module => enabled
  imagick module version => 3.8.0
  ```
- If nothing appears, double-check the DLL compatibility, file placement, and `php.ini` configuration.

## Troubleshooting

- **"Unable to load dynamic library" Error**:
  - Ensure the ImageMagick path is in your system PATH.
  - Verify the `php_imagick.dll` matches your PHP version, thread safety, and architecture.
  - Check that all required DLLs are in the PHP root directory.
- **Herd-Specific Issues**:
  - Herd’s precompiled PHP may not always play nicely with manually added extensions. If issues persist, consider switching to an alternative like Laragon or XAMPP, which offer easier extension management.
- **No Output in php -i**:
  - Confirm `php.ini` is being loaded by running `php --ini` and checking the "Loaded Configuration File" path.

## Notes

- Laravel Herd’s documentation (as of March 13, 2025) doesn’t officially support adding extensions like Imagick on Windows due to its focus on precompiled PHP binaries. This method is a workaround based on standard Windows PHP practices.
- If your project uses a library like Intervention Image, ensure it’s configured to use Imagick (e.g., set `'driver' => 'imagick'` in `config/image.php`).
