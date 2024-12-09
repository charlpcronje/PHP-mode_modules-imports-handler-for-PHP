# PHP Node Modules Import Handler

A PHP script that dynamically resolves and serves JavaScript files from a `node_modules` directory, allowing you to use ES module imports directly in the browser without requiring a build step.

This handler is particularly useful for lightweight development environments where you want to avoid using bundlers like Webpack, Vite, or Rollup.

---

## **Features**

- Dynamically resolves `main` files for Node.js modules based on their `package.json`.
- Supports relative imports from the main file of the module.
- Automatically tracks the module context using cookies to ensure accurate resolution of subsequent imports.
- Provides detailed logging for all operations, making debugging easier.

---

## **How It Works**

1. **Initial Request:**
   - A request to a Node.js module (e.g., `/lit.js`) is routed to the PHP handler.
   - The handler reads the `package.json` of the module (`lit`) in the `node_modules` directory.
   - It resolves the `main` entry (e.g., `index.js`) and serves it to the browser.

2. **Subsequent Imports:**
   - If the main file (`index.js`) imports other files (e.g., `./utils.js` or `../helper.js`), the browser requests these files.
   - The PHP handler:
     - Reads the `Referer` header to determine the originating file (e.g., `/lit.js`).
     - Uses a cookie (set during the initial request) to identify the module and its main file.
     - Resolves the relative path of the imported file and serves it.

3. **Logging:**
   - All actions, including file resolutions, errors, and served files, are logged to a `logs/imports.log` file for debugging.

---

## **Setup**

### **1. Clone the Repository**

```bash
git clone https://github.com/charlpcronje/PHP-node_modules-imports-handler-for-PHP.git
cd PHP-node_modules-imports-handler-for-PHP
```

### **2. Place the Handler Script**

Move the `import_handler.php` script to your project directory.

### **3. Configure `.htaccess`

Add the following to your `.htaccess` file:

```apache
RewriteEngine On

# Redirect requests for non-existent files or directories to import_handler.php
RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f
RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d

# Route to import_handler.php
RewriteRule ^(.*)$ /import_handler.php [QSA,L]
```

### **4. Configure `import_handler.php`**

At the top of `import_handler.php`, configure the following variables:

```php
// Configuration
$nodeModulesPath = '/absolute/path/to/your/node_modules';
$cookieName = 'module_base';
$logFile = './logs/imports.log';
```

- **`$nodeModulesPath`:** The absolute path to your `node_modules` directory.
- **`$cookieName`:** The name of the cookie used to track module context.
- **`$logFile`:** The location of the log file.

### **5. Ensure Logging Directory Exists**

Make sure the `logs` directory exists and is writable by the web server:

```bash
mkdir -p logs
chmod -R 755 logs
chown -R www-data:www-data logs
```

---

## **Usage**

### **1. Initial Import**

To use a Node.js module in your HTML file, request it as follows:

```html
<script type="module">
    import { html, render } from '/lit.js';
    render(html`<h1>Hello, Lit!</h1>`, document.body);
</script>
```

- The request for `/lit.js` will trigger `import_handler.php`.
- The handler resolves the `main` file of the `lit` module (e.g., `/node_modules/lit/index.js`) and serves it.

### **2. Subsequent Imports**

If the `lit` module's main file (`index.js`) contains relative imports like:

```javascript
import './utils.js';
import '../helper.js';
```

- The browser requests `/utils.js` and `/helper.js`.
- `import_handler.php`:
  - Uses the `Referer` header to identify the requesting file (e.g., `/lit.js`).
  - Retrieves the module context (set via a cookie) to determine the correct base path.
  - Resolves the relative imports (`utils.js`, `helper.js`) and serves them.

---

## **Example Workflow**

### **Folder Structure**

```
/var/www/project/
    ├── src/
    │   ├── app/
    │   │   └── index.html
    ├── node_modules/
    │   └── lit/
    │       ├── index.js
    │       ├── utils.js
    │       └── helper.js
    ├── logs/
    │   └── imports.log
    └── import_handler.php
```

### **Virtual Host Configuration**

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/project/src/app
    <Directory /var/www/project/src/app>
        Require all granted
        Options -Indexes +FollowSymLinks
        AllowOverride All
    </Directory>
    CustomLog /var/www/project/logs/access.log combined
    ErrorLog /var/www/project/logs/error.log
</VirtualHost>
```

### **HTML File (`index.html`)**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Example</title>
</head>
<body>
    <script type="module">
        import { html, render } from '/lit.js';
        render(html`<h1>Hello, Lit!</h1>`, document.body);
    </script>
</body>
</html>
```

### **Logs (`logs/imports.log`)**

```log
[2024-01-01 12:00:00] Handler initialized with nodeModulesPath: /var/www/project/node_modules, cookieName: module_base, logFile: ./logs/imports.log
[2024-01-01 12:00:01] Handling request: /lit.js
[2024-01-01 12:00:01] Handling initial request for module: lit
[2024-01-01 12:00:01] Set cookie for module lit. Serving main file: /var/www/project/node_modules/lit/index.js
[2024-01-01 12:00:02] Handling request: /utils.js
[2024-01-01 12:00:02] Handling relative import: /utils.js, referer: http://example.com/lit.js
[2024-01-01 12:00:02] Resolved relative import: /var/www/project/node_modules/lit/utils.js
```

---

## **Limitations**

- This handler is designed for local development. Avoid using it in production without additional performance optimizations.
- All module imports must be within the `node_modules` directory.

---

## **Contributing**

Contributions are welcome! Please open an issue or submit a pull request for improvements or bug fixes.

---

## **License**

This project is licensed under the MIT License. See the [LICENSE](LICENSE.md) file for details.
