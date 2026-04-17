<?php

use PHPUnit\Framework\TestCase;

final class ProjectContextTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_URI'] = '/';
    }

    public function testGetAvailableProjectsReturnsExpectedList(): void
    {
        $this->assertSame(['ghom', 'pardis'], getAvailableProjects());
    }

    public function testResolvesFromSession(): void
    {
        $_SESSION['current_project'] = 'pardis';
        $this->assertSame('pardis', getCurrentProject());
    }

    public function testSessionTakesPriorityOverUrl(): void
    {
        $_SESSION['current_project'] = 'ghom';
        $_SERVER['REQUEST_URI'] = '/pardis/api/get_stages.php';
        $this->assertSame('ghom', getCurrentProject());
    }

    public function testResolvesFromUrlWhenSessionMissing(): void
    {
        $_SERVER['REQUEST_URI'] = '/pardis/api/get_stages.php';
        $this->assertSame('pardis', getCurrentProject());
    }

    public function testResolvesFromGetParam(): void
    {
        $_GET['project'] = 'ghom';
        $this->assertSame('ghom', getCurrentProject());
    }

    public function testResolvesFromPostParam(): void
    {
        $_POST['project'] = 'pardis';
        $this->assertSame('pardis', getCurrentProject());
    }

    public function testIgnoresUnknownSessionValue(): void
    {
        $_SESSION['current_project'] = 'tehran';
        $_SERVER['REQUEST_URI'] = '/ghom/api/foo.php';
        $this->assertSame('ghom', getCurrentProject());
    }

    public function testThrowsWhenNoContextAvailable(): void
    {
        $this->expectException(RuntimeException::class);
        getCurrentProject();
    }
}
