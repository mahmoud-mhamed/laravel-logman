<?php

namespace MahmoudMhamed\Logman\Tests\Unit;

use MahmoudMhamed\Logman\Support\EditorLink;
use MahmoudMhamed\Logman\Tests\TestCase;

class EditorLinkTest extends TestCase
{
    public function test_returns_null_when_editor_not_configured(): void
    {
        config(['logman.viewer.editor' => null]);

        $this->assertNull(EditorLink::url('app/Foo.php:10'));
    }

    public function test_returns_null_for_unknown_editor(): void
    {
        config(['logman.viewer.editor' => 'notepad']);

        $this->assertNull(EditorLink::url('app/Foo.php:10'));
    }

    public function test_returns_null_for_empty_reference(): void
    {
        config(['logman.viewer.editor' => 'phpstorm']);

        $this->assertNull(EditorLink::url(''));
        $this->assertNull(EditorLink::url(null));
    }

    public function test_builds_vscode_url_with_absolute_path(): void
    {
        config(['logman.viewer.editor' => 'vscode']);

        $url = EditorLink::url('app/Foo.php:10');

        // Relative path is resolved against base_path().
        $this->assertSame('vscode://file/'.base_path('app/Foo.php').':10', $url);
    }

    public function test_keeps_absolute_paths_as_is(): void
    {
        config(['logman.viewer.editor' => 'phpstorm']);

        $url = EditorLink::url('/srv/app/Foo.php:99');

        $this->assertSame('phpstorm://open?file=/srv/app/Foo.php&line=99', $url);
    }

    public function test_applies_remote_to_local_path_map(): void
    {
        config([
            'logman.viewer.editor' => 'phpstorm',
            'logman.viewer.editor_path_map' => ['/var/www/app' => '/Users/me/app'],
        ]);

        $url = EditorLink::url('/var/www/app/Http/Kernel.php:15');

        $this->assertSame('phpstorm://open?file=/Users/me/app/Http/Kernel.php&line=15', $url);
    }

    public function test_defaults_line_to_zero_when_missing(): void
    {
        config(['logman.viewer.editor' => 'phpstorm']);

        $url = EditorLink::url('/srv/app/Foo.php');

        $this->assertSame('phpstorm://open?file=/srv/app/Foo.php&line=0', $url);
    }
}
