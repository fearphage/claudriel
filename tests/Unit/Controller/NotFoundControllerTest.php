<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Controller;

use Claudriel\Controller\NotFoundController;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class NotFoundControllerTest extends TestCase
{
    public function test_returns404_with_twig_template(): void
    {
        $twig = new Environment(new ArrayLoader([
            '404.html.twig' => '<h1>Not Found</h1><p>{{ path }}</p>',
        ]));
        $controller = new NotFoundController($twig);

        $response = $controller->show(['path' => 'nonexistent-page']);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Not Found', $response->content);
        self::assertStringContainsString('/nonexistent-page', $response->content);
    }

    public function test_returns404_without_twig(): void
    {
        $controller = new NotFoundController;

        $response = $controller->show(['path' => 'missing']);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Not Found', $response->content);
        self::assertStringContainsString('/missing', $response->content);
    }

    public function test_falls_back_to_plain_html_on_twig_error(): void
    {
        // Twig environment with no templates loaded, so rendering will fail.
        $twig = new Environment(new ArrayLoader([]));
        $controller = new NotFoundController($twig);

        $response = $controller->show(['path' => 'broken']);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('Not Found', $response->content);
        self::assertStringContainsString('/broken', $response->content);
    }

    public function test_normalizes_path_with_leading_slash(): void
    {
        $controller = new NotFoundController;

        $response = $controller->show(['path' => '/already-slashed']);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('/already-slashed', $response->content);
        // Should not double-slash.
        self::assertStringNotContainsString('//already-slashed', $response->content);
    }

    public function test_handles_empty_path(): void
    {
        $controller = new NotFoundController;

        $response = $controller->show([]);

        self::assertSame(404, $response->statusCode);
        self::assertStringContainsString('/', $response->content);
    }

    public function test_escapes_html_in_path(): void
    {
        $controller = new NotFoundController;

        $response = $controller->show(['path' => '<script>alert(1)</script>']);

        self::assertSame(404, $response->statusCode);
        self::assertStringNotContainsString('<script>', $response->content);
        self::assertStringContainsString('&lt;script&gt;', $response->content);
    }
}
