<?php

declare(strict_types=1);

namespace RPurinton;

class CssCache
{
    protected string $cacheFile;

    public function __construct(protected string $dir)
    {
        if (!is_dir($dir)) {
            throw new \Exception('Directory does not exist: ' . $dir);
        }
        $this->cacheFile = $this->dir . '/style.cache';
    }

    public static function compile(string $dir): void
    {
        (new self($dir))->process();
    }

    protected function getCssFiles(): array
    {
        $files = glob($this->dir . '/*.css');
        if ($files === false || empty($files)) {
            throw new \Exception('No CSS files found in directory: ' . $this->dir);
        }
        return $files;
    }

    protected function getLastModifiedTime(array $files): int
    {
        $lastModified = 0;
        foreach ($files as $file) {
            if (!is_file($file)) {
                error_log("Not a file: $file");
                continue;
            }
            $mtime = @filemtime($file);
            if ($mtime === false) {
                error_log("Error retrieving mtime for file: $file");
                continue;
            }
            $lastModified = max($lastModified, $mtime);
        }
        return $lastModified;
    }

    protected function generateCss(array $files): string
    {
        $css = '';
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                error_log("Error reading CSS file: $file");
                continue;
            }
            $css .= $content;
        }
        if (empty($css)) {
            throw new \Exception('Generated CSS is empty.');
        }
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(['; ', ': ', ' {', '{ '], [';', ':', '{', '{'], $css);
        return $css;
    }

    protected function cacheCss(string $css): int
    {
        if (file_put_contents($this->cacheFile, $css, LOCK_EX) === false) {
            throw new \Exception('Error writing to cache file.');
        }
        clearstatcache(true, $this->cacheFile);
        $newTime = filemtime($this->cacheFile);
        if ($newTime === false) {
            throw new \Exception('Error retrieving cache file modified time.');
        }
        return $newTime;
    }

    public function process(): void
    {
        $cssFiles = $this->getCssFiles();
        $lastModified = $this->getLastModifiedTime($cssFiles);

        $cacheFileTime = (file_exists($this->cacheFile) && is_file($this->cacheFile))
            ? filemtime($this->cacheFile)
            : 0;

        if ($lastModified > $cacheFileTime) {
            $css = $this->generateCss($cssFiles);
            if (trim($css) === '') {
                throw new \Exception('Generated CSS is empty after trimming.');
            }
            $lastModified = $this->cacheCss($css);
        } else {
            $css = @file_get_contents($this->cacheFile);
            if ($css === false) {
                error_log('Error reading cache file. Regenerating CSS.');
                $css = $this->generateCss($cssFiles);
                $lastModified = $this->cacheCss($css);
            }
        }

        header('Content-Type: text/css');
        header('Content-Length: ' . strlen($css));
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('Etag: ' . md5(strval($lastModified)));

        if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) ||
            (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === md5(strval($lastModified)))
        ) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        echo $css;
    }
}
