# PHP GET Endpoint Project

A minimal PHP project with a GET endpoint that returns HTTP 200 status code.

## Prerequisites

- Windows with WSL (Windows Subsystem for Linux) installed
- PHP 8.0 or higher installed in WSL
- Composer installed in WSL
- PhpStorm IDE

## Installing PHP and Composer in WSL

### Installing PHP

1. **Update package list:**
   ```bash
   sudo apt update
   ```

2. **Install PHP and required extensions:**
   ```bash
   sudo apt install -y php php-cli php-curl php-mbstring php-xml php-json
   ```

   This installs:
   - `php` - Core PHP package
   - `php-cli` - Command-line interface
   - `php-curl` - cURL extension (for HTTP requests)
   - `php-mbstring` - Multibyte string functions
   - `php-xml` - XML parsing support
   - `php-json` - JSON support

3. **Verify PHP installation:**
   ```bash
   php -v
   ```
   You should see PHP version information (8.0 or higher recommended).

4. **Check installed extensions:**
   ```bash
   php -m
   ```

### Installing Composer

**Method 1: Direct Installation (Recommended)**

The simplest and most current method:

```bash
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

**Method 2: With Hash Verification (More Secure)**

If you want to verify the installer hash (optional):

```bash
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

**Verify Composer installation:**
```bash
composer --version
```
You should see Composer version information.

**Note:** The hash verification in Method 2 fetches the current hash from Composer's website, so it's always up-to-date and won't fail due to outdated hashes.


**However, the official installer method above is strongly recommended** to ensure you get the latest stable version with all security updates.

### Troubleshooting Installation

**If PHP installation fails:**
```bash
# Add PHP repository (for Ubuntu/Debian)
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-curl php8.2-mbstring php8.2-xml php8.2-json
```

**If Composer download fails:**
```bash
# Try using wget instead
wget https://getcomposer.org/installer -O composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

**If permission denied errors occur:**
```bash
# Ensure you have sudo privileges
sudo -v
```

## Setup Steps

### 1. Open WSL Terminal

In PhpStorm:
- Go to `File` → `Settings` → `Tools` → `Terminal`
- Set shell path to: `wsl.exe` or `C:\Windows\System32\wsl.exe`
- Or use PhpStorm's built-in terminal and select WSL from the terminal dropdown

Alternatively, open WSL directly:
- Press `Win + R`, type `wsl`, and press Enter
- Navigate to your project directory

### 2. Navigate to Project Directory

```bash
cd /mnt/d/dev/tests/php-maxim-ai-logging
```

Note: Windows drives are mounted under `/mnt/` in WSL (e.g., `D:\` becomes `/mnt/d/`)

### 3. Install Dependencies

```bash
composer install
```

This will install Slim framework and its dependencies.

### 4. Run the Development Server

```bash
php -S localhost:8000 -t public
```

The server will start and listen on `http://localhost:8000`

### 5. Test the Endpoint

Open a browser or use curl:

```bash
curl http://localhost:8000/query
```

Or from Windows PowerShell/CMD:
```powershell
curl http://localhost:8000/query
```

The endpoint should return HTTP 200 status code.

## PhpStorm Configuration

### Configure PHP Interpreter (WSL)

1. Go to `File` → `Settings` → `PHP`
2. Click the `...` next to CLI Interpreter
3. Click `+` to add a new interpreter
4. Select `From Docker, Vagrant, VM, WSL, Remote`
5. Choose `WSL` and select your WSL distribution
6. PhpStorm will auto-detect PHP in WSL

### Running from PhpStorm Terminal

1. Open PhpStorm terminal (View → Tool Windows → Terminal)
2. Select WSL from the terminal dropdown (if configured)
3. Navigate to project directory: `cd /mnt/d/dev/tests/php-maxim-ai-logging`
4. Run the development server: `php -S localhost:8000 -t public`
5. Keep the terminal running (the server runs in the foreground)
6. Test the endpoint in another terminal or browser: `curl http://localhost:8000/query`

**Important**: Do NOT run `php public/index.php` directly - Slim requires an HTTP server context. Always use the PHP development server.

## Project Structure

```
.
├── composer.json          # PHP dependencies
├── public/
│   └── index.php         # Application entry point
├── .htaccess             # Apache rewrite rules (for Apache servers)
└── README.md             # This file
```

## Endpoint Details

- **URL**: `http://localhost:8000/query`
- **Method**: GET
- **Response**: HTTP 200 status code

## Troubleshooting

### vendor/autoload.php Not Found Error

If you see an error like:
```
PHP Fatal error: Failed opening required 'vendor/autoload.php'
```

This means Composer dependencies haven't been installed yet. Run:

```bash
composer install
```

This will:
- Download all required packages (Slim framework, etc.)
- Create the `vendor/` directory
- Generate `vendor/autoload.php`

**Important**: You must run `composer install` before running the PHP application.

### "Not found" Error When Running PHP File Directly

If you see an error like:
```
Slim\Exception\HttpNotFoundException: Not found.
```

This happens when you try to run the PHP file directly (e.g., `php public/index.php` or running it from PhpStorm's run configuration). **Slim requires an HTTP server context** to handle requests and match routes.

**Solution**: Always run the application through the PHP development server:

```bash
php -S localhost:8000 -t public
```

Then access the endpoint via HTTP:
```bash
curl http://localhost:8000/query
```

**Running from PhpStorm:**
- Don't create a run configuration that executes `index.php` directly
- Instead, use PhpStorm's terminal (configured for WSL) and run the server command
- Or create a run configuration that runs: `php -S localhost:8000 -t public` (but this will run indefinitely)

### Port Already in Use

If port 8000 is already in use, use a different port:

```bash
php -S localhost:8080 -t public
```

### Composer Not Found

If Composer is not found, follow the installation steps in the "Installing PHP and Composer in WSL" section above.

Quick fix:
```bash
# Check if Composer is in PATH
which composer

# If not found, ensure it's in /usr/local/bin
ls -la /usr/local/bin/composer

# If missing, reinstall Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### PHP Not Found

If PHP is not found, follow the installation steps in the "Installing PHP and Composer in WSL" section above.

Quick fix:
```bash
# Check PHP installation
php -v

# If not found, install PHP
sudo apt update
sudo apt install -y php php-cli php-curl php-mbstring php-xml php-json

# Verify installation
php -v
```

### PHP Version Too Old

If you need a newer PHP version:

```bash
# Add PHP repository
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# Install specific PHP version (e.g., 8.2)
sudo apt install -y php8.2 php8.2-cli php8.2-curl php8.2-mbstring php8.2-xml php8.2-json

# Set as default (if multiple versions installed)
sudo update-alternatives --set php /usr/bin/php8.2
```
