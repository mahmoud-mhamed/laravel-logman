<?php

namespace MahmoudMhamed\Logman\Support;

use Throwable;

/**
 * Builds "open in IDE" URLs (phpstorm://, vscode://, ...) from a file:line
 * reference so log locations can be clicked to jump straight to the source.
 *
 * The editor is chosen via logman.viewer.editor (LOGMAN_EDITOR). Opening only
 * works when the viewer runs on the same machine as the IDE — for server logs
 * viewed locally, map the remote path to your local checkout with
 * logman.viewer.editor_path_map.
 */
class EditorLink
{
    /**
     * Supported editors and their URL templates ({file}/{line} placeholders).
     *
     * @var array<string, string>
     */
    protected static array $formats = [
        'phpstorm' => 'phpstorm://open?file={file}&line={line}',
        'idea' => 'idea://open?file={file}&line={line}',
        'vscode' => 'vscode://file/{file}:{line}',
        'vscode-insiders' => 'vscode-insiders://file/{file}:{line}',
        'vscodium' => 'vscodium://file/{file}:{line}',
        'sublime' => 'subl://open?url=file://{file}&line={line}',
        'atom' => 'atom://core/open/file?filename={file}&line={line}',
        'nova' => 'nova://open?path={file}&line={line}',
        'macvim' => 'mvim://open/?url=file://{file}&line={line}',
        'emacs' => 'emacs://open?url=file://{file}&line={line}',
        'textmate' => 'txmt://open?url=file://{file}&line={line}',
        'netbeans' => 'netbeans://open/?f={file}:{line}',
    ];

    /**
     * Build an editor URL for a "file:line" reference, or null when the editor
     * isn't configured/supported or the reference can't be parsed.
     */
    public static function url(?string $fileLine): ?string
    {
        try {
            if ($fileLine === null || $fileLine === '') {
                return null;
            }

            $editor = config('logman.viewer.editor');

            if (empty($editor) || ! isset(static::$formats[$editor])) {
                return null;
            }

            [$file, $line] = static::split($fileLine);

            if ($file === '') {
                return null;
            }

            $file = static::mapPath(static::absolute($file));

            return str_replace(
                ['{file}', '{line}'],
                [$file, $line],
                static::$formats[$editor],
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Split "path/to/File.php:42" into [file, line]. Handles Windows drive
     * letters (C:\...) by matching the trailing :<digits> only.
     *
     * @return array{0: string, 1: int}
     */
    protected static function split(string $fileLine): array
    {
        if (preg_match('/^(.*):(\d+)$/', $fileLine, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$fileLine, 0];
    }

    /**
     * Resolve a possibly-relative path (stored relative to base_path) back to
     * an absolute filesystem path the IDE can open.
     */
    protected static function absolute(string $file): string
    {
        // Already absolute? (unix /... or windows C:\... / C:/...)
        if (str_starts_with($file, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $file)) {
            return $file;
        }

        try {
            $base = base_path();
        } catch (Throwable $e) {
            return $file;
        }

        return rtrim($base, '/\\').'/'.ltrim($file, '/\\');
    }

    /**
     * Apply remote→local path mapping so logs written on a server can open in
     * a local IDE. Configured as ['<remote-prefix>' => '<local-prefix>', ...].
     */
    protected static function mapPath(string $file): string
    {
        $map = config('logman.viewer.editor_path_map', []);

        foreach ((array) $map as $remote => $local) {
            if (is_string($remote) && $remote !== '' && str_starts_with($file, $remote)) {
                return $local.substr($file, strlen($remote));
            }
        }

        return $file;
    }
}
