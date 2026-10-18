<?php

namespace App\Domain\Seo\WpConnector;

use RuntimeException;
use ZipArchive;

/**
 * The connector plugin as a zip a WordPress site can install or update from:
 * built from the plugin folder in this repository whenever its contents
 * change, so what the CRM serves is always what is in the code.
 */
class WpPluginPackage
{
    public const SLUG = 'farsiwp-seo-connector';

    public function sourcePath(): string
    {
        return base_path('wordpress-plugin/'.self::SLUG);
    }

    /**
     * The "Version:" in the plugin's header - the number sites compare theirs to.
     */
    public function version(): string
    {
        $header = (string) file_get_contents($this->sourcePath().'/'.self::SLUG.'.php');

        return preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $header, $matches) ? $matches[1] : '0.0.0';
    }

    /**
     * @return string path of the zip, built now if the plugin changed since the last one
     */
    public function zipPath(): string
    {
        $files = $this->files();
        $fingerprint = substr(sha1(implode('|', array_map(fn (string $file) => $file.sha1_file($this->sourcePath().'/'.$file), $files))), 0, 10);
        $directory = storage_path('app/wp-plugin');
        $path = "{$directory}/".self::SLUG."-{$this->version()}-{$fingerprint}.zip";

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        foreach (glob("{$directory}/*.zip") ?: [] as $old) {
            @unlink($old);
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساخت فایل zip پلاگین ممکن نیست.');
        }

        foreach ($files as $file) {
            $zip->addFile($this->sourcePath().'/'.$file, self::SLUG.'/'.$file);
        }

        $zip->close();

        return $path;
    }

    /**
     * @return list<string> paths relative to the plugin folder, in a stable order
     */
    private function files(): array
    {
        $root = $this->sourcePath();
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            }
        }

        sort($files);

        return $files;
    }
}
