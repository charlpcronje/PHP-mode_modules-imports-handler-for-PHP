<?php
declare(strict_types=1);

// Configuration
$nodeModulesPath = '/var/www/html/example.co.za/node_modules/';
$cookieName = 'module_base';
$logFile = './logs/imports.log';

class Handler
{
    private string $nodeModulesPath;
    private string $cookieName;
    private string $logFile;

    public function __construct(string $nodeModulesPath, string $cookieName, string $logFile)
    {
        $this->nodeModulesPath = $nodeModulesPath;
        $this->cookieName = $cookieName;
        $this->logFile = $logFile;

        $this->log("Handler initialized with nodeModulesPath: $nodeModulesPath, cookieName: $cookieName, logFile: $logFile");
    }

    public function handleRequest(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $path = strtok($requestUri, '?'); // Remove query parameters if any

        $this->log("Handling request: $requestUri");

        // Handle initial module requests
        if (preg_match('/^\/([^\/]+)\.js$/', $path, $matches)) {
            $this->handleInitialRequest($matches[1]);
            return;
        }

        // Handle subsequent relative imports
        if (isset($_SERVER['HTTP_REFERER'])) {
            $this->handleRelativeImport($path, $_SERVER['HTTP_REFERER']);
            return;
        }

        // Fallback for invalid requests
        $this->log("Invalid request: $requestUri");
        $this->serveError(404, "File not found.");
    }

    private function handleInitialRequest(string $moduleName): void
    {
        $this->log("Handling initial request for module: $moduleName");

        $packageJsonPath = $this->nodeModulesPath . $moduleName . '/package.json';
        if (!file_exists($packageJsonPath)) {
            $this->log("Module not found: $moduleName");
            $this->serveError(404, "Module not found: $moduleName");
            return;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $mainFile = $packageJson['main'] ?? 'index.js';
        $mainFilePath = $this->nodeModulesPath . $moduleName . '/' . $mainFile;

        if (!file_exists($mainFilePath)) {
            $this->log("Main file not found for module $moduleName: $mainFile");
            $this->serveError(404, "Main file not found: $mainFile");
            return;
        }

        setcookie($this->cookieName, json_encode([
            'module' => $moduleName,
            'main' => $mainFile
        ]), 0, '/');

        $this->log("Set cookie for module $moduleName. Serving main file: $mainFilePath");
        $this->serveFile($mainFilePath);
    }

    private function handleRelativeImport(string $path, string $referer): void
    {
        $this->log("Handling relative import: $path, referer: $referer");

        $refererPath = parse_url($referer, PHP_URL_PATH);
        if (preg_match('/^\/([^\/]+)\.js$/', $refererPath, $matches)) {
            $refModuleName = $matches[1];

            $cookie = $_COOKIE[$this->cookieName] ?? null;
            if (!$cookie) {
                $this->log("Cookie not found for referer: $refererPath");
                $this->serveError(404, "Context not found for referer: $refererPath");
                return;
            }

            $moduleBase = json_decode($cookie, true);
            if ($moduleBase['module'] !== $refModuleName) {
                $this->log("Cookie module mismatch: expected {$moduleBase['module']}, got $refModuleName");
                $this->serveError(404, "Invalid module context.");
                return;
            }

            $refMainFilePath = $this->nodeModulesPath . $moduleBase['module'] . '/' . $moduleBase['main'];
            $relativeFilePath = dirname($refMainFilePath) . '/' . $path;
            $absoluteFilePath = realpath($relativeFilePath);

            if (!$absoluteFilePath || strpos($absoluteFilePath, $this->nodeModulesPath) !== 0 || !file_exists($absoluteFilePath)) {
                $this->log("Relative import file not found: $path (resolved to $absoluteFilePath)");
                $this->serveError(404, "File not found.");
                return;
            }

            $this->log("Resolved relative import: $absoluteFilePath");
            $this->serveFile($absoluteFilePath);
        }
    }

    private function serveFile(string $filePath): void
    {
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        $this->log("Served file: $filePath");
    }

    private function serveError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        echo $message;
        $this->log("Error $statusCode: $message");
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

// Ensure the logs directory exists
if (!is_dir('./logs')) {
    mkdir('./logs', 0755, true);
}

// Instantiate and handle the request
$handler = new Handler($nodeModulesPath, $cookieName, $logFile);
$handler->handleRequest();
